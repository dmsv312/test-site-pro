<?php

declare(strict_types=1);

namespace app\services\cleaning;

use app\models\AdGroup;
use app\models\BrandTerm;
use app\models\Keyword;
use app\models\RuleConfig;
use yii\db\Expression;

/**
 * Runs the cleaning pipeline over the keyword table: junk → dedup → brand → volume.
 *
 * Rules never delete rows — they set a flag (`is_junk` / `is_duplicate` / `is_brand` /
 * `below_volume`) and a human-readable `drop_reason`, so the admin funnel can explain every
 * decision. It is a sequential funnel: a keyword dropped by an earlier rule is not evaluated by
 * later ones, so each row carries exactly one flag and the stage counts are disjoint.
 *
 * Cleaning is the head of the pipeline, so it is a pure function of the imported data: a run
 * resets the whole downstream — every keyword back to `imported`, all cleaning *and* preparation
 * flags cleared, and the derived `ad_group` table emptied — then recomputes from scratch. This is
 * why re-cleaning invalidates stage 5: changing a threshold or brand term must reconsider every
 * row (dedup is global — a canonical must never be hidden from its duplicates), and any downstream
 * result computed from the old cleaned set is stale. After a re-clean, run preparation again.
 *
 * The run is idempotent: reset then recompute always yields the same result regardless of history.
 * Dedup groups by the normalized term across the whole dataset; the canonical survivor is the
 * highest-volume row, ties broken by lowest id. The group link (`dedup_group_id`) is written only
 * when the canonical actually survives to `cleaned`, so no live row ever points at a dropped
 * canonical; merging the duplicates' metrics into the canonical is a later stage (5).
 */
final class CleaningService
{
    public function __construct(
        private readonly JunkRule $junkRule,
        private readonly BrandRule $brandRule,
        private readonly VolumeRule $volumeRule,
    ) {
    }

    /** Wires the rules from the editable config and brand list. */
    public static function create(): self
    {
        return new self(
            new JunkRule(RuleConfig::intValue(RuleConfig::MAX_TERM_LENGTH)),
            new BrandRule(BrandTerm::terms()),
            new VolumeRule(RuleConfig::intValue(RuleConfig::MIN_VOLUME)),
        );
    }

    /**
     * Cleaning reconsiders every keyword — dedup is global, so no row may be hidden from it. The
     * all-rows condition (`id` is a never-null PK) is used for both the reset and the reload.
     */
    private static function allRows(): array
    {
        return ['not', ['id' => null]];
    }

    /**
     * Execute the pipeline. Returns the {@see snapshot()} summary of the resulting funnel.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $db = Keyword::getDb();
        $transaction = $db->beginTransaction();

        try {
            $scope = self::allRows();

            // 1. Reset the whole pipeline: every keyword back to `imported`, all cleaning AND
            //    preparation flags cleared, and the derived ad groups removed. Cleaning is the head
            //    of the pipeline, so a re-run is a deterministic pure function of the imported data
            //    and never leaves stale downstream state. Re-run preparation afterwards.
            AdGroup::deleteAll();
            Keyword::updateAll([
                'is_junk' => false,
                'is_duplicate' => false,
                'is_brand' => false,
                'below_volume' => false,
                'is_already_used' => false,
                'is_forbidden' => false,
                'drop_reason' => null,
                'dedup_group_id' => null,
                'ad_group_id' => null,
                'stage' => Keyword::STAGE_IMPORTED,
            ], $scope);

            /** @var Keyword[] $keywords */
            $keywords = Keyword::find()->where($scope)->all();

            /** @var array<int, array<string, mixed>> $updates id → changed attributes */
            $updates = [];

            // 2. Junk.
            $survivors = [];
            foreach ($keywords as $k) {
                $reason = $this->junkRule->reason($k->normalized_term);
                if ($reason !== null) {
                    $updates[$k->id] = ['is_junk' => true, 'drop_reason' => $reason];
                } else {
                    $survivors[] = $k;
                }
            }

            // 3. Dedup: mark duplicates now (so the funnel count is right); the group link is
            //    finalized in step 5 once we know whether the canonical survives.
            [$survivors, $groups] = $this->dedup($survivors, $updates);

            // 4. Brand, then volume, on what's left. First matching rule wins.
            $keptIds = [];
            foreach ($survivors as $k) {
                if (($reason = $this->brandRule->reason($k->normalized_term)) !== null) {
                    $updates[$k->id]['is_brand'] = true;
                    $updates[$k->id]['drop_reason'] = $reason;
                    continue;
                }
                $volume = $k->avg_monthly_searches === null ? null : (int) $k->avg_monthly_searches;
                if (($reason = $this->volumeRule->reason($volume)) !== null) {
                    $updates[$k->id]['below_volume'] = true;
                    $updates[$k->id]['drop_reason'] = $reason;
                    continue;
                }
                $keptIds[$k->id] = true;
            }

            // 5. Finalize dedup groups. Link duplicates to the canonical only when the canonical
            //    survived to "cleaned"; otherwise the whole group is gone, so keep the dedup flag
            //    but leave no dangling link to a dropped row.
            foreach ($groups as $group) {
                $canonicalId = $group['canonical'];
                if (isset($keptIds[$canonicalId])) {
                    $updates[$canonicalId]['dedup_group_id'] = (string) $canonicalId;
                    foreach ($group['duplicates'] as $dupId) {
                        $updates[$dupId]['dedup_group_id'] = (string) $canonicalId;
                        $updates[$dupId]['drop_reason'] = "duplicate of #{$canonicalId}";
                    }
                } else {
                    foreach ($group['duplicates'] as $dupId) {
                        $updates[$dupId]['drop_reason'] = 'duplicate (group removed)';
                    }
                }
            }

            // 6. Survivors advance to the "cleaned" stage.
            foreach (array_keys($keptIds) as $id) {
                $updates[$id]['stage'] = Keyword::STAGE_CLEANED;
            }

            // 7. Persist only the rows that changed.
            foreach ($updates as $id => $attributes) {
                Keyword::updateAll($attributes, ['id' => $id]);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return self::snapshot();
    }

    /**
     * Mark duplicates within each normalized-term group. Duplicates get `is_duplicate` here; the
     * `dedup_group_id` link and reason are written later (step 5), only for surviving canonicals.
     *
     * @param Keyword[] $keywords
     * @param array<int, array<string, mixed>> $updates modified in place
     * @return array{0: Keyword[], 1: array<int, array{canonical: int, duplicates: int[]}>}
     *         [the canonical survivor of each group plus every singleton, the group descriptors]
     */
    private function dedup(array $keywords, array &$updates): array
    {
        $byTerm = [];
        foreach ($keywords as $k) {
            $byTerm[$k->normalized_term][] = $k;
        }

        $survivors = [];
        $groups = [];
        foreach ($byTerm as $group) {
            if (count($group) === 1) {
                $survivors[] = $group[0];
                continue;
            }

            $canonical = $this->pickCanonical($group);
            $duplicates = [];
            foreach ($group as $k) {
                if ($k->id === $canonical->id) {
                    $survivors[] = $k;
                } else {
                    $updates[$k->id]['is_duplicate'] = true;
                    $duplicates[] = $k->id;
                }
            }
            $groups[] = ['canonical' => $canonical->id, 'duplicates' => $duplicates];
        }

        return [$survivors, $groups];
    }

    /**
     * The keyword a duplicate group collapses to: highest volume, ties broken by lowest id
     * (deterministic). A null volume counts as lower than any real number.
     *
     * @param Keyword[] $group
     */
    private function pickCanonical(array $group): Keyword
    {
        $best = $group[0];
        foreach ($group as $k) {
            $kv = (int) ($k->avg_monthly_searches ?? -1);
            $bv = (int) ($best->avg_monthly_searches ?? -1);
            if ($kv > $bv || ($kv === $bv && $k->id < $best->id)) {
                $best = $k;
            }
        }

        return $best;
    }

    /**
     * The current funnel, computed from the database. Usable whether or not a run just happened,
     * so the dashboard can render the last result. Stage counts are disjoint by construction, so
     * every "remaining" is the previous step minus that step's drops — a single consistent basis.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        $count = static fn(array $condition): int => (int) Keyword::find()->where($condition)->count();

        $total = (int) Keyword::find()->count();
        $junk = $count(['is_junk' => true]);
        $duplicate = $count(['is_duplicate' => true]);
        $brand = $count(['is_brand' => true]);
        $belowVolume = $count(['below_volume' => true]);

        $afterJunk = $total - $junk;
        $afterDedup = $afterJunk - $duplicate;
        $afterBrand = $afterDedup - $brand;
        $afterVolume = $afterBrand - $belowVolume; // survivors — the ad-candidate keywords

        // Only cleaning's own drops — a row flagged by one of the four cleaning rules. Stage 5
        // writes its own drop reasons (already-used / forbidden) on rows that *survived* cleaning,
        // so filtering by the cleaning flags keeps them out of this funnel's breakdown (which would
        // otherwise disagree with the "Dropped" total above).
        $reasons = Keyword::find()
            ->select(['drop_reason', 'cnt' => new Expression('COUNT(*)')])
            ->where(['not', ['drop_reason' => null]])
            ->andWhere(['or',
                ['is_junk' => true], ['is_duplicate' => true], ['is_brand' => true], ['below_volume' => true],
            ])
            ->groupBy('drop_reason')
            ->orderBy(['cnt' => SORT_DESC, 'drop_reason' => SORT_ASC])
            ->asArray()
            ->all();

        return [
            'total' => $total,
            'survivors' => $afterVolume,
            'dropped' => [
                'junk' => $junk,
                'duplicate' => $duplicate,
                'brand' => $brand,
                'below_volume' => $belowVolume,
                'total' => $junk + $duplicate + $brand + $belowVolume,
            ],
            'hasRun' => ($junk + $duplicate + $brand + $belowVolume) > 0,
            'funnel' => [
                ['label' => 'Imported', 'remaining' => $total, 'dropped' => 0],
                ['label' => 'After junk', 'remaining' => $afterJunk, 'dropped' => $junk],
                ['label' => 'After dedup', 'remaining' => $afterDedup, 'dropped' => $duplicate],
                ['label' => 'After brand', 'remaining' => $afterBrand, 'dropped' => $brand],
                ['label' => 'Cleaned (after volume)', 'remaining' => $afterVolume, 'dropped' => $belowVolume],
            ],
            'reasons' => $reasons,
        ];
    }
}

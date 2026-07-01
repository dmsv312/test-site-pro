<?php

declare(strict_types=1);

namespace app\services\preparation;

use app\models\ForbiddenTerm;
use app\models\Keyword;

/**
 * Runs the preparation pipeline over the cleaned keywords: already-used → forbidden → merge,
 * leaving a net-new, campaign-ready set that stage 5b groups by language and theme.
 *
 * Like cleaning, a rule never deletes a row — it sets a flag (`is_already_used` / `is_forbidden`)
 * and a human-readable `drop_reason`, so the admin funnel explains every drop. First matching
 * rule wins, so each dropped row carries exactly one reason and the stage counts stay disjoint.
 *
 * Scope: preparation owns the rows cleaning handed off — stage `cleaned` (candidates) and
 * `prepared` (survivors of a previous run). It never touches rows still `imported` (dropped by
 * cleaning) or an `ad_ready` row a later stage advanced. The run is idempotent: it resets stage-5
 * state for owned rows first, then recomputes, so editing the forbidden list and re-running is
 * deterministic.
 *
 * Merge: dedup (stage 4) already collapsed every duplicate group to its highest-volume canonical,
 * so the surviving representative already carries the group's true (max) volume — exactly the
 * "keep one true value, don't sum" decision. Stage 5 reports how many groups the survivors stand
 * for; it does not touch the metric columns, which keeps the run idempotent.
 */
final class PreparationService
{
    public function __construct(
        private readonly AlreadyUsedRule $alreadyUsedRule,
        private readonly ForbiddenRule $forbiddenRule,
        private readonly GroupingService $groupingService,
    ) {
    }

    /** Wires the rules from the google_ads source (used-set) and the editable forbidden list. */
    public static function create(): self
    {
        return new self(
            AlreadyUsedRule::fromDatabase(),
            new ForbiddenRule(ForbiddenTerm::terms()),
            GroupingService::create(),
        );
    }

    /** The rows preparation owns and may modify: the cleaned candidates and prior survivors. */
    private static function ownedScope(): array
    {
        return ['stage' => [Keyword::STAGE_CLEANED, Keyword::STAGE_PREPARED]];
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
            $scope = self::ownedScope();

            // 1. Reset stage-5 state for owned rows only → deterministic re-run, and a row a
            //    previous run advanced to "prepared" drops back to "cleaned" before re-evaluation.
            Keyword::updateAll([
                'is_already_used' => false,
                'is_forbidden' => false,
                'drop_reason' => null,
                'stage' => Keyword::STAGE_CLEANED,
                'ad_group_id' => null,   // grouping rebuilds links; clearing here keeps them consistent
            ], $scope);

            /** @var Keyword[] $keywords */
            $keywords = Keyword::find()->where($scope)->all();

            /** @var array<int, array<string, mixed>> $updates id → changed attributes */
            $updates = [];
            $keptIds = [];

            // 2. already-used → forbidden. First matching rule wins.
            foreach ($keywords as $k) {
                if (($reason = $this->alreadyUsedRule->reason($k->normalized_term)) !== null) {
                    $updates[$k->id] = ['is_already_used' => true, 'drop_reason' => $reason];
                    continue;
                }
                if (($reason = $this->forbiddenRule->reason($k->normalized_term)) !== null) {
                    $updates[$k->id] = ['is_forbidden' => true, 'drop_reason' => $reason];
                    continue;
                }
                $keptIds[$k->id] = true;
            }

            // 3. Survivors advance to the "prepared" stage.
            foreach (array_keys($keptIds) as $id) {
                $updates[$id]['stage'] = Keyword::STAGE_PREPARED;
            }

            // 4. Persist only the rows that changed.
            foreach ($updates as $id => $attributes) {
                Keyword::updateAll($attributes, ['id' => $id]);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Group the prepared survivors by language + theme (own transaction, full rebuild).
        $this->groupingService->run();

        return self::snapshot();
    }

    /**
     * The current preparation funnel, computed from the database (usable whether or not a run just
     * happened). Stage counts are disjoint by construction, so each "remaining" is the previous
     * step minus that step's drops — one consistent basis.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        $count = static fn(array $condition): int => (int) Keyword::find()->where($condition)->count();

        // Candidates handed to stage 5 = everything that survived cleaning. After a run, dropped
        // rows stay at "cleaned" (flagged) and survivors are "prepared", so cleaned + prepared is
        // the candidate total on every basis.
        $candidates = $count(['stage' => [Keyword::STAGE_CLEANED, Keyword::STAGE_PREPARED]]);
        $alreadyUsed = $count(['is_already_used' => true]);
        $forbidden = $count(['is_forbidden' => true]);

        $afterUsed = $candidates - $alreadyUsed;
        $afterForbidden = $afterUsed - $forbidden;   // == the prepared survivors

        // Merge reporting: prepared canonicals that stand in for a duplicate group (stage-4 dedup).
        $mergedGroups = (int) Keyword::find()
            ->where(['stage' => Keyword::STAGE_PREPARED])
            ->andWhere(['not', ['dedup_group_id' => null]])
            ->count();

        return [
            'candidates' => $candidates,
            'prepared' => $afterForbidden,
            'dropped' => [
                'already_used' => $alreadyUsed,
                'forbidden' => $forbidden,
                'total' => $alreadyUsed + $forbidden,
            ],
            'mergedGroups' => $mergedGroups,
            'hasRun' => ($alreadyUsed + $forbidden) > 0 || $count(['stage' => Keyword::STAGE_PREPARED]) > 0,
            'funnel' => [
                ['label' => 'Cleaned (candidates)', 'remaining' => $candidates, 'dropped' => 0],
                ['label' => 'After already-used', 'remaining' => $afterUsed, 'dropped' => $alreadyUsed],
                ['label' => 'Prepared (after forbidden)', 'remaining' => $afterForbidden, 'dropped' => $forbidden],
            ],
            'grouping' => GroupingService::snapshot(),
        ];
    }
}

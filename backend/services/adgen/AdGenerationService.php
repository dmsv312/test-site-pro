<?php

declare(strict_types=1);

namespace app\services\adgen;

use app\models\AdGroup;
use app\models\GeneratedAd;
use Yii;

/**
 * Stage 6: generate one responsive search ad per ad group. For each group it prefers stored,
 * offline-authored copy ({@see StoredAdSource}) and falls back to the deterministic template engine
 * ({@see TemplateAdGenerator}) — so the deployed host needs no AI credentials (decision 3). All copy,
 * whichever source, must clear {@see RsaValidator} before it is stored; stored copy that fails
 * validation is discarded and the template used instead.
 *
 * The target URL is authoritative from the ad group (the verified localized landing page), never
 * from the generated copy — untrusted text can't redirect a campaign. `generated_ad` is fully
 * derived and rebuilt each run (idempotent), so a re-run just refreshes every ad; re-running
 * preparation rebuilds the ad groups and cascades these ads away (re-prep invalidates stage 6).
 */
final class AdGenerationService
{
    public function __construct(
        private readonly StoredAdSource $storedAds,
        private readonly TemplateAdGenerator $template,
        private readonly RsaValidator $validator,
    ) {
    }

    public static function create(): self
    {
        $path = (string) (Yii::$app->params['storedAdsPath'] ?? '');

        return new self(
            StoredAdSource::fromFile($path),
            new TemplateAdGenerator(),
            new RsaValidator(),
        );
    }

    /**
     * Rebuild every ad group's ad. Returns the {@see snapshot()} summary.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $db = GeneratedAd::getDb();
        $transaction = $db->beginTransaction();

        try {
            GeneratedAd::deleteAll();

            /** @var AdGroup[] $groups */
            $groups = AdGroup::find()->all();
            $now = time();

            foreach ($groups as $group) {
                [$content, $by, $note] = $this->copyFor($group);
                $errors = $this->validator->validate($content);

                $ad = new GeneratedAd([
                    'ad_group_id' => $group->id,
                    'language' => $group->language,
                    'final_url' => $group->final_url,   // authoritative — from the ad group, not the copy
                    'headlines' => $this->encode($content->headlines),
                    'descriptions' => $this->encode($content->descriptions),
                    'path1' => $content->path1,
                    'path2' => $content->path2,
                    'generated_by' => $by,
                    'is_valid' => $errors === [],
                    'note' => $errors === [] ? $note : ('Invalid: ' . implode(' ', $errors)),
                    'created_at' => $now,
                ]);
                $ad->save(false);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return self::snapshot();
    }

    /**
     * Choose the copy for a group: stored (if present and valid) else the template.
     *
     * @return array{0: AdContent, 1: string, 2: string|null} [content, generated_by, note]
     */
    private function copyFor(AdGroup $group): array
    {
        $stored = $this->storedAds->get($group->language, $group->theme_key);
        if ($stored !== null && $this->validator->isValid($stored)) {
            return [$stored, GeneratedAd::BY_STORED, null];
        }

        $note = $stored !== null ? 'Stored copy failed validation; used template.' : null;
        $content = $this->template->generate($group->language, $group->theme, $group->theme_key);

        return [$content, GeneratedAd::BY_TEMPLATE, $note];
    }

    /** @param string[] $list */
    private function encode(array $list): string
    {
        return (string) json_encode(array_values($list), JSON_UNESCAPED_UNICODE);
    }

    /**
     * The current ad set, grouped by language for the preview and with per-source totals. Reads the
     * database, so it's usable whether or not a run just happened.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        $adGroups = (int) AdGroup::find()->count();

        /** @var AdGroup[] $groups */
        $groups = AdGroup::find()
            ->with('generatedAd')
            ->orderBy(['language' => SORT_ASC, 'keyword_count' => SORT_DESC, 'theme' => SORT_ASC])
            ->all();

        $byLanguage = [];
        $generated = 0;
        $byStored = 0;
        $byTemplate = 0;
        $invalid = 0;
        $keywordsCovered = 0;

        foreach ($groups as $g) {
            $ad = $g->generatedAd;
            $byLanguage[$g->language]['campaign'] ??= $g->campaign;
            $byLanguage[$g->language]['final_url'] ??= $g->final_url;
            $byLanguage[$g->language]['groups'][] = $g;

            if ($ad !== null) {
                $generated++;
                $keywordsCovered += (int) $g->keyword_count;
                $ad->generated_by === GeneratedAd::BY_STORED ? $byStored++ : $byTemplate++;
                if (!$ad->is_valid) {
                    $invalid++;
                }
            }
        }

        return [
            'adGroups' => $adGroups,
            'generated' => $generated,
            'pending' => $adGroups - $generated,
            'byStored' => $byStored,
            'byTemplate' => $byTemplate,
            'invalid' => $invalid,
            'keywordsCovered' => $keywordsCovered,
            'languages' => count($byLanguage),
            'byLanguage' => $byLanguage,
            'hasRun' => $generated > 0,
        ];
    }
}

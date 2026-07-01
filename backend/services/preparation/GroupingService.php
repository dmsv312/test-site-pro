<?php

declare(strict_types=1);

namespace app\services\preparation;

use app\models\AdGroup;
use app\models\Keyword;
use Yii;

/**
 * Groups the prepared keywords (stage 5b): one campaign per language, and inside it, themed ad
 * groups from {@see ThemeClusterer}. Each keyword gets an `ad_group_id`; each group gets its
 * localized target URL from params.php `languageUrlMap` (the verified Site.pro landing pages).
 *
 * The `ad_group` table is fully derived, so a run truncates it and rebuilds from the current
 * prepared set — idempotent, and it can never hold a stale group. Only rows at stage `prepared`
 * are grouped; a keyword that leaves the prepared set is unlinked. A keyword with no/empty language
 * falls back to English so it is never silently dropped from a campaign.
 */
final class GroupingService
{
    public function __construct(
        private readonly ThemeClusterer $clusterer,
        private readonly array $languageUrlMap,
        private readonly string $defaultUrl,
    ) {
    }

    public static function create(): self
    {
        $params = Yii::$app->params;

        return new self(
            new ThemeClusterer(),
            $params['languageUrlMap'] ?? [],
            (string) ($params['defaultLandingUrl'] ?? 'https://site.pro/'),
        );
    }

    /**
     * Rebuild all ad groups from the current prepared keywords.
     *
     * @return array<string, mixed> the grouping {@see snapshot()}
     */
    public function run(): array
    {
        $db = Keyword::getDb();
        $transaction = $db->beginTransaction();

        try {
            // Rebuild the prepared set's campaigns only. A later stage advances an ad group to
            // `ad_ready` as a whole (its keywords move together), and those groups + links are left
            // intact here, so a re-run never wipes a stage-6 campaign. No `ad_ready` rows exist yet,
            // so today this is an exact full rebuild — but the scope already matches what
            // PreparationService declares it owns (`cleaned`/`prepared`, never `ad_ready`).
            $adReadyGroupIds = Keyword::find()
                ->select('ad_group_id')
                ->where(['stage' => Keyword::STAGE_AD_READY])
                ->andWhere(['not', ['ad_group_id' => null]])
                ->distinct();
            Keyword::updateAll(['ad_group_id' => null], ['stage' => Keyword::STAGE_PREPARED]);
            AdGroup::deleteAll(['not in', 'id', $adReadyGroupIds]);

            /** @var Keyword[] $prepared */
            $prepared = Keyword::find()->where(['stage' => Keyword::STAGE_PREPARED])->all();

            // Bucket ids + terms by language (empty language → English fallback).
            $byLanguage = [];
            foreach ($prepared as $k) {
                $lang = ($k->language !== null && $k->language !== '') ? $k->language : 'en';
                $byLanguage[$lang][$k->id] = $k->normalized_term;
            }

            $now = time();
            foreach ($byLanguage as $language => $terms) {
                $assignment = $this->clusterer->assign($terms);

                // Collect ids per theme, preserving the first-seen label for each key.
                $idsByTheme = [];
                $labelByTheme = [];
                foreach ($assignment as $id => $theme) {
                    $idsByTheme[$theme['theme_key']][] = $id;
                    $labelByTheme[$theme['theme_key']] ??= $theme['theme'];
                }

                $url = $this->languageUrlMap[$language] ?? $this->defaultUrl;
                $campaign = 'Site.pro — ' . strtoupper($language);

                foreach ($idsByTheme as $themeKey => $ids) {
                    $group = new AdGroup([
                        'language' => $language,
                        'theme' => $labelByTheme[$themeKey],
                        'theme_key' => $themeKey,
                        'campaign' => $campaign,
                        'final_url' => $url,
                        'keyword_count' => count($ids),
                        'created_at' => $now,
                    ]);
                    $group->save(false);
                    Keyword::updateAll(['ad_group_id' => $group->id], ['id' => $ids]);
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return self::snapshot();
    }

    /**
     * The current campaign structure, grouped for display: languages → their ad groups.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        /** @var AdGroup[] $groups */
        $groups = AdGroup::find()
            ->orderBy(['language' => SORT_ASC, 'keyword_count' => SORT_DESC, 'theme' => SORT_ASC])
            ->all();

        $byLanguage = [];
        $groupedKeywords = 0;
        foreach ($groups as $g) {
            $byLanguage[$g->language]['campaign'] ??= $g->campaign;
            $byLanguage[$g->language]['final_url'] ??= $g->final_url;
            $byLanguage[$g->language]['groups'][] = $g;
            $byLanguage[$g->language]['keywords'] = ($byLanguage[$g->language]['keywords'] ?? 0) + $g->keyword_count;
            $groupedKeywords += $g->keyword_count;
        }

        return [
            'adGroups' => count($groups),
            'languages' => count($byLanguage),
            'groupedKeywords' => $groupedKeywords,
            'byLanguage' => $byLanguage,
        ];
    }
}

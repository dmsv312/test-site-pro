<?php

declare(strict_types=1);

namespace app\services\export;

use app\models\AdGroup;

/**
 * Stage 7: turn the prepared campaigns into a Google Ads Editor CSV (keywords + responsive search
 * ads) and the numbers behind the admin preview. Like the rest of the pipeline the export is
 * **fully derived** — it reads the current `ad_group` / `generated_ad` / `keyword` state and produces
 * the file on demand (decision 31: no persisted artifact table), so it always reflects the latest run.
 *
 * The CSV layout and all formatting live in the pure {@see GoogleAdsEditorExport}; this service only
 * walks the models, sanitizes/dedupes the keyword text per ad group, and emits an RSA row for each ad
 * group that has a **valid** generated ad (an invalid ad is never written to the file).
 */
final class ExportService
{
    public const MATCH_TYPE = GoogleAdsEditorExport::MATCH_TYPE_PHRASE;

    /**
     * The export as an ordered list of CSV rows: for each ad group, its keyword rows followed by its
     * responsive-search-ad row (when a valid ad exists). Each row is an associative partial map keyed
     * by column name, ready for {@see GoogleAdsEditorExport::render()}.
     *
     * @return array<int, array<string, string>>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->orderedGroups() as $group) {
            $seen = [];
            /** @var string[] $terms */
            $terms = $group->getKeywords()->select('raw_term')->column();
            foreach ($terms as $term) {
                $row = GoogleAdsEditorExport::keywordRow(
                    $group->campaign,
                    $group->theme,
                    (string) $term,
                    $group->final_url,
                    self::MATCH_TYPE,
                );
                if ($row === null || isset($seen[$row['Keyword']])) {
                    continue;
                }
                $seen[$row['Keyword']] = true;
                $rows[] = $row;
            }

            $ad = $group->generatedAd;
            if ($ad !== null && $ad->is_valid) {
                $rows[] = GoogleAdsEditorExport::adRow(
                    $group->campaign,
                    $group->theme,
                    $ad->getHeadlines(),
                    $ad->getDescriptions(),
                    $ad->path1,
                    $ad->path2,
                    $group->final_url,
                );
            }
        }

        return $rows;
    }

    /** The complete Google Ads Editor CSV string. */
    public function toCsv(): string
    {
        return GoogleAdsEditorExport::render($this->rows());
    }

    /**
     * Counts and per-campaign breakdown for the admin preview. Reads the database, so it is usable
     * whether or not a run just happened.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        $service = new self();
        $rows = $service->rows();

        $keywordRows = 0;
        $adRows = 0;
        foreach ($rows as $row) {
            if (isset($row['Ad Type'])) {
                $adRows++;
            } elseif (isset($row['Keyword'])) {
                $keywordRows++;
            }
        }

        $byLanguage = [];
        $adGroups = 0;
        $keywords = 0;
        $validAds = 0;
        $invalidAds = 0;
        $groupsWithoutAd = 0;

        foreach ($service->orderedGroups() as $group) {
            $adGroups++;
            $keywords += (int) $group->keyword_count;

            $ad = $group->generatedAd;
            $hasValidAd = $ad !== null && $ad->is_valid;
            if ($hasValidAd) {
                $validAds++;
            } else {
                $groupsWithoutAd++;
                if ($ad !== null) {
                    $invalidAds++;
                }
            }

            $lang = $group->language;
            $byLanguage[$lang]['campaign'] ??= $group->campaign;
            $byLanguage[$lang]['final_url'] ??= $group->final_url;
            $byLanguage[$lang]['groups'][] = $group;
            $byLanguage[$lang]['keywords'] = ($byLanguage[$lang]['keywords'] ?? 0) + (int) $group->keyword_count;
            $byLanguage[$lang]['ads'] = ($byLanguage[$lang]['ads'] ?? 0) + ($hasValidAd ? 1 : 0);
        }

        return [
            'ready' => $adRows > 0,
            'campaigns' => count($byLanguage),
            'adGroups' => $adGroups,
            'keywords' => $keywords,
            'validAds' => $validAds,
            'invalidAds' => $invalidAds,
            'groupsWithoutAd' => $groupsWithoutAd,
            'keywordRows' => $keywordRows,
            'adRows' => $adRows,
            'totalRows' => $keywordRows + $adRows,
            'matchType' => self::MATCH_TYPE,
            'byLanguage' => $byLanguage,
        ];
    }

    /**
     * Ad groups in a stable preview/export order: language, then largest group first, then theme.
     *
     * @return AdGroup[]
     */
    private function orderedGroups(): array
    {
        return AdGroup::find()
            ->with('generatedAd')
            ->orderBy(['language' => SORT_ASC, 'keyword_count' => SORT_DESC, 'theme' => SORT_ASC])
            ->all();
    }
}

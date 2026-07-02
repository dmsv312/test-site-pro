<?php

declare(strict_types=1);

namespace app\services\export;

/**
 * Format prepared campaigns for the Google Ads **web UI** bulk-upload tool
 * (Tools → Bulk actions → Uploads), as opposed to the desktop Google Ads Editor file that
 * {@see GoogleAdsEditorExport} produces.
 *
 * Pure and side-effect-free (no database, no Yii) so it is fully unit-testable.
 *
 * The web tool does **not** accept one combined sheet: every official downloadable template is
 * single-entity, and the header sets are mutually incompatible (a campaign row carries
 * `Campaign type` / `Bid strategy type`, a keyword row `Match Type`, an ad row `Ad type` /
 * `Headline N`). So this exporter emits **one sheet per entity type**, to be uploaded in
 * dependency order: campaigns → ad groups → keywords → responsive search ads. Column headers are
 * verbatim from Google's official bulk-upload templates (support.google.com/google-ads/answer/
 * 10702525 and /10702623). Notable, easy-to-get-wrong details preserved here:
 *   - the keyword match type value is spelled `Phrase match` (not the Editor's bare `Phrase`);
 *   - a responsive search ad's first description column is literally `Description` (no number),
 *     then `Description 2..4`; the ad also carries an explicit `Ad type` = `Responsive search ad`;
 *   - each entity's status column has a different name (`Campaign status` / `Status` /
 *     `Ad status`); an `Action` = `Add` column drives creation.
 *
 * New campaigns are emitted **paused**, with `Manual CPC` (a bid strategy needing no target) and no
 * budget column, so an accidental import never spends money — the operator sets a budget and enables
 * the campaign when ready. Ads are emitted paused for the same reason. `Final URL` is always the ad
 * group's verified localized URL, never generated text.
 */
final class GoogleAdsBulkUploadExport
{
    public const ACTION_ADD = 'Add';
    public const STATUS_ENABLED = 'Enabled';
    public const STATUS_PAUSED = 'Paused';
    public const CAMPAIGN_TYPE_SEARCH = 'Search';
    public const BID_STRATEGY_MANUAL_CPC = 'Manual CPC';
    public const MATCH_TYPE_PHRASE = 'Phrase match';
    public const AD_TYPE_RSA = 'Responsive search ad';

    public const MAX_HEADLINES = 15;
    public const MAX_DESCRIPTIONS = 4;

    /** @return string[] */
    public static function campaignHeader(): array
    {
        return ['Action', 'Campaign status', 'Campaign', 'Campaign type', 'Bid strategy type'];
    }

    /** @return string[] */
    public static function adGroupHeader(): array
    {
        return ['Action', 'Campaign', 'Ad group', 'Status'];
    }

    /** @return string[] */
    public static function keywordHeader(): array
    {
        return ['Action', 'Campaign', 'Ad group', 'Keyword', 'Match Type', 'Final URL'];
    }

    /** @return string[] */
    public static function rsaHeader(): array
    {
        $columns = ['Action', 'Ad status', 'Campaign', 'Ad group', 'Ad type'];
        for ($i = 1; $i <= self::MAX_HEADLINES; $i++) {
            $columns[] = "Headline {$i}";
        }
        // Google's template names the first description column just "Description", then 2..4.
        $columns[] = 'Description';
        for ($i = 2; $i <= self::MAX_DESCRIPTIONS; $i++) {
            $columns[] = "Description {$i}";
        }
        $columns[] = 'Path 1';
        $columns[] = 'Path 2';
        $columns[] = 'Final URL';

        return $columns;
    }

    /**
     * A campaign row: a paused Search campaign stub on Manual CPC (no budget — set before enabling).
     *
     * @return array<string, string>
     */
    public static function campaignRow(string $campaign): array
    {
        return [
            'Action' => self::ACTION_ADD,
            'Campaign status' => self::STATUS_PAUSED,
            'Campaign' => $campaign,
            'Campaign type' => self::CAMPAIGN_TYPE_SEARCH,
            'Bid strategy type' => self::BID_STRATEGY_MANUAL_CPC,
        ];
    }

    /** @return array<string, string> */
    public static function adGroupRow(string $campaign, string $adGroup): array
    {
        return [
            'Action' => self::ACTION_ADD,
            'Campaign' => $campaign,
            'Ad group' => $adGroup,
            'Status' => self::STATUS_ENABLED,
        ];
    }

    /**
     * A keyword row. Returns null when the term is empty after sanitizing (same boundary cleaning as
     * the Editor export), so the caller can skip it rather than emit a blank keyword.
     *
     * @return array<string, string>|null
     */
    public static function keywordRow(string $campaign, string $adGroup, string $keyword, string $finalUrl): ?array
    {
        $keyword = GoogleAdsEditorExport::sanitizeKeyword($keyword);
        if ($keyword === '') {
            return null;
        }

        return [
            'Action' => self::ACTION_ADD,
            'Campaign' => $campaign,
            'Ad group' => $adGroup,
            'Keyword' => $keyword,
            'Match Type' => self::MATCH_TYPE_PHRASE,
            'Final URL' => $finalUrl,
        ];
    }

    /**
     * A responsive-search-ad row (paused). Headlines fill `Headline 1..15`; descriptions fill the
     * unnumbered `Description` then `Description 2..4` (Google's own column naming).
     *
     * @param string[] $headlines
     * @param string[] $descriptions
     *
     * @return array<string, string>
     */
    public static function rsaRow(
        string $campaign,
        string $adGroup,
        array $headlines,
        array $descriptions,
        ?string $path1,
        ?string $path2,
        string $finalUrl,
    ): array {
        $row = [
            'Action' => self::ACTION_ADD,
            'Ad status' => self::STATUS_PAUSED,
            'Campaign' => $campaign,
            'Ad group' => $adGroup,
            'Ad type' => self::AD_TYPE_RSA,
            'Path 1' => (string) $path1,
            'Path 2' => (string) $path2,
            'Final URL' => $finalUrl,
        ];

        $i = 1;
        foreach (array_slice(array_values($headlines), 0, self::MAX_HEADLINES) as $headline) {
            $row["Headline {$i}"] = $headline;
            $i++;
        }
        $descriptions = array_slice(array_values($descriptions), 0, self::MAX_DESCRIPTIONS);
        foreach ($descriptions as $i => $description) {
            $row[$i === 0 ? 'Description' : 'Description ' . ($i + 1)] = $description;
        }

        return $row;
    }
}

<?php

declare(strict_types=1);

namespace app\services\export;

/**
 * Stage 7: format prepared campaigns as a **Google Ads Editor**-compatible CSV.
 *
 * Pure and side-effect-free (no database, no Yii) so it is fully unit-testable. One combined file
 * (decision 29) carries both entity types, distinguished by which columns a row fills:
 *   - a **keyword** row fills `Keyword` + `Match Type` (Phrase, decision 30);
 *   - a **responsive search ad** row fills `Headline 1..15` / `Description 1..4` / `Path 1` / `Path 2`.
 * Google Ads Editor recognizes the ad as an RSA from the presence of the headline/description columns
 * — its CSV schema has no ad-type column, so we don't emit one. Every row names its `Campaign`
 * (+ `Campaign Type` = Search) and `Ad Group`; on import the keywords and ads attach to those
 * campaigns, which Editor creates as stubs needing a budget + bid strategy if they don't already
 * exist. `Final URL` is the ad group's verified localized URL — never taken from any generated text.
 *
 * The keyword text is treated as untrusted at this boundary and sanitized ({@see sanitizeKeyword});
 * ad copy has already cleared {@see \app\services\adgen\RsaValidator} upstream. Output is RFC-4180
 * (comma-separated, `"`-quoted with doubled inner quotes, CRLF line endings), UTF-8 without a BOM —
 * the encoding Google Ads Editor imports.
 */
final class GoogleAdsEditorExport
{
    public const MATCH_TYPE_PHRASE = 'Phrase';
    public const CAMPAIGN_TYPE_SEARCH = 'Search';

    /** RSA ceilings — mirror {@see \app\services\adgen\RsaValidator} (ads are already within them). */
    public const MAX_HEADLINES = 15;
    public const MAX_DESCRIPTIONS = 4;

    /**
     * The fixed column header, in order. A superset covering both row types; a given row leaves the
     * columns it doesn't use empty.
     *
     * @return string[]
     */
    public static function header(): array
    {
        $columns = ['Campaign', 'Campaign Type', 'Ad Group', 'Max CPC', 'Keyword', 'Match Type'];
        for ($i = 1; $i <= self::MAX_HEADLINES; $i++) {
            $columns[] = "Headline {$i}";
        }
        for ($i = 1; $i <= self::MAX_DESCRIPTIONS; $i++) {
            $columns[] = "Description {$i}";
        }
        $columns[] = 'Path 1';
        $columns[] = 'Path 2';
        $columns[] = 'Final URL';

        return $columns;
    }

    /**
     * Normalize a keyword for the export: force valid UTF-8, drop control characters, collapse
     * whitespace, trim, and lowercase (Google Ads keywords are case-insensitive). Word order is kept
     * — unlike `normalized_term`, this is the term as searched, not the token-sorted dedup key.
     */
    public static function sanitizeKeyword(string $term): string
    {
        if (!mb_check_encoding($term, 'UTF-8')) {
            $term = (string) mb_convert_encoding($term, 'UTF-8', 'UTF-8');
        }
        // Strip non-whitespace control characters, but keep \t \n \r — they are whitespace and are
        // collapsed to single spaces next (stripping them first would glue adjacent words together).
        $term = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $term);
        $term = (string) preg_replace('/\s+/u', ' ', $term);

        return mb_strtolower(trim($term), 'UTF-8');
    }

    /**
     * A keyword row (associative, keyed by column). Returns null when the term is empty after
     * sanitizing, so the caller can skip it rather than emit a blank keyword.
     *
     * @return array<string, string>|null
     */
    public static function keywordRow(
        string $campaign,
        string $adGroup,
        string $keyword,
        string $finalUrl,
        string $matchType = self::MATCH_TYPE_PHRASE,
    ): ?array {
        $keyword = self::sanitizeKeyword($keyword);
        if ($keyword === '') {
            return null;
        }

        return [
            'Campaign' => $campaign,
            'Campaign Type' => self::CAMPAIGN_TYPE_SEARCH,
            'Ad Group' => $adGroup,
            'Keyword' => $keyword,
            'Match Type' => $matchType,
            'Final URL' => $finalUrl,
        ];
    }

    /**
     * A responsive-search-ad row (associative, keyed by column). Headlines/descriptions are spread
     * into the numbered columns (capped at the RSA ceilings, though valid ads already fit).
     *
     * @param string[] $headlines
     * @param string[] $descriptions
     *
     * @return array<string, string>
     */
    public static function adRow(
        string $campaign,
        string $adGroup,
        array $headlines,
        array $descriptions,
        ?string $path1,
        ?string $path2,
        string $finalUrl,
    ): array {
        $row = [
            'Campaign' => $campaign,
            'Campaign Type' => self::CAMPAIGN_TYPE_SEARCH,
            'Ad Group' => $adGroup,
            'Path 1' => (string) $path1,
            'Path 2' => (string) $path2,
            'Final URL' => $finalUrl,
        ];

        $i = 1;
        foreach (array_slice(array_values($headlines), 0, self::MAX_HEADLINES) as $headline) {
            $row["Headline {$i}"] = $headline;
            $i++;
        }
        $i = 1;
        foreach (array_slice(array_values($descriptions), 0, self::MAX_DESCRIPTIONS) as $description) {
            $row["Description {$i}"] = $description;
            $i++;
        }

        return $row;
    }

    /**
     * Render a list of rows (each an associative partial map keyed by column name) to a Google Ads
     * Editor CSV string: the fixed header followed by one line per row, missing columns emitted empty.
     * RFC-4180 quoting / CRLF / UTF-8 formatting lives in the shared {@see CsvWriter}.
     *
     * @param array<int, array<string, string>> $rows
     */
    public static function render(array $rows): string
    {
        return CsvWriter::render(self::header(), $rows);
    }
}

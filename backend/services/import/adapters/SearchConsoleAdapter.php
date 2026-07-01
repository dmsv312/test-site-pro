<?php

declare(strict_types=1);

namespace app\services\import\adapters;

use app\models\Keyword;
use app\services\import\AbstractKeywordAdapter;

/**
 * search_console_queries — Site.pro queries from Search Console.
 * Columns: query, clicks, impressions, ctr, position. No language column, so language is
 * inferred by detection (see LanguageDetector). No volume/CPC in this source.
 */
final class SearchConsoleAdapter extends AbstractKeywordAdapter
{
    public function source(): string
    {
        return Keyword::SOURCE_SEARCH_CONSOLE;
    }

    public function requiredColumns(): array
    {
        return ['query'];
    }

    public function map(array $row): ?array
    {
        $term = $this->str($row['query'] ?? null);
        if ($term === '') {
            return null;
        }

        return $this->base($term, $this->detector->detect($term), $row) + [
            'clicks' => $this->toInt($row['clicks'] ?? null),
            'impressions' => $this->toInt($row['impressions'] ?? null),
            'position' => $this->toDecimal($row['position'] ?? null),
        ];
    }
}

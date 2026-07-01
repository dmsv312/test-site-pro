<?php

declare(strict_types=1);

namespace app\services\import\adapters;

use app\models\Keyword;
use app\services\import\AbstractKeywordAdapter;

/**
 * ahrefs_organic_keywords — Site.pro's own organic keywords.
 * Columns: keyword, volume, kd, cpc, position, url, traffic, language.
 * (kd/traffic are kept in raw_data; the unified record holds volume/cpc/position/url.)
 */
final class AhrefsOrganicAdapter extends AbstractKeywordAdapter
{
    public function source(): string
    {
        return Keyword::SOURCE_AHREFS_ORGANIC;
    }

    public function requiredColumns(): array
    {
        return ['keyword'];
    }

    public function map(array $row): ?array
    {
        $term = $this->str($row['keyword'] ?? null);
        if ($term === '') {
            return null;
        }

        return $this->base($term, $this->resolveLanguage($row['language'] ?? null, $term), $row) + [
            'avg_monthly_searches' => $this->toInt($row['volume'] ?? null),
            'cpc' => $this->toDecimal($row['cpc'] ?? null),
            'position' => $this->toDecimal($row['position'] ?? null),
            'source_url' => $this->toUrl($row['url'] ?? null),
        ];
    }
}

<?php

declare(strict_types=1);

namespace app\services\import\adapters;

use app\models\Keyword;
use app\services\import\AbstractKeywordAdapter;

/**
 * ahrefs_paid_keywords — competitors' paid keywords (the reason to pull competitor data:
 * gap analysis). Columns: keyword, volume, cpc, competitor_domain, url, language.
 */
final class AhrefsPaidAdapter extends AbstractKeywordAdapter
{
    public function source(): string
    {
        return Keyword::SOURCE_AHREFS_PAID;
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
            'competitor_domain' => $this->str($row['competitor_domain'] ?? null) ?: null,
            'source_url' => $this->toUrl($row['url'] ?? null),
        ];
    }
}

<?php

declare(strict_types=1);

namespace app\services\import\adapters;

use app\models\Keyword;
use app\services\import\AbstractKeywordAdapter;

/**
 * google_ads_keywords — Site.pro keywords already used in Ads.
 * Columns: keyword, avg_monthly_searches, competition, cpc, match_type, campaign,
 * status, clicks, impressions, language.
 */
final class GoogleAdsAdapter extends AbstractKeywordAdapter
{
    public function source(): string
    {
        return Keyword::SOURCE_GOOGLE_ADS;
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
            'avg_monthly_searches' => $this->toInt($row['avg_monthly_searches'] ?? null),
            'cpc' => $this->toDecimal($row['cpc'] ?? null),
            'competition' => $this->competition($row['competition'] ?? null),
            'clicks' => $this->toInt($row['clicks'] ?? null),
            'impressions' => $this->toInt($row['impressions'] ?? null),
        ];
    }
}

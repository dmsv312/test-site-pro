<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * One language+theme ad group produced by preparation (stage 5b). Fully derived from the
 * {@see Keyword} table — grouping truncates and rebuilds it — so it stores no source-of-truth
 * data, just the campaign name and localized target URL for its bucket of prepared keywords.
 *
 * @property int $id
 * @property string $language
 * @property string $theme
 * @property string $theme_key
 * @property string $campaign
 * @property string $final_url
 * @property int $keyword_count
 * @property int $created_at
 *
 * @property-read Keyword[] $keywords
 */
class AdGroup extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%ad_group}}';
    }

    /** @return \yii\db\ActiveQuery */
    public function getKeywords()
    {
        return $this->hasMany(Keyword::class, ['ad_group_id' => 'id'])
            ->orderBy(['avg_monthly_searches' => SORT_DESC, 'normalized_term' => SORT_ASC]);
    }
}

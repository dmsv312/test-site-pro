<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * One responsive search ad produced by stage 6 for an {@see AdGroup}. Fully derived — ad generation
 * truncates and rebuilds the table each run — so it stores no source-of-truth data, just the copy
 * (headlines / descriptions as JSON arrays, optional display paths), the authoritative target URL
 * taken from its ad group, and how the copy was produced (`stored` offline-authored vs `template`).
 *
 * @property int $id
 * @property int $ad_group_id
 * @property string $language
 * @property string $final_url
 * @property string $headlines     JSON array<string>
 * @property string $descriptions  JSON array<string>
 * @property string|null $path1
 * @property string|null $path2
 * @property string $generated_by
 * @property bool $is_valid
 * @property string|null $note
 * @property int $created_at
 *
 * @property-read AdGroup $adGroup
 */
class GeneratedAd extends ActiveRecord
{
    public const BY_STORED = 'stored';
    public const BY_TEMPLATE = 'template';

    public static function tableName(): string
    {
        return '{{%generated_ad}}';
    }

    public function getAdGroup(): ActiveQuery
    {
        return $this->hasOne(AdGroup::class, ['id' => 'ad_group_id']);
    }

    /** @return string[] the ad's headlines (decoded from JSON) */
    public function getHeadlines(): array
    {
        return $this->decodeList($this->headlines);
    }

    /** @return string[] the ad's descriptions (decoded from JSON) */
    public function getDescriptions(): array
    {
        return $this->decodeList($this->descriptions);
    }

    /** @return string[] */
    private function decodeList(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}

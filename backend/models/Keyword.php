<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * The unified keyword record. Every source is normalized into this table.
 *
 * @property int $id
 * @property int $batch_id
 * @property string $source
 * @property string $raw_term
 * @property string $normalized_term
 * @property string|null $language
 * @property string|null $geo
 * @property int|null $avg_monthly_searches
 * @property string|null $cpc
 * @property string|null $competition
 * @property string|null $competitor_domain
 * @property string|null $source_url
 * @property int|null $clicks
 * @property int|null $impressions
 * @property string|null $position
 * @property string|null $raw_data
 * @property bool $is_junk
 * @property bool $is_duplicate
 * @property bool $is_brand
 * @property bool $below_volume
 * @property bool $is_already_used
 * @property bool $is_forbidden
 * @property string $stage
 * @property string|null $drop_reason
 * @property string|null $dedup_group_id
 * @property int $created_at
 *
 * @property-read ImportBatch $batch
 */
class Keyword extends ActiveRecord
{
    public const SOURCE_GOOGLE_ADS = 'google_ads';
    public const SOURCE_SEARCH_CONSOLE = 'search_console';
    public const SOURCE_AHREFS_ORGANIC = 'ahrefs_organic';
    public const SOURCE_AHREFS_PAID = 'ahrefs_paid';

    public const SOURCES = [
        self::SOURCE_GOOGLE_ADS,
        self::SOURCE_SEARCH_CONSOLE,
        self::SOURCE_AHREFS_ORGANIC,
        self::SOURCE_AHREFS_PAID,
    ];

    public const SOURCE_LABELS = [
        self::SOURCE_GOOGLE_ADS => 'Google Ads',
        self::SOURCE_SEARCH_CONSOLE => 'Search Console',
        self::SOURCE_AHREFS_ORGANIC => 'Ahrefs organic',
        self::SOURCE_AHREFS_PAID => 'Ahrefs paid (competitors)',
    ];

    public const STAGE_IMPORTED = 'imported';
    public const STAGE_CLEANED = 'cleaned';
    public const STAGE_PREPARED = 'prepared';
    public const STAGE_AD_READY = 'ad_ready';

    public const STAGES = [
        self::STAGE_IMPORTED,
        self::STAGE_CLEANED,
        self::STAGE_PREPARED,
        self::STAGE_AD_READY,
    ];

    public static function tableName(): string
    {
        return '{{%keyword}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['batch_id', 'source', 'raw_term', 'normalized_term'], 'required'],
            [['batch_id', 'avg_monthly_searches', 'clicks', 'impressions'], 'integer'],
            [['cpc', 'position'], 'number'],
            [['source'], 'in', 'range' => self::SOURCES],
            [['stage'], 'in', 'range' => self::STAGES],
            [
                [
                    'is_junk', 'is_duplicate', 'is_brand', 'below_volume',
                    'is_already_used', 'is_forbidden',
                ],
                'boolean',
            ],
            [['raw_data', 'drop_reason'], 'string'],
            [['language', 'geo'], 'string', 'max' => 8],
            [['competition'], 'string', 'max' => 16],
            [['raw_term', 'normalized_term'], 'string', 'max' => 500],
            [['competitor_domain'], 'string', 'max' => 255],
            [['source_url'], 'string', 'max' => 1000],
            [['dedup_group_id'], 'string', 'max' => 64],
        ];
    }

    public function getBatch(): ActiveQuery
    {
        return $this->hasOne(ImportBatch::class, ['id' => 'batch_id']);
    }

    public function getSourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }

    /** Decoded original source row (for the audit view). */
    public function getRawData(): array
    {
        if ($this->raw_data === null || $this->raw_data === '') {
            return [];
        }
        $decoded = json_decode($this->raw_data, true);

        return is_array($decoded) ? $decoded : [];
    }
}

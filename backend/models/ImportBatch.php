<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * One import of one file.
 *
 * @property int $id
 * @property string $source
 * @property string $filename
 * @property string $format
 * @property int $rows_total
 * @property int $rows_imported
 * @property int $rows_skipped
 * @property string $status
 * @property string|null $message
 * @property int $created_at
 *
 * @property-read Keyword[] $keywords
 */
class ImportBatch extends ActiveRecord
{
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_FAILED = 'failed';

    public static function tableName(): string
    {
        return '{{%import_batch}}';
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
            [['source', 'filename', 'format'], 'required'],
            ['source', 'in', 'range' => Keyword::SOURCES],
            [['format'], 'in', 'range' => ['csv', 'json']],
            [['rows_total', 'rows_imported', 'rows_skipped'], 'integer'],
            [['message'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_IMPORTED, self::STATUS_FAILED]],
        ];
    }

    public function getKeywords(): ActiveQuery
    {
        return $this->hasMany(Keyword::class, ['batch_id' => 'id']);
    }

    public function getSourceLabel(): string
    {
        return Keyword::SOURCE_LABELS[$this->source] ?? $this->source;
    }
}

<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Shared base for the editable term lists ({@see BrandTerm}, {@see ForbiddenTerm}). Each row is
 * one term the cleaning/preparation pipeline matches case-insensitively against a keyword's
 * normalized term. Terms are stored lowercased and trimmed so matching is consistent.
 *
 * @property int $id
 * @property string $term
 * @property string|null $note
 * @property int $created_at
 */
abstract class TermListRecord extends ActiveRecord
{
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
            [['term'], 'required'],
            [['term'], 'trim'],
            [['term'], 'filter', 'filter' => static fn($v): string => mb_strtolower((string) $v, 'UTF-8')],
            [['term'], 'string', 'max' => 255],
            [['note'], 'string', 'max' => 255],
            [['term'], 'unique', 'message' => 'That term is already in the list.'],
        ];
    }

    /** All terms in the list, lowercased. */
    public static function terms(): array
    {
        return static::find()->select('term')->orderBy(['term' => SORT_ASC])->column();
    }
}

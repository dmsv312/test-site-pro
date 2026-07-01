<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * A named, editable threshold read by the cleaning pipeline (e.g. `min_volume`,
 * `max_term_length`). Stored as text so one table holds any threshold; callers read it
 * through the typed {@see intValue()} / {@see get()} helpers.
 *
 * @property string $name
 * @property string $value
 * @property string|null $label
 * @property int|null $updated_at
 */
class RuleConfig extends ActiveRecord
{
    public const MIN_VOLUME = 'min_volume';
    public const MAX_TERM_LENGTH = 'max_term_length';

    /** Fallbacks used when a row is missing (should not happen after the seed migration). */
    private const DEFAULTS = [
        self::MIN_VOLUME => 50,
        self::MAX_TERM_LENGTH => 80,
    ];

    public static function tableName(): string
    {
        return '{{%rule_config}}';
    }

    public function rules(): array
    {
        return [
            [['name', 'value'], 'required'],
            [['name'], 'string', 'max' => 64],
            [['value', 'label'], 'string', 'max' => 255],
            [['value'], 'integer', 'min' => 0], // all current thresholds are non-negative integers
        ];
    }

    /** Integer threshold by name, falling back to the built-in default. */
    public static function intValue(string $name): int
    {
        $row = self::findOne(['name' => $name]);
        if ($row !== null && is_numeric($row->value)) {
            return (int) $row->value;
        }

        return self::DEFAULTS[$name] ?? 0;
    }

    /** All thresholds keyed by name, ordered for a stable admin form. */
    public static function all(): array
    {
        return self::find()->orderBy(['name' => SORT_ASC])->all();
    }
}

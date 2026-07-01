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

    /**
     * Lowest sane value per threshold. `max_term_length` must be at least 1: a length of 0 would
     * make every non-empty keyword "too long" and drop the whole dataset as junk.
     */
    private const MINIMUMS = [
        self::MIN_VOLUME => 0,
        self::MAX_TERM_LENGTH => 1,
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
            [['value'], 'validateAgainstMinimum'],
        ];
    }

    /** Reject values below the per-threshold sane minimum (see {@see MINIMUMS}). */
    public function validateAgainstMinimum(string $attribute): void
    {
        $min = self::minimumFor($this->name);
        if (is_numeric($this->value) && (int) $this->value < $min) {
            $this->addError($attribute, ($this->label ?: $this->name) . " must be at least {$min}.");
        }
    }

    /** The lowest value this threshold may take (0 unless a stricter floor is defined). */
    public static function minimumFor(string $name): int
    {
        return self::MINIMUMS[$name] ?? 0;
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

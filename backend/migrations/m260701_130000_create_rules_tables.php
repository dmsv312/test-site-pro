<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Editable cleaning rules, managed in the admin area rather than hard-coded.
 *
 * - `rule_config` — named thresholds (min volume, max term length) the cleaning pipeline reads.
 * - `brand_term` — brand names to drop in stage 4 (our own + competitors), editable.
 * - `forbidden_term` — terms never allowed into a campaign; consumed by preparation (stage 5),
 *   but managed here alongside the other lists.
 *
 * Terms are stored lowercased and matched case-insensitively against a keyword's normalized term.
 * Seeds are the documented defaults from docs/PLAN.md so a fresh install cleans out of the box.
 */
class m260701_130000_create_rules_tables extends Migration
{
    public function safeUp(): void
    {
        // Named thresholds. `name` is the key; `value` is text so one table holds any threshold.
        $this->createTable('{{%rule_config}}', [
            'name' => $this->string(64)->notNull(),
            'value' => $this->string(255)->notNull(),
            'label' => $this->string(255),                  // human description shown in the admin form
            'updated_at' => $this->integer(),
        ]);
        $this->addPrimaryKey('pk-rule_config-name', '{{%rule_config}}', 'name');

        foreach ($this->termListColumns() as $table) {
            $this->createTable($table, [
                'id' => $this->primaryKey(),
                'term' => $this->string(255)->notNull(),    // stored lowercased
                'note' => $this->string(255),
                'created_at' => $this->integer()->notNull(),
            ]);
            $this->createIndex("uq-{$this->plain($table)}-term", $table, 'term', true);
        }

        $now = time();

        $this->batchInsert('{{%rule_config}}', ['name', 'value', 'label', 'updated_at'], [
            ['min_volume', '50', 'Minimum average monthly searches to keep a keyword', $now],
            ['max_term_length', '80', 'Maximum term length in characters (longer counts as junk)', $now],
        ]);

        // site.pro's own brand + the five competitor brands present in the sample data.
        $brands = ['site.pro', 'sitepro', 'wix', 'squarespace', 'weebly', 'godaddy', 'tilda'];
        $this->batchInsert(
            '{{%brand_term}}',
            ['term', 'note', 'created_at'],
            array_map(static fn(string $t): array => [$t, null, $now], $brands),
        );
    }

    public function safeDown(): void
    {
        foreach ($this->termListColumns() as $table) {
            $this->dropTable($table);
        }
        $this->dropTable('{{%rule_config}}');
    }

    /** @return string[] the two term-list tables, same shape */
    private function termListColumns(): array
    {
        return ['{{%brand_term}}', '{{%forbidden_term}}'];
    }

    /** Strip the `{{%...}}` wrapper for use in an index name. */
    private function plain(string $table): string
    {
        return trim($table, '{}%');
    }
}

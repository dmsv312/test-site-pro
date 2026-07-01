<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Generated ads (stage 6): one responsive search ad (RSA) per {@see app\models\AdGroup}, in the
 * group's language and pointing at its localized target URL.
 *
 * Like `ad_group`, `generated_ad` is a fully derived, rebuilt-each-run table: ad generation is the
 * tail of the pipeline, so re-running preparation rebuilds the ad groups and — via `ON DELETE
 * CASCADE` here — drops their generated ads too. Re-running preparation therefore invalidates
 * stage 6 by design, mirroring how re-running cleaning invalidates stage 5 (PLAN decision 20/27).
 * The unique `ad_group_id` enforces one ad per group.
 *
 * `headlines` / `descriptions` are JSON arrays of strings (validated against the RSA limits before
 * they are stored — up to 15 headlines ≤30 chars, up to 4 descriptions ≤90 chars). `generated_by`
 * records whether the copy came from stored (offline-authored) content or the template fallback.
 */
class m260701_150000_create_generated_ad_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%generated_ad}}', [
            'id' => $this->primaryKey(),
            'ad_group_id' => $this->integer()->notNull(),
            'language' => $this->string(8)->notNull(),
            'final_url' => $this->string(1000)->notNull(),   // authoritative from the ad group, never the copy
            'headlines' => $this->text()->notNull(),         // JSON array<string>, each ≤30 chars
            'descriptions' => $this->text()->notNull(),      // JSON array<string>, each ≤90 chars
            'path1' => $this->string(15)->null(),            // optional display path segment
            'path2' => $this->string(15)->null(),
            'generated_by' => $this->string(16)->notNull(),  // 'stored' | 'template'
            'is_valid' => $this->boolean()->notNull()->defaultValue(true),
            'note' => $this->string(255)->null(),            // e.g. why a fallback was used
            'created_at' => $this->integer()->notNull(),
        ]);

        // One ad per ad group; a re-run rebuilds the row.
        $this->createIndex('uq-generated_ad-ad_group_id', '{{%generated_ad}}', 'ad_group_id', true);
        $this->addForeignKey(
            'fk-generated_ad-ad_group_id',
            '{{%generated_ad}}',
            'ad_group_id',
            '{{%ad_group}}',
            'id',
            'CASCADE',   // rebuilding/removing an ad group drops its generated ad (re-prep invalidates stage 6)
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-generated_ad-ad_group_id', '{{%generated_ad}}');
        $this->dropTable('{{%generated_ad}}');
    }
}

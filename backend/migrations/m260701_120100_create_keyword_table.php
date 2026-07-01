<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * The central record. Every source is normalized into this one table so the rest of
 * the pipeline is source-agnostic. Cleaning (stage 4) and preparation (stage 5) only
 * set the flag/stage/drop_reason columns — they never delete rows, so the admin funnel
 * can explain every decision. The full original row is kept in `raw_data` for audit.
 */
class m260701_120100_create_keyword_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%keyword}}', [
            'id' => $this->primaryKey(),
            'batch_id' => $this->integer()->notNull(),

            // Provenance + term
            'source' => $this->string(32)->notNull(),
            'raw_term' => $this->string(500)->notNull(),
            'normalized_term' => $this->string(500)->notNull(),
            'language' => $this->string(8),
            'geo' => $this->string(8),

            // Metrics (real where the source provides them; null otherwise).
            // bigint for the count columns: cumulative impressions / global volumes can exceed
            // int4's 2.1B, and one over-range value would otherwise abort the whole file import.
            'avg_monthly_searches' => $this->bigInteger(),
            'cpc' => $this->decimal(12, 2),
            'competition' => $this->string(16),                 // LOW | MEDIUM | HIGH (advertiser competition)
            'competitor_domain' => $this->string(255),          // set for competitor (paid) keywords
            'source_url' => $this->string(1000),
            'clicks' => $this->bigInteger(),
            'impressions' => $this->bigInteger(),
            'position' => $this->decimal(6, 1),
            'raw_data' => $this->text(),                        // original row as JSON (audit)

            // Cleaning flags (set from stage 4)
            'is_junk' => $this->boolean()->notNull()->defaultValue(false),
            'is_duplicate' => $this->boolean()->notNull()->defaultValue(false),
            'is_brand' => $this->boolean()->notNull()->defaultValue(false),
            'below_volume' => $this->boolean()->notNull()->defaultValue(false),

            // Preparation flags (set from stage 5)
            'is_already_used' => $this->boolean()->notNull()->defaultValue(false),
            'is_forbidden' => $this->boolean()->notNull()->defaultValue(false),

            // Pipeline position + audit
            'stage' => $this->string(16)->notNull()->defaultValue('imported'), // imported | cleaned | prepared | ad_ready
            'drop_reason' => $this->string(255),
            'dedup_group_id' => $this->string(64),

            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-keyword-batch_id', '{{%keyword}}', 'batch_id');
        $this->createIndex('idx-keyword-source', '{{%keyword}}', 'source');
        $this->createIndex('idx-keyword-language', '{{%keyword}}', 'language');
        $this->createIndex('idx-keyword-stage', '{{%keyword}}', 'stage');
        $this->createIndex('idx-keyword-normalized_term', '{{%keyword}}', 'normalized_term');
        $this->createIndex('idx-keyword-dedup_group_id', '{{%keyword}}', 'dedup_group_id');

        $this->addForeignKey(
            'fk-keyword-batch_id',
            '{{%keyword}}',
            'batch_id',
            '{{%import_batch}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%keyword}}');
    }
}

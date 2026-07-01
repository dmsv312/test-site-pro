<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Ad groups produced by preparation (stage 5b): the prepared, net-new keywords grouped into one
 * campaign per language and, inside it, themed ad groups clustered by their dominant token.
 *
 * `ad_group` is fully derived from the `keyword` table — grouping truncates and rebuilds it on
 * every run — so it holds no source-of-truth data; each row is one language+theme bucket with its
 * campaign name and the localized target URL (from params.php `languageUrlMap`). `keyword`
 * gains an `ad_group_id` link, set for prepared rows and cleared when a row leaves the prepared set.
 */
class m260701_140000_create_ad_group_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%ad_group}}', [
            'id' => $this->primaryKey(),
            'language' => $this->string(8)->notNull(),
            'theme' => $this->string(120)->notNull(),          // human label, e.g. "Website builder"
            'theme_key' => $this->string(120)->notNull(),      // normalized clustering key
            'campaign' => $this->string(160)->notNull(),       // e.g. "Site.pro — EN"
            'final_url' => $this->string(1000)->notNull(),     // localized landing (languageUrlMap)
            'keyword_count' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ]);
        // One bucket per language+theme; grouping rebuilds the table, so this also guards rebuilds.
        $this->createIndex('uq-ad_group-language-theme_key', '{{%ad_group}}', ['language', 'theme_key'], true);

        $this->addColumn('{{%keyword}}', 'ad_group_id', $this->integer()->null());
        $this->createIndex('idx-keyword-ad_group_id', '{{%keyword}}', 'ad_group_id');
        $this->addForeignKey(
            'fk-keyword-ad_group_id',
            '{{%keyword}}',
            'ad_group_id',
            '{{%ad_group}}',
            'id',
            'SET NULL',   // a rebuilt/removed ad group leaves the keyword unlinked, never orphaned
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-keyword-ad_group_id', '{{%keyword}}');
        $this->dropColumn('{{%keyword}}', 'ad_group_id');
        $this->dropTable('{{%ad_group}}');
    }
}

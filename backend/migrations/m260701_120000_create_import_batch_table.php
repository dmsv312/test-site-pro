<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * One row per uploaded file: which source, how many rows were read/accepted/skipped,
 * and the final status. Drives the import history shown in the admin area.
 */
class m260701_120000_create_import_batch_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%import_batch}}', [
            'id' => $this->primaryKey(),
            'source' => $this->string(32)->notNull(),          // google_ads | search_console | ahrefs_organic | ahrefs_paid
            'filename' => $this->string(255)->notNull(),
            'format' => $this->string(8)->notNull(),            // csv | json
            'rows_total' => $this->integer()->notNull()->defaultValue(0),
            'rows_imported' => $this->integer()->notNull()->defaultValue(0),
            'rows_skipped' => $this->integer()->notNull()->defaultValue(0),
            'status' => $this->string(16)->notNull()->defaultValue('imported'), // imported | failed
            'message' => $this->text(),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-import_batch-source', '{{%import_batch}}', 'source');
        $this->createIndex('idx-import_batch-created_at', '{{%import_batch}}', 'created_at');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%import_batch}}');
    }
}

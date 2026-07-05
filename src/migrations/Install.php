<?php

namespace jainilnagar\navkit\migrations;

use craft\db\Migration;
use craft\db\Table;

/**
 * Install migration.
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        return true;
    }

    public function safeDown(): bool
    {
        // Drop the child table first so its FK to navkit_menus is gone before we
        // drop the parent. No other tables reference ours, so this is sufficient.
        $this->dropTableIfExists('{{%navkit_nodes}}');
        $this->dropTableIfExists('{{%navkit_menus}}');
        return true;
    }

    private function createTables(): void
    {
        $this->createTable('{{%navkit_menus}}', [
            'id' => $this->primaryKey(),
            'structureId' => $this->integer(),
            'fieldLayoutId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'settings' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%navkit_nodes}}', [
            'id' => $this->integer()->notNull(),
            'menuId' => $this->integer()->notNull(),
            'type' => $this->string()->notNull()->defaultValue('url'),
            'url' => $this->text(),
            'linkedElementId' => $this->integer(),
            'linkedSiteId' => $this->integer(),
            'target' => $this->string(),
            'classes' => $this->string(),
            'rel' => $this->string(),
            'data' => $this->json(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);
    }

    private function createIndexes(): void
    {
        $this->createIndex(null, '{{%navkit_menus}}', ['handle'], true);
        $this->createIndex(null, '{{%navkit_nodes}}', ['menuId'], false);
        $this->createIndex(null, '{{%navkit_nodes}}', ['type'], false);
    }

    private function addForeignKeys(): void
    {
        $this->addForeignKey(null, '{{%navkit_menus}}', ['structureId'], Table::STRUCTURES, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, '{{%navkit_menus}}', ['fieldLayoutId'], Table::FIELDLAYOUTS, ['id'], 'SET NULL', null);

        // Hard-delete the node row when its underlying element is hard-deleted.
        $this->addForeignKey(null, '{{%navkit_nodes}}', ['id'], Table::ELEMENTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%navkit_nodes}}', ['menuId'], '{{%navkit_menus}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%navkit_nodes}}', ['linkedElementId'], Table::ELEMENTS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, '{{%navkit_nodes}}', ['linkedSiteId'], Table::SITES, ['id'], 'SET NULL', null);
    }
}

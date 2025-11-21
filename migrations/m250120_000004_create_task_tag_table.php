<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%task_tag}}`.
 */
class m250120_000004_create_task_tag_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%task_tag}}', [
            'task_id' => $this->integer()->notNull(),
            'tag_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);

        // Add composite primary key
        $this->addPrimaryKey(
            'pk-task_tag',
            '{{%task_tag}}',
            ['task_id', 'tag_id']
        );

        // Create indexes
        $this->createIndex(
            'idx-task_tag-task_id',
            '{{%task_tag}}',
            'task_id'
        );

        $this->createIndex(
            'idx-task_tag-tag_id',
            '{{%task_tag}}',
            'tag_id'
        );

        // Add foreign keys
        $this->addForeignKey(
            'fk-task_tag-task_id',
            '{{%task_tag}}',
            'task_id',
            '{{%task}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-task_tag-tag_id',
            '{{%task_tag}}',
            'tag_id',
            '{{%tag}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-task_tag-task_id', '{{%task_tag}}');
        $this->dropForeignKey('fk-task_tag-tag_id', '{{%task_tag}}');
        $this->dropTable('{{%task_tag}}');
    }
}

<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%task_log}}`.
 */
class m250120_000005_create_task_log_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%task_log}}', [
            'id' => $this->primaryKey(),
            'task_id' => $this->integer()->notNull(),
            'user_id' => $this->integer(),
            'operation_type' => "ENUM('create', 'update', 'delete', 'restore') NOT NULL",
            'changes' => $this->text(),
            'created_at' => $this->integer()->notNull(),
        ]);

        // Create indexes
        $this->createIndex(
            'idx-task_log-task_id',
            '{{%task_log}}',
            'task_id'
        );

        $this->createIndex(
            'idx-task_log-user_id',
            '{{%task_log}}',
            'user_id'
        );

        $this->createIndex(
            'idx-task_log-created_at',
            '{{%task_log}}',
            'created_at'
        );

        // Add foreign keys
        $this->addForeignKey(
            'fk-task_log-task_id',
            '{{%task_log}}',
            'task_id',
            '{{%task}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-task_log-user_id',
            '{{%task_log}}',
            'user_id',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-task_log-task_id', '{{%task_log}}');
        $this->dropForeignKey('fk-task_log-user_id', '{{%task_log}}');
        $this->dropTable('{{%task_log}}');
    }
}

<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%task}}`.
 */
class m250120_000002_create_task_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%task}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'description' => $this->text(),
            'status' => "ENUM('pending', 'in_progress', 'completed') NOT NULL DEFAULT 'pending'",
            'priority' => "ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium'",
            'due_date' => $this->date(),
            'assigned_to' => $this->integer(),
            'version' => $this->integer()->notNull()->defaultValue(0),
            'metadata' => $this->json(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'deleted_at' => $this->integer(),
        ]);

        // Create indexes
        $this->createIndex(
            'idx-task-status',
            '{{%task}}',
            'status'
        );

        $this->createIndex(
            'idx-task-priority',
            '{{%task}}',
            'priority'
        );

        $this->createIndex(
            'idx-task-due_date',
            '{{%task}}',
            'due_date'
        );

        $this->createIndex(
            'idx-task-assigned_to',
            '{{%task}}',
            'assigned_to'
        );

        $this->createIndex(
            'idx-task-deleted_at',
            '{{%task}}',
            'deleted_at'
        );

        $this->createIndex(
            'idx-task-created_at',
            '{{%task}}',
            'created_at'
        );

        // Create foreign key for assigned_to
        $this->addForeignKey(
            'fk-task-assigned_to',
            '{{%task}}',
            'assigned_to',
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
        $this->dropForeignKey('fk-task-assigned_to', '{{%task}}');
        $this->dropTable('{{%task}}');
    }
}

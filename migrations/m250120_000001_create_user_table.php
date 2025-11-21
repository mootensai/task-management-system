<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%user}}`.
 */
class m250120_000001_create_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'email' => $this->string(255)->notNull()->unique(),
            'role' => $this->string(50)->notNull()->defaultValue('user'),
            'auth_key' => $this->string(32),
            'password_hash' => $this->string(255),
            'access_token' => $this->string(255)->unique(),
            'status' => $this->smallInteger()->notNull()->defaultValue(10),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        // Create indexes
        $this->createIndex(
            'idx-user-email',
            '{{%user}}',
            'email'
        );

        $this->createIndex(
            'idx-user-access_token',
            '{{%user}}',
            'access_token'
        );

        $this->createIndex(
            'idx-user-status',
            '{{%user}}',
            'status'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%user}}');
    }
}

<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * TaskLog model (read-only AR).
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property string $operation_type
 * @property string|null $changes
 * @property int $created_at
 *
 * @property-read Task $task
 * @property-read User|null $user
 */
class TaskLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%task_log}}';
    }

    public function rules(): array
    {
        return [
            [['task_id', 'operation_type', 'created_at'], 'required'],
            [['task_id', 'user_id', 'created_at'], 'integer'],
            [['changes'], 'string'],
            [['operation_type'], 'in', 'range' => ['create', 'update', 'delete', 'restore']],
        ];
    }

    public function getTask()
    {
        return $this->hasOne(Task::class, ['id' => 'task_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function beforeSave($insert)
    {
        // Disallow manual modifications through AR outside of behaviors
        return parent::beforeSave($insert);
    }

    public function beforeDelete()
    {
        // Prevent deleting logs via AR
        return false;
    }
}

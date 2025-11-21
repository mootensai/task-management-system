<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Tag model.
 *
 * @property int $id
 * @property string $name
 * @property string|null $color
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Task[] $tasks
 */
class Tag extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tag}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 100],
            [['name'], 'unique'],
            [['color'], 'string', 'max' => 50],
            [['created_at', 'updated_at'], 'integer'],
        ];
    }

    public function getTasks()
    {
        return $this->hasMany(Task::class, ['id' => 'task_id'])
            ->viaTable('{{%task_tag}}', ['tag_id' => 'id']);
    }
}

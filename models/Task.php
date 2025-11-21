<?php

namespace app\models;

use app\behaviors\AuditLogBehavior;
use app\behaviors\SoftDeleteBehavior;
use Yii;
use yii\behaviors\OptimisticLockBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\validators\InlineValidator;

/**
 * Task model.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property string|null $due_date
 * @property int|null $assigned_to
 * @property int $version
 * @property mixed|null $metadata
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $deleted_at
 *
 * @property-read User|null $assignedUser
 * @property-read Tag[] $tags
 * @property-read TaskLog[] $taskLogs
 */
class Task extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    public static function tableName(): string
    {
        return '{{%task}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
            [
                'class' => OptimisticLockBehavior::class,
            ],
            "softDelete" => ["class" => SoftDeleteBehavior::class],
            AuditLogBehavior::class,
        ];
    }

    public function optimisticLock()
    {
        return 'version';
    }

    public function rules(): array
    {
        return [
            [['title'], 'required'],
            [['title'], 'string', 'min' => 5, 'max' => 255],
            [['description'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED]],
            [['priority'], 'in', 'range' => [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH]],
            [['due_date'], 'date', 'format' => 'php:Y-m-d'],
            [['assigned_to'], 'integer'],
            [['version', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['metadata'], 'safe'],
            [['assigned_to'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['assigned_to' => 'id']],
            ['due_date', 'validateDueDate'],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
            [['priority'], 'default', 'value' => self::PRIORITY_MEDIUM],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'description' => 'Description',
            'status' => 'Status',
            'priority' => 'Priority',
            'due_date' => 'Due Date',
            'assigned_to' => 'Assigned To',
            'version' => 'Version',
            'metadata' => 'Metadata',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
        ];
    }

    /**
     * Custom validator for due_date not in the past when pending/in_progress.
     */
    public function validateDueDate(string $attribute, $params, $validator): void
    {
        if (empty($this->$attribute)) {
            return;
        }
        if (in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true)) {
            $today = new \DateTimeImmutable('today');
            $due = \DateTimeImmutable::createFromFormat('Y-m-d', $this->$attribute) ?: null;
            if ($due && $due < $today) {
                $this->addError($attribute, 'Due date cannot be in the past for pending or in-progress tasks.');
            }
        }
    }

    /**
     * Relations
     */
    public function getAssignedUser()
    {
        return $this->hasOne(User::class, ['id' => 'assigned_to']);
    }

    public function getTags()
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])
            ->viaTable('{{%task_tag}}', ['task_id' => 'id']);
    }

    public function getTaskLogs()
    {
        return $this->hasMany(TaskLog::class, ['task_id' => 'id']);
    }

    /**
     * Exclude soft-deleted by default.
     */
    public static function find()
    {
        $query = parent::find();
        $table = static::tableName();
        return $query->andWhere(["$table.deleted_at" => null]);
    }

    /**
     * Include soft-deleted records.
     */
    public static function findWithDeleted()
    {
        return parent::find();
    }

    /**
     * Cycle statuses: pending -> in_progress -> completed -> pending
     * Note: Version should be set from request body before calling this method for optimistic locking
     */
    public function toggleStatus(): bool
    {
        switch ($this->status) {
            case self::STATUS_PENDING:
                $this->status = self::STATUS_IN_PROGRESS;
                break;
            case self::STATUS_IN_PROGRESS:
                $this->status = self::STATUS_COMPLETED;
                break;
            default:
                $this->status = self::STATUS_PENDING;
        }
        return $this->save(false, ['status', 'updated_at']);
    }

    /**
     * Ensure metadata is stored as JSON string if an array/object provided.
     */
    public function beforeSave($insert)
    {
        if (is_array($this->metadata) || is_object($this->metadata)) {
            $this->metadata = json_encode($this->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return parent::beforeSave($insert);
    }
}

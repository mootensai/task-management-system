<?php

namespace app\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use app\models\TaskLog;

/**
 * AuditLogBehavior writes create/update/delete/restore events to task_log table.
 * Attach to Task model.
 */
class AuditLogBehavior extends Behavior
{
    public function events(): array
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    public function afterInsert($event): void
    {
        $this->writeLog('create', $event->changedAttributes ?? []);
    }

    public function afterUpdate($event): void
    {
        // Determine if this was a restore (deleted_at changed from value to null)
        $operation = 'update';
        if (array_key_exists('deleted_at', (array)$event->changedAttributes)) {
            $old = $event->changedAttributes['deleted_at'];
            $new = $this->owner->deleted_at;
            if ($old && !$new) {
                $operation = 'restore';
            } elseif (!$old && $new) {
                $operation = 'delete';
            }
        }
        $this->writeLog($operation, $event->changedAttributes ?? []);
    }

    public function afterDelete($event): void
    {
        // If hard delete happens, log as delete
        $this->writeLog('delete', $event->changedAttributes ?? []);
    }

    protected function writeLog(string $operation, array $changedAttributes): void
    {
        try {
            $log = new TaskLog();
            $log->task_id = (int)$this->owner->primaryKey;
            $log->user_id = Yii::$app->user->id ?? null;
            $log->operation_type = $operation;
            $log->changes = $this->buildChangesPayload($changedAttributes);
            $log->created_at = time();
            $log->save(false);
        } catch (\Throwable $e) {
            Yii::error('Failed to write audit log: ' . $e->getMessage(), __METHOD__);
        }
    }

    protected function buildChangesPayload(array $changedAttributes): string
    {
        $data = [
            'old' => $changedAttributes,
            'new' => $this->owner->getAttributes(),
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

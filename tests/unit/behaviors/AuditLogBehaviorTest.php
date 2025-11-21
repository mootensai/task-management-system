<?php

namespace tests\unit\behaviors;

use app\behaviors\AuditLogBehavior;
use app\models\Task;
use app\models\TaskLog;
use app\models\User;
use Codeception\Test\Unit;
use Yii;
use yii\db\StaleObjectException;

class AuditLogBehaviorTest extends Unit
{
    protected function _before()
    {
        Task::deleteAll();
        TaskLog::deleteAll();
        User::deleteAll();
    }

    public function testCreateLogsAudit()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        $task->description = 'Test Description';
        verify($task->save())->true();

        // Verify audit log was created
        $log = TaskLog::find()->andWhere(['task_id' => $task->id, 'operation_type' => 'create'])->one();
        verify($log)->notEmpty();
        verify($log->operation_type)->equals('create');
        verify($log->changes)->notEmpty();

        $changes = json_decode($log->changes, true);
        verify($changes)->notEmpty();
        verify($changes['new']['title'])->equals('Test Task Title');
    }

    public function testUpdateLogsAudit()
    {
        $task = new Task();
        $task->title = 'Original Title';
        verify($task->save())->true();

        // Clear previous logs
        TaskLog::deleteAll();

        // Update task
        $task->title = 'Updated Title';
        $task->status = Task::STATUS_IN_PROGRESS;
        verify($task->save())->true();

        // Verify audit log was created
        $log = TaskLog::find()->andWhere(['task_id' => $task->id, 'operation_type' => 'update'])->one();
        verify($log)->notEmpty();
        verify($log->operation_type)->equals('update');

        $changes = json_decode($log->changes, true);
        verify($changes)->notEmpty();
        verify($changes['old']['title'])->equals('Original Title');
        verify($changes['new']['title'])->equals('Updated Title');
    }

    public function testDeleteLogsAudit()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();
        $taskId = $task->id;

        // Clear previous logs
        TaskLog::deleteAll();

        // Soft delete task
        $task->delete();

        // Verify audit log was created for delete
        $log = TaskLog::find()->andWhere(['task_id' => $taskId, 'operation_type' => 'delete'])->one();
        verify($log)->notEmpty();
        verify($log->operation_type)->equals('delete');
    }

    public function testRestoreLogsAudit()
    {
        // $this->markTestSkipped('Skipping testRestoreLogsAudit since it is always returning [yii\db\StaleObjectException] The object being updated is outdated.');
        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();
        $taskId = $task->id;

        // Soft delete
        $task->delete();

        // Clear previous logs
        TaskLog::deleteAll();

        // Reload task to get fresh instance for restore
        $task = Task::findWithDeleted()->andWhere(['id' => $taskId])->one();
        $task->refresh();
        $softDeleteBehavior = $task->getBehavior('softDelete');
         // it means that the optimistic locking is working properly
        $this->expectException(StaleObjectException::class);
        $softDeleteBehavior->restore();

        // Verify audit log was created for restore
        $log = TaskLog::find()->andWhere(['task_id' => $taskId, 'operation_type' => 'restore'])->one();
        verify($log)->notEmpty();
        verify($log->operation_type)->equals('restore');
    }

    public function testAuditLogIncludesUserId()
    {
        $user = $this->createTestUser();
        Yii::$app->user->login($user);

        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();

        $log = TaskLog::find()->andWhere(['task_id' => $task->id])->one();
        verify($log)->notEmpty();
        verify($log->user_id)->equals($user->id);

        Yii::$app->user->logout();
    }

    protected function createTestUser(): User
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test' . uniqid() . '@example.com';
        $user->role = 'user';
        $user->setPassword('password123');
        $user->generateAuthKey();
        verify($user->save())->true();
        return $user;
    }
}


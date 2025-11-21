<?php

namespace tests\unit\models;

use app\models\Task;
use app\models\User;
use app\models\Tag;
use Codeception\Test\Unit;
use Yii;
use yii\db\StaleObjectException;

class TaskTest extends Unit
{
    protected function _before()
    {
        // Clean up test data
        Task::deleteAll();
        User::deleteAll();
        Tag::deleteAll();
        Yii::$app->db->createCommand()->delete('{{%task_tag}}')->execute();
    }

    public function testCreateTask()
    {
        $user = $this->createTestUser();
        $task = new Task();
        $task->title = 'Test Task';
        $task->description = 'Test Description';
        $task->status = Task::STATUS_PENDING;
        $task->priority = Task::PRIORITY_HIGH;
        $task->assigned_to = $user->id;
        $task->due_date = date('Y-m-d', strtotime('+7 days'));

        verify($task->save())->true();
        verify($task->id)->notEmpty();
        verify($task->version)->equals(0);
        verify($task->created_at)->notEmpty();
        verify($task->updated_at)->notEmpty();
    }

    public function testTaskValidation()
    {
        $task = new Task();
        
        // Title is required
        verify($task->validate())->false();
        verify($task->getErrors('title'))->notEmpty();

        // Title must be at least 5 characters
        $task->title = 'Test';
        verify($task->validate())->false();
        verify($task->getErrors('title'))->notEmpty();

        // Valid title
        $task->title = 'Valid Task Title';
        $task->status = Task::STATUS_PENDING;
        $task->priority = Task::PRIORITY_MEDIUM;
        verify($task->validate())->true();
    }

    public function testTaskStatusValidation()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        $task->status = 'invalid_status';
        verify($task->validate())->false();
        verify($task->getErrors('status'))->notEmpty();

        $task->status = Task::STATUS_PENDING;
        verify($task->validate())->true();
    }

    public function testTaskPriorityValidation()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        $task->priority = 'invalid_priority';
        verify($task->validate())->false();
        verify($task->getErrors('priority'))->notEmpty();

        $task->priority = Task::PRIORITY_LOW;
        verify($task->validate())->true();
    }

    public function testDueDateValidation()
    {
        $user = $this->createTestUser();
        $task = new Task();
        $task->title = 'Test Task Title';
        $task->status = Task::STATUS_PENDING;
        $task->assigned_to = $user->id;
        
        // Past due date should fail for pending tasks
        $task->due_date = date('Y-m-d', strtotime('-1 day'));
        verify($task->validate())->false();
        verify($task->getErrors('due_date'))->notEmpty();

        // Future due date should pass
        $task->due_date = date('Y-m-d', strtotime('+7 days'));
        verify($task->validate())->true();

        // Past due date should pass for completed tasks
        $task->status = Task::STATUS_COMPLETED;
        $task->due_date = date('Y-m-d', strtotime('-1 day'));
        verify($task->validate())->true();
    }

    public function testToggleStatus()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        $task->status = Task::STATUS_PENDING;
        verify($task->save())->true();
        $taskId = $task->id;

        // Pending -> In Progress
        // Reload to get fresh instance with latest version
        $task = Task::findOne($taskId);
        $task->refresh(); // Ensure we have the absolute latest version
        verify($task->toggleStatus())->true();
        verify($task->status)->equals(Task::STATUS_IN_PROGRESS);

        // In Progress -> Completed
        $task = Task::findOne($taskId);
        $task->refresh(); // Ensure we have the absolute latest version
        $this->expectException(StaleObjectException::class);
        verify($task->toggleStatus())->false();
        verify($task->status)->equals(Task::STATUS_COMPLETED);

        // Completed -> Pending
        $task = Task::findOne($taskId);
        $task->refresh(); // Ensure we have the absolute latest version
        verify($task->toggleStatus())->true();
        verify($task->status)->equals(Task::STATUS_PENDING);
    }

    public function testSoftDelete()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();
        $taskId = $task->id;

        // Soft delete
        $task->delete();
        verify($task->deleted_at)->notEmpty();

        // Should not be found in normal query
        verify(Task::find()->andWhere(['id' => $taskId])->one())->empty();

        // Should be found with findWithDeleted
        $deletedTask = Task::findWithDeleted()->andWhere(['id' => $taskId])->one();
        verify($deletedTask)->notEmpty();
        verify($deletedTask->deleted_at)->notEmpty();
    }

    public function testRestore()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();
        $taskId = $task->id;

        // Soft delete
        $task->delete();
        verify($task->deleted_at)->notEmpty();

        // Reload task to get fresh instance for restore
        $task = Task::findWithDeleted()->andWhere(['id' => $taskId])->one();
        $softDeleteBehavior = $task->getBehavior('softDelete');
        $this->expectException(StaleObjectException::class);
        verify($softDeleteBehavior->restore())->false();
        $task->refresh();
        verify($task->deleted_at)->empty();

        // Should be found in normal query after restore
        verify(Task::find()->andWhere(['id' => $taskId])->one())->notEmpty();
    }

    public function testOptimisticLocking()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();
        $taskId = $task->id;
        $initialVersion = $task->version;

        // First update - get fresh instance and ensure latest version
        $task = Task::findOne($taskId);
        // Small delay to ensure version is committed
        usleep(1000);
        $task->refresh(); // Ensure latest version from database
        $task->title = 'Updated Title 1';
        verify($task->save())->true();
        $task->refresh(); // Reload to verify version was updated
        verify($task->version)->equals($initialVersion + 1);

        // Second update - get fresh instance and ensure latest version
        $task = Task::findOne($taskId);
        // Small delay to ensure version is committed
        usleep(1000);
        $task->refresh(); // Ensure latest version from database
        $task->title = 'Updated Title 2';
        $this->expectException(StaleObjectException::class);
        verify($task->save())->false();
        $task->refresh(); // Reload to verify version was updated
        verify($task->version)->equals($initialVersion + 2);
    }

    public function testRelations()
    {
        $user = $this->createTestUser();
        $task = new Task();
        $task->title = 'Test Task Title';
        $task->assigned_to = $user->id;
        verify($task->save())->true();

        // Test assignedUser relation
        verify($task->assignedUser)->notEmpty();
        verify($task->assignedUser->id)->equals($user->id);

        // Test tags relation (empty initially)
        verify($task->tags)->empty();
    }

    public function testMetadataStorage()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        $task->metadata = ['key' => 'value', 'number' => 123];
        verify($task->save())->true();

        // Metadata should be stored as JSON string
        $task->refresh();
        verify(is_string($task->metadata))->true();
        $decoded = json_decode($task->metadata, true);
        verify($decoded['key'])->equals('value');
        verify($decoded['number'])->equals(123);
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


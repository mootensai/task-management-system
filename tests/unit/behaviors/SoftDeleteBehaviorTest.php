<?php

namespace tests\unit\behaviors;

use app\behaviors\SoftDeleteBehavior;
use app\models\Task;
use Codeception\Test\Unit;
use Yii;
use yii\db\StaleObjectException;

class SoftDeleteBehaviorTest extends Unit
{
    protected function _before()
    {
        Task::deleteAll();
    }

    public function testSoftDeleteBehavior()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();
        $taskId = $task->id;

        // Verify behavior is attached
        $behavior = $task->getBehavior('softDelete');
        verify($behavior)->notEmpty();
        verify($behavior)->instanceOf(SoftDeleteBehavior::class);

        // Initially not deleted
        verify($behavior->isDeleted())->false();

        // Soft delete
        verify($behavior->softDelete())->true();
        verify($behavior->isDeleted())->true();
        verify($task->deleted_at)->notEmpty();

        // Task should not be found in normal query
        verify(Task::find()->andWhere(['id' => $taskId])->one())->empty();
    }

    public function testRestoreBehavior()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();
        $taskId = $task->id;

        $behavior = $task->getBehavior('softDelete');

        // Soft delete
        $behavior->softDelete();
        verify($behavior->isDeleted())->true();

        // Reload task to get fresh instance for restore
        $task = Task::findWithDeleted()->andWhere(['id' => $taskId])->one();
        $behavior = $task->getBehavior('softDelete');
        
        // Restore
        $this->expectException(StaleObjectException::class);
        verify($behavior->restore())->false();
        $task->refresh();
        verify($behavior->isDeleted())->false();
        verify($task->deleted_at)->empty();

        // Task should be found after restore
        verify(Task::find()->andWhere(['id' => $taskId])->one())->notEmpty();
    }

    public function testDeleteMethodTriggersSoftDelete()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();
        $taskId = $task->id;

        // Call delete() - should trigger soft delete
        $task->delete();

        // Verify soft deleted
        $task->refresh();
        verify($task->deleted_at)->notEmpty();

        // Should not be found in normal query
        verify(Task::find()->andWhere(['id' => $taskId])->one())->empty();
    }

    public function testDoubleSoftDelete()
    {
        $task = new Task();
        $task->title = 'Test Task Title';
        verify($task->save())->true();

        $behavior = $task->getBehavior('softDelete');

        // First soft delete
        verify($behavior->softDelete())->true();
        $firstDeletedAt = $task->deleted_at;

        // Second soft delete should return true but not change deleted_at
        verify($behavior->softDelete())->true();
        verify($task->deleted_at)->equals($firstDeletedAt);
    }
}


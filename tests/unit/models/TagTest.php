<?php

namespace tests\unit\models;

use app\models\Tag;
use app\models\Task;
use app\models\User;
use Codeception\Test\Unit;
use Yii;

class TagTest extends Unit
{
    protected function _before()
    {
        // Clean up test data
        Tag::deleteAll();
        Task::deleteAll();
        User::deleteAll();
        Yii::$app->db->createCommand()->delete('{{%task_tag}}')->execute();
    }

    public function testCreateTag()
    {
        $tag = new Tag();
        $tag->name = 'Bug';
        $tag->color = '#dc3545';
        
        verify($tag->save())->true();
        verify($tag->id)->notEmpty();
        verify($tag->created_at)->notEmpty();
        verify($tag->updated_at)->notEmpty();
        verify($tag->name)->equals('Bug');
        verify($tag->color)->equals('#dc3545');
    }

    public function testTagValidation()
    {
        $tag = new Tag();
        
        // Name is required
        verify($tag->validate())->false();
        verify($tag->getErrors('name'))->notEmpty();

        // Name must be max 100 characters
        $tag->name = str_repeat('a', 101);
        verify($tag->validate())->false();
        verify($tag->getErrors('name'))->notEmpty();

        // Valid name
        $tag->name = 'Valid Tag Name';
        verify($tag->validate())->true();
    }

    public function testTagNameUnique()
    {
        $tag1 = new Tag();
        $tag1->name = 'Unique Tag';
        verify($tag1->save())->true();

        // Try to create another tag with same name
        $tag2 = new Tag();
        $tag2->name = 'Unique Tag';
        verify($tag2->save())->false();
        verify($tag2->getErrors('name'))->notEmpty();
    }

    public function testTagColorValidation()
    {
        $tag = new Tag();
        $tag->name = 'Test Tag';
        
        // Color is optional
        verify($tag->validate())->true();
        verify($tag->save())->true();

        // Color must be max 50 characters
        $tag2 = new Tag();
        $tag2->name = 'Test Tag 2';
        $tag2->color = str_repeat('a', 51);
        verify($tag2->validate())->false();
        verify($tag2->getErrors('color'))->notEmpty();

        // Valid color
        $tag2->color = '#ff0000';
        verify($tag2->validate())->true();
    }

    public function testTimestampBehavior()
    {
        $tag = new Tag();
        $tag->name = 'Timestamp Test';
        $beforeSave = time();
        verify($tag->save())->true();
        $afterSave = time();

        verify($tag->created_at)->greaterThanOrEqual($beforeSave);
        verify($tag->created_at)->lessThanOrEqual($afterSave);
        verify($tag->updated_at)->greaterThanOrEqual($beforeSave);
        verify($tag->updated_at)->lessThanOrEqual($afterSave);
        verify($tag->created_at)->equals($tag->updated_at);

        // Update should change updated_at but not created_at
        $originalCreatedAt = $tag->created_at;
        sleep(1); // Wait 1 second to ensure timestamp changes
        $tag->color = '#00ff00';
        verify($tag->save())->true();
        verify($tag->created_at)->equals($originalCreatedAt);
        verify($tag->updated_at)->greaterThan($originalCreatedAt);
    }

    public function testTagRelations()
    {
        $user = $this->createTestUser();
        $tag = new Tag();
        $tag->name = 'Bug';
        verify($tag->save())->true();

        // Test tasks relation (empty initially)
        verify($tag->tasks)->empty();

        // Create a task and link it to the tag
        $task = new Task();
        $task->title = 'Test Task with Tag';
        $task->status = Task::STATUS_PENDING;
        $task->priority = Task::PRIORITY_MEDIUM;
        $task->assigned_to = $user->id;
        verify($task->save())->true();

        // Link task to tag via junction table
        $createdAt = time();
        Yii::$app->db->createCommand()
            ->insert('{{%task_tag}}', [
                'task_id' => $task->id,
                'tag_id' => $tag->id,
                'created_at' => $createdAt,
            ])
            ->execute();

        // Refresh tag to load relation
        $tag->refresh();
        verify($tag->tasks)->notEmpty();
        verify(count($tag->tasks))->equals(1);
        verify($tag->tasks[0]->id)->equals($task->id);
    }

    public function testTagWithMultipleTasks()
    {
        $user = $this->createTestUser();
        $tag = new Tag();
        $tag->name = 'Feature';
        verify($tag->save())->true();

        // Create multiple tasks
        $task1 = $this->createTask('Task 1', $user->id);
        $task2 = $this->createTask('Task 2', $user->id);
        $task3 = $this->createTask('Task 3', $user->id);

        // Link all tasks to the tag
        $createdAt = time();
        Yii::$app->db->createCommand()
            ->batchInsert('{{%task_tag}}', ['task_id', 'tag_id', 'created_at'], [
                [$task1->id, $tag->id, $createdAt],
                [$task2->id, $tag->id, $createdAt],
                [$task3->id, $tag->id, $createdAt],
            ])
            ->execute();

        // Refresh tag to load relation
        $tag->refresh();
        verify(count($tag->tasks))->equals(3);
    }

    public function testTagUpdate()
    {
        $tag = new Tag();
        $tag->name = 'Original Name';
        $tag->color = '#ff0000';
        verify($tag->save())->true();
        $tagId = $tag->id;
        $originalCreatedAt = $tag->created_at;

        // Wait to ensure timestamp changes
        sleep(1);

        // Update tag
        $tag->name = 'Updated Name';
        $tag->color = '#00ff00';
        verify($tag->save())->true();

        // Verify update
        verify($tag->name)->equals('Updated Name');
        verify($tag->color)->equals('#00ff00');
        verify($tag->created_at)->equals($originalCreatedAt);
        verify($tag->updated_at)->greaterThan($originalCreatedAt);
    }

    public function testTagDelete()
    {
        $tag = new Tag();
        $tag->name = 'Tag to Delete';
        verify($tag->save())->true();
        $tagId = $tag->id;

        // Delete tag
        verify($tag->delete())->equals(1);

        // Verify deleted
        verify(Tag::findOne($tagId))->empty();
    }

    protected function createTestUser(): User
    {
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test' . uniqid() . '@example.com';
        $user->role = 'user';
        $user->setPassword('password123');
        $user->generateAuthKey();
        $user->status = 10;
        verify($user->save())->true();
        return $user;
    }

    protected function createTask(string $title, int $assignedTo): Task
    {
        $task = new Task();
        $task->title = $title;
        $task->status = Task::STATUS_PENDING;
        $task->priority = Task::PRIORITY_MEDIUM;
        $task->assigned_to = $assignedTo;
        verify($task->save())->true();
        return $task;
    }
}
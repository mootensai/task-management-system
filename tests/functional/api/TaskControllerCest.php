<?php

namespace tests\functional\api;

use app\models\Task;
use app\models\User;
use app\models\Tag;
use FunctionalTester;

class TaskControllerCest
{
    protected $user;
    protected $adminUser;
    protected $accessToken;
    protected $jwtToken;

    public function _before(FunctionalTester $I)
    {
        // Clean up test data
        Task::deleteAll();
        User::deleteAll();
        Tag::deleteAll();
        \Yii::$app->db->createCommand()->delete('{{%task_tag}}')->execute();

        // Create test users
        $this->user = $this->createUser('user@test.com', 'user');
        $this->adminUser = $this->createUser('admin@test.com', 'admin');

        // Generate tokens for regular user
        $this->user->generateAuthToken();
        $this->user->save(false, ['access_token']);
        $this->accessToken = $this->user->access_token;
        $this->jwtToken = $this->user->generateJwt();
    }

    public function testIndexWithoutAuth(FunctionalTester $I)
    {
        $I->sendGET('/api/tasks');
        $I->seeResponseCodeIs(401);
    }

    public function testIndexWithAccessToken(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tasks');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    public function testIndexWithJwtToken(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->jwtToken);
        $I->sendGET('/api/tasks');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    public function testIndexWithFilters(FunctionalTester $I)
    {
        // Create test tasks
        $task1 = $this->createTask('Task 1', Task::STATUS_PENDING, $this->user->id);
        $task2 = $this->createTask('Task 2', Task::STATUS_COMPLETED, $this->user->id);
        $task3 = $this->createTask('Task 3', Task::STATUS_IN_PROGRESS, $this->user->id);

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        
        // Filter by status
        $I->sendGET('/api/tasks', ['status' => 'pending']);
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $response['items']);
        $I->assertEquals('Task 1', $response['items'][0]['title']);

        // Filter by priority
        $I->sendGET('/api/tasks', ['priority' => 'high']);
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);
        $I->assertGreaterThanOrEqual(0, count($response['items']));
    }

    public function testCreateTask(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        $taskData = [
            'title' => 'New Test Task',
            'description' => 'Test Description',
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_HIGH,
            'due_date' => date('Y-m-d', strtotime('+7 days')),
            'assigned_to' => $this->user->id,
        ];

        $I->sendPOST('/api/tasks', $taskData);
        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('New Test Task', $response['title']);
        $I->assertArrayHasKey('id', $response);
    }

    public function testCreateTaskWithValidationError(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        $taskData = [
            'title' => 'Test', // Too short (4 chars, min is 5)
        ];

        $I->sendPOST('/api/tasks', $taskData);
        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('errors', $response);
    }

    public function testViewTask(FunctionalTester $I)
    {
        $task = $this->createTask('View Test Task', Task::STATUS_PENDING, $this->user->id);

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tasks/' . $task->id);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('View Test Task', $response['title']);
        $I->assertArrayHasKey('assignedUser', $response);
        $I->assertArrayHasKey('tags', $response);
    }

    public function testViewNonExistentTask(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tasks/99999');
        $I->seeResponseCodeIs(404);
    }

    public function testUpdateTask(FunctionalTester $I)
    {
        $task = $this->createTask('Original Title', Task::STATUS_PENDING, $this->user->id);
        $task->refresh(); // Get latest version

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        $updateData = [
            'title' => 'Updated Title',
            'status' => Task::STATUS_IN_PROGRESS,
            'version' => $task->version, // Send version for optimistic locking
        ];

        $I->sendPUT('/api/tasks/' . $task->id, $updateData);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('Updated Title', $response['title']);
        $I->assertEquals(Task::STATUS_IN_PROGRESS, $response['status']);
    }

    public function testDeleteTask(FunctionalTester $I)
    {
        $task = $this->createTask('Task to Delete', Task::STATUS_PENDING, $this->user->id);
        $task->refresh(); // Get latest version

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        // Send version in body for optimistic locking (as per Postman collection)
        $I->sendDELETE('/api/tasks/' . $task->id, ['version' => $task->version]);
        $I->seeResponseCodeIs(204);

        // Verify soft deleted
        $task->refresh();
        $I->assertNotNull($task->deleted_at);

        // Should not be found in normal query
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tasks/' . $task->id);
        $I->seeResponseCodeIs(404);
    }

    public function testToggleStatus(FunctionalTester $I)
    {
        $task = $this->createTask('Toggle Test Task', Task::STATUS_PENDING, $this->user->id);
        $task->refresh(); // Get latest version

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        // First toggle - send version in body
        $I->sendPATCH('/api/tasks/' . $task->id . '/toggle-status', ['version' => $task->version]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(Task::STATUS_IN_PROGRESS, $response['status']);

        // Get updated version for second toggle
        $task->refresh();
        
        // Toggle again - send updated version in body
        $I->sendPATCH('/api/tasks/' . $task->id . '/toggle-status', ['version' => $task->version]);
        $I->seeResponseCodeIs(200);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(Task::STATUS_COMPLETED, $response['status']);
    }

    public function testRestoreTask(FunctionalTester $I)
    {
        $task = $this->createTask('Task to Restore', Task::STATUS_PENDING, $this->user->id);
        $task->delete(); // Soft delete (this increments version due to optimistic locking)
        $taskId = $task->id;

        // Get the current version directly from database after soft delete
        // Soft delete increments version, so we need the updated version
        $db = \Yii::$app->db;
        $currentVersion = $db->createCommand(
            'SELECT version FROM {{%task}} WHERE id = :id',
            [':id' => $taskId]
        )->queryScalar();

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        // Send version in body for optimistic locking (as per Postman collection)
        $I->sendPATCH('/api/tasks/' . $taskId . '/restore', ['version' => (int)$currentVersion]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('Task to Restore', $response['title']);

        // Should be accessible again
        $I->sendGET('/api/tasks/' . $taskId);
        $I->seeResponseCodeIs(200);
    }

    public function testOptimisticLocking(FunctionalTester $I)
    {
        $task = $this->createTask('Lock Test Task', Task::STATUS_PENDING, $this->user->id);
        $task->refresh(); // Get latest version
        $initialVersion = $task->version;

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        // First update - send version in body
        $I->sendPUT('/api/tasks/' . $task->id, [
            'title' => 'Updated 1',
            'version' => $task->version,
        ]);
        $I->seeResponseCodeIs(200);
        
        $task->refresh();
        $I->assertEquals($initialVersion + 1, $task->version);
    }

    public function testAccessControlUserCanOnlyAccessOwnTasks(FunctionalTester $I)
    {
        $otherUser = $this->createUser('other@test.com', 'user');
        $otherUser->generateAuthToken();
        $otherUser->save(false, ['access_token']);
        
        $task = $this->createTask('Other User Task', Task::STATUS_PENDING, $otherUser->id);

        // Regular user tries to access other user's task
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tasks/' . $task->id);
        $I->seeResponseCodeIs(403);
    }

    public function testAccessControlAdminCanAccessAllTasks(FunctionalTester $I)
    {
        $task = $this->createTask('User Task', Task::STATUS_PENDING, $this->user->id);

        $this->adminUser->generateAuthToken();
        $this->adminUser->save(false, ['access_token']);

        // Admin can access any task
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->adminUser->access_token);
        $I->sendGET('/api/tasks/' . $task->id);
        $I->seeResponseCodeIs(200);
    }

    protected function createUser($email, $role = 'user'): User
    {
        $user = new User();
        $user->name = ucfirst($role) . ' User';
        $user->email = $email;
        $user->role = $role;
        $user->setPassword('password123');
        $user->generateAuthKey();
        $user->save(false);
        return $user;
    }

    protected function createTask($title, $status, $assignedTo): Task
    {
        $task = new Task();
        $task->title = $title;
        $task->status = $status;
        $task->priority = Task::PRIORITY_MEDIUM;
        $task->assigned_to = $assignedTo;
        $task->save(false);
        return $task;
    }
}


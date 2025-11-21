<?php

namespace tests\functional\api;

use app\models\Tag;
use app\models\User;
use FunctionalTester;

class TagControllerCest
{
    protected $user;
    protected $adminUser;
    protected $accessToken;

    public function _before(FunctionalTester $I)
    {
        // Clean up test data
        Tag::deleteAll();
        User::deleteAll();

        // Create test users
        $this->user = $this->createUser('user@test.com', 'user');
        $this->adminUser = $this->createUser('admin@test.com', 'admin');

        // Generate tokens for regular user
        $this->user->generateAuthToken();
        $this->user->save(false, ['access_token']);
        $this->accessToken = $this->user->access_token;
    }

    public function testIndexWithoutAuth(FunctionalTester $I)
    {
        $I->sendGET('/api/tags');
        $I->seeResponseCodeIs(401);
    }

    public function testIndexWithAccessToken(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tags');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        // ActiveController returns array directly, not wrapped in 'items'
        $I->assertIsArray($response);
    }

    public function testIndexWithTags(FunctionalTester $I)
    {
        // Create test tags
        $tag1 = $this->createTag('Bug', '#dc3545');
        $tag2 = $this->createTag('Feature', '#28a745');
        $tag3 = $this->createTag('Enhancement', '#007bff');

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tags');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        // ActiveController returns array directly, not wrapped in 'items'
        $I->assertIsArray($response);
        $I->assertGreaterThanOrEqual(3, count($response));
    }

    public function testCreateTag(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        $tagData = [
            'name' => 'New Tag',
            'color' => '#ff0000',
        ];

        $I->sendPOST('/api/tags', $tagData);
        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('New Tag', $response['name']);
        $I->assertEquals('#ff0000', $response['color']);
        $I->assertArrayHasKey('id', $response);
        $I->assertArrayHasKey('created_at', $response);
        $I->assertArrayHasKey('updated_at', $response);
    }

    public function testCreateTagWithValidationError(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        // Name is required
        $tagData = [];

        $I->sendPOST('/api/tags', $tagData);
        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        // ActiveController returns validation errors as array of error objects
        $I->assertIsArray($response);
        $I->assertNotEmpty($response);
        // Check that it contains error information
        $I->assertArrayHasKey(0, $response);
        $I->assertArrayHasKey('field', $response[0]);
        $I->assertArrayHasKey('message', $response[0]);
    }

    public function testCreateTagWithDuplicateName(FunctionalTester $I)
    {
        // Create existing tag
        $this->createTag('Duplicate', '#000000');

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        $tagData = [
            'name' => 'Duplicate', // Same name
            'color' => '#ffffff',
        ];

        $I->sendPOST('/api/tags', $tagData);
        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        // ActiveController returns validation errors as array of error objects
        $I->assertIsArray($response);
        $I->assertNotEmpty($response);
        // Check that it contains error information about name
        $I->assertArrayHasKey(0, $response);
        $I->assertEquals('name', $response[0]['field']);
        $I->assertStringContainsString('Duplicate', $response[0]['message']);
    }

    public function testViewTag(FunctionalTester $I)
    {
        $tag = $this->createTag('View Test Tag', '#00ff00');

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tags/' . $tag->id);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('View Test Tag', $response['name']);
        $I->assertEquals('#00ff00', $response['color']);
        $I->assertArrayHasKey('id', $response);
    }

    public function testViewNonExistentTag(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tags/99999');
        $I->seeResponseCodeIs(404);
    }

    public function testUpdateTag(FunctionalTester $I)
    {
        $tag = $this->createTag('Original Name', '#ff0000');

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        $updateData = [
            'name' => 'Updated Name',
            'color' => '#00ff00',
        ];

        $I->sendPUT('/api/tags/' . $tag->id, $updateData);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('Updated Name', $response['name']);
        $I->assertEquals('#00ff00', $response['color']);
    }

    public function testUpdateTagWithValidationError(FunctionalTester $I)
    {
        $tag = $this->createTag('Valid Tag', '#ff0000');

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        
        // Name too long (max 100)
        $updateData = [
            'name' => str_repeat('a', 101),
        ];

        $I->sendPUT('/api/tags/' . $tag->id, $updateData);
        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
        
        $response = json_decode($I->grabResponse(), true);
        // ActiveController returns validation errors as array of error objects
        $I->assertIsArray($response);
        $I->assertNotEmpty($response);
        // Check that it contains error information
        $I->assertArrayHasKey(0, $response);
        $I->assertArrayHasKey('field', $response[0]);
        $I->assertArrayHasKey('message', $response[0]);
    }

    public function testDeleteTag(FunctionalTester $I)
    {
        $tag = $this->createTag('Tag to Delete', '#ff0000');
        $tagId = $tag->id;

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendDELETE('/api/tags/' . $tagId);
        $I->seeResponseCodeIs(204);

        // Verify deleted
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendGET('/api/tags/' . $tagId);
        $I->seeResponseCodeIs(404);
    }

    public function testDeleteNonExistentTag(FunctionalTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->accessToken);
        $I->sendDELETE('/api/tags/99999');
        $I->seeResponseCodeIs(404);
    }

    protected function createUser($email, $role = 'user'): User
    {
        $user = new User();
        $user->name = ucfirst($role) . ' User';
        $user->email = $email;
        $user->role = $role;
        $user->setPassword('password123');
        $user->generateAuthKey();
        $user->status = 10;
        $user->save(false);
        return $user;
    }

    protected function createTag($name, $color = null): Tag
    {
        $tag = new Tag();
        $tag->name = $name;
        if ($color !== null) {
            $tag->color = $color;
        }
        $tag->save(false);
        return $tag;
    }
}


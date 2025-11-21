<?php

namespace tests\unit\models;

use app\models\User;
use Yii;

class UserTest extends \Codeception\Test\Unit
{
    protected function _before()
    {
        User::deleteAll();
    }

    public function testFindUserById()
    {
        $user = $this->createTestUser('admin@test.com', 'admin', 'password123');
        $userId = $user->id;

        verify($foundUser = User::findIdentity($userId))->notEmpty();
        verify($foundUser->email)->equals('admin@test.com');

        verify(User::findIdentity(99999))->empty();
    }

    public function testFindUserByAccessToken()
    {
        $user = $this->createTestUser('admin@test.com', 'admin', 'password123');
        $user->access_token = 'test-token-123';
        $user->save(false, ['access_token']);

        verify($foundUser = User::findIdentityByAccessToken('test-token-123'))->notEmpty();
        verify($foundUser->email)->equals('admin@test.com');

        verify(User::findIdentityByAccessToken('non-existing'))->empty();
    }

    public function testFindUserByUsername()
    {
        $this->createTestUser('admin@test.com', 'admin', 'password123');

        verify($user = User::findByUsername('admin@test.com'))->notEmpty();
        verify(User::findByUsername('not-admin@test.com'))->empty();
    }

    /**
     * @depends testFindUserByUsername
     */
    public function testValidateUser()
    {
        $user = $this->createTestUser('admin@test.com', 'admin', 'password123');
        $authKey = $user->auth_key;

        verify($user->validateAuthKey($authKey))->true();
        verify($user->validateAuthKey('wrong-key'))->false();

        verify($user->validatePassword('password123'))->true();
        verify($user->validatePassword('wrong-password'))->false();
    }

    protected function createTestUser($email, $role, $password): User
    {
        $user = new User();
        $user->name = ucfirst($role) . ' User';
        $user->email = $email;
        $user->role = $role;
        $user->setPassword($password);
        $user->generateAuthKey();
        $user->status = 10;
        verify($user->save())->true();
        return $user;
    }
}

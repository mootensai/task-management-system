<?php

namespace app\models;

use Yii;
 use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * User model.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property string|null $auth_key
 * @property string|null $password_hash
 * @property string|null $access_token
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    public static function tableName(): string
    {
        return '{{%user}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'email'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['email'], 'email'],
            [['email'], 'string', 'max' => 255],
            [['email'], 'unique'],
            [['role'], 'in', 'range' => ['admin', 'user']],
            [['role'], 'default', 'value' => 'user'],
            [['status'], 'default', 'value' => 10],
            [['status', 'created_at', 'updated_at'], 'integer'],
            [['auth_key'], 'string', 'max' => 32],
            [['password_hash', 'access_token'], 'string', 'max' => 255],
        ];
    }

    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['password_hash'], $fields['auth_key'], $fields['access_token']);
        return $fields;
    }

    // IdentityInterface
    public static function findIdentity($id): ?IdentityInterface
    {
        return static::findOne(['id' => $id, 'status' => 10]);
    }

    public static function findIdentityByAccessToken($token, $type = null): ?IdentityInterface
    {
        if (!$token) {
            return null;
        }
        return static::findOne(['access_token' => $token, 'status' => 10]);
    }

    /**
     * Finds user by username (treats username as email for compatibility)
     * @param string $username
     * @return static|null
     */
    public static function findByUsername(string $username): ?static
    {
        return static::findOne(['email' => $username, 'status' => 10]);
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getAuthKey(): ?string
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    // Password helpers
    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function validatePassword(?string $password): bool
    {
        if ($this->password_hash === null || $password === null) {
            return false;
        }
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    public function generateAuthToken(): void
    {
        $this->access_token = Yii::$app->security->generateRandomString(64);
    }

    /**
     * Generate a JWT for this user using HS256.
     */
    public function generateJwt(): string
    {
        $now = time();
        $cfg = Yii::$app->params['jwt'] ?? [];
        $secret = $cfg['secret'] ?? getenv('JWT_SECRET') ?: 'dev-secret-change-me';
        $issuer = $cfg['issuer'] ?? getenv('JWT_ISSUER') ?: 'rti-solution-yii2';
        $aud = $cfg['audience'] ?? getenv('JWT_AUDIENCE') ?: 'rti-solution-yii2-clients';
        $ttl = (int)($cfg['ttl'] ?? (getenv('JWT_TTL') ?: 3600));

        $payload = [
            'iss' => $issuer,
            'aud' => $aud,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'sub' => (int)$this->id,
            'email' => (string)$this->email,
            'role' => (string)$this->role,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Decode a JWT and return the corresponding identity, or null.
     */
    public static function findIdentityByJwt(string $token): ?IdentityInterface
    {
        try {
            $cfg = Yii::$app->params['jwt'] ?? [];
            $secret = $cfg['secret'] ?? getenv('JWT_SECRET') ?: 'dev-secret-change-me';
            $issuer = $cfg['issuer'] ?? getenv('JWT_ISSUER') ?: 'rti-solution-yii2';
            $aud = $cfg['audience'] ?? getenv('JWT_AUDIENCE') ?: 'rti-solution-yii2-clients';
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            if (!empty($issuer) && isset($decoded->iss) && $decoded->iss !== $issuer) {
                return null;
            }
            if (!empty($aud) && isset($decoded->aud) && $decoded->aud !== $aud) {
                return null;
            }
            $userId = isset($decoded->sub) ? (int)$decoded->sub : null;
            if (!$userId) {
                return null;
            }
            return static::findOne(['id' => $userId, 'status' => 10]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // Relations
    public function getAssignedTasks()
    {
        return $this->hasMany(Task::class, ['assigned_to' => 'id']);
    }
}

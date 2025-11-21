<?php

namespace app\modules\api\components;

use app\models\User;
use Yii;
use yii\filters\auth\AuthMethod;
use yii\filters\auth\HttpBearerAuth;
use yii\web\UnauthorizedHttpException;

/**
 * Authenticator that accepts either a JWT or a legacy DB access_token in Bearer header.
 */
class JwtOrBearerAuth extends HttpBearerAuth
{
    public function authenticate($user, $request, $response)
    {
        $authHeader = $request->getHeaders()->get('Authorization');
        if ($authHeader === null) {
            return null;
        }
        if (!preg_match('/^\s*Bearer\s+(.*?)\s*$/i', $authHeader, $matches)) {
            return null;
        }
        $token = $matches[1];
        if (!$token) {
            return null;
        }

        // Try JWT first
        $identity = User::findIdentityByJwt($token);
        if ($identity) {
            $user->switchIdentity($identity);
            return $identity;
        }

        // Fallback to legacy access_token
        $identity = User::findIdentityByAccessToken($token);
        if ($identity) {
            $user->switchIdentity($identity);
            return $identity;
        }

        // Authentication failed - handleFailure will throw UnauthorizedHttpException
        // which automatically triggers the challenge
        $this->handleFailure($response);
        return null;
    }
}

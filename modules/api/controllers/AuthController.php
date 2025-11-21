<?php

namespace app\modules\api\controllers;

use app\models\User;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class AuthController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
        ];

        return $behaviors;
    }

    public function verbs()
    {
        return [
            'login' => ['POST'],
            'register' => ['POST'],
            'logout' => ['POST'],
        ];
    }

    public function actionLogin()
    {
        $body = Yii::$app->request->bodyParams;
        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;
        if (!$email || !$password) {
            throw new BadRequestHttpException('Email and password are required');
        }

        /** @var User $user */
        $user = User::findOne(['email' => $email, 'status' => 10]);
        if (!$user || !$user->validatePassword($password)) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Invalid credentials'];
        }

        $user->generateAuthToken();
        $user->save(false, ['access_token', 'updated_at']);
        $jwt = $user->generateJwt();

        return [
            'access_token' => $user->access_token,
            'jwt' => $jwt,
            'token_type' => 'Bearer',
            'user' => $user->toArray(),
        ];
    }

    public function actionRegister()
    {
        $body = Yii::$app->request->bodyParams;
        $user = new User();
        $user->load($body, '');
        if (empty($body['password'])) {
            throw new BadRequestHttpException('Password is required');
        }
        $user->setPassword($body['password']);
        $user->generateAuthKey();
        $user->generateAuthToken();

        if (!$user->save()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $user->getErrors()];
        }

        $jwt = $user->generateJwt();
        Yii::$app->response->statusCode = 201;
        return [
            'access_token' => $user->access_token,
            'jwt' => $jwt,
            'token_type' => 'Bearer',
            'user' => $user->toArray(),
        ];
    }

    public function actionLogout()
    {
        $auth = Yii::$app->request->headers->get('Authorization');
        $token = null;
        if ($auth && stripos($auth, 'Bearer ') === 0) {
            $token = substr($auth, 7);
        }
        if (!$token) {
            return ['status' => 'ok'];
        }
        $user = User::findIdentityByAccessToken($token);
        if ($user) {
            $user->access_token = null;
            $user->save(false, ['access_token', 'updated_at']);
        }
        return ['status' => 'ok'];
    }
}

<?php

namespace app\modules\api\controllers;

use app\models\User;
use app\modules\api\components\JwtOrBearerAuth;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\ActiveController;
use yii\web\Response;

class UserController extends ActiveController
{
    public $modelClass = User::class;

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

        $behaviors['authenticator'] = [
            'class' => JwtOrBearerAuth::class,
            'except' => [],
        ];

        return $behaviors;
    }

    public function actions()
    {
        $actions = parent::actions();
        // Only allow index (list) action for now
        unset($actions['create'], $actions['update'], $actions['delete'], $actions['view']);
        return $actions;
    }

    public function actionIndex(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => User::find()->where(['status' => 10]),
            'pagination' => [
                'pageSize' => 100, // Get all active users
            ],
        ]);
    }
}


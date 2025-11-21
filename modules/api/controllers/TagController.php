<?php

namespace app\modules\api\controllers;

use app\models\Tag;
use app\modules\api\components\JwtOrBearerAuth;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\ActiveController;
use yii\web\Response;

class TagController extends ActiveController
{
    public $modelClass = Tag::class;

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
}

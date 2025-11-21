<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';

/**
 * Application configuration shared by all test types
 */
return [
    'id' => 'basic-tests',
    'basePath' => dirname(__DIR__),
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'language' => 'en-US',
    'components' => [
        'db' => $db,
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
            'messageClass' => 'yii\symfonymailer\Message'
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => true,
            'enableStrictParsing' => true,
            'rules' => [
                // Auth routes
                'POST api/auth/login' => 'api/auth/login',
                'POST api/auth/register' => 'api/auth/register',
                'POST api/auth/logout' => 'api/auth/logout',

                // Task routes with extra actions
                [
                    'class' => yii\rest\UrlRule::class,
                    'controller' => ['api/task'],
                    'pluralize' => true,
                    'extraPatterns' => [
                        'PATCH {id}/toggle-status' => 'toggle-status',
                        'PATCH {id}/restore' => 'restore',
                    ],
                ],
                // Tag routes
                [
                    'class' => yii\rest\UrlRule::class,
                    'controller' => ['api/tag'],
                    'pluralize' => true,
                ],
                // User routes
                [
                    'class' => yii\rest\UrlRule::class,
                    'controller' => ['api/user'],
                    'pluralize' => true,
                ],
            ],
        ],
        'user' => [
            'identityClass' => 'app\models\User',
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
    ],
    'modules' => [
        'api' => [
            'class' => app\modules\api\Module::class,
        ],
    ],
    'params' => $params,
];

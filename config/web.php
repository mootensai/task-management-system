<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => $_ENV['COOKIE_VALIDATION_KEY'] ?? 'x9EcTJ2uVHl_BM76cHzkL558L-Imwyvw',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
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
    ],
    'modules' => [
        'api' => [
            'class' => app\modules\api\Module::class,
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;

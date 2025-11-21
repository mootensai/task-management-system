<?php

// Load environment variables
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Define constants from environment
defined('YII_DEBUG') or define('YII_DEBUG', filter_var($_ENV['YII_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN));
defined('YII_ENV') or define('YII_ENV', $_ENV['YII_ENV'] ?? 'dev');

require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();

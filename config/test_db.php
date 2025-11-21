<?php

$db = require __DIR__ . '/db.php';
// test database! Important not to run tests on production or development databases

// Use environment variables if available, otherwise use defaults
// For ddev: host should be 'db', for local: 'localhost'
$isDdev = isset($_ENV['DDEV_SITENAME']) || isset($_SERVER['DDEV_SITENAME']) || getenv('DDEV_SITENAME');

$dbHost = $_ENV['TEST_DB_HOST'] ?? $_ENV['DB_HOST'] ?? ($isDdev ? 'db' : 'localhost');
$dbName = $_ENV['TEST_DB_NAME'] ?? $_ENV['DB_NAME'] ?? 'db';
$dbUser = $_ENV['TEST_DB_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? ($isDdev ? 'db' : 'root');
$dbPass = $_ENV['TEST_DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? ($isDdev ? 'db' : '');

$db['dsn'] = "mysql:host={$dbHost};dbname={$dbName}";
$db['username'] = $dbUser;
$db['password'] = $dbPass;

return $db;

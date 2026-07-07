<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));

echo "STEP 1 OK\n";

$app = require BASE_PATH . '/bootstrap/app.php';

echo "STEP 2 OK\n";

$app->run();
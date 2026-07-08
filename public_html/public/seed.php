<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

if (!\IPKF\Support\Env::isDebug()) {
    http_response_code(404);
    echo "404 - Route not found: /seed.php";
    exit;
}

try {
    $manager = new \IPKF\Database\DatabaseManager();
    $manager->seeders([
        new \IPKF\Database\Seeds\RuntimeCheckSeeder(),
    ]);

    $manager->seed();

    header('Content-Type: text/plain; charset=UTF-8');
    echo "SEED DONE: foundation_v0_2";
} catch (Throwable $exception) {
    http_response_code(500);
    echo "SEED FAILED";
}

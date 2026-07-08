<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

try {
    $manager = new \IPKF\Database\DatabaseManager();

    $manager->migrate();
    $manager->seed();

    echo "MIGRATION DONE";
} catch (Throwable $exception) {
    http_response_code(500);
    echo "MIGRATION FAILED";
}

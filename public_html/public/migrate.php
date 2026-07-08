<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

if (!\IPKF\Support\Env::isDebug()) {
    \IPKF\Support\Maintenance::deny('/migrate.php');
}

if (!\IPKF\Support\Maintenance::keyIsValid($_GET['key'] ?? null)) {
    \IPKF\Support\Maintenance::deny('/migrate.php');
}

try {
    $manager = new \IPKF\Database\DatabaseManager();
    $manager->migrations([
        new \IPKF\Database\Migrations\CreateRuntimeChecksTable(),
        new \IPKF\Database\Migrations\CreateAuthRbacSchemaTables(),
    ]);

    $manager->migrate();

    header('Content-Type: text/plain; charset=UTF-8');
    echo "MIGRATION DONE: ipkf_runtime_checks, auth_rbac_schema";
} catch (Throwable $exception) {
    http_response_code(500);
    echo "MIGRATION FAILED";
}

<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

$installer = new \IPKF\Installer\Installer();

if (!$installer->state()->canAccess()) {
    http_response_code(404);
    echo "404 - Route not found: /install.php";
    exit;
}

header('Content-Type: application/json');
echo json_encode($installer->payload()) ?: '{}';

<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

$registry =
    new \IPKF\Database\Connections\ConnectionRegistry();
$resolver =
    new \IPKF\Database\Connections\ConnectionResolver(
        $registry
    );
$pdo = $resolver->resolve('core.primary');

(new \IPKF\Database\Seeds\CommunicationCenterSeeder(
    $pdo
))->run();

echo "COMMUNICATION NAVIGATION R7 APPLIED\n";

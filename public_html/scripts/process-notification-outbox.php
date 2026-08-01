<?php

declare(strict_types=1);

use App\Services\NotificationOutboxProcessorService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

$options = getopt('', ['limit::', 'worker::']);
$limit = isset($options['limit'])
    ? (int) $options['limit']
    : 50;
$worker = isset($options['worker'])
    ? trim((string) $options['worker'])
    : null;

$result = (new NotificationOutboxProcessorService())
    ->process($limit, $worker);

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_PRETTY_PRINT
) . PHP_EOL;

exit(($result['failed'] ?? 0) > 0 ? 1 : 0);

<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

if (!\IPKF\Support\Env::isDebug()) {
    \IPKF\Support\Maintenance::deny('/geography-import.php');
}

if (!\IPKF\Support\Maintenance::keyIsValid($_GET['key'] ?? null)) {
    \IPKF\Support\Maintenance::deny('/geography-import.php');
}

header('Content-Type: application/json; charset=UTF-8');

try {
    @set_time_limit(0);
    $summary = (new \App\Services\GeographyImport\GeographyImportManager())->validate(
        (string) ($_GET['source'] ?? ''),
        (string) ($_GET['file'] ?? ''),
        (string) ($_GET['mode'] ?? '')
    );
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Source validation request was rejected.',
        'canonical_write_performed' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Source validation failed safely.',
        'canonical_write_performed' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

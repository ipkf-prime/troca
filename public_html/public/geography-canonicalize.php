<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

if (!\IPKF\Support\Env::isDebug()
    || strtolower((string) \IPKF\Support\Env::get('APP_ENV', '')) !== 'development'
) {
    \IPKF\Support\Maintenance::deny('/geography-canonicalize.php');
}

if (!\IPKF\Support\Maintenance::keyIsValid($_GET['key'] ?? null)) {
    \IPKF\Support\Maintenance::deny('/geography-canonicalize.php');
}

header('Content-Type: application/json; charset=UTF-8');

try {
    @set_time_limit(0);
    $summary = (new \App\Services\GeographyCanonicalization\MinistryCanonicalGeographyService())->run(
        (string) ($_GET['source_batch'] ?? ''),
        (string) ($_GET['mode'] ?? ''),
        isset($_GET['plan_reference']) ? (string) $_GET['plan_reference'] : null,
        isset($_GET['fingerprint']) ? (string) $_GET['fingerprint'] : null
    );
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Canonical geography request was rejected.',
        'canonical_write_performed' => false,
        'sci_write_performed' => false,
        'bot_write_performed' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(409);
    $response = [
        'success' => false,
        'message' => 'Canonical geography operation stopped safely.',
        'sci_write_performed' => false,
        'bot_write_performed' => false,
    ];

    if (($_GET['mode'] ?? '') !== 'apply') {
        $response['canonical_write_performed'] = false;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

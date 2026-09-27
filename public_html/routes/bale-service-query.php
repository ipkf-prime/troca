<?php
declare(strict_types=1);
/* Exact CSRF-exempt POST; authenticates with the receiving bot HMAC. */
$router->post('/integrations/bale/service-access/query', function ($request, $response) {
    try {
        $result = (new \App\Services\BaleServiceQueryService())->run(
            (string) file_get_contents('php://input'),
            trim((string)($_SERVER['HTTP_X_BALE_TIMESTAMP'] ?? '')),
            trim((string)($_SERVER['HTTP_X_BALE_SIGNATURE'] ?? ''))
        );
        return $response
            ->header('Cache-Control', 'no-store')
            ->header('X-IPKF-Service-Signature', $result['signature'])
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->send($result['json']);
    } catch (\Throwable) {
        return $response->status(401)->json(['ok' => false]);
    }
});

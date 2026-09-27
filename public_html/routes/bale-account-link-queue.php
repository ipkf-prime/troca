<?php

declare(strict_types=1);

$baleQueueResponse = static function (
    $response,
    int $status,
    array $payload
) {
    return $response
        ->status($status)
        ->header(
            'Content-Type',
            'application/json; charset=UTF-8'
        )
        ->header('Cache-Control', 'no-store, max-age=0')
        ->header('Referrer-Policy', 'no-referrer')
        ->header('X-Content-Type-Options', 'nosniff')
        ->send(
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
            )
        );
};

$baleQueueHandler = static function (
    $request,
    $response
) use ($baleQueueResponse) {
    $method = strtoupper(
        (string) $request->method()
    );

    $uri = (string) (
        $_SERVER['REQUEST_URI'] ?? ''
    );

    $path = parse_url(
        $uri,
        PHP_URL_PATH
    );

    if (
        !is_string($path) ||
        str_contains($uri, '?')
    ) {
        return $baleQueueResponse(
            $response,
            400,
            ['ok' => false]
        );
    }

    $body = file_get_contents(
        'php://input',
        false,
        null,
        0,
        257
    );

    if (!is_string($body)) {
        $body = '';
    }

    try {
        $result = (
            new \App\Services\BaleAccountLinkQueueApiService()
        )->handle(
            $method,
            $path,
            $body,
            (string) (
                $_SERVER[
                    'HTTP_X_IPKF_BALE_QUEUE_TIMESTAMP'
                ] ?? ''
            ),
            (string) (
                $_SERVER[
                    'HTTP_X_IPKF_BALE_QUEUE_NONCE'
                ] ?? ''
            ),
            (string) (
                $_SERVER[
                    'HTTP_X_IPKF_BALE_QUEUE_SIGNATURE'
                ] ?? ''
            )
        );
    } catch (\Throwable) {
        $result = [
            'status' => 503,
            'payload' => ['ok' => false],
        ];
    }

    return $baleQueueResponse(
        $response,
        $result['status'],
        $result['payload']
    );
};

$router->get(
    '/integrations/bale/link-queue/v1/next',
    $baleQueueHandler
);

$router->post(
    '/integrations/bale/link-queue/v1/ack',
    $baleQueueHandler
);

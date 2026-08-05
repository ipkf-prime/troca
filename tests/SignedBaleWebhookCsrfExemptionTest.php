<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $path
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $path
    );

    if (!is_string($content)) {
        fwrite(
            STDERR,
            "FAIL: cannot read {$path}\n"
        );
        exit(1);
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$middleware = $read(
    'public_html/system/Http/Middleware/'
    . 'CsrfMiddleware.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'NotificationBaleEnrollmentService.php'
);
$routes = $read(
    'public_html/routes/communication-center.php'
);

$expect(
    str_contains(
        $middleware,
        'private function isExempt'
    )
    && str_contains(
        $middleware,
        'npi_[a-f0-9]{24}'
    )
    && str_contains(
        $middleware,
        '[a-f0-9]{64}'
    )
    && str_contains(
        $middleware,
        "strtoupper(\$request->method()) === 'POST'"
    ),
    'Signed Bale webhook CSRF exemption is incomplete.'
);

$expect(
    str_contains(
        $routes,
        '/webhooks/notifications/bale/'
        . '{reference}/{signature}'
    )
    && str_contains(
        $routes,
        "'signature'"
    ),
    'Signed Bale webhook route is incomplete.'
);

$expect(
    str_contains(
        $service,
        'string $signature'
    )
    && str_contains(
        $service,
        'private function webhookSignature'
    )
    && str_contains(
        $service,
        'hash_hmac'
    )
    && str_contains(
        $service,
        'hash_equals'
    )
    && str_contains(
        $service,
        'notification_bale_webhook_signature_invalid'
    ),
    'Signed Bale webhook verification is incomplete.'
);

echo "Signed Bale webhook CSRF exemption checks passed.\n";

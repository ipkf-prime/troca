<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        fwrite(STDERR, "FAIL: cannot read {$path}\n");
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

$service = $read(
    'public_html/app/Services/'
    . 'NotificationProviderTestService.php'
);
$transport = $read(
    'public_html/app/Services/'
    . 'NotificationSmtpTransport.php'
);
$repository = $read(
    'public_html/app/Repositories/'
    . 'NotificationProviderManagementRepository.php'
);
$routes = $read(
    'public_html/routes/communication-center.php'
);
$view = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$style = $read(
    'public_html/resources/views/admin/partials/'
    . 'communication-style.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'EnableNotificationProviderTestSend.php'
);
$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);
$migrate = $read(
    'public_html/public/migrate.php'
);

$expect(
    str_contains(
        $service,
        "public function sendEmail("
    )
    && str_contains(
        $service,
        "'notifications.providers.manage'"
    )
    && str_contains(
        $service,
        "driver_code'] ?? '')\n                !== 'smtp'"
    ),
    'Provider email test service is incomplete.'
);

$expect(
    str_contains($transport, 'stream_socket_client(')
    && str_contains($transport, "'STARTTLS'")
    && str_contains($transport, "'AUTH LOGIN'")
    && str_contains($transport, "'X-IPKF-Notification-Test: 1'"),
    'SMTP transport is incomplete.'
);

$expect(
    str_contains(
        $repository,
        'public function recordTestResult('
    )
    && str_contains(
        $repository,
        'last_test_status_code'
    )
    && str_contains(
        $repository,
        "'provider.test_email.sent'"
    ),
    'Provider test audit persistence is incomplete.'
);

$expect(
    str_contains(
        $routes,
        "/test-email'"
    )
    && str_contains(
        $routes,
        'NotificationProviderTestService'
    ),
    'Provider email test route is missing.'
);

$expect(
    str_contains(
        $view,
        'data-provider-test-open'
    )
    && str_contains(
        $view,
        'data-provider-test-dialog'
    )
    && str_contains(
        $view,
        'provider_test_sent'
    ),
    'Provider email test interface is incomplete.'
);

$expect(
    str_contains(
        $style,
        'notification-provider-test-send-v061'
    ),
    'Provider email test styles are missing.'
);

$expect(
    str_contains(
        $migration,
        '/test-email'
    )
    && str_contains(
        $registry,
        'EnableNotificationProviderTestSend::class'
    )
    && str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\EnableNotificationProviderTestSend()'
    ),
    'Provider email test migration registration is incomplete.'
);

echo "Notification provider email test send checks passed.\n";

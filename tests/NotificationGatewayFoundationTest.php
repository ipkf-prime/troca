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

$gateway = $read(
    'public_html/app/Services/'
    . 'NotificationGatewayService.php'
);
$repository = $read(
    'public_html/app/Repositories/'
    . 'NotificationGatewayRepository.php'
);
$registry = $read(
    'public_html/app/Services/'
    . 'NotificationGatewayAdapterRegistry.php'
);
$smtp = $read(
    'public_html/app/Services/'
    . 'NotificationSmtpGatewayAdapter.php'
);
$kavenegar = $read(
    'public_html/app/Services/'
    . 'NotificationKavenegarGatewayAdapter.php'
);
$bale = $read(
    'public_html/app/Services/'
    . 'NotificationBaleGatewayAdapter.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'CreateNotificationGatewayFoundation.php'
);
$appRegistry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);
$migrate = $read(
    'public_html/public/migrate.php'
);
$layout = $read(
    'public_html/resources/views/admin/layout.php'
);
$css = $read(
    'public_html/public/assets/admin/css/admin.css'
);
$version = $read(
    'public_html/system/Support/Version.php'
);

$expect(
    str_contains(
        $gateway,
        'class NotificationGatewayService'
    )
    && str_contains(
        $gateway,
        'public function sendDirect('
    )
    && str_contains(
        $gateway,
        'notification_gateway_all_providers_failed'
    )
    && str_contains(
        $gateway,
        "'fallback_used'"
    ),
    'Gateway service or fallback is incomplete.'
);

$expect(
    str_contains(
        $repository,
        'public function createDirectDelivery('
    )
    && str_contains(
        $repository,
        'notification_delivery_attempts'
    )
    && str_contains(
        $repository,
        'public function completeSuccess('
    )
    && str_contains(
        $repository,
        'public function completeFailure('
    ),
    'Gateway delivery logging is incomplete.'
);

$expect(
    str_contains(
        $registry,
        'NotificationSmtpGatewayAdapter'
    )
    && str_contains(
        $registry,
        'NotificationKavenegarGatewayAdapter'
    )
    && str_contains(
        $registry,
        'NotificationBaleGatewayAdapter'
    )
    && str_contains(
        $smtp,
        "'is_test' => false"
    )
    && str_contains(
        $kavenegar,
        'IPKF-Notification-Gateway/1.0'
    )
    && str_contains(
        $bale,
        'IPKF-Notification-Gateway/1.0'
    ),
    'Gateway adapters are incomplete.'
);

$expect(
    str_contains(
        $migration,
        'provider_instance_id'
    )
    && str_contains(
        $migration,
        'response_metadata_json'
    )
    && str_contains(
        $migration,
        'notification_deliveries_reference_unique'
    )
    && str_contains(
        $appRegistry,
        'CreateNotificationGatewayFoundation::class'
    )
    && str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\CreateNotificationGatewayFoundation()'
    ),
    'Gateway migration is incomplete.'
);

$expect(
    str_contains(
        $css,
        'notification-footer-layout-v061'
    )
    && str_contains(
        $css,
        'padding-bottom: 18px'
    )
    && str_contains(
        $layout,
        'admin-footer__version'
    )
    && str_contains(
        $version,
        '0.6.1-notification-provider-management-dev'
    )
    && !str_contains(
        $version,
        'work-project-management-dev'
    ),
    'Footer spacing or version fix is incomplete.'
);

echo "Notification gateway foundation checks passed.\n";

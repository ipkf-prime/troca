<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
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

$repository = $read(
    'public_html/app/Repositories/'
    . 'NotificationSendCenterRepository.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'NotificationSendCenterService.php'
);
$gatewayRepository = $read(
    'public_html/app/Repositories/'
    . 'NotificationGatewayRepository.php'
);
$gatewayService = $read(
    'public_html/app/Services/'
    . 'NotificationGatewayService.php'
);
$settings = $read(
    'public_html/app/Services/'
    . 'CommunicationSettingsService.php'
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
    . 'EnableNotificationSendCenterFoundation.php'
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
        $repository,
        'class NotificationSendCenterRepository'
    )
    && str_contains(
        $repository,
        'destinationsForUsers'
    )
    && str_contains(
        $repository,
        'messenger_destination'
    ),
    'Send-center repository is incomplete.'
);

$expect(
    str_contains(
        $service,
        'class NotificationSendCenterService'
    )
    && str_contains(
        $service,
        'private const IMMEDIATE_LIMIT = 30'
    )
    && str_contains(
        $service,
        'notifications.send.manage'
    )
    && str_contains(
        $service,
        'confirm_dispatch'
    )
    && str_contains(
        $service,
        'recipient_user_id'
    ),
    'Send-center service is incomplete.'
);

$expect(
    str_contains(
        $gatewayRepository,
        '?int $recipientUserId = null'
    )
    && str_contains(
        $gatewayRepository,
        '?string $recipientUserReference = null'
    )
    && str_contains(
        $gatewayService,
        "'recipient_user_id'"
    ),
    'Gateway user-recipient integration is incomplete.'
);

$expect(
    str_contains(
        $settings,
        "'send' => ["
    )
    && str_contains(
        $settings,
        'NotificationSendCenterService'
    )
    && str_contains(
        $routes,
        '/admin/communications/settings/send'
    )
    && str_contains(
        $routes,
        'notification_send_completed'
    ),
    'Settings and route integration are incomplete.'
);

$expect(
    str_contains(
        $view,
        'notification-send-center-v061'
    )
    && str_contains(
        $view,
        'data-notification-send-form'
    )
    && str_contains(
        $view,
        'ارسال تکی و گروهی اعلان'
    )
    && str_contains(
        $style,
        'notification-send-center-style-v061'
    ),
    'Send-center UI is incomplete.'
);

$expect(
    str_contains(
        $migration,
        'notifications.send.manage'
    )
    && str_contains(
        $migration,
        'notification-send-center'
    )
    && str_contains(
        $registry,
        'EnableNotificationSendCenterFoundation::class'
    )
    && str_contains(
        $migrate,
        'EnableNotificationSendCenterFoundation()'
    ),
    'Send-center migration registration is incomplete.'
);

echo "Notification send center checks passed.\n";

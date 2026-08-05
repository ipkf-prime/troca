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

$repository = $read(
    'public_html/app/Repositories/'
    . 'NotificationMessengerEnrollmentRepository.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'NotificationBaleEnrollmentService.php'
);
$sendRepository = $read(
    'public_html/app/Repositories/'
    . 'NotificationSendCenterRepository.php'
);
$sendService = $read(
    'public_html/app/Services/'
    . 'NotificationSendCenterService.php'
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
    . 'EnableNotificationSendExperienceAndBaleEnrollment.php'
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
        'notification_messenger_bindings'
    )
    && str_contains(
        $repository,
        'pendingByTokenHash'
    )
    && str_contains(
        $service,
        'request_contact'
    )
    && str_contains(
        $service,
        'handleWebhook'
    ),
    'Bale enrollment backend is incomplete.'
);

$expect(
    str_contains(
        $sendRepository,
        'notification_messenger_bindings'
    ),
    'Send center does not use verified Bale bindings.'
);

$expect(
    str_contains(
        $sendService,
        'notification_send_multimedia_delivery_pending'
    )
    && str_contains(
        $sendService,
        "'message_type_code'"
    ),
    'Message type guard is incomplete.'
);

$expect(
    str_contains(
        $routes,
        '/admin/communications/settings/send/bale-invitations'
    )
    && str_contains(
        $routes,
        '/webhooks/notifications/bale/{reference}'
    ),
    'Bale enrollment routes are incomplete.'
);

$expect(
    str_contains(
        $view,
        'notification-send-tabs-v061'
    )
    && str_contains(
        $view,
        'data-send-message-type'
    )
    && str_contains(
        $style,
        'notification-minimal-controls-v061'
    ),
    'Tabbed compact UI is incomplete.'
);

$expect(
    str_contains(
        $migration,
        'notification_media_assets'
    )
    && str_contains(
        $migration,
        'notification_messenger_enrollments'
    )
    && str_contains(
        $migration,
        'notification_messenger_bindings'
    )
    && str_contains(
        $registry,
        'EnableNotificationSendExperienceAndBaleEnrollment::class'
    )
    && str_contains(
        $migrate,
        'EnableNotificationSendExperienceAndBaleEnrollment()'
    ),
    'Migration registration is incomplete.'
);

$expect(
    str_contains(
        $repository,
        'membershipAuthBaleProviders'
    )
    && str_contains(
        $repository,
        "'bot_purpose_code'"
    )
    && str_contains(
        $repository,
        "'membership_auth'"
    )
    && str_contains(
        $service,
        'notification_bale_auth_provider_unconfigured'
    )
    && str_contains(
        $service,
        'notification_bale_auth_provider_ambiguous'
    ),
    'Dedicated Bale membership/auth provider selection is incomplete.'
);

$expect(
    str_contains(
        $migration,
        "'bot_purpose_code'"
    )
    && str_contains(
        $migration,
        "'membership_auth'"
    )
    && str_contains(
        $view,
        'notification_bale_auth_provider_unconfigured'
    )
    && str_contains(
        $view,
        'notification_bale_auth_provider_ambiguous'
    ),
    'Dedicated Bale membership/auth provider configuration is incomplete.'
);

echo "Notification send experience and Bale enrollment checks passed.\n";

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
$enrollmentService = $read(
    'public_html/app/Services/'
    . 'NotificationBaleEnrollmentService.php'
);
$managementService = $read(
    'public_html/app/Services/'
    . 'NotificationBaleConnectionManagementService.php'
);
$settingsService = $read(
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

$expect(
    str_contains(
        $settingsService,
        "'bale_connections'"
    )
    && str_contains(
        $settingsService,
        'NotificationBaleConnectionManagementService'
    ),
    'Bale connection settings section is incomplete.'
);

$expect(
    str_contains(
        $repository,
        'connectionStatuses'
    )
    && str_contains(
        $repository,
        'revokeBinding'
    )
    && str_contains(
        $repository,
        'has_active_binding'
    ),
    'Bale connection repository support is incomplete.'
);

$expect(
    str_contains(
        $enrollmentService,
        'recipient_already_connected'
    )
    && str_contains(
        $managementService,
        'can_invite_bale'
    )
    && str_contains(
        $managementService,
        'can_disconnect_bale'
    ),
    'Bale connection management logic is incomplete.'
);

$expect(
    str_contains(
        $routes,
        '/bale-connections/disconnect'
    )
    && str_contains(
        $routes,
        '?section=bale_connections'
    ),
    'Bale connection routes are incomplete.'
);

$expect(
    str_contains(
        $view,
        'bale-connection-management-v061'
    )
    && str_contains(
        $view,
        'ارسال دعوت اتصال با پیامک'
    )
    && str_contains(
        $view,
        'data-bale-disconnect-user'
    )
    && !str_contains(
        $view,
        'userActions?.append(inviteButton)'
    ),
    'Dedicated Bale connection UI is incomplete.'
);

$expect(
    str_contains(
        $style,
        'bale-connection-management-style-v061'
    )
    && str_contains(
        $style,
        '.bale-connection-status--connected'
    ),
    'Bale connection styles are incomplete.'
);

echo "Bale connection management UI checks passed.\n";

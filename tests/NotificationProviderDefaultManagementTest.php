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

$repository = $read(
    'public_html/app/Repositories/'
    . 'NotificationProviderDefaultRepository.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'NotificationProviderDefaultService.php'
);
$resolver = $read(
    'public_html/app/Services/'
    . 'NotificationProviderResolver.php'
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
    . 'EnableNotificationProviderDefaultManagement.php'
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
        'public function saveGlobalDefaults('
    )
    && str_contains(
        $repository,
        "'provider.defaults.updated'"
    ),
    'Provider default persistence is incomplete.'
);

$expect(
    str_contains(
        $service,
        'class NotificationProviderDefaultService'
    )
    && str_contains(
        $service,
        "'primary_reference'"
    )
    && str_contains(
        $service,
        "'fallback_reference'"
    ),
    'Provider default service is incomplete.'
);

$expect(
    str_contains(
        $resolver,
        'class NotificationProviderResolver'
    )
    && str_contains(
        $resolver,
        'public function resolve('
    )
    && str_contains(
        $resolver,
        "'resolution_rank'"
    ),
    'Provider resolver is incomplete.'
);

$expect(
    str_contains(
        $settings,
        'provider_default_management'
    )
    && str_contains(
        $settings,
        'saveProviderDefaults('
    ),
    'Settings integration is incomplete.'
);

$expect(
    str_contains(
        $routes,
        '/admin/communications/settings/defaults/save'
    )
    && str_contains(
        $view,
        'data-provider-default-form'
    )
    && str_contains(
        $style,
        'notification-provider-default-management-v061'
    ),
    'Provider default UI or route is incomplete.'
);

$expect(
    str_contains(
        $migration,
        '/admin/communications/settings/defaults/save'
    )
    && str_contains(
        $registry,
        'EnableNotificationProviderDefaultManagement::class'
    )
    && str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\EnableNotificationProviderDefaultManagement()'
    ),
    'Migration registration is incomplete.'
);

echo "Notification provider default management checks passed.\n";

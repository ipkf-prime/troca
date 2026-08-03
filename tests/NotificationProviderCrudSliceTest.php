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
    . 'NotificationProviderManagementRepository.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'NotificationProviderManagementService.php'
);
$settingsRepository = $read(
    'public_html/app/Repositories/'
    . 'CommunicationSettingsRepository.php'
);
$settingsService = $read(
    'public_html/app/Services/'
    . 'CommunicationSettingsService.php'
);
$view = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$routes = $read(
    'public_html/routes/communication-center.php'
);
$style = $read(
    'public_html/resources/views/admin/partials/'
    . 'communication-style.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'EnableNotificationProviderManagementCrud.php'
);
$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);
$migrate = $read('public_html/public/migrate.php');
$seeder = $read(
    'public_html/system/Database/Seeds/'
    . 'CommunicationCenterSeeder.php'
);

foreach ([
    'notification_provider_instances',
    'notification_provider_secret_sets',
    'notification_provider_audit_events',
] as $table) {
    $expect(
        str_contains($repository, $table),
        "Missing repository table: {$table}"
    );
}

$expect(
    str_contains($repository, 'beginTransaction')
    && str_contains($repository, 'rollBack')
    && str_contains($repository, 'provider.created')
    && str_contains($repository, 'provider.updated')
    && str_contains($repository, 'provider.enabled')
    && str_contains($repository, 'provider.disabled'),
    'Provider writes must be transactional and audited.'
);

foreach ([
    "'smtp'",
    "'kavenegar'",
    "'generic_sms'",
    "'bale_bot'",
    "'telegram_bot'",
    "'eitaa_bot'",
    "'whatsapp_cloud'",
] as $provider) {
    $expect(
        str_contains($service, $provider),
        "Missing provider definition: {$provider}"
    );
}

$expect(
    str_contains($service, 'NotificationProviderSecretService')
    && str_contains($service, '->encrypt(')
    && str_contains($service, '->decrypt(')
    && str_contains($service, 'secret_keys_updated'),
    'Provider secret management is incomplete.'
);

$expect(
    str_contains($settingsService, 'provider_management')
    && str_contains($settingsRepository, 'has_secret'),
    'Communication settings integration is incomplete.'
);

$expect(
    str_contains(
        $routes,
        "'page' => \$page,\n"
        . "                'status' => trim("
    ),
    'Communication settings redirect status is not rendered.'
);

$expect(
    str_contains(
        $view,
        "'messenger' => 'پیام‌رسان'"
    ),
    'Messenger channel must have a Persian label.'
);

foreach ([
    'data-provider-form',
    'data-provider-type',
    'data-provider-fields',
    'data-provider-secrets',
    'secrets[',
    'برای حفظ مقدار فعلی خالی بگذارید',
] as $marker) {
    $expect(
        str_contains($view, $marker),
        "Missing provider form marker: {$marker}"
    );
}

$expect(
    !str_contains($view, 'encrypted_payload')
    && !str_contains($view, 'payload_checksum'),
    'Encrypted payload metadata must not be rendered.'
);

foreach ([
    '/admin/communications/settings/providers/save',
    '/admin/communications/settings/providers/{reference}/status',
] as $route) {
    $expect(
        str_contains($routes, $route)
        && str_contains($migration, $route)
        && str_contains($seeder, $route),
        "Missing provider route registration: {$route}"
    );
}

$expect(
    substr_count(
        $routes,
        '(new \IPKF\Security\Csrf())->check'
    ) >= 2,
    'Provider mutation routes must verify CSRF.'
);

$expect(
    str_contains(
        $registry,
        'EnableNotificationProviderManagementCrud::class'
    )
    && str_contains(
        $migrate,
        'new \IPKF\Database\Migrations\\'
        . 'EnableNotificationProviderManagementCrud()'
    ),
    'Provider CRUD migration is not registered.'
);

$expect(
    str_contains($style, '.provider-management-layout')
    && str_contains($style, '.provider-dynamic-grid')
    && str_contains($style, '.provider-row-actions'),
    'Provider management styles are incomplete.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $migration . $repository
    ),
    'Destructive SQL is present.'
);

echo "Notification provider CRUD slice checks passed.\n";

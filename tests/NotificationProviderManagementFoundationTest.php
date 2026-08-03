<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        throw new RuntimeException(
            "Cannot read required file: {$path}"
        );
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

$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'ExtendNotificationProviderManagement.php'
);
$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);
$migrateEndpoint = $read(
    'public_html/public/migrate.php'
);
$secretService = $read(
    'public_html/app/Services/'
    . 'NotificationProviderSecretService.php'
);
$seeder = $read(
    'public_html/system/Database/Seeds/'
    . 'CommunicationCenterSeeder.php'
);
$settingsService = $read(
    'public_html/app/Services/'
    . 'CommunicationSettingsService.php'
);

foreach ([
    'notification_provider_secret_sets',
    'notification_provider_audit_events',
    'notification_webhook_endpoints',
    'notification_webhook_events',
] as $table) {
    $expect(
        str_contains($migration, $table),
        "Missing provider-management table: {$table}"
    );
}

foreach ([
    'public_reference',
    'instance_kind',
    'is_enabled',
    'health_status_code',
    'purpose_code',
    'is_default',
    'fallback_order',
] as $column) {
    $expect(
        str_contains($migration, $column),
        "Missing provider-management column: {$column}"
    );
}

$expect(
    str_contains(
        $registry,
        'ExtendNotificationProviderManagement::class'
    ),
    'Provider-management migration is not registered.'
);

$expect(
    str_contains(
        $migrateEndpoint,
        'new \IPKF\Database\Migrations\\'
        . 'ExtendNotificationProviderManagement()'
    ),
    'Provider-management migration is missing from migrate.php.'
);

$expect(
    str_contains($secretService, 'sodium_crypto_secretbox')
    && str_contains($secretService, 'aes-256-gcm')
    && str_contains($secretService, "Env::get('APP_KEY'")
    && !preg_match(
        '/\b(?:password|api_key|bot_token|access_token)\s*=>\s*[\'"][^\'"]+/i',
        $secretService
    ),
    'Provider secrets must use authenticated encryption.'
);

foreach ([
    "'smtp'",
    "'kavenegar'",
    "'bale_bot'",
    "'eitaa_bot'",
    "'telegram_bot'",
    "'whatsapp_cloud'",
] as $provider) {
    $expect(
        str_contains($seeder, $provider),
        "Missing provider catalog entry: {$provider}"
    );
}

$expect(
    str_contains($settingsService, "'providers'")
    && str_contains($settingsService, "'defaults'"),
    'Provider registration and default selection must remain separate sections.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $migration
    ),
    'Destructive SQL is present.'
);

echo "Notification provider management foundation checks passed.\n";

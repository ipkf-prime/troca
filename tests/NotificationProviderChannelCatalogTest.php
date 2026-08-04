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
    . 'NotificationProviderManagementService.php'
);
$view = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$routes = $read(
    'public_html/routes/'
    . 'communication-center.php'
);
$seeder = $read(
    'public_html/system/Database/Seeds/'
    . 'CommunicationCenterSeeder.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'ExpandNotificationProviderCatalog.php'
);
$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);
$migrate = $read('public_html/public/migrate.php');

foreach ([
    "'gmail_smtp'",
    "'yahoo_smtp'",
    "'microsoft365_smtp'",
    "'smtp'",
    "'kavenegar'",
    "'melipayamak'",
    "'ippanel'",
    "'generic_sms'",
    "'bale_bot'",
    "'telegram_bot'",
    "'eitaa_bot'",
    "'whatsapp_cloud'",
] as $provider) {
    $expect(
        str_contains($seeder, $provider)
        && str_contains($migration, $provider),
        "Missing provider catalog entry: {$provider}"
    );
}

$expect(
    str_contains($view, 'پیام کوتاه (SMS)')
    && str_contains($seeder, 'پیام کوتاه (SMS)'),
    'SMS channel terminology is inconsistent.'
);

foreach ([
    'data-provider-channel',
    'data-provider-channel-code',
    'کانال ارسال',
    'سرویس‌دهنده',
] as $marker) {
    $expect(
        str_contains($view, $marker),
        "Missing channel/provider form marker: {$marker}"
    );
}

$expect(
    str_contains($service, "'channel_code'")
    && str_contains($service, 'provider_channel_required')
    && str_contains($service, 'provider_channel_mismatch')
    && str_contains($service, "'provider_name'"),
    'Provider channel validation is incomplete.'
);
$expect(
    str_contains(
        $routes,
        "'channel_code' =>\n"
        . "                        \$request->input(\n"
        . "                            'channel_code',"
    ),
    'Provider save route does not forward the selected channel.'
);

$expect(
    str_contains($view, 'name="form_mode"')
    && str_contains($view, "? 'edit'")
    && str_contains($view, ": 'create'"),
    'Provider form does not declare create/edit mode.'
);

$expect(
    str_contains($routes, "\$formMode === 'edit'")
    && str_contains($routes, ": '';"),
    'Provider create route does not discard stale public references.'
);


$expect(
    str_contains(
        $registry,
        'ExpandNotificationProviderCatalog::class'
    )
    && str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\\\'
        . 'ExpandNotificationProviderCatalog()'
    ),
    'Provider catalog migration is not registered.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $migration
    ),
    'Destructive SQL is present.'
);

$expect(
    str_contains($migration, 'notification_channels')
    && str_contains($migration, 'updateChannelTitles')
    && str_contains($migration, 'پیام کوتاه (SMS)'),
    'Provider catalog migration does not update the SMS channel title.'
);

echo "Notification provider channel catalog checks passed.\n";

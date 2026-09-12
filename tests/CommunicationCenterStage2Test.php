<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

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
    . 'CreateCommunicationCenterFoundationTables.php'
);
$seeder = $read(
    'public_html/system/Database/Seeds/'
    . 'CommunicationCenterSeeder.php'
);
$routes = $read(
    'public_html/routes/communication-center.php'
);
$patcher = $read(
    'tools/apply-communication-center-stage2.php'
);
$messageService = $read(
    'public_html/app/Services/InternalMessageService.php'
);
$loginNotifier = $read(
    'public_html/app/Services/'
    . 'InternalMessageLoginNotifierService.php'
);
$navigationService = $read(
    'public_html/app/Services/'
    . 'DynamicAdminNavigationService.php'
);

foreach ([
    'admin_navigation_items',
    'admin_route_permissions',
    'message_conversations',
    'message_conversation_participants',
    'message_messages',
    'notification_provider_types',
    'notification_provider_instances',
    'notification_provider_defaults',
    'notification_provider_balance_snapshots',
    'notification_event_catalog',
    'notification_routing_rules',
] as $table) {
    $expect(
        str_contains($migration, $table),
        "Missing table: {$table}"
    );
}

$currentCommunicationChildren = [
    'communications-inbox',
    'communications-compose',
    'communications-sent',
    'communications-notifications',
    'communications-settings',
];

foreach ($currentCommunicationChildren as $itemKey) {
    $expect(
        str_contains(
            $seeder,
            "'" . $itemKey . "'"
        ),
        "Missing active Communication Center child: {$itemKey}"
    );
}

$expect(
    substr_count(
        $seeder,
        "['core', 'communications-"
    ) === 5,
    'Communication Center must seed exactly five active consolidated children.'
);

$obsoleteCommunicationChildren = [
    'communications-providers',
    'communications-provider-defaults',
    'communications-routing',
    'communications-preferences',
    'communications-reports',
];

foreach ($obsoleteCommunicationChildren as $itemKey) {
    $expect(
        str_contains(
            $seeder,
            "'" . $itemKey . "'"
        ),
        "Missing obsolete Communication Center key: {$itemKey}"
    );
}

$expect(
    str_contains(
        $seeder,
        'SET is_active = 0'
    )
    && str_contains(
        $seeder,
        '$deactivate->execute($obsolete);'
    ),
    'Obsolete granular Communication Center navigation must be explicitly deactivated.'
);

foreach ([
    '/admin/messages/inbox',
    '/admin/messages/compose',
    '/admin/messages/sent',
    '/admin/messages/thread/{reference}',
    '/admin/communications/settings',
] as $path) {
    $expect(
        str_contains($routes, $path),
        "Missing route: {$path}"
    );
}

$expect(
    str_contains(
        $patcher,
        'DynamicAdminNavigationService'
    )
    && str_contains(
        $patcher,
        'activeChannelCodes'
    )
    && str_contains(
        $patcher,
        'InternalMessageLoginNotifierService'
    ),
    'Runtime integration patch is incomplete.'
);

$expect(
    str_contains(
        $navigationService,
        'public function topbar'
    )
    && str_contains(
        $navigationService,
        "['children']"
    )
    && str_contains(
        $patcher,
        'admin-nav__children'
    )
    && str_contains(
        $patcher,
        '$topbarNav'
    ),
    'Nested navigation or dynamic topbar is missing.'
);

$expect(
    str_contains($seeder, 'messages-unread-alert')
    && str_contains($seeder, "'topbar'")
    && str_contains($migration, 'placement_code')
    && str_contains($migration, 'hide_when_badge_empty'),
    'Unread-message topbar registry is incomplete.'
);

$expect(
    str_contains($messageService, "'messages.new'")
    && str_contains($messageService, "'channels' => ['in_app']")
    && str_contains(
        $loginNotifier,
        "'messages.unread_on_login'"
    ),
    'Internal-message notification flow is incomplete.'
);

$expect(
    str_contains($seeder, "'bale_bot'")
    && str_contains($seeder, "'telegram_bot'")
    && str_contains($seeder, "'eitaa_bot'")
    && str_contains($seeder, "'whatsapp_cloud'"),
    'Messenger provider catalog is incomplete.'
);

$expect(
    str_contains(
        $seeder,
        "'notifications.preferences.self'"
    )
    && str_contains(
        $routes,
        'savePreferences'
    ),
    'Per-user notification preferences are missing.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $migration . $seeder
    ),
    'Destructive SQL is present.'
);

echo "Communication Center Stage 2 checks passed.\n";

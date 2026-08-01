<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        throw new RuntimeException('Unable to read ' . $path);
    }

    return $content;
};

$seeder = $read(
    'public_html/system/Database/Seeds/'
    . 'CommunicationCenterSeeder.php'
);
$layout = $read(
    'public_html/resources/views/admin/layout.php'
);
$settings = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'CommunicationSettingsService.php'
);
$navigation = $read(
    'public_html/app/Services/'
    . 'DynamicAdminNavigationService.php'
);
$routeLoader = $read(
    'public_html/system/Routing/RouteLoader.php'
);
$dashboard = $read(
    'public_html/resources/views/admin/dashboard.php'
);

$expect(
    str_contains($seeder, "'communications-settings'"),
    'Consolidated communication settings menu is missing.'
);
$expect(
    str_contains($seeder, "'account-cartable'"),
    'Dynamic account cartable item is missing.'
);
$expect(
    str_contains($seeder, "'communications_unread_total'"),
    'Combined topbar badge is missing.'
);
$expect(
    str_contains($seeder, 'SET is_active = 0'),
    'Obsolete settings submenu deactivation is missing.'
);
$expect(
    str_contains($navigation, 'public function account('),
    'Dynamic account placement reader is missing.'
);
$expect(
    str_contains($layout, 'admin-bell-icon')
    && str_contains($layout, 'has-badge')
    && str_contains($layout, 'admin-account-nav-badge')
    && str_contains($layout, '$coreTopbarNav')
    && str_contains($layout, "'core'
    )
    : [];")
    && str_contains($layout, '$dynamicAccountNav'),
    'Global header bell or account cartable is missing.'
);
$expect(
    str_contains($settings, 'روش‌های دریافت اعلان')
    && str_contains($settings, 'communication-preference-card'),
    'Preference UI redesign is missing.'
);
$expect(
    str_contains($service, 'allowedSections')
    && str_contains($service, 'AuthorizationService'),
    'Permission-aware settings tabs are missing.'
);
$expect(
    str_contains($routeLoader, '/routes/communication-ui.php'),
    'Communication UI route override is not loaded.'
);
$expect(
    str_contains($dashboard, 'repeat(4, minmax(0, 1fr))')
    && str_contains($dashboard, 'min-height: 122px'),
    'Compact dashboard module tiles are missing.'
);

echo "Communication navigation and UI R6 checks passed.\n";

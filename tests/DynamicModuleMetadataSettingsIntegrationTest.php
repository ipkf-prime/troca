<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$repository = file_get_contents(
    $root
    . '/public_html/app/Repositories/'
    . 'ApplicationModuleRepository.php'
);

$service = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'ApplicationModuleRegistryService.php'
);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/settings.php'
);

$dashboard = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'DynamicModuleDashboardService.php'
);

$panel = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'AdminPanelService.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach ([
    'route_path',
    'permission_key',
    'icon_code',
    'color_code',
    'sidebar_enabled',
    'dashboard_enabled',
    'dashboard_description',
] as $column) {

    $expect(
        str_contains($repository, $column),
        'Repository metadata missing: ' . $column
    );

    $expect(
        str_contains(
            $view,
            'name="' . $column . '"'
        ),
        'Settings field missing: ' . $column
    );
}

$expect(
    str_contains(
        $service,
        "_DB_PASSWORD"
    ),
    'Dynamic secret reference missing.'
);

$expect(
    str_contains(
        $service,
        'clearCache();'
    ),
    'Runtime cache reconciliation missing.'
);

$expect(
    str_contains(
        $dashboard,
        "'permission'"
    )
    && str_contains(
        $dashboard,
        "'route'"
    ),
    'Dashboard runtime metadata missing.'
);

$expect(
    str_contains(
        $panel,
        '$this->navigation->can'
    )
    && str_contains(
        $panel,
        '$permission'
    ),
    'Dashboard RBAC enforcement missing.'
);

echo "DYNAMIC_MODULE_METADATA_SETTINGS_PASS\n";

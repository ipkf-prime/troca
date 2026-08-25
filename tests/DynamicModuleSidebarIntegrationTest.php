<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$panel = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'AdminPanelService.php'
);

$sync = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'DynamicAdminNavigationService.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(
    str_contains(
        $panel,
        'new \\IPKF\\Support\\ModuleRuntimeConfig()'
    ),
    'Sidebar must read runtime module registry.'
);

$expect(
    str_contains(
        $panel,
        "['sidebar_enabled']"
    ),
    'Sidebar must honor sidebar_enabled.'
);

$expect(
    str_contains(
        $panel,
        "['permission_key']"
    ),
    'Sidebar must honor module permission.'
);

$expect(
    str_contains(
        $panel,
        '$this->navigation->can'
    ),
    'Sidebar must enforce RBAC.'
);

$expect(
    str_contains(
        $panel,
        '/auth/module-sso/start?return_path='
    ),
    'Core module navigation must enter through SSO.'
);

$expect(
    str_contains(
        $sync,
        "SET is_active = 0"
    ),
    'Sync must deactivate hidden/inactive module navigation.'
);

$expect(
    str_contains(
        $sync,
        "['sidebar_enabled']"
    ),
    'Navigation sync must honor sidebar_enabled.'
);

$expect(
    !str_contains(
        $sync,
        'DELETE FROM admin_navigation_items'
    ),
    'Navigation sync must preserve registry records.'
);

echo "DYNAMIC_MODULE_SIDEBAR_PASS\n";

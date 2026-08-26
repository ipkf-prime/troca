<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static fn (
        string $path
    ): string =>
        file_get_contents(
            $root . '/' . $path
        );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'ExtendAdminNavigationCoreFeatureMetadata.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$service =
    $read(
        'public_html/app/Services/'
        . 'CoreFeatureRegistryService.php'
    );

$panel =
    $read(
        'public_html/app/Services/'
        . 'AdminPanelService.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'core-feature-settings.php'
    );

$web =
    $read(
        'public_html/routes/web.php'
    );

$icons =
    $read(
        'public_html/app/Support/'
        . 'AdminIcon.php'
    );

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };


foreach ([
    'dashboard_enabled',
    "'support'",
    "'book-open'",
    "'list-check'",
    "'core-feature-settings'",
] as $needle) {
    $expect(
        str_contains(
            $migration,
            $needle
        ),
        'Core feature migration missing: '
        . $needle
    );
}


$expect(
    str_contains(
        $registry,
        'ExtendAdminNavigationCoreFeatureMetadata::class'
    ),
    'Core feature migration not registered.'
);


foreach ([
    'class CoreFeatureRegistryService',
    'dashboardCards(',
    'appearanceMap(',
    "'dashboard_enabled'",
    "'sidebar_enabled'",
    'Editable presentation fields only',
] as $needle) {
    $expect(
        str_contains(
            $service,
            $needle
        ),
        'Core feature service missing: '
        . $needle
    );
}


/*
 * Editable registry service must not accept
 * route or permission values from user input.
 */
foreach ([
    "\$input['route_path']",
    "\$input['permission_mode']",
    "\$input['permission_codes_json']",
    "\$input['target_application']",
] as $forbidden) {
    $expect(
        !str_contains(
            $service,
            $forbidden
        ),
        'Security identity became editable: '
        . $forbidden
    );
}


foreach ([
    'CoreFeatureRegistryService',
    'dashboardCards($userId)',
    'appearanceMap()',
    'core_sidebar_enabled',
] as $needle) {
    $expect(
        str_contains(
            $panel,
            $needle
        ),
        'AdminPanel Core registry integration '
        . 'missing: '
        . $needle
    );
}


foreach ([
    'تنظیمات بخش‌های داخلی پنل',
    'feature_key',
    'sidebar_enabled',
    'dashboard_enabled',
    'route_path',
    'permission_codes',
    'data-core-feature-color',
] as $needle) {
    $expect(
        str_contains(
            $view,
            $needle
        ),
        'Core feature settings UI missing: '
        . $needle
    );
}


foreach ([
    "get(\n    '/admin/settings/core-features'",
    "post(\n    '/admin/settings/core-features'",
    'CoreFeatureRegistryService',
] as $needle) {
    $expect(
        str_contains(
            $web,
            $needle
        ),
        'Core feature route missing: '
        . $needle
    );
}


foreach ([
    "'support' => 'book-open'",
    "'book-open' => 'book-open'",
    "'list-check' => 'list-check'",
    "'tasks' => 'list-check'",
    'public static function supports(',
] as $needle) {
    $expect(
        str_contains(
            $icons,
            $needle
        ),
        'Admin icon registry missing: '
        . $needle
    );
}


$dashboard =
    $read(
        'public_html/resources/views/admin/'
        . 'dashboard.php'
    );


$expect(
    str_contains(
        $dashboard,
        'بخش‌های سامانه'
    ),
    'Dashboard user-facing section terminology missing.'
);

$expect(
    !str_contains(
        $dashboard,
        'ماژول‌های سامانه'
    ),
    'Legacy dashboard module terminology remains.'
);

$expect(
    str_contains(
        $view,
        'بخش‌های داخلی سامانه'
    ),
    'Core feature user-facing heading missing.'
);

$expect(
    !str_contains(
        $view,
        'بخش‌های Core'
    ),
    'Technical Core terminology leaked into UI.'
);


echo "CORE_FEATURE_REGISTRY_PASS\n";

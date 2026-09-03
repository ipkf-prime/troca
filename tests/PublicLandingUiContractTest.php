<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$targets = [
    'web' =>
        'public_html/routes/web.php',
    'routes' =>
        'public_html/routes/public-landing.php',
    'loader' =>
        'public_html/system/Routing/RouteLoader.php',
    'nav' =>
        'public_html/app/Services/AdminNavigationRbacService.php',
    'public_view' =>
        'public_html/resources/views/site/landing.php',
    'admin_view' =>
        'public_html/resources/views/admin/public-page.php',
    'migrate' =>
        'public_html/public/migrate.php',
    'registry' =>
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php',
];

$content = [];

foreach ($targets as $key => $relative) {
    $value = file_get_contents(
        $root . '/' . $relative
    );

    if (!is_string($value)) {
        throw new RuntimeException(
            "Missing {$key}"
        );
    }

    $content[$key] = $value;
}

$contracts = [
    ['web', 'PublicLandingService'],
    ['web', 'public_page_settings'],
    ['routes', '/admin/public-page'],
    ['routes', 'saveSettings'],
    ['routes', 'saveItem'],
    ['routes', 'deleteItem'],
    ['loader', "routes/public-landing.php"],
    ['nav', "'/admin/public-page' => 'admin.settings.manage'"],
    ['nav', 'مدیریت صفحه عمومی'],
    ['public_view', 'data-slider'],
    ['public_view', 'show_register'],
    ['public_view', "runtime_slots"],
    ['admin_view', 'multipart/form-data'],
    ['admin_view', 'starts_date'],
    ['admin_view', 'mobile_image'],
    ['migrate', 'CreatePublicLandingFoundation'],
    ['registry', 'CreatePublicLandingFoundation::class'],
];

foreach ($contracts as [$key, $marker]) {
    if (!str_contains($content[$key], $marker)) {
        throw new RuntimeException(
            "{$key} missing {$marker}"
        );
    }
}

if (
    str_contains(
        $content['public_view'],
        'اتوماسیون مکاتبات'
    )
    || str_contains(
        $content['public_view'],
        'مدیریت کار'
    )
    || str_contains(
        $content['public_view'],
        'تیکتینگ'
    )
) {
    throw new RuntimeException(
        'Pre-login module disclosure detected.'
    );
}

echo "PUBLIC_LANDING_UI_CONTRACT=PASS\n";

<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$versionPath =
    $root
    . '/public_html/system/Support/Version.php';

$gatePath =
    $root
    . '/scripts/ipkf-platform-runtime-gate.sh';

$manifestPath =
    $root
    . '/scripts/ipkf-platform-shared-runtime-files.txt';

$consumers = [
    $root
    . '/public_html/app/Services/AdminPanelService.php',
    $root
    . '/public_html/resources/views/admin/layout.php',
    $root
    . '/public_html/routes/web.php',
    $root
    . '/public_html/system/Installer/Installer.php',
];

$version =
    file_get_contents($versionPath);

$gate =
    file_get_contents($gatePath);

$manifest =
    file_get_contents($manifestPath);

if (
    !is_string($version)
    || !is_string($gate)
    || !is_string($manifest)
) {
    throw new RuntimeException(
        'Dynamic version source unreadable.'
    );
}

foreach ([
    'public static function current(): string',
    'runtime-build.json',
    'IPKF_APP_VERSION',
] as $needle) {

    if (!str_contains(
        $version,
        $needle
    )) {
        throw new RuntimeException(
            'Version resolver contract missing: '
            . $needle
        );
    }
}

foreach ($consumers as $path) {

    $source =
        file_get_contents($path);

    if (!is_string($source)) {
        throw new RuntimeException(
            'Cannot read version consumer: '
            . $path
        );
    }

    if (!str_contains(
        $source,
        'Version::current()'
    )) {
        throw new RuntimeException(
            'Dynamic version consumer missing: '
            . $path
        );
    }

    if (str_contains(
        $source,
        'Version::CURRENT'
    )) {
        throw new RuntimeException(
            'Static version consumer remains: '
            . $path
        );
    }
}

foreach ([
    'BUILD_BRANCH',
    'BUILD_VERSION',
    'BUILD_COMMIT',
    'runtime-build.json',
] as $needle) {

    if (!str_contains(
        $gate,
        $needle
    )) {
        throw new RuntimeException(
            'Deployment version contract missing: '
            . $needle
        );
    }
}

foreach ([
    'system/Support/Version.php',
    'system/Routing/Router.php',
    'resources/views/admin/layout.php',
    'routes/web.php',
    'system/Installer/Installer.php',
] as $needle) {

    if (!str_contains(
        $manifest,
        $needle
    )) {
        throw new RuntimeException(
            'Shared version closure missing: '
            . $needle
        );
    }
}


/*
 * OPTIONAL_TICKETING_REQUESTER_ROUTE_CONTRACT
 *
 * routes/web.php is part of the shared runtime closure,
 * while routes/ticketing-requester.php is deliberately
 * module-specific and absent from Automation / Work.
 */
$webPath =
    $root
    . '/public_html/routes/web.php';

$web =
    file_get_contents($webPath);

if (!is_string($web)) {
    throw new RuntimeException(
        'Shared web route source unreadable.'
    );
}

foreach ([
    '$ticketingRequesterRoutes',
    "BASE_PATH\n    . '/routes/ticketing-requester.php'",
    'if (is_file($ticketingRequesterRoutes))',
    'require $ticketingRequesterRoutes;',
] as $needle) {

    if (
        !str_contains(
            $web,
            $needle
        )
    ) {
        throw new RuntimeException(
            'Optional Ticketing route guard missing: '
            . $needle
        );
    }
}

if (
    str_contains(
        $web,
        "require BASE_PATH . '/routes/ticketing-requester.php';"
    )
) {
    throw new RuntimeException(
        'Unguarded Ticketing requester route require remains.'
    );
}

if (
    str_contains(
        $manifest,
        'routes/ticketing-requester.php'
    )
) {
    throw new RuntimeException(
        'Ticketing requester route must remain module-specific.'
    );
}


/*
 * ADMIN_THEME_SHARED_ASSET_CONTRACT
 *
 * layout.php is shared across all active runtimes and directly
 * consumes AdminThemeService::assetUrls(). Therefore the provider
 * must be part of the same shared closure.
 */
$themeServicePath =
    $root
    . '/public_html/app/Services/AdminThemeService.php';

$layoutPath =
    $root
    . '/public_html/resources/views/admin/layout.php';

$themeService =
    file_get_contents($themeServicePath);

$layoutSource =
    file_get_contents($layoutPath);

if (
    !is_string($themeService)
    || !is_string($layoutSource)
) {
    throw new RuntimeException(
        'Admin theme asset contract sources unreadable.'
    );
}

if (
    !str_contains(
        $manifest,
        'app/Services/AdminThemeService.php'
    )
) {
    throw new RuntimeException(
        'AdminThemeService missing from shared runtime closure.'
    );
}

foreach ([
    "'admin_css' =>",
    "'foundation_css' =>",
    "'icons_css' =>",
    "'admin_js' =>",
    "'foundation_js' =>",
] as $needle) {

    if (
        !str_contains(
            $themeService,
            $needle
        )
    ) {
        throw new RuntimeException(
            'Admin theme asset provider contract missing: '
            . $needle
        );
    }
}

foreach ([
    '$themeService->assetUrls()',
    "\$themeAssets['foundation_css']",
    "\$themeAssets['foundation_js']",
] as $needle) {

    if (
        !str_contains(
            $layoutSource,
            $needle
        )
    ) {
        throw new RuntimeException(
            'Admin layout asset consumer contract missing: '
            . $needle
        );
    }
}


/*
 * ADMIN_FOUNDATION_STATIC_ASSET_CLOSURE
 *
 * AdminThemeService and admin/layout.php are shared platform
 * components. Their Foundation CSS/JS artifacts therefore belong
 * to the same runtime closure and must exist on every active runtime.
 */
$foundationAssets = [
    'public/assets/admin/css/foundation.css',
    'public/assets/admin/js/foundation.js',
];

foreach ($foundationAssets as $asset) {

    if (
        !str_contains(
            $manifest,
            $asset
        )
    ) {
        throw new RuntimeException(
            'Foundation asset missing from shared manifest: '
            . $asset
        );
    }

    $sourceAsset =
        $root
        . '/public_html/'
        . $asset;

    if (
        !is_file($sourceAsset)
        || !is_readable($sourceAsset)
    ) {
        throw new RuntimeException(
            'Foundation source asset missing or unreadable: '
            . $asset
        );
    }
}

if (
    str_contains(
        $version,
        "'0.7.0'"
    )
    || str_contains(
        $version,
        '"0.7.0"'
    )
) {
    throw new RuntimeException(
        'Release version is hardcoded.'
    );
}

echo "PLATFORM_DYNAMIC_VERSION_PASS\n";

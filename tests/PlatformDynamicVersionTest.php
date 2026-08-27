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

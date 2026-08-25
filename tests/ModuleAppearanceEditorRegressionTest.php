<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$settings = file_get_contents(
    $root
    . '/public_html/resources/views/admin/settings.php'
);

$dashboard = file_get_contents(
    $root
    . '/public_html/resources/views/admin/dashboard.php'
);

$service = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'ApplicationModuleRegistryService.php'
);

$css = file_get_contents(
    $root
    . '/public_html/public/assets/admin/css/admin.css'
);

foreach ([
    'type="color"',
    'data-module-color-picker',
    'data-module-color-hex',
    'admin-module-runtime-grid',
    'admin-module-runtime-toggles',
    'legacyModuleColors',
    'normalizeModuleColor',
] as $token) {
    if (!str_contains($settings, $token)) {
        throw new RuntimeException(
            'Settings appearance contract missing: '
            . $token
        );
    }
}

foreach ([
    'admin_module_color_hex',
    '--module-color-a:',
    '--module-color-b:',
    "'orange' => '#f97316'",
] as $token) {
    if (!str_contains($dashboard, $token)) {
        throw new RuntimeException(
            'Dashboard color contract missing: '
            . $token
        );
    }
}

foreach ([
    'private function normalizeModuleColor',
    "'orange' => '#f97316'",
    "'green' => '#16a34a'",
    "'indigo' => '#4f46e5'",
    'رنگ کارت ماژول معتبر نیست',
] as $token) {
    if (!str_contains($service, $token)) {
        throw new RuntimeException(
            'Server color normalization missing: '
            . $token
        );
    }
}

foreach ([
    '.admin-module-runtime-grid',
    '.admin-module-color-control',
    '.admin-module-runtime-toggles',
    'input[type="color"]',
] as $token) {
    if (!str_contains($css, $token)) {
        throw new RuntimeException(
            'Appearance CSS missing: '
            . $token
        );
    }
}

if (
    !str_contains(
        $css,
        ".admin-module-runtime-description {\n    grid-column: auto;"
    )
) {
    throw new RuntimeException(
        'Module description field is not compact on desktop.'
    );
}

echo "MODULE_APPEARANCE_EDITOR_PASS\n";

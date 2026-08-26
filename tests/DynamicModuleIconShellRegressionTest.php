<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$panel =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'AdminPanelService.php'
    );

$layout =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'layout.php'
    );

$dashboard =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'DynamicModuleDashboardService.php'
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
    'ModuleRuntimeConfig',
    "'icon_code'",
    '$moduleShellContext',
] as $needle) {
    $expect(
        str_contains(
            $panel,
            $needle
        ),
        'Module shell icon pipeline missing: '
        . $needle
    );
}


foreach ([
    '$moduleIconCode',
    "['icon_code']",
    'AdminIcon::html(',
    'admin-brand__module-icon',
    'data-module-brand-icon',
] as $needle) {
    $expect(
        str_contains(
            $layout,
            $needle
        ),
        'Dynamic module brand icon missing: '
        . $needle
    );
}


$expect(
    strpos(
        $layout,
        '<?php if ($isModuleShell): ?>'
    )
    < strpos(
        $layout,
        "(\$theme['logo_url'] ?? '')"
    ),
    'Module icon must take precedence '
    . 'over the global theme logo.'
);


foreach ([
    "'icon_code'",
    "'color_code'",
] as $needle) {
    $expect(
        str_contains(
            $dashboard,
            $needle
        ),
        'Dashboard module appearance '
        . 'pipeline missing: '
        . $needle
    );
}


echo "DYNAMIC_MODULE_ICON_SHELL_PASS\n";

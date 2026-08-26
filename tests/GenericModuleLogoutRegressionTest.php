<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$web =
    file_get_contents(
        $root
        . '/public_html/routes/web.php'
    );

$urls =
    file_get_contents(
        $root
        . '/public_html/system/Support/'
        . 'ApplicationUrlRegistry.php'
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
    'applicationModuleKeyForHost(',
    'return_module',
    'ModuleRuntimeConfig',
    "'route_path'",
] as $needle) {
    $expect(
        str_contains(
            $web,
            $needle
        ),
        'Generic logout contract missing: '
        . $needle
    );
}


foreach ([
    'return_module=automation',
    'return_module=work',
    "=== 'automation'",
    "=== 'work'",
] as $legacy) {
    $expect(
        !str_contains(
            $web,
            $legacy
        ),
        'Legacy logout hardcode remains: '
        . $legacy
    );
}


$expect(
    str_contains(
        $urls,
        'applicationModuleKeyForHost('
    ),
    'Generic module host resolver required.'
);


echo "GENERIC_MODULE_LOGOUT_PASS\n";

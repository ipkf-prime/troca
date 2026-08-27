<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$gatePath =
    $root
    . '/scripts/ipkf-platform-runtime-gate.sh';

$manifestPath =
    $root
    . '/scripts/ipkf-platform-shared-runtime-files.txt';

if (!is_file($gatePath)) {
    throw new RuntimeException(
        'Dynamic runtime gate is missing.'
    );
}

if (!is_file($manifestPath)) {
    throw new RuntimeException(
        'Shared runtime manifest is missing.'
    );
}

$gate =
    file_get_contents(
        $gatePath
    );

$manifest =
    file_get_contents(
        $manifestPath
    );

if (!is_string($gate)) {
    throw new RuntimeException(
        'Cannot read dynamic runtime gate.'
    );
}

if (!is_string($manifest)) {
    throw new RuntimeException(
        'Cannot read shared runtime manifest.'
    );
}


foreach ([
    'FROM application_modules',
    'WHERE is_active = 1',
    'base_url',
    'route_path',
    'runtime_mode',
    'parse_url',
    'IPKF_RUNTIME_PARENT',
    'IPKF_CORE_BASE_URL',
    'discover|verify|sync',
    'AUTOLOAD=PASS',
    'HTTP_NON_500=PASS',
] as $needle) {

    if (!str_contains(
        $gate,
        $needle
    )) {
        throw new RuntimeException(
            'Dynamic gate contract missing: '
            . $needle
        );
    }
}


foreach ([
    'AdminPanelService.php',
    'DynamicModuleDashboardService.php',
    'ModuleRuntimeConfig.php',
] as $needle) {

    if (!str_contains(
        $manifest,
        $needle
    )) {
        throw new RuntimeException(
            'Shared manifest contract missing: '
            . $needle
        );
    }
}


/*
 * Module runtime membership must never be hardcoded.
 */
foreach ([
    'oa-dev.troca.ir',
    'work-dev.troca.ir',
    'ticketing-dev.troca.ir',

    '"automation"',
    "'automation'",

    '"work"',
    "'work'",

    '"ticketing"',
    "'ticketing'",
] as $forbidden) {

    if (str_contains(
        $gate,
        $forbidden
    )) {
        throw new RuntimeException(
            'Hardcoded module/runtime leaked into gate: '
            . $forbidden
        );
    }
}


/*
 * Explicit arrays of application runtimes are forbidden.
 */
if (
    preg_match(
        '/RUNTIMES\s*=\s*\(/',
        $gate
    ) === 1
) {
    throw new RuntimeException(
        'Dynamic gate must not contain a RUNTIMES array.'
    );
}


if (
    str_contains(
        $gate,
        'https://dev.troca.ir/admin/dashboard'
    )
) {
    throw new RuntimeException(
        'Core smoke endpoint must be environment-driven.'
    );
}

if (
    str_contains(
        $gate,
        'Deduplicate physical runtimes'
    )
) {
    throw new RuntimeException(
        'Gate must preserve one smoke target per active module.'
    );
}

if (
    !str_contains(
        $gate,
        "IFS=$'\\x1f'"
    )
) {
    throw new RuntimeException(
        'Dynamic registry records must preserve empty fields.'
    );
}


echo "PLATFORM_DYNAMIC_RUNTIME_GATE_PASS\n";

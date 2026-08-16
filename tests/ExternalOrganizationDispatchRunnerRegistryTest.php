<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$migrationRegistry = file_get_contents(
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);

$seederRegistry = file_get_contents(
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationSeederRegistry.php'
);

$migrate = file_get_contents(
    $root
    . '/public_html/public/migrate.php'
);

$seed = file_get_contents(
    $root
    . '/public_html/public/seed.php'
);

foreach ([
    'migrationRegistry' => $migrationRegistry,
    'seederRegistry' => $seederRegistry,
    'migrate' => $migrate,
    'seed' => $seed,
] as $label => $source) {
    if (!is_string($source)) {
        fwrite(
            STDERR,
            "FAIL: {$label} unavailable.\n"
        );
        exit(1);
    }
}

$required = [
    [
        $migrationRegistry,
        'CreateExternalOrganizationCorrespondenceDirectory::class',
    ],
    [
        $migrationRegistry,
        'CreateAutomationCorrespondenceDispatchFoundation::class',
    ],
    [
        $seederRegistry,
        'ExternalOrganizationContactCatalogSeeder::class',
    ],
    [
        $migrate,
        'new \\IPKF\\Database\\Migrations\\CreateExternalOrganizationCorrespondenceDirectory()',
    ],
    [
        $seed,
        'new \\IPKF\\Database\\Seeds\\ExternalOrganizationContactCatalogSeeder()',
    ],
];

foreach ($required as [$source, $needle]) {
    if (!str_contains($source, $needle)) {
        fwrite(
            STDERR,
            "FAIL: Missing runner registration: {$needle}\n"
        );
        exit(1);
    }
}

/*
 * Automation dispatch belongs only to automation.primary.
 * It must not be added to the legacy Core migration list.
 */
if (
    str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\CreateAutomationCorrespondenceDispatchFoundation()'
    )
) {
    fwrite(
        STDERR,
        "FAIL: Automation dispatch migration leaked into Core runner.\n"
    );
    exit(1);
}

echo "External organization and dispatch runner registry checks passed.\n";

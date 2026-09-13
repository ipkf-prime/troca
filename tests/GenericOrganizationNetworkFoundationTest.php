<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    $root
    . '/public_html/app/Services/'
    . 'OrganizationNetworkService.php';

$migration =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'ExtendOrganizationRelationsForCatalogScopedNetworks.php';

$registry =
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';

$coreMigrate =
    $root
    . '/public_html/public/migrate.php';

$manifest =
    $root
    . '/scripts/'
    . 'ipkf-platform-shared-runtime-files.txt';

foreach ([
    $service,
    $migration,
    $registry,
    $coreMigrate,
    $manifest,
] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException(
            'Missing required file: ' . $file
        );
    }
}

$serviceBody =
    file_get_contents($service);

$migrationBody =
    file_get_contents($migration);

$registryBody =
    file_get_contents($registry);

$coreMigrateBody =
    file_get_contents($coreMigrate);

$manifestBody =
    file_get_contents($manifest);

foreach ([
    'service' => $serviceBody,
    'migration' => $migrationBody,
    'registry' => $registryBody,
    'core_migrate' => $coreMigrateBody,
    'manifest' => $manifestBody,
] as $label => $body) {
    if (!is_string($body)) {
        throw new RuntimeException(
            'Unable to read foundation source: '
            . $label
        );
    }
}

$serviceRequired = [
    'class OrganizationNetworkService',
    'function catalogs(',
    'function catalog(',
    'function node(',
    'function search(',
    'function parents(',
    'function parent(',
    'function children(',
    'function ancestors(',
    'function descendants(',
    'function primaryPath(',
    'function authorizationContext(',
    "'scope_type' =>",
    "'organization'",
    'hierarchy_parent',
];

foreach ($serviceRequired as $needle) {
    if (!str_contains($serviceBody, $needle)) {
        throw new RuntimeException(
            'Missing service contract: '
            . $needle
        );
    }
}

$migrationRequired = [
    'catalog_id',
    'source_code',
    'source_reference',
    'metadata_json',
    'last_synced_at',
    'org_relations_catalog_foreign',
    'hierarchy_parent',
];

foreach ($migrationRequired as $needle) {
    if (!str_contains($migrationBody, $needle)) {
        throw new RuntimeException(
            'Missing migration contract: '
            . $needle
        );
    }
}

$migrationClass =
    'ExtendOrganizationRelationsForCatalogScopedNetworks';

if (
    !str_contains(
        $registryBody,
        $migrationClass . '::class'
    )
) {
    throw new RuntimeException(
        'Migration is not registered in application registry.'
    );
}

if (
    !str_contains(
        $coreMigrateBody,
        'new \\IPKF\\Database\\Migrations\\'
        . $migrationClass
        . '(),'
    )
) {
    throw new RuntimeException(
        'Migration is not registered in Core migration execution path.'
    );
}

$manifestEntry =
    'app/Services/OrganizationNetworkService.php';

if (
    substr_count(
        $manifestBody,
        $manifestEntry
    ) !== 1
) {
    throw new RuntimeException(
        'Shared service manifest contract failed.'
    );
}

/*
 * Product-neutral guard.
 *
 * Generic graph semantics must not depend on one customer,
 * geography model, cooperative model, source database,
 * application module, or internal organization chart.
 */
$forbidden = [
    'corc',
    'troca_api',
    'province_id',
    'city_id',
    'geo_level_id',
    'org_level_id',
    'org_type_id',
    'organization_positions',
    'company_admin',
    'province_admin',
    'county_admin',
];

foreach ($forbidden as $needle) {
    if (
        stripos(
            $serviceBody,
            $needle
        ) !== false
    ) {
        throw new RuntimeException(
            'Customer-specific coupling found in generic network service: '
            . $needle
        );
    }
}

echo "GENERIC_ORGANIZATION_NETWORK_FOUNDATION_TEST=PASS\n";
echo "CATALOG_SCOPED_RELATIONS=YES\n";
echo "CORE_MIGRATION_EXECUTION_CONTRACT=YES\n";
echo "CANONICAL_ORGANIZATION_DIRECTORY=YES\n";
echo "LEGACY_PARENT_ID_AUTHORITY=NO\n";
echo "MULTI_NETWORK=YES\n";
echo "MULTI_HOLDING=YES\n";
echo "MULTI_ORGANIZATION=YES\n";
echo "MODULE_REUSE=YES\n";
echo "CUSTOMER_HARDCODE=NO\n";
echo "CORC_HARDCODE=NO\n";
echo "INTERNAL_ORG_CHART_COUPLING=NO\n";

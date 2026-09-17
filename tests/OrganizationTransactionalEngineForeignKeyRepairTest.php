<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$migration =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'RepairOrganizationTransactionalEnginesAndForeignKeys.php';

$registry =
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';

$migrate =
    $root
    . '/public_html/public/migrate.php';


foreach ([
    $migration,
    $registry,
    $migrate,
] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException(
            'Missing required file: '
            . $file
        );
    }
}


$body =
    file_get_contents($migration);

$registryBody =
    file_get_contents($registry);

$migrateBody =
    file_get_contents($migrate);

if (
    !is_string($body)
    || !is_string($registryBody)
    || !is_string($migrateBody)
) {
    throw new RuntimeException(
        'Unable to read repair sources.'
    );
}


$class =
    'RepairOrganizationTransactionalEnginesAndForeignKeys';


if (
    !str_contains(
        $registryBody,
        '\\IPKF\\Database\\Migrations\\'
        . $class
        . '::class'
    )
) {
    throw new RuntimeException(
        'Repair migration missing from application registry.'
    );
}


if (
    !str_contains(
        $migrateBody,
        'new \\IPKF\\Database\\Migrations\\'
        . $class
        . '(),'
    )
) {
    throw new RuntimeException(
        'Repair migration missing from legacy Core migration path.'
    );
}


$engineTables = [
    'organizations',
    'positions',
    'org_units',
    'organization_classification_schemes',
    'organization_classification_terms',
    'organization_classifications',
    'organization_relation_types',
    'organization_relations',
    'organization_unit_types',
    'organization_positions',
];

foreach ($engineTables as $table) {
    if (!str_contains($body, "'" . $table . "'")) {
        throw new RuntimeException(
            'Missing engine repair contract: '
            . $table
        );
    }
}


$constraints = [
    'org_class_terms_scheme_foreign',
    'org_class_terms_parent_foreign',
    'org_classes_organization_foreign',
    'org_classes_term_foreign',

    'org_relations_source_foreign',
    'org_relations_target_foreign',
    'org_relations_type_foreign',
    'org_relations_catalog_foreign',

    'org_units_organization_foreign',
    'org_units_unit_type_foreign',

    'org_positions_organization_foreign',
    'org_positions_unit_foreign',
    'org_positions_position_foreign',
    'org_positions_parent_foreign',

    'org_appointments_organization_foreign',
    'org_appointments_person_foreign',
    'org_appointments_position_foreign',

    'corr_organization_fk',
    'corr_org_unit_fk',

    'corr_parties_org_fk',
    'corr_parties_unit_fk',

    'registry_books_org_fk',
    'registry_books_unit_fk',

    'corr_ref_source_unit_fk',
    'corr_ref_target_unit_fk',
    'corr_ref_target_pos_fk',

    'corr_events_unit_fk',

    'platform_installations_owner_org_fk',
];


if (count($constraints) !== 28) {
    throw new RuntimeException(
        'Test contract count is not 28.'
    );
}


foreach ($constraints as $constraint) {
    if (
        substr_count(
            $body,
            "'" . $constraint . "'"
        ) !== 1
    ) {
        throw new RuntimeException(
            'Constraint contract missing or duplicated: '
            . $constraint
        );
    }
}


foreach ([
    'preflight()',
    'verifyPostconditions()',
    'ensureInnoDb(',
    'ensureForeignKey(',
    'orphanCount(',
    'equivalentForeignKey(',
    'ENGINE=InnoDB',
] as $needle) {
    if (!str_contains($body, $needle)) {
        throw new RuntimeException(
            'Repair safety contract missing: '
            . $needle
        );
    }
}


if (
    str_contains(
        $body,
        'DELETE FROM'
    )
    ||
    str_contains(
        $body,
        'UPDATE organizations'
    )
    ||
    str_contains(
        $body,
        'TRUNCATE'
    )
) {
    throw new RuntimeException(
        'Destructive/customer-data mutation detected.'
    );
}


echo "ENGINE_TABLE_CONTRACT_COUNT=10\n";
echo "FOREIGN_KEY_CONTRACT_COUNT=28\n";
echo "REGISTRY_BINDING=PASS\n";
echo "LEGACY_MIGRATE_BINDING=PASS\n";
echo "PREFLIGHT_CONTRACT=PASS\n";
echo "ORPHAN_GUARD_CONTRACT=PASS\n";
echo "POSTCONDITION_CONTRACT=PASS\n";
echo "DESTRUCTIVE_DATA_MUTATION=NO\n";
echo "ORGANIZATION_ENGINE_FK_REPAIR_TEST=PASS\n";

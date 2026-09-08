<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$migrationPath =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateTicketingRealmPortalBindingFoundation.php';

$registryPath =
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';


$migration =
    file_get_contents(
        $migrationPath
    );

$registry =
    file_get_contents(
        $registryPath
    );


if (
    !is_string($migration)
    || !is_string($registry)
) {
    throw new RuntimeException(
        'portal_foundation_source_unreadable'
    );
}


$mustContain = [
    'TICKETING_REALM_PORTAL_BINDING_FOUNDATION_V1',

    'ticketing_support_portals',
    'ticketing_support_portal_hosts',

    'ticketing_support_project_brand_settings',
    'ticketing_support_portal_brand_settings',

    'default_portal_id',

    'ticketing_support_portals_project_realm_fk',
    'ticketing_support_realms_default_portal_fk',

    'ticketing_support_portals_project_realm_identity_unique',

    'ticketing_portal_hosts_hostname_unique',
    'ticketing_portal_hosts_canonical_unique',

    'ticketing_project_brand_settings_key_unique',
    'ticketing_portal_brand_settings_key_unique',

    'ticketing-default-portal:',

    "'default'",
];


foreach ($mustContain as $needle) {

    if (
        !str_contains(
            $migration,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_foundation_marker_missing:'
            . $needle
        );
    }
}


/*
 * Host is environment configuration.
 *
 * Migration must create the Host table but must never seed a Host.
 */
if (
    preg_match(
        '/INSERT\s+INTO\s+ticketing_support_portal_hosts/i',
        $migration
    ) === 1
) {
    throw new RuntimeException(
        'portal_host_seed_forbidden'
    );
}


/*
 * Core Landing / Theme tables must not be mutated by Ticketing
 * Portal Foundation.
 */
foreach ([
    'app_settings',
    'public_page_settings',
    'public_page_items',
    'platform_domains',
] as $forbiddenTable) {

    if (
        str_contains(
            $migration,
            $forbiddenTable
        )
    ) {
        throw new RuntimeException(
            'cross_control_plane_table_reference_forbidden:'
            . $forbiddenTable
        );
    }
}


/*
 * Default Portal backfill must be deterministic and idempotent.
 */
if (
    !str_contains(
        $migration,
        'WHERE NOT EXISTS'
    )
) {
    throw new RuntimeException(
        'default_portal_idempotency_missing'
    );
}


/*
 * Realm -> default Portal must prove exact same Project + Realm.
 */
if (
    !preg_match(
        '/FOREIGN\s+KEY\s*\(\s*project_id\s*,\s*id\s*,\s*default_portal_id\s*\).*?REFERENCES\s+ticketing_support_portals\s*\(\s*project_id\s*,\s*realm_id\s*,\s*id\s*\)/is',
        $migration
    )
) {
    throw new RuntimeException(
        'realm_default_portal_composite_fk_missing'
    );
}


/*
 * Portal -> Realm must prove same Project.
 */
if (
    !preg_match(
        '/FOREIGN\s+KEY\s*\(\s*project_id\s*,\s*realm_id\s*\).*?REFERENCES\s+ticketing_support_realms\s*\(\s*project_id\s*,\s*id\s*\)/is',
        $migration
    )
) {
    throw new RuntimeException(
        'portal_same_project_realm_fk_missing'
    );
}


/*
 * Migration Registry order:
 *
 * R2B M2 -> R3 Portal Foundation.
 */
$m2 =
    '\\IPKF\\Database\\Migrations\\'
    . 'FinalizeTicketingStrictOperationalRealmEnforcement'
    . '::class';

$r3 =
    '\\IPKF\\Database\\Migrations\\'
    . 'CreateTicketingRealmPortalBindingFoundation'
    . '::class';


if (
    substr_count(
        $registry,
        $m2
    ) !== 1
) {
    throw new RuntimeException(
        'm2_registry_marker_invalid'
    );
}


if (
    substr_count(
        $registry,
        $r3
    ) !== 1
) {
    throw new RuntimeException(
        'r3_registry_marker_invalid'
    );
}


$m2Position =
    strpos(
        $registry,
        $m2
    );

$r3Position =
    strpos(
        $registry,
        $r3
    );


if (
    $m2Position === false
    || $r3Position === false
    || $m2Position >= $r3Position
) {
    throw new RuntimeException(
        'r3_registry_order_invalid'
    );
}


echo
    'TICKETING_REALM_PORTAL_BINDING_FOUNDATION_PASS'
    . PHP_EOL;

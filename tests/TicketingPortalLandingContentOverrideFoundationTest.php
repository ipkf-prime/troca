<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$migrationPath =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateTicketingPortalLandingContentOverrideFoundation.php';


$runtimePath =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingPortalLandingService.php';


$registryPath =
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';


$migration =
    file_get_contents(
        $migrationPath
    );


$runtime =
    file_get_contents(
        $runtimePath
    );


$registry =
    file_get_contents(
        $registryPath
    );


if (
    !is_string($migration)
    || !is_string($runtime)
    || !is_string($registry)
) {
    throw new RuntimeException(
        'portal_landing_override_source_unreadable'
    );
}


foreach ([
    'TICKETING_PORTAL_LANDING_CONTENT_OVERRIDE_FOUNDATION_V1',

    'ticketing_support_portal_landing_settings',
    'ticketing_support_portal_landing_items',

    'ticketing_portal_landing_settings_key_unique',
    'ticketing_portal_landing_items_reference_unique',
    'ticketing_portal_landing_items_identity_unique',

    'ticketing_portal_landing_settings_portal_fk',
    'ticketing_portal_landing_items_portal_fk',

    'override_mode',
    "'upsert'",
] as $needle) {

    if (
        !str_contains(
            $migration,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_landing_migration_marker_missing:'
            . $needle
        );
    }
}


/*
 * Migration must never couple to Core public landing tables.
 */
foreach ([
    'public_page_settings',
    'public_page_items',
    'app_settings',
    'platform_domains',
] as $forbidden) {

    if (
        str_contains(
            $migration,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'portal_landing_cross_control_plane_reference_forbidden:'
            . $forbidden
        );
    }
}


foreach ([
    'TICKETING_PORTAL_LANDING_CONTENT_OVERLAY_V1',

    'ticketing_support_portal_landing_settings',
    'ticketing_support_portal_landing_items',

    "'upsert'",
    "'hide'",

    '_portal_override',
    '_overlay_identity',

    'normalizeGlobalItemMedia',
] as $needle) {

    if (
        !str_contains(
            $runtime,
            $needle
        )
    ) {
        throw new RuntimeException(
            'portal_landing_runtime_marker_missing:'
            . $needle
        );
    }
}


/*
 * Global content must remain sourced from Core.
 */
foreach ([
    'public_page_settings',
    'public_page_items',
] as $coreTable) {

    if (
        !str_contains(
            $runtime,
            $coreTable
        )
    ) {
        throw new RuntimeException(
            'global_landing_baseline_missing:'
            . $coreTable
        );
    }
}


/*
 * No environment Host hardcoding in content overlay.
 */
foreach ([
    'ticketing-dev.troca.ir',
    'ticketing.troca.ir',
] as $host) {

    if (
        str_contains(
            $migration,
            $host
        )
        || str_contains(
            $runtime,
            $host
        )
    ) {
        throw new RuntimeException(
            'portal_landing_environment_host_hardcode_forbidden'
        );
    }
}


/*
 * Registry order:
 *
 * Portal Binding Foundation
 *      ->
 * Portal Landing Content Override Foundation
 */
$r3 =
    '\\IPKF\\Database\\Migrations\\'
    . 'CreateTicketingRealmPortalBindingFoundation'
    . '::class';


$d1 =
    '\\IPKF\\Database\\Migrations\\'
    . 'CreateTicketingPortalLandingContentOverrideFoundation'
    . '::class';


if (
    substr_count(
        $registry,
        $r3
    ) !== 1
    ||
    substr_count(
        $registry,
        $d1
    ) !== 1
) {
    throw new RuntimeException(
        'portal_landing_registry_marker_invalid'
    );
}


$r3Position =
    strpos(
        $registry,
        $r3
    );


$d1Position =
    strpos(
        $registry,
        $d1
    );


if (
    $r3Position === false
    || $d1Position === false
    || $r3Position >= $d1Position
) {
    throw new RuntimeException(
        'portal_landing_registry_order_invalid'
    );
}


echo
    'TICKETING_PORTAL_LANDING_CONTENT_OVERRIDE_FOUNDATION_PASS'
    . PHP_EOL;


/*
 * TICKETING_PORTAL_ITEM_MEDIA_SCOPE_CONTRACT_V1
 *
 * Core-owned media may be rewritten to the Core public host.
 * Portal-owned overlay media must remain Portal-local.
 */
if (
    !str_contains(
        $runtime,
        'TICKETING_PORTAL_ITEM_MEDIA_SCOPE_V1'
    )
) {
    throw new RuntimeException(
        'portal_item_media_scope_contract_missing'
    );
}


echo
    'TICKETING_PORTAL_ITEM_MEDIA_SCOPE_CONTRACT_PASS'
    . PHP_EOL;

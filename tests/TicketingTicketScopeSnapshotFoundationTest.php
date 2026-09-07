<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$migrationPath =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateTicketingTicketScopeSnapshotFoundation.php';

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
        'source_read_failed'
    );
}

$required = [
    'TICKETING_TICKET_SCOPE_SNAPSHOT_FOUNDATION_V1',

    'ticketing_ticket_scope_snapshots',
    'ticketing_ticket_scope_snapshot_dimensions',
    'ticketing_ticket_scope_snapshot_values',
    'ticketing_ticket_scope_states',

    'ticketing_ticket_scope_snapshots_ticket_version_unique',
    'ticketing_ticket_scope_snapshots_ticket_identity_unique',

    'ticketing_ticket_scope_snapshot_values_snapshot_dimension_fk',
    'ticketing_ticket_scope_snapshot_values_dimension_value_fk',

    'ticketing_ticket_scope_states_snapshot_fk',

    'version_no',
    'capture_reason_code',
    'current_snapshot_id',
    'current_version_no',

    'dimension_reference_snapshot',
    'dimension_code_snapshot',
    'dimension_title_snapshot',

    'value_reference_snapshot',
    'value_title_snapshot',
];

foreach ($required as $marker) {
    if (!str_contains($migration, $marker)) {
        throw new RuntimeException(
            'contract_marker_missing:'
            . $marker
        );
    }
}

$registryMarker =
    '\\IPKF\\Database\\Migrations\\'
    . 'CreateTicketingTicketScopeSnapshotFoundation::class,';

if (
    substr_count(
        $registry,
        $registryMarker
    ) !== 1
) {
    throw new RuntimeException(
        'registry_contract_invalid'
    );
}

$withoutComments =
    preg_replace(
        '~/\*.*?\*/~s',
        '',
        $migration
    );

if (!is_string($withoutComments)) {
    throw new RuntimeException(
        'comment_strip_failed'
    );
}

/*
 * Existing Ticketing tables cannot be altered.
 */
if (
    preg_match(
        '/\bALTER\s+TABLE\b/i',
        $withoutComments
    ) === 1
) {
    throw new RuntimeException(
        'alter_existing_table_forbidden'
    );
}

/*
 * Foundation migration contains no DML.
 */
foreach ([
    '/\bINSERT\s+INTO\b/i',
    '/\bDELETE\s+FROM\b/i',
    '/\bREPLACE\s+INTO\b/i',
] as $pattern) {
    if (
        preg_match(
            $pattern,
            $withoutComments
        ) === 1
    ) {
        throw new RuntimeException(
            'migration_dml_forbidden:'
            . $pattern
        );
    }
}

/*
 * Generic only.
 */
foreach ([
    'np',
    'nep',
    'province',
    'county',
    'affiliation',
    'national_union',
] as $token) {
    if (
        preg_match(
            '/[\'"]'
            . preg_quote(
                $token,
                '/'
            )
            . '[\'"]/i',
            $withoutComments
        ) === 1
    ) {
        throw new RuntimeException(
            'business_specific_token:'
            . $token
        );
    }
}

if (
    preg_match(
        '/FOREIGN\s+KEY\s*\(\s*ticket_id\s*,\s*current_snapshot_id\s*,\s*current_version_no\s*\)/is',
        $withoutComments
    ) !== 1
) {
    throw new RuntimeException(
        'current_snapshot_identity_fk_missing'
    );
}

if (
    preg_match(
        '/FOREIGN\s+KEY\s*\(\s*snapshot_id\s*,\s*dimension_id\s*\)/is',
        $withoutComments
    ) !== 1
) {
    throw new RuntimeException(
        'snapshot_dimension_fk_missing'
    );
}

echo
    "TICKETING_TICKET_SCOPE_SNAPSHOT_FOUNDATION_PASS"
    . PHP_EOL;

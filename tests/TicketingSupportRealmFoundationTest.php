<?php

declare(strict_types=1);

$migration =
    __DIR__
    . '/../public_html/system/Database/Migrations/'
    . 'CreateTicketingSupportRealmFoundation.php';

if (!is_file($migration)) {
    throw new RuntimeException(
        'Realm migration missing.'
    );
}

$source =
    file_get_contents(
        $migration
    );

if (!is_string($source)) {
    throw new RuntimeException(
        'Could not read Realm migration.'
    );
}


$required = [
    'TICKETING_SUPPORT_REALM_FOUNDATION_V1',

    'ticketing_support_realms',

    'public_reference',
    'project_id',
    'code',
    'title',
    'status',
    'archived_at',

    'ticketing_support_realms_reference_unique',
    'ticketing_support_realms_project_code_unique',
    'ticketing_support_realms_project_identity_unique',
    'ticketing_support_realms_project_status_index',
    'ticketing_support_realms_project_fk',

    'default_realm_id',

    'ticketing_support_projects_default_realm_index',
    'ticketing_support_projects_default_realm_identity_index',
    'ticketing_support_projects_default_realm_fk',

    'FOREIGN KEY',
    'id,',
    'default_realm_id',

    'project_id,',
    'id',

    'ticketing-default-realm:',
    "'default'",

    'p.updated_at =',
    'p.updated_at',

    'Non-destructive by design.',
];


foreach ($required as $marker) {

    if (
        !str_contains(
            $source,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Missing Realm marker: '
            . $marker
        );
    }
}


/*
 * Canonical default ownership is Project.default_realm_id.
 * Do not create competing is_default semantics.
 */
if (
    str_contains(
        $source,
        'is_default'
    )
) {
    throw new RuntimeException(
        'Realm must not introduce is_default.'
    );
}


/*
 * Phase boundary:
 *
 * R1 creates only Realm catalog + Project default pointer.
 * Operational topology becomes Realm-aware in R2.
 */
$forbiddenAlterTargets = [
    'ticketing_support_layers',
    'ticketing_support_nodes',
    'ticketing_support_node_relations',
    'ticketing_support_teams',
    'ticketing_support_team_nodes',
    'ticketing_support_queues',
    'ticketing_support_team_queues',
    'ticketing_support_team_members',

    'ticketing_tickets',
    'ticketing_assignments',

    'ticketing_scope_dimensions',
    'ticketing_scope_subject_facts',

    'ticketing_access_grants',
    'ticketing_access_grant_dimension_rules',
    'ticketing_access_grant_dimension_values',
    'ticketing_access_grant_resource_rules',
    'ticketing_access_grant_resource_values',
];


foreach (
    $forbiddenAlterTargets
    as $table
) {
    if (
        preg_match(
            '/ALTER\s+TABLE\s+'
            . preg_quote(
                $table,
                '/'
            )
            . '\b/i',
            $source
        ) === 1
    ) {
        throw new RuntimeException(
            'Realm R1 must not ALTER: '
            . $table
        );
    }
}


/*
 * Portal and Storage remain independent future bindings.
 */
$forbiddenFoundationMarkers = [
    'ticketing_support_portals',
    'ticketing_realm_portal_bindings',
    'ticketing_storage_bindings',
    'storage_mode_code',
    'portal_id',
    'storage_profile_id',
];


foreach (
    $forbiddenFoundationMarkers
    as $marker
) {
    if (
        str_contains(
            $source,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Premature Realm concern: '
            . $marker
        );
    }
}


/*
 * No NP-specific configuration belongs in the generic foundation.
 */
if (
    preg_match(
        '/[\'"](?:np|nep)[\'"]/i',
        $source
    ) === 1
) {
    throw new RuntimeException(
        'Business-specific Realm seed detected.'
    );
}


echo
    "TICKETING_SUPPORT_REALM_FOUNDATION_CONTRACT_PASS"
    . PHP_EOL;

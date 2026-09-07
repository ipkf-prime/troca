<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$file =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateTicketingDynamicScopeDimensionFoundation.php';

$source =
    file_get_contents(
        $file
    );

if (!is_string($source)) {
    throw new RuntimeException(
        'dimension_migration_unreadable'
    );
}


$required = [
    'TICKETING_DYNAMIC_SCOPE_DIMENSION_FOUNDATION_V1',

    'ticketing_scope_dimensions',
    'ticketing_scope_dimension_values',
    'ticketing_scope_dimension_value_paths',

    'project_id',
    'value_kind_code',
    'cardinality_code',
    'hierarchy_mode_code',

    'source_mode_code',
    'source_key',
    'source_config_json',

    'supports_descendants',

    'dimension_id',
    'parent_value_id',
    'value_reference',
    'source_reference',

    'ancestor_value_id',
    'descendant_value_id',
    'depth',

    "DEFAULT 'managed'",
    "DEFAULT 'single'",
    "DEFAULT 'flat'",

    'ON DELETE RESTRICT',
];

foreach ($required as $marker) {

    if (
        !str_contains(
            $source,
            $marker
        )
    ) {
        throw new RuntimeException(
            'dimension_contract_missing:'
            . $marker
        );
    }
}


/*
 * The foundation must remain business-neutral.
 * Concrete project semantics are configuration, not schema.
 */
$forbiddenPatterns = [
    "/['\"]province['\"]/i",
    "/['\"]county['\"]/i",
    "/['\"]national_union['\"]/i",
    "/['\"]affiliation['\"]/i",
    "/['\"]np['\"]/i",
    "/['\"]nep['\"]/i",
];

foreach ($forbiddenPatterns as $pattern) {

    if (
        preg_match(
            $pattern,
            $source
        ) === 1
    ) {
        throw new RuntimeException(
            'business_specific_scope_found:'
            . $pattern
        );
    }
}


/*
 * This migration must not mutate existing member-scope,
 * participant, ticket or Core RBAC structures.
 */
$forbiddenMutations = [
    'ALTER TABLE ticketing_support_project_member_scopes',
    'ALTER TABLE ticketing_participants',
    'ALTER TABLE ticketing_tickets',
    'ALTER TABLE role_assignment_scopes',
    'ALTER TABLE access_scope_types',
];

foreach ($forbiddenMutations as $mutation) {

    if (
        stripos(
            $source,
            $mutation
        ) !== false
    ) {
        throw new RuntimeException(
            'existing_foundation_mutation_found:'
            . $mutation
        );
    }
}


echo
    "TICKETING_DYNAMIC_SCOPE_DIMENSION_FOUNDATION_PASS"
    . PHP_EOL;

<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$file =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateTicketingScopeSubjectFactsFoundation.php';

$source =
    file_get_contents($file);

if (!is_string($source)) {
    throw new RuntimeException(
        'subject_fact_migration_unreadable'
    );
}

$required = [
    'TICKETING_SCOPE_SUBJECT_FACTS_FOUNDATION_V1',
    'ticketing_scope_subject_facts',

    'dimension_id',
    'subject_type_code',
    'subject_reference',
    'dimension_value_id',

    'fact_source_code',
    'source_reference',

    'is_primary',
    'status',
    'valid_from',
    'valid_until',
    'metadata_json',

    'ticketing_scope_subject_facts_subject_index',
    'ticketing_scope_subject_facts_dimension_index',
    'ticketing_scope_subject_facts_exact_index',
    'ticketing_scope_subject_facts_validity_index',
    'ticketing_scope_subject_facts_source_index',

    'ticketing_scope_dimensions(id)',
    'ticketing_scope_dimension_values',

    'ON DELETE RESTRICT',
    'ON UPDATE RESTRICT',
];

foreach ($required as $marker) {
    if (!str_contains($source, $marker)) {
        throw new RuntimeException(
            'subject_fact_contract_missing:'
            . $marker
        );
    }
}


/*
 * NP/business semantics must remain configuration only.
 */
$forbiddenPatterns = [
    "/['\"]province['\"]/i",
    "/['\"]county['\"]/i",
    "/['\"]national_union['\"]/i",
    "/['\"]affiliation['\"]/i",
    "/['\"]np['\"]/i",
    "/['\"]nep['\"]/i",
    "/تشکل/u",
    "/استان/u",
    "/شهرستان/u",
];

foreach ($forbiddenPatterns as $pattern) {
    if (preg_match($pattern, $source) === 1) {
        throw new RuntimeException(
            'business_specific_fact_found:'
            . $pattern
        );
    }
}


/*
 * Existing identity/member/ticket models must be reused.
 */
$forbiddenMutations = [
    'ALTER TABLE ticketing_participants',
    'ALTER TABLE ticketing_support_project_members',
    'ALTER TABLE ticketing_tickets',
    'CREATE TABLE IF NOT EXISTS ticketing_organizations',
    'CREATE TABLE ticketing_organizations',
];

foreach ($forbiddenMutations as $mutation) {
    if (stripos($source, $mutation) !== false) {
        throw new RuntimeException(
            'parallel_or_existing_model_mutation:'
            . $mutation
        );
    }
}


/*
 * Strip PHP block comments before checking actual SQL syntax.
 * The previous test matched documentation text saying
 * "not an ENUM/CHECK".
 */
$codeOnly =
    preg_replace(
        '~/\*.*?\*/~s',
        '',
        $source
    );

if (!is_string($codeOnly)) {
    throw new RuntimeException(
        'comment_strip_failed'
    );
}


/*
 * subject_type_code must remain extensible.
 */
if (
    preg_match(
        '/subject_type_code\s+(?:ENUM|SET)\s*\(/i',
        $codeOnly
    ) === 1
) {
    throw new RuntimeException(
        'subject_type_enum_not_extensible'
    );
}

if (
    preg_match(
        '/CHECK\s*\([^)]*subject_type_code[^)]*\)/is',
        $codeOnly
    ) === 1
) {
    throw new RuntimeException(
        'subject_type_check_not_extensible'
    );
}

echo
    "TICKETING_SCOPE_SUBJECT_FACTS_FOUNDATION_PASS"
    . PHP_EOL;

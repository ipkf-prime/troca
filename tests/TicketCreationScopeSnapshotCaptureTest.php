<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$routingPath =
    $root
    . '/public_html/app/Repositories/'
    . 'TicketCreateRoutingRepository.php';

$snapshotPath =
    $root
    . '/public_html/app/Repositories/'
    . 'TicketScopeSnapshotRepository.php';

$routing =
    file_get_contents(
        $routingPath
    );

$snapshot =
    file_get_contents(
        $snapshotPath
    );

if (
    !is_string($routing)
    || !is_string($snapshot)
) {
    throw new RuntimeException(
        'a5l5b_source_read_failed'
    );
}


$requiredSnapshotMarkers = [
    'TICKETING_TICKET_CREATED_SCOPE_CAPTURE_REPOSITORY_V1',
    'captureCreatedTicket',
    'ticket_scope_capture_requires_active_transaction',

    'ticketing_ticket_scope_snapshots',
    'ticketing_ticket_scope_snapshot_dimensions',
    'ticketing_ticket_scope_snapshot_values',
    'ticketing_ticket_scope_states',

    'ticketing_scope_dimensions',
    'ticketing_scope_subject_facts',
    'ticketing_scope_dimension_values',

    'requester_participant_id',
    'requester_organization_reference',

    "d.project_id = ?",
    "d.status = 'active'",
    "v.status = 'active'",
    "f.status = 'active'",

    'f.valid_from IS NULL',
    'f.valid_from <= UTC_TIMESTAMP()',
    'f.valid_until IS NULL',
    'f.valid_until > UTC_TIMESTAMP()',

    'ticket_scope_single_cardinality_conflict',

    "'ticket_created'",
    'version_no',
    'current_snapshot_id',
    'current_version_no',
];

foreach (
    $requiredSnapshotMarkers
    as $marker
) {
    if (
        !str_contains(
            $snapshot,
            $marker
        )
    ) {
        throw new RuntimeException(
            'snapshot_contract_marker_missing:'
            . $marker
        );
    }
}


/*
 * Snapshot capture owns no transaction boundary.
 */
if (
    str_contains(
        $snapshot,
        '->beginTransaction()'
    )
    ||
    str_contains(
        $snapshot,
        '->commit()'
    )
    ||
    str_contains(
        $snapshot,
        '->rollBack()'
    )
) {
    throw new RuntimeException(
        'snapshot_repository_must_not_own_transaction'
    );
}


/*
 * Capture context must come from persisted Ticket identity.
 */
if (
    !preg_match(
        '/FROM\s+ticketing_tickets\s+t.*WHERE\s+t\.id\s*=\s*\?/is',
        $snapshot
    )
) {
    throw new RuntimeException(
        'persisted_ticket_context_not_canonical'
    );
}


/*
 * Participant display/organization title strings cannot become
 * identity references.
 */
foreach ([
    'requester_display_name_snapshot',
    'requester_organization_snapshot',
] as $forbiddenIdentity) {

    if (
        preg_match(
            '/subject_reference.*'
            . preg_quote(
                $forbiddenIdentity,
                '/'
            )
            . '/is',
            $snapshot
        )
    ) {
        throw new RuntimeException(
            'display_snapshot_used_as_scope_identity:'
            . $forbiddenIdentity
        );
    }
}


/*
 * No authorization/grant evaluation in A5L5-B.
 */
foreach ([
    'TicketingAccessGrantEvaluator',
    'ticketing_access_grants',
    'visibleNodeClause',
    'can_takeover',
    'can_transfer',
    'can_assign',
] as $forbiddenAuthorization) {

    if (
        str_contains(
            $snapshot,
            $forbiddenAuthorization
        )
    ) {
        throw new RuntimeException(
            'authorization_integration_too_early:'
            . $forbiddenAuthorization
        );
    }
}


/*
 * Hook must be between inserted ticket identity and existing commit.
 */
$begin =
    strpos(
        $routing,
        '$this->db->beginTransaction();'
    );

$ticketInsert =
    strpos(
        $routing,
        'INSERT INTO ticketing_tickets',
        $begin === false
            ? 0
            : $begin
    );

$ticketId =
    strpos(
        $routing,
        '$ticketId =',
        $ticketInsert === false
            ? 0
            : $ticketInsert
    );

$hook =
    strpos(
        $routing,
        'TICKETING_TICKET_CREATED_SCOPE_CAPTURE_V1'
    );

$commit =
    strpos(
        $routing,
        '$this->db->commit();',
        $hook === false
            ? 0
            : $hook
    );

if (
    $begin === false
    || $ticketInsert === false
    || $ticketId === false
    || $hook === false
    || $commit === false
    || !(
        $begin
        < $ticketInsert
        && $ticketInsert
        < $ticketId
        && $ticketId
        < $hook
        && $hook
        < $commit
    )
) {
    throw new RuntimeException(
        'capture_hook_transaction_order_invalid'
    );
}


if (
    substr_count(
        $routing,
        'TICKETING_TICKET_CREATED_SCOPE_CAPTURE_V1'
    ) !== 1
) {
    throw new RuntimeException(
        'capture_hook_count_invalid'
    );
}


if (
    !str_contains(
        $routing,
        '$this->db->rollBack();'
    )
) {
    throw new RuntimeException(
        'existing_creation_rollback_missing'
    );
}


/*
 * No business-specific scope configuration.
 */
foreach ([
    'province',
    'county',
    'affiliation',
    'national_union',
    "'np'",
    "'nep'",
] as $token) {

    if (
        stripos(
            $snapshot,
            $token
        ) !== false
    ) {
        throw new RuntimeException(
            'business_specific_capture_logic:'
            . $token
        );
    }
}


echo
    "TICKETING_TICKET_CREATION_SCOPE_SNAPSHOT_CAPTURE_PASS"
    . PHP_EOL;

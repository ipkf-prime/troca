<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$files = [
    'migration' =>
        $root
        . '/public_html/system/Database/Migrations/CreateWorkTicketLifecycleSyncFoundation.php',

    'repository' =>
        $root
        . '/public_html/app/Repositories/WorkTicketLifecycleSyncRepository.php',

    'service' =>
        $root
        . '/public_html/app/Services/Work/WorkTicketLifecycleSyncService.php',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            'MISSING='
            . $name
            . ':'
            . $path
            . PHP_EOL
        );

        exit(1);
    }
}

$migration =
    (string) file_get_contents(
        $files['migration']
    );

$repository =
    (string) file_get_contents(
        $files['repository']
    );

$service =
    (string) file_get_contents(
        $files['service']
    );

$all =
    $migration
    . $repository
    . $service;

$checks = [
    'dynamic_rule_table' =>
        str_contains(
            $migration,
            'work_ticket_lifecycle_sync_rules'
        ),

    'audit_attempt_table' =>
        str_contains(
            $migration,
            'work_ticket_lifecycle_sync_attempts'
        ),

    'idempotency_unique' =>
        str_contains(
            $migration,
            'work_ticket_lifecycle_sync_attempts_idempotency_unique'
        ),

    'activity_event_identity' =>
        str_contains(
            $migration,
            'work_activity_event_id BIGINT UNSIGNED NOT NULL'
        ),

    'no_seeded_mapping' =>
        !preg_match(
            '/INSERT\s+INTO\s+work_ticket_lifecycle_sync_rules/i',
            $migration
        ),

    'rule_lookup_by_resulting_status' =>
        str_contains(
            $repository,
            'public function activeRulesForStatus('
        ),

    'ticket_source_link_lookup' =>
        str_contains(
            $repository,
            'public function ticketSourceLinksForItem('
        ),

    'planner_api' =>
        str_contains(
            $service,
            'public function plansForTransition('
        ),

    'same_status_noop' =>
        str_contains(
            $service,
            '$previousWorkStatusCode'
            . "\n"
            . '            === $resultingWorkStatusCode'
        ),

    'event_based_idempotency' =>
        str_contains(
            $service,
            '(string) $workActivityEventId'
        ),

    'native_action_vocabulary' =>
        str_contains(
            $service,
            "'resolve'"
        )
        && str_contains(
            $service,
            "'close'"
        )
        && str_contains(
            $service,
            "'reopen'"
        ),

    'no_ticket_transition_execution_yet' =>
        !str_contains(
            $service,
            'TicketLifecycleTransitionService'
        )
        && !str_contains(
            $service,
            '->transition('
        ),

    'no_direct_ticket_update' =>
        !preg_match(
            '/UPDATE\s+ticketing_tickets/i',
            $all
        ),
];

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

/*
 * No Work-status name may be embedded as a mapping in this foundation.
 * The actual resulting status comes exclusively from rule data.
 */
if (
    preg_match(
        "/['\"](?:done|cancelled|backlog|planned|in_progress|blocked|review)['\"]\s*=>/i",
        $all
    )
) {
    $failed[] =
        'hardcoded_work_status_mapping';
}

if (
    preg_match(
        '/سامانه نهاده|TSP-NEP|TSVC-NEP|NP-000|WRK-PRJ-D53B/i',
        $all
    )
) {
    $failed[] =
        'customer_project_literal';
}

if ($failed !== []) {
    fwrite(
        STDERR,
        'FAILED='
        . implode(',', $failed)
        . PHP_EOL
    );

    exit(1);
}

echo
    'TICKET_WORK_LIFECYCLE_SYNC_FOUNDATION_TEST=PASS'
    . PHP_EOL;

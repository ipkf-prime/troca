<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$files = [
    'ticket_repository' =>
        $root
        . '/public_html/app/Repositories/TicketLifecycleTransitionRepository.php',

    'executor' =>
        $root
        . '/public_html/app/Services/Work/WorkTicketLifecycleSyncExecutorService.php',
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

$ticket =
    (string) file_get_contents(
        $files['ticket_repository']
    );

$executor =
    (string) file_get_contents(
        $files['executor']
    );

$checks = [
    'native_outer_transaction' =>
        str_contains(
            $ticket,
            '$ownsTransaction ='
        )
        && str_contains(
            $ticket,
            'if ($ownsTransaction) {'
        ),

    'native_event_reference_return' =>
        str_contains(
            $ticket,
            "'event_reference' =>"
        )
        && str_contains(
            $ticket,
            'return $eventReference;'
        ),

    'executor_native_service' =>
        str_contains(
            $executor,
            'TicketLifecycleTransitionService'
        )
        && str_contains(
            $executor,
            '->transition('
        ),

    'executor_shared_resolver' =>
        str_contains(
            $executor,
            'new TicketLifecycleTransitionRepository('
        )
        && str_contains(
            $executor,
            '$resolver'
        ),

    'executor_idempotency_precheck' =>
        str_contains(
            $executor,
            'attemptByIdempotencyKey('
        ),

    'executor_actor_binding' =>
        str_contains(
            $executor,
            'work_ticket_lifecycle_sync_actor_mismatch'
        ),

    'executor_fail_closed_existing_attempt' =>
        str_contains(
            $executor,
            'existingResult('
        ),

    'executor_completed_audit' =>
        str_contains(
            $executor,
            "'result_code' =>"
        )
        && str_contains(
            $executor,
            "'completed'"
        ),

    'executor_no_direct_ticket_sql' =>
        !preg_match(
            '/UPDATE\s+ticketing_tickets|INSERT\s+INTO\s+ticketing_events/i',
            $executor
        ),
];

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

if (
    preg_match(
        '/سامانه نهاده|TSP-NEP|TSVC-NEP|NP-000|WRK-PRJ-D53B/i',
        $ticket . $executor
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
    'TICKET_WORK_LIFECYCLE_SYNC_EXECUTOR_INTEGRATION_TEST=PASS'
    . PHP_EOL;

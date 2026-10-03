<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$files = [
    'repository' =>
        $root
        . '/public_html/app/Repositories/WorkItemRepository.php',

    'service' =>
        $root
        . '/public_html/app/Services/Work/WorkItemService.php',

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

$repository =
    (string) file_get_contents(
        $files['repository']
    );

$service =
    (string) file_get_contents(
        $files['service']
    );

$executor =
    (string) file_get_contents(
        $files['executor']
    );

$checks = [
    'repository_update_with_activity' =>
        str_contains(
            $repository,
            'public function updateWithActivity('
        ),

    'repository_activity_id' =>
        str_contains(
            $repository,
            "'activity_event_id' =>"
        ),

    'repository_caller_transaction' =>
        str_contains(
            $repository,
            '$ownsTransaction ='
        )
        && str_contains(
            $repository,
            'if ($ownsTransaction) {'
        ),

    'record_activity_returns_id' =>
        str_contains(
            $repository,
            'private function recordActivity('
        )
        && str_contains(
            $repository,
            '): int {'
        ),

    'service_status_comparison' =>
        str_contains(
            $service,
            '$previousStatusCode'
        )
        && str_contains(
            $service,
            '$resultingStatusCode'
        )
        && str_contains(
            $service,
            'status_unchanged'
        ),

    'service_planner_hook' =>
        str_contains(
            $service,
            '->plansForTransition('
        ),

    'service_executor_hook' =>
        str_contains(
            $service,
            '->executePlan('
        ),

    'service_best_effort_work_save' =>
        str_contains(
            $service,
            "'executor_error'"
        )
        && str_contains(
            $service,
            "'ok' => true"
        ),

    'service_no_direct_ticket_sql' =>
        !preg_match(
            '/UPDATE\s+ticketing_tickets|INSERT\s+INTO\s+ticketing_events/i',
            $service
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

$all =
    $repository
    . $service
    . $executor;

/*
 * Application source must not encode a Work-status -> Ticket-action mapping.
 * String literals used as form defaults are permitted, but a direct mapping
 * expression such as 'done' => 'resolve' is forbidden.
 */
if (
    preg_match(
        "/['\"](?:done|cancelled|backlog|planned|in_progress|blocked|review)['\"]\s*=>\s*['\"](?:resolve|close|reopen)['\"]/i",
        $all
    )
) {
    $failed[] =
        'hardcoded_status_action_mapping';
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
    'TICKET_WORK_LIFECYCLE_SYNC_WORK_UPDATE_HOOK_TEST=PASS'
    . PHP_EOL;

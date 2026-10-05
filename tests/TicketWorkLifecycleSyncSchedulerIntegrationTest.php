<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$paths = [
    'factory' =>
        $root . '/public_html/app/Scheduler/TicketingSchedulerRegistryFactory.php',
    'job' =>
        $root . '/public_html/app/Scheduler/WorkTicketLifecycleSyncRecoveryJob.php',
    'scheduler_service' =>
        $root . '/public_html/app/Services/Work/WorkTicketLifecycleSyncSchedulerService.php',
    'recovery_service' =>
        $root . '/public_html/app/Services/Work/WorkTicketLifecycleSyncRecoveryService.php',
    'ui' =>
        $root . '/public_html/resources/ui-content/ticket-work-policy-ui.json',
    'interface' =>
        $root . '/public_html/system/Scheduler/SchedulerJobInterface.php',
];

foreach ($paths as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, 'MISSING=' . $name . ':' . $path . PHP_EOL);
        exit(1);
    }
}

$c = [];
foreach ($paths as $name => $path) {
    $c[$name] = (string) file_get_contents($path);
}

$ui = json_decode($c['ui'], true, 512, JSON_THROW_ON_ERROR);
$policy = $ui['scheduler_recovery']['policy'] ?? null;

$checks = [
    'canonical_factory_registration' =>
        str_contains(
            $c['factory'],
            'new WorkTicketLifecycleSyncRecoveryJob()'
        ),
    'canonical_scheduler_interface' =>
        str_contains(
            $c['job'],
            'implements SchedulerJobInterface'
        ),
    'dynamic_title_description_contract' =>
        isset($ui['scheduler_recovery']['title'])
        && isset($ui['scheduler_recovery']['description'])
        && !preg_match('/[\x{0600}-\x{06FF}]/u', $c['job']),
    'dynamic_policy_contract' =>
        is_array($policy)
        && isset(
            $policy['batch_limit'],
            $policy['max_attempts'],
            $policy['base_backoff_seconds'],
            $policy['max_backoff_seconds'],
            $policy['stale_retrying_seconds'],
            $policy['automatic_retry'],
            $policy['automatic_audit_repair']
        ),
    'scheduled_system_actor' =>
        str_contains(
            $c['recovery_service'],
            'public function recoverScheduled('
        )
        && str_contains(
            $c['recovery_service'],
            "'operator_reference'"
        ),
    'fresh_reconciliation_before_mutation' =>
        str_contains(
            $c['recovery_service'],
            'inspectAttempt('
        )
        && str_contains(
            $c['scheduler_service'],
            'inspectAttempt('
        ),
    'exponential_backoff' =>
        str_contains(
            $c['scheduler_service'],
            'public static function backoffDelaySeconds('
        )
        && str_contains(
            $c['scheduler_service'],
            '$delay * 2'
        ),
    'max_attempt_cap' =>
        str_contains(
            $c['scheduler_service'],
            "'max_attempts_reached'"
        ),
    'stale_retry_fail_closed' =>
        str_contains(
            $c['scheduler_service'],
            "'stale_needs_review'"
        )
        && !str_contains(
            $c['scheduler_service'],
            'resetRecovery'
        ),
    'no_direct_ticket_sql' =>
        !preg_match(
            '/UPDATE\s+ticketing_tickets|INSERT\s+INTO\s+ticketing_events/i',
            $c['scheduler_service'] . "\n" . $c['recovery_service']
        ),
];

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

$joined = implode("\n", $c);

if (
    preg_match(
        '/سامانه نهاده|TSP-NEP|TSVC-NEP|NP-000|WRK-PRJ-D53B/i',
        $joined
    )
) {
    $failed[] = 'customer_project_literal';
}

require_once $paths['interface'];
require_once $paths['job'];
require_once $paths['scheduler_service'];

$job = new \App\Scheduler\WorkTicketLifecycleSyncRecoveryJob();

if ($job->key() !== 'ticketing.work.lifecycle_sync_recovery') {
    $failed[] = 'job_key_contract';
}

if ($job->applicationKey() !== 'ticketing') {
    $failed[] = 'job_application_contract';
}

$scopes = $job->scopes();

if (
    count($scopes) !== 1
    || ($scopes[0]['type'] ?? '') !== 'global'
    || !is_array(
        $scopes[0]['context']['recovery_policy']
        ?? null
    )
) {
    $failed[] = 'job_global_scope_contract';
}

$normalized =
    \App\Services\Work\WorkTicketLifecycleSyncSchedulerService
        ::normalizePolicy([
            'base_backoff_seconds' => 300,
            'max_backoff_seconds' => 1000,
        ]);

$delay1 =
    \App\Services\Work\WorkTicketLifecycleSyncSchedulerService
        ::backoffDelaySeconds($normalized, 1);

$delay2 =
    \App\Services\Work\WorkTicketLifecycleSyncSchedulerService
        ::backoffDelaySeconds($normalized, 2);

$delay5 =
    \App\Services\Work\WorkTicketLifecycleSyncSchedulerService
        ::backoffDelaySeconds($normalized, 5);

if (
    $delay1 !== 300
    || $delay2 !== 600
    || $delay5 !== 1000
) {
    $failed[] = 'pure_backoff_contract';
}

if ($failed !== []) {
    fwrite(
        STDERR,
        'FAILED=' . implode(',', $failed) . PHP_EOL
    );
    exit(1);
}

echo 'TICKET_WORK_LIFECYCLE_SYNC_SCHEDULER_INTEGRATION_TEST=PASS' . PHP_EOL;

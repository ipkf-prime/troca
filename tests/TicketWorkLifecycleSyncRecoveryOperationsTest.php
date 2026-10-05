<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'repo' => $root . '/public_html/app/Repositories/WorkTicketLifecycleSyncRepository.php',
    'reconciliation' => $root . '/public_html/app/Services/Work/WorkTicketLifecycleSyncReconciliationService.php',
    'recovery' => $root . '/public_html/app/Services/Work/WorkTicketLifecycleSyncRecoveryService.php',
    'route' => $root . '/public_html/routes/work-settings.php',
    'policy_view' => $root . '/public_html/resources/views/admin/work-ticket-policy.php',
    'recovery_view' => $root . '/public_html/resources/views/admin/work-ticket-lifecycle-recovery.php',
    'ui' => $root . '/public_html/resources/ui-content/ticket-work-policy-ui.json',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, 'MISSING=' . $name . ':' . $path . PHP_EOL);
        exit(1);
    }
}

$c = [];
foreach ($files as $name => $path) {
    $c[$name] = (string) file_get_contents($path);
}

$ui = json_decode($c['ui'], true, 512, JSON_THROW_ON_ERROR);
$checks = [
    'repo_atomic_retry_lease' =>
        str_contains($c['repo'], "result_code = 'retrying'")
        && str_contains($c['repo'], "result_code IN ('pending','failed')")
        && str_contains($c['repo'], 'attempt_count = attempt_count + 1'),

    'repo_exact_audit_repair_guard' =>
        str_contains($c['repo'], 'repairAttemptFromEvidence(')
        && str_contains($c['repo'], "'pending','failed','retrying'"),

    'reconciliation_retrying_fail_closed' =>
        str_contains($c['reconciliation'], "'recovery_in_progress_without_exact_event'")
        && str_contains($c['reconciliation'], "'retrying'"),

    'recovery_native_ticket_service_only' =>
        str_contains($c['recovery'], 'TicketLifecycleTransitionService')
        && str_contains($c['recovery'], '->transition(')
        && !preg_match('/UPDATE\s+ticketing_tickets|INSERT\s+INTO\s+ticketing_events/i', $c['recovery']),

    'recovery_original_actor_binding' =>
        str_contains($c['recovery'], "actor_user_reference")
        && str_contains($c['recovery'], 'actorUserId('),

    'recovery_exact_correlation_reused' =>
        str_contains($c['recovery'], "'correlation_reference' =>")
        && str_contains($c['recovery'], "'idempotency_key' =>")
        && str_contains($c['recovery'], "'attempt_reference' =>"),

    'route_rbac_and_csrf' =>
        str_contains($c['route'], "'work.settings.view'")
        && str_contains($c['route'], "'work.settings.manage'")
        && str_contains($c['route'], 'ticket-work-lifecycle-recovery/{attempt_id}/{operation}')
        && str_contains($c['route'], 'new \\IPKF\\Security\\Csrf()'),

    'ui_copy_data_driven' =>
        isset($ui['page']['recovery_tab'])
        && isset($ui['recovery']['title'])
        && isset($ui['recovery']['action']['retry'])
        && isset($ui['recovery']['action']['repair_audit'])
        && !preg_match('/[\x{0600}-\x{06FF}]/u', $c['recovery_view']),

    'policy_recovery_navigation' =>
        str_contains($c['policy_view'], '/admin/work/settings/ticket-work-lifecycle-recovery')
        && str_contains($c['policy_view'], "page.recovery_tab"),

    'view_admin_layout_contract' =>
        str_contains($c['recovery_view'], 'ob_start();')
        && str_contains($c['recovery_view'], "require __DIR__ . '/layout.php';")
        && str_contains($c['recovery_view'], "recovery.action.retry")
        && str_contains($c['recovery_view'], "recovery.action.repair_audit"),
];

$failed = [];
foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

$joined = implode("\n", $c);
if (preg_match('/سامانه نهاده|TSP-NEP|TSVC-NEP|NP-000|WRK-PRJ-D53B/i', $joined)) {
    $failed[] = 'customer_project_literal';
}

if ($failed !== []) {
    fwrite(STDERR, 'FAILED=' . implode(',', $failed) . PHP_EOL);
    exit(1);
}

echo 'TICKET_WORK_LIFECYCLE_SYNC_RECOVERY_OPERATIONS_TEST=PASS' . PHP_EOL;

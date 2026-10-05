<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$paths = [
    'ticket_repository' =>
        $root
        . '/public_html/app/Repositories/TicketLifecycleTransitionRepository.php',
    'ticket_service' =>
        $root
        . '/public_html/app/Services/Ticketing/TicketLifecycleTransitionService.php',
    'sync_repository' =>
        $root
        . '/public_html/app/Repositories/WorkTicketLifecycleSyncRepository.php',
    'executor' =>
        $root
        . '/public_html/app/Services/Work/WorkTicketLifecycleSyncExecutorService.php',
    'reconciliation' =>
        $root
        . '/public_html/app/Services/Work/WorkTicketLifecycleSyncReconciliationService.php',
];

foreach ($paths as $name => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            'MISSING=' . $name . ':' . $path . PHP_EOL
        );
        exit(1);
    }
}

$contents = [];

foreach ($paths as $name => $path) {
    $contents[$name] =
        (string) file_get_contents($path);
}

$checks = [
    'ticket_event_exact_correlation_payload' =>
        str_contains(
            $contents['ticket_repository'],
            "'integration_context' =>"
        )
        && str_contains(
            $contents['ticket_repository'],
            '$.integration_context.correlation_reference'
        )
        && str_contains(
            $contents['ticket_repository'],
            '$.integration_context.idempotency_key'
        )
        && str_contains(
            $contents['ticket_repository'],
            '$.integration_context.attempt_reference'
        ),

    'ticket_read_only_snapshot' =>
        str_contains(
            $contents['ticket_repository'],
            'public function reconciliationSnapshot('
        )
        && str_contains(
            $contents['ticket_service'],
            'public function reconciliationSnapshot('
        ),

    'executor_correlation_binding' =>
        str_contains(
            $contents['executor'],
            'withLifecycleAuditContext('
        )
        && str_contains(
            $contents['executor'],
            "'attempt_reference' =>"
        )
        && str_contains(
            $contents['executor'],
            "'idempotency_key' =>"
        ),

    'recovery_candidates_pending_failed_only' =>
        str_contains(
            $contents['sync_repository'],
            'public function recoveryCandidates('
        )
        && str_contains(
            $contents['sync_repository'],
            "'pending',"
        )
        && str_contains(
            $contents['sync_repository'],
            "'failed'"
        ),

    'work_current_state_guard' =>
        str_contains(
            $contents['sync_repository'],
            'public function workItemState('
        )
        && str_contains(
            $contents['reconciliation'],
            "'work_status_moved'"
        ),

    'a1_no_automatic_retry' =>
        !str_contains(
            $contents['reconciliation'],
            '->transition('
        )
        && !str_contains(
            $contents['reconciliation'],
            'completeAttempt('
        ),

    'a1_exact_correlation_required_for_applied' =>
        str_contains(
            $contents['reconciliation'],
            "'already_applied'"
        )
        && str_contains(
            $contents['reconciliation'],
            "'target_state_without_exact_correlation'"
        ),

    'native_guards_drive_retryability' =>
        str_contains(
            $contents['reconciliation'],
            "'native_ticket_guard_allows_retry'"
        )
        && str_contains(
            $contents['reconciliation'],
            "'native_ticket_guard_blocks_retry'"
        ),
];

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

$joined = implode("\n", $contents);

if (
    preg_match(
        '/سامانه نهاده|TSP-NEP|TSVC-NEP|NP-000|WRK-PRJ-D53B/i',
        $joined
    )
) {
    $failed[] = 'customer_project_literal';
}

require_once $paths['reconciliation'];

use App\Services\Work\WorkTicketLifecycleSyncReconciliationService;

$baseAttempt = [
    'id' => 101,
    'public_reference' => 'TWLSA-TEST-FOUNDATION',
    'idempotency_key' => 'idem-test-foundation',
    'correlation_reference' => 'corr-test-foundation',
    'work_item_id' => 9,
    'resulting_work_status_code' => 'done',
    'ticket_reference' => 'TICKET-TEST-FOUNDATION',
    'ticket_action_code' => 'resolve',
    'actor_user_reference' => 'user:7',
];

$workState = [
    'id' => 9,
    'archived_at' => null,
    'status_code' => 'done',
];

$retrySnapshot = [
    'found' => true,
    'status_code' => 'in_progress',
    'can_resolve' => true,
    'can_close' => false,
    'can_reopen' => false,
    'correlated_event' => null,
];

$retry =
    WorkTicketLifecycleSyncReconciliationService
        ::classifyEvidence(
            [
                ...$baseAttempt,
                'result_code' => 'failed',
            ],
            $workState,
            $retrySnapshot
        );

if (
    ($retry['decision'] ?? '') !== 'retryable'
    || ($retry['can_retry'] ?? false) !== true
) {
    $failed[] = 'pure_retryable_classification';
}

$alreadyApplied =
    WorkTicketLifecycleSyncReconciliationService
        ::classifyEvidence(
            [
                ...$baseAttempt,
                'result_code' => 'pending',
            ],
            $workState,
            [
                'found' => true,
                'status_code' => 'resolved',
                'can_resolve' => false,
                'can_close' => false,
                'can_reopen' => false,
                'correlated_event' => [
                    'event_code' => 'ticket_resolved',
                    'actor_user_reference' => 'user:7',
                    'resulting_status_code' => 'resolved',
                    'integration_source_module_code' => 'work',
                    'integration_correlation_reference' =>
                        'corr-test-foundation',
                    'integration_idempotency_key' =>
                        'idem-test-foundation',
                    'integration_attempt_reference' =>
                        'TWLSA-TEST-FOUNDATION',
                    'integration_ticket_action_code' => 'resolve',
                ],
            ]
        );

if (
    ($alreadyApplied['decision'] ?? '') !== 'already_applied'
    || ($alreadyApplied['can_repair_audit'] ?? false) !== true
    || ($alreadyApplied['can_retry'] ?? true) !== false
) {
    $failed[] = 'pure_already_applied_classification';
}

$ambiguous =
    WorkTicketLifecycleSyncReconciliationService
        ::classifyEvidence(
            [
                ...$baseAttempt,
                'result_code' => 'failed',
            ],
            $workState,
            [
                'found' => true,
                'status_code' => 'resolved',
                'can_resolve' => false,
                'can_close' => false,
                'can_reopen' => false,
                'correlated_event' => null,
            ]
        );

if (
    ($ambiguous['decision'] ?? '') !== 'needs_review'
    || ($ambiguous['reason'] ?? '')
        !== 'target_state_without_exact_correlation'
) {
    $failed[] = 'pure_ambiguous_target_state_classification';
}

$moved =
    WorkTicketLifecycleSyncReconciliationService
        ::classifyEvidence(
            [
                ...$baseAttempt,
                'result_code' => 'failed',
            ],
            [
                ...$workState,
                'status_code' => 'in_progress',
            ],
            $retrySnapshot
        );

if (
    ($moved['decision'] ?? '') !== 'needs_review'
    || ($moved['reason'] ?? '') !== 'work_status_moved'
) {
    $failed[] = 'pure_work_moved_classification';
}

$terminal =
    WorkTicketLifecycleSyncReconciliationService
        ::classifyEvidence(
            [
                ...$baseAttempt,
                'result_code' => 'rejected',
            ],
            $workState,
            $retrySnapshot
        );

if (
    ($terminal['decision'] ?? '') !== 'terminal_rejected'
    || ($terminal['can_retry'] ?? true) !== false
) {
    $failed[] = 'pure_terminal_rejected_classification';
}

if ($failed !== []) {
    fwrite(
        STDERR,
        'FAILED=' . implode(',', $failed) . PHP_EOL
    );
    exit(1);
}

echo
    'TICKET_WORK_LIFECYCLE_SYNC_RECONCILIATION_FOUNDATION_TEST=PASS'
    . PHP_EOL;

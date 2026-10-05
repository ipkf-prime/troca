<?php

declare(strict_types=1);

namespace App\Services\Work;

use App\Repositories\TicketLifecycleTransitionRepository;
use App\Repositories\WorkTicketLifecycleSyncRepository;
use App\Services\Ticketing\TicketLifecycleTransitionService;
use IPKF\Database\Connections\ConnectionResolver;

/**
 * C4 recovery/reconciliation foundation.
 *
 * A1 is intentionally read-only: it does not retry, mutate attempts,
 * transition Tickets, or repair audit rows.
 */
final class WorkTicketLifecycleSyncReconciliationService
{
    private WorkTicketLifecycleSyncRepository $sync;
    private TicketLifecycleTransitionService $tickets;

    public function __construct(
        ?ConnectionResolver $connections = null,
        ?WorkTicketLifecycleSyncRepository $sync = null,
        ?TicketLifecycleTransitionService $tickets = null
    ) {
        $resolver = $connections ?? new ConnectionResolver();

        $this->sync =
            $sync
            ?? new WorkTicketLifecycleSyncRepository($resolver);

        $this->tickets =
            $tickets
            ?? new TicketLifecycleTransitionService(
                new TicketLifecycleTransitionRepository($resolver)
            );
    }

    public function inspectCandidates(int $limit = 50): array
    {
        $results = [];

        foreach ($this->sync->recoveryCandidates($limit) as $attempt) {
            $results[] = $this->inspectAttempt($attempt);
        }

        return $results;
    }

    public function inspectOperations(int $limit = 100): array
    {
        $results = [];

        foreach ($this->sync->operationsAttempts($limit) as $attempt) {
            $results[] = $this->inspectAttempt($attempt);
        }

        return $results;
    }

    public function inspectAttempt(array $attempt): array
    {
        $workState =
            $this->sync->workItemState(
                (int) ($attempt['work_item_id'] ?? 0)
            );

        $actorUserId =
            self::actorUserId(
                (string) ($attempt['actor_user_reference'] ?? '')
            );

        if ($actorUserId < 1) {
            return self::decision(
                'needs_review',
                'actor_user_reference_invalid',
                false,
                false,
                $attempt,
                $workState,
                [
                    'found' => false,
                    'correlated_event' => null,
                ]
            );
        }

        $ticketSnapshot =
            $this->tickets->reconciliationSnapshot(
                (string) ($attempt['ticket_reference'] ?? ''),
                $actorUserId,
                (string) ($attempt['correlation_reference'] ?? '')
            );

        return self::classifyEvidence(
            $attempt,
            $workState,
            $ticketSnapshot
        );
    }

    public static function classifyEvidence(
        array $attempt,
        ?array $workState,
        array $ticketSnapshot
    ): array {
        $resultCode =
            strtolower(
                trim((string) ($attempt['result_code'] ?? ''))
            );

        if ($resultCode === 'completed') {
            return self::decision(
                'already_completed',
                'attempt_terminal_completed',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        if ($resultCode === 'rejected') {
            return self::decision(
                'terminal_rejected',
                'attempt_terminal_rejected',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        $recoveryInProgress =
            $resultCode === 'retrying';

        if (!in_array($resultCode, ['pending', 'failed', 'retrying'], true)) {
            return self::decision(
                'needs_review',
                'attempt_result_code_unsupported',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        if (!is_array($workState)) {
            return self::decision(
                'needs_review',
                'work_item_missing',
                false,
                false,
                $attempt,
                null,
                $ticketSnapshot
            );
        }

        if (!empty($workState['archived_at'])) {
            return self::decision(
                'needs_review',
                'work_item_archived',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        $expectedWorkStatus =
            trim(
                (string) (
                    $attempt['resulting_work_status_code']
                    ?? ''
                )
            );

        $currentWorkStatus =
            trim(
                (string) ($workState['status_code'] ?? '')
            );

        if (
            $expectedWorkStatus === ''
            || $currentWorkStatus === ''
            || !hash_equals(
                $expectedWorkStatus,
                $currentWorkStatus
            )
        ) {
            return self::decision(
                'needs_review',
                'work_status_moved',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        if (($ticketSnapshot['found'] ?? false) !== true) {
            return self::decision(
                'needs_review',
                'ticket_not_found',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        $action =
            strtolower(
                trim(
                    (string) ($attempt['ticket_action_code'] ?? '')
                )
            );

        $contracts = [
            'resolve' => [
                'event_code' => 'ticket_resolved',
                'resulting_status_code' => 'resolved',
                'capability' => 'can_resolve',
            ],
            'close' => [
                'event_code' => 'ticket_closed',
                'resulting_status_code' => 'closed',
                'capability' => 'can_close',
            ],
            'reopen' => [
                'event_code' => 'ticket_reopened',
                'resulting_status_code' => 'in_progress',
                'capability' => 'can_reopen',
            ],
        ];

        if (!isset($contracts[$action])) {
            return self::decision(
                'needs_review',
                'ticket_action_invalid',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        $contract = $contracts[$action];
        $event = $ticketSnapshot['correlated_event'] ?? null;

        if (is_array($event)) {
            $eventMatches =
                trim((string) ($event['event_code'] ?? ''))
                    === $contract['event_code']
                && trim(
                    (string) (
                        $event['resulting_status_code']
                        ?? ''
                    )
                ) === $contract['resulting_status_code']
                && trim(
                    (string) (
                        $event['actor_user_reference']
                        ?? ''
                    )
                ) === trim(
                    (string) (
                        $attempt['actor_user_reference']
                        ?? ''
                    )
                )
                && trim(
                    (string) (
                        $event['integration_source_module_code']
                        ?? ''
                    )
                ) === 'work'
                && trim(
                    (string) (
                        $event['integration_correlation_reference']
                        ?? ''
                    )
                ) === trim(
                    (string) (
                        $attempt['correlation_reference']
                        ?? ''
                    )
                )
                && trim(
                    (string) (
                        $event['integration_idempotency_key']
                        ?? ''
                    )
                ) === trim(
                    (string) (
                        $attempt['idempotency_key']
                        ?? ''
                    )
                )
                && trim(
                    (string) (
                        $event['integration_attempt_reference']
                        ?? ''
                    )
                ) === trim(
                    (string) (
                        $attempt['public_reference']
                        ?? ''
                    )
                )
                && trim(
                    (string) (
                        $event['integration_ticket_action_code']
                        ?? ''
                    )
                ) === $action;

            if ($eventMatches) {
                return self::decision(
                    'already_applied',
                    'exact_correlated_ticket_event_found',
                    false,
                    true,
                    $attempt,
                    $workState,
                    $ticketSnapshot
                );
            }

            return self::decision(
                'needs_review',
                'correlated_ticket_event_mismatch',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        $currentTicketStatus =
            trim(
                (string) ($ticketSnapshot['status_code'] ?? '')
            );

        if (
            $currentTicketStatus
            === $contract['resulting_status_code']
        ) {
            return self::decision(
                'needs_review',
                'target_state_without_exact_correlation',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        if ($recoveryInProgress) {
            return self::decision(
                'needs_review',
                'recovery_in_progress_without_exact_event',
                false,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        if (
            ($ticketSnapshot[$contract['capability']] ?? false)
            === true
        ) {
            return self::decision(
                'retryable',
                'native_ticket_guard_allows_retry',
                true,
                false,
                $attempt,
                $workState,
                $ticketSnapshot
            );
        }

        return self::decision(
            'needs_review',
            'native_ticket_guard_blocks_retry',
            false,
            false,
            $attempt,
            $workState,
            $ticketSnapshot
        );
    }

    private static function actorUserId(
        string $actorUserReference
    ): int {
        $actorUserReference = trim($actorUserReference);

        if (
            !preg_match(
                '/^user:([1-9][0-9]*)$/D',
                $actorUserReference,
                $matches
            )
        ) {
            return 0;
        }

        return (int) $matches[1];
    }

    private static function decision(
        string $decision,
        string $reason,
        bool $canRetry,
        bool $canRepairAudit,
        array $attempt,
        ?array $workState,
        array $ticketSnapshot
    ): array {
        return [
            'decision' => $decision,
            'reason' => $reason,
            'can_retry' => $canRetry,
            'can_repair_audit' => $canRepairAudit,
            'attempt_id' => (int) ($attempt['id'] ?? 0),
            'attempt_reference' =>
                (string) ($attempt['public_reference'] ?? ''),
            'correlation_reference' =>
                (string) ($attempt['correlation_reference'] ?? ''),
            'ticket_reference' =>
                (string) ($attempt['ticket_reference'] ?? ''),
            'ticket_action_code' =>
                (string) ($attempt['ticket_action_code'] ?? ''),
            'attempt' => $attempt,
            'work_state' => $workState,
            'ticket_snapshot' => $ticketSnapshot,
        ];
    }
}

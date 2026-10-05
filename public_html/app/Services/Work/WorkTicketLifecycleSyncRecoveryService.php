<?php

declare(strict_types=1);

namespace App\Services\Work;

use App\Repositories\TicketLifecycleTransitionRepository;
use App\Repositories\WorkTicketLifecycleSyncRepository;
use App\Services\Ticketing\TicketLifecycleTransitionService;
use IPKF\Database\Connections\ConnectionResolver;
use Throwable;

final class WorkTicketLifecycleSyncRecoveryService
{
    private WorkTicketLifecycleSyncRepository $sync;
    private WorkTicketLifecycleSyncReconciliationService $reconciliation;
    private TicketLifecycleTransitionService $tickets;

    public function __construct(
        ?ConnectionResolver $connections = null,
        ?WorkTicketLifecycleSyncRepository $sync = null,
        ?WorkTicketLifecycleSyncReconciliationService $reconciliation = null,
        ?TicketLifecycleTransitionService $tickets = null
    ) {
        $resolver = $connections ?? new ConnectionResolver();

        $this->sync = $sync ?? new WorkTicketLifecycleSyncRepository($resolver);
        $this->tickets = $tickets ?? new TicketLifecycleTransitionService(
            new TicketLifecycleTransitionRepository($resolver)
        );
        $this->reconciliation = $reconciliation
            ?? new WorkTicketLifecycleSyncReconciliationService(
                $resolver,
                $this->sync,
                $this->tickets
            );
    }

    public function recover(
        int $attemptId,
        string $operation,
        int $operatorUserId
    ): array {
        $operation = strtolower(trim($operation));

        if ($attemptId < 1 || $operatorUserId < 1) {
            return ['ok' => false, 'status' => 'invalid'];
        }

        return $this->recoverAuthorized(
            $attemptId,
            $operation,
            'user:' . $operatorUserId
        );
    }

    public function recoverScheduled(
        int $attemptId,
        string $operation,
        string $operatorReference
    ): array {
        $operation = strtolower(trim($operation));
        $operatorReference = trim($operatorReference);

        if (
            $attemptId < 1
            || !preg_match(
                '/^system:[A-Za-z0-9_.:-]{1,170}$/D',
                $operatorReference
            )
        ) {
            return ['ok' => false, 'status' => 'invalid'];
        }

        return $this->recoverAuthorized(
            $attemptId,
            $operation,
            $operatorReference
        );
    }

    private function recoverAuthorized(
        int $attemptId,
        string $operation,
        string $operatorReference
    ): array {
        if (!in_array($operation, ['retry', 'repair_audit'], true)) {
            return ['ok' => false, 'status' => 'invalid'];
        }

        $attempt = $this->sync->attemptById($attemptId);
        if (!is_array($attempt)) {
            return ['ok' => false, 'status' => 'not_found'];
        }

        $inspection = $this->reconciliation->inspectAttempt($attempt);

        if ($operation === 'repair_audit') {
            return $this->repairAudit(
                $attempt,
                $inspection,
                $operatorReference
            );
        }

        return $this->retry(
            $attempt,
            $inspection,
            $operatorReference
        );
    }

    private function retry(
        array $attempt,
        array $inspection,
        string $operatorReference
    ): array {
        if (
            ($inspection['decision'] ?? '') !== 'retryable'
            || ($inspection['can_retry'] ?? false) !== true
        ) {
            return ['ok' => false, 'status' => 'not_actionable'];
        }

        $actorUserId = $this->actorUserId(
            (string) ($attempt['actor_user_reference'] ?? '')
        );

        if ($actorUserId < 1) {
            return ['ok' => false, 'status' => 'not_actionable'];
        }

        $attemptId = (int) ($attempt['id'] ?? 0);

        if (!$this->sync->beginRecoveryRetry($attemptId)) {
            return ['ok' => false, 'status' => 'conflict'];
        }

        try {
            $ticketResult = $this->tickets->transition(
                (string) ($attempt['ticket_reference'] ?? ''),
                (string) ($attempt['ticket_action_code'] ?? ''),
                $actorUserId,
                [
                    'lifecycle_sync' => [
                        'source_module_code' => 'work',
                        'correlation_reference' =>
                            (string) ($attempt['correlation_reference'] ?? ''),
                        'idempotency_key' =>
                            (string) ($attempt['idempotency_key'] ?? ''),
                        'attempt_reference' =>
                            (string) ($attempt['public_reference'] ?? ''),
                        'ticket_action_code' =>
                            (string) ($attempt['ticket_action_code'] ?? ''),
                    ],
                ]
            );

            if (($ticketResult['ok'] ?? false) !== true) {
                $error = trim(
                    (string) ($ticketResult['status'] ?? 'ticket_transition_rejected')
                );

                $this->sync->completeRecoveryAttempt(
                    $attemptId,
                    [
                        'result_code' => 'failed',
                        'ticket_previous_status_code' => null,
                        'ticket_resulting_status_code' => null,
                        'ticket_event_reference' => null,
                        'error_code' => $error,
                        'metadata_json' => $this->recoveryMetadata(
                            $attempt,
                            $operatorReference,
                            'retry',
                            $inspection,
                            $ticketResult
                        ),
                        'completed_at' => gmdate('Y-m-d H:i:s'),
                    ]
                );

                return ['ok' => false, 'status' => 'retry_failed'];
            }

            $eventReference = trim(
                (string) ($ticketResult['event_reference'] ?? '')
            );

            if ($eventReference === '') {
                throw new \RuntimeException(
                    'work_ticket_lifecycle_recovery_ticket_event_missing'
                );
            }

            $this->sync->completeRecoveryAttempt(
                $attemptId,
                [
                    'result_code' => 'completed',
                    'ticket_previous_status_code' =>
                        $ticketResult['previous_status_code'] ?? null,
                    'ticket_resulting_status_code' =>
                        $ticketResult['resulting_status_code'] ?? null,
                    'ticket_event_reference' => $eventReference,
                    'error_code' => null,
                    'metadata_json' => $this->recoveryMetadata(
                        $attempt,
                        $operatorReference,
                        'retry',
                        $inspection,
                        $ticketResult
                    ),
                    'completed_at' => gmdate('Y-m-d H:i:s'),
                ]
            );

            return ['ok' => true, 'status' => 'retry_completed'];

        } catch (Throwable $exception) {
            try {
                $this->sync->completeRecoveryAttempt(
                    $attemptId,
                    [
                        'result_code' => 'failed',
                        'ticket_previous_status_code' => null,
                        'ticket_resulting_status_code' => null,
                        'ticket_event_reference' => null,
                        'error_code' => $this->safeErrorCode($exception),
                        'metadata_json' => $this->recoveryMetadata(
                            $attempt,
                            $operatorReference,
                            'retry',
                            $inspection,
                            ['exception_class' => $exception::class]
                        ),
                        'completed_at' => gmdate('Y-m-d H:i:s'),
                    ]
                );
            } catch (Throwable) {
                throw $exception;
            }

            return ['ok' => false, 'status' => 'retry_failed'];
        }
    }

    private function repairAudit(
        array $attempt,
        array $inspection,
        string $operatorReference
    ): array {
        if (
            ($inspection['decision'] ?? '') !== 'already_applied'
            || ($inspection['can_repair_audit'] ?? false) !== true
        ) {
            return ['ok' => false, 'status' => 'not_actionable'];
        }

        $event = $inspection['ticket_snapshot']['correlated_event'] ?? null;

        if (!is_array($event)) {
            return ['ok' => false, 'status' => 'not_actionable'];
        }

        $updated = $this->sync->repairAttemptFromEvidence(
            (int) ($attempt['id'] ?? 0),
            [
                'ticket_previous_status_code' =>
                    $event['previous_status_code'] ?? null,
                'ticket_resulting_status_code' =>
                    $event['resulting_status_code'] ?? null,
                'ticket_event_reference' =>
                    $event['public_reference'] ?? null,
                'metadata_json' => $this->recoveryMetadata(
                    $attempt,
                    $operatorReference,
                    'repair_audit',
                    $inspection,
                    ['correlated_event' => $event]
                ),
            ]
        );

        return $updated
            ? ['ok' => true, 'status' => 'audit_repaired']
            : ['ok' => false, 'status' => 'conflict'];
    }

    private function actorUserId(string $reference): int
    {
        $reference = trim($reference);

        if (!preg_match('/^user:([1-9][0-9]*)$/D', $reference, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function recoveryMetadata(
        array $attempt,
        string $operatorReference,
        string $operation,
        array $inspection,
        array $result
    ): string {
        $metadata = json_decode(
            (string) ($attempt['metadata_json'] ?? ''),
            true
        );

        if (!is_array($metadata)) {
            $metadata = [];
        }

        $history = $metadata['recovery_history'] ?? [];

        if (!is_array($history)) {
            $history = [];
        }

        $history[] = [
            'operation' => $operation,
            'operator_reference' => $operatorReference,
            'operator_user_reference' =>
                str_starts_with($operatorReference, 'user:')
                    ? $operatorReference
                    : null,
            'decision_before' => (string) ($inspection['decision'] ?? ''),
            'reason_before' => (string) ($inspection['reason'] ?? ''),
            'at' => gmdate('Y-m-d H:i:s'),
            'result' => $result,
        ];

        $metadata['recovery_history'] = array_slice($history, -20);

        return json_encode(
            $metadata,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }

    private function safeErrorCode(Throwable $exception): string
    {
        $code = trim($exception->getMessage());

        if ($code === '') {
            return 'lifecycle_recovery_exception';
        }

        return substr(
            preg_replace('/[^A-Za-z0-9_.:-]+/', '_', $code)
                ?? 'lifecycle_recovery_exception',
            0,
            100
        );
    }
}

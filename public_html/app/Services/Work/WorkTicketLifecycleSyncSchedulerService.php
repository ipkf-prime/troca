<?php

declare(strict_types=1);

namespace App\Services\Work;

use App\Repositories\WorkTicketLifecycleSyncRepository;
use DateTimeImmutable;
use DateTimeZone;
use IPKF\Database\Connections\ConnectionResolver;

final class WorkTicketLifecycleSyncSchedulerService
{
    private WorkTicketLifecycleSyncRepository $sync;
    private WorkTicketLifecycleSyncReconciliationService $reconciliation;
    private WorkTicketLifecycleSyncRecoveryService $recovery;

    public function __construct(
        ?ConnectionResolver $connections = null,
        ?WorkTicketLifecycleSyncRepository $sync = null,
        ?WorkTicketLifecycleSyncReconciliationService $reconciliation = null,
        ?WorkTicketLifecycleSyncRecoveryService $recovery = null
    ) {
        $resolver = $connections ?? new ConnectionResolver();

        $this->sync = $sync
            ?? new WorkTicketLifecycleSyncRepository($resolver);

        $this->reconciliation = $reconciliation
            ?? new WorkTicketLifecycleSyncReconciliationService(
                $resolver,
                $this->sync
            );

        $this->recovery = $recovery
            ?? new WorkTicketLifecycleSyncRecoveryService(
                $resolver,
                $this->sync,
                $this->reconciliation
            );
    }

    public function process(
        array $policy,
        string $operatorReference
    ): array {
        $policy = self::normalizePolicy($policy);
        $operatorReference = trim($operatorReference);

        if (!preg_match('/^system:[A-Za-z0-9_.:-]{1,170}$/D', $operatorReference)) {
            throw new \RuntimeException(
                'work_ticket_lifecycle_scheduler_operator_invalid'
            );
        }

        $summary = [
            'scanned' => 0,
            'already_applied' => 0,
            'audit_repaired' => 0,
            'retryable' => 0,
            'retried' => 0,
            'retry_completed' => 0,
            'retry_failed' => 0,
            'backoff_wait' => 0,
            'max_attempts_reached' => 0,
            'recovery_in_progress' => 0,
            'stale_needs_review' => 0,
            'needs_review' => 0,
            'not_actionable' => 0,
            'conflict' => 0,
        ];

        $attempts = $this->sync->operationsAttempts(
            $policy['batch_limit']
        );

        foreach ($attempts as $attempt) {
            $summary['scanned']++;

            $inspection = $this->reconciliation->inspectAttempt($attempt);
            $decision = (string) ($inspection['decision'] ?? '');
            $resultCode = strtolower(
                trim((string) ($attempt['result_code'] ?? ''))
            );

            if (
                $decision === 'already_applied'
                && ($inspection['can_repair_audit'] ?? false) === true
            ) {
                $summary['already_applied']++;

                if ($policy['automatic_audit_repair'] !== true) {
                    $summary['not_actionable']++;
                    continue;
                }

                $result = $this->recovery->recoverScheduled(
                    (int) ($attempt['id'] ?? 0),
                    'repair_audit',
                    $operatorReference
                );

                if (($result['status'] ?? '') === 'audit_repaired') {
                    $summary['audit_repaired']++;
                } else {
                    $this->collectRecoveryResult($summary, $result);
                }

                continue;
            }

            if ($resultCode === 'retrying') {
                if (
                    self::isStale(
                        $attempt,
                        $policy['stale_retrying_seconds']
                    )
                ) {
                    $summary['stale_needs_review']++;
                } else {
                    $summary['recovery_in_progress']++;
                }

                continue;
            }

            if (
                $decision !== 'retryable'
                || ($inspection['can_retry'] ?? false) !== true
            ) {
                $summary['needs_review']++;
                continue;
            }

            $summary['retryable']++;

            if ($policy['automatic_retry'] !== true) {
                $summary['not_actionable']++;
                continue;
            }

            $attemptCount = max(
                0,
                (int) ($attempt['attempt_count'] ?? 0)
            );

            if ($attemptCount >= $policy['max_attempts']) {
                $summary['max_attempts_reached']++;
                continue;
            }

            if (!self::backoffElapsed($attempt, $policy)) {
                $summary['backoff_wait']++;
                continue;
            }

            $summary['retried']++;

            $result = $this->recovery->recoverScheduled(
                (int) ($attempt['id'] ?? 0),
                'retry',
                $operatorReference
            );

            if (($result['status'] ?? '') === 'retry_completed') {
                $summary['retry_completed']++;
            } elseif (($result['status'] ?? '') === 'retry_failed') {
                $summary['retry_failed']++;
            } else {
                $this->collectRecoveryResult($summary, $result);
            }
        }

        $summary['policy'] = $policy;

        return $summary;
    }

    public static function normalizePolicy(array $policy): array
    {
        $base = max(
            30,
            min(
                86400,
                (int) ($policy['base_backoff_seconds'] ?? 300)
            )
        );

        $maxBackoff = max(
            $base,
            min(
                604800,
                (int) ($policy['max_backoff_seconds'] ?? 3600)
            )
        );

        return [
            'batch_limit' => max(
                1,
                min(200, (int) ($policy['batch_limit'] ?? 50))
            ),
            'max_attempts' => max(
                1,
                min(20, (int) ($policy['max_attempts'] ?? 4))
            ),
            'base_backoff_seconds' => $base,
            'max_backoff_seconds' => $maxBackoff,
            'stale_retrying_seconds' => max(
                60,
                min(
                    604800,
                    (int) ($policy['stale_retrying_seconds'] ?? 900)
                )
            ),
            'automatic_retry' => filter_var(
                $policy['automatic_retry'] ?? true,
                FILTER_VALIDATE_BOOL
            ),
            'automatic_audit_repair' => filter_var(
                $policy['automatic_audit_repair'] ?? true,
                FILTER_VALIDATE_BOOL
            ),
        ];
    }

    public static function backoffDelaySeconds(
        array $policy,
        int $attemptCount
    ): int {
        $policy = self::normalizePolicy($policy);
        $attemptCount = max(0, min(20, $attemptCount));

        $delay = $policy['base_backoff_seconds'];
        $exponent = max(0, $attemptCount - 1);

        for ($i = 0; $i < $exponent; $i++) {
            if ($delay >= $policy['max_backoff_seconds']) {
                break;
            }

            $delay = min(
                $policy['max_backoff_seconds'],
                $delay * 2
            );
        }

        return $delay;
    }

    private static function backoffElapsed(
        array $attempt,
        array $policy
    ): bool {
        $anchor = trim(
            (string) (
                $attempt['last_attempted_at']
                ?? $attempt['created_at']
                ?? ''
            )
        );

        $timestamp = self::utcTimestamp($anchor);

        if ($timestamp === null) {
            return false;
        }

        $delay = self::backoffDelaySeconds(
            $policy,
            (int) ($attempt['attempt_count'] ?? 0)
        );

        return time() >= $timestamp + $delay;
    }

    private static function isStale(
        array $attempt,
        int $staleSeconds
    ): bool {
        $anchor = trim(
            (string) (
                $attempt['last_attempted_at']
                ?? $attempt['updated_at']
                ?? ''
            )
        );

        $timestamp = self::utcTimestamp($anchor);

        if ($timestamp === null) {
            return true;
        }

        return time() >= $timestamp + max(60, $staleSeconds);
    }

    private static function utcTimestamp(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        try {
            return (
                new DateTimeImmutable(
                    $value,
                    new DateTimeZone('UTC')
                )
            )->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    private function collectRecoveryResult(
        array &$summary,
        array $result
    ): void {
        $status = (string) ($result['status'] ?? '');

        if ($status === 'conflict') {
            $summary['conflict']++;
            return;
        }

        if (
            $status === 'not_actionable'
            || $status === 'invalid'
            || $status === 'not_found'
        ) {
            $summary['not_actionable']++;
        }
    }
}

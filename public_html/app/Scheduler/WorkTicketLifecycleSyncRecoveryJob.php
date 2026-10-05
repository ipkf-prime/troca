<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Services\Work\WorkTicketLifecycleSyncSchedulerService;
use IPKF\Scheduler\SchedulerJobInterface;
use RuntimeException;

final class WorkTicketLifecycleSyncRecoveryJob
    implements SchedulerJobInterface
{
    private const JOB_KEY = 'ticketing.work.lifecycle_sync_recovery';
    private const SCOPE_REFERENCE = 'work-ticket-lifecycle-sync-recovery';

    public function key(): string
    {
        return self::JOB_KEY;
    }

    public function applicationKey(): string
    {
        return 'ticketing';
    }

    public function title(): string
    {
        return (string) ($this->contract()['title'] ?? '');
    }

    public function description(): string
    {
        return (string) ($this->contract()['description'] ?? '');
    }

    public function scopeModel(): string
    {
        return 'global';
    }

    public function defaultIntervalMinutes(): int
    {
        $policy = $this->policy();

        return max(
            1,
            min(
                1440,
                (int) ($policy['schedule_interval_minutes'] ?? 5)
            )
        );
    }

    public function scopes(): array
    {
        $contract = $this->contract();

        return [[
            'type' => 'global',
            'reference' => self::SCOPE_REFERENCE,
            'title' => (string) (
                $contract['scope_title']
                ?? self::SCOPE_REFERENCE
            ),
            'context' => [
                'recovery_policy' => $this->policy(),
            ],
        ]];
    }

    public function run(array $context): array
    {
        $scope = is_array($context['scope_context'] ?? null)
            ? $context['scope_context']
            : [];

        $policy = is_array($scope['recovery_policy'] ?? null)
            ? $scope['recovery_policy']
            : [];

        return (
            new WorkTicketLifecycleSyncSchedulerService()
        )->process(
            $policy,
            'system:scheduler:' . self::JOB_KEY
        );
    }

    private function policy(): array
    {
        $policy = $this->contract()['policy'] ?? null;

        if (!is_array($policy)) {
            throw new RuntimeException(
                'ticket_work_scheduler_recovery_policy_missing'
            );
        }

        return $policy;
    }

    private function contract(): array
    {
        $base = defined('BASE_PATH')
            ? (string) BASE_PATH
            : dirname(__DIR__, 2);

        $path = $base
            . '/resources/ui-content/'
            . 'ticket-work-policy-ui.json';

        if (!is_file($path)) {
            throw new RuntimeException(
                'ticket_work_scheduler_recovery_contract_missing'
            );
        }

        $data = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $contract = $data['scheduler_recovery'] ?? null;

        if (
            !is_array($contract)
            || trim((string) ($contract['title'] ?? '')) === ''
            || trim((string) ($contract['description'] ?? '')) === ''
        ) {
            throw new RuntimeException(
                'ticket_work_scheduler_recovery_contract_invalid'
            );
        }

        return $contract;
    }
}

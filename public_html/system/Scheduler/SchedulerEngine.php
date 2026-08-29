<?php

declare(strict_types=1);

namespace IPKF\Scheduler;

use Throwable;

final class SchedulerEngine
{
    public function __construct(
        private string $applicationKey,
        private SchedulerRepository $repository,
        private JobRegistry $registry
    ) {
    }


    public function synchronize(): void
    {
        $this->repository
            ->synchronize(
                $this->registry
            );
    }


    public function runDue(
        int $limit = 20
    ): array {
        $this->synchronize();
        $this->repository
            ->activateUnscheduled();

        $rows =
            $this->repository
                ->due(
                    $this->applicationKey,
                    $limit
                );

        $result = [
            'application' =>
                $this->applicationKey,

            'due' =>
                count($rows),

            'success' => 0,
            'failed' => 0,
            'skipped' => 0,

            'runs' => [],
        ];

        foreach ($rows as $row) {

            $run =
                $this->execute(
                    $row,
                    'automatic',
                    null
                );

            $status =
                (string) (
                    $run['status']
                    ?? 'failed'
                );

            if (
                !in_array(
                    $status,
                    [
                        'success',
                        'failed',
                        'skipped',
                    ],
                    true
                )
            ) {
                $status =
                    'failed';
            }

            $result[$status]++;

            $result['runs'][] =
                $run;
        }

        return
            $result;
    }


    public function runNow(
        int $bindingId,
        ?string $actorReference
    ): array {
        $this->synchronize();

        $binding =
            $this->repository
                ->binding(
                    $bindingId
                );

        if (
            !is_array($binding)
            ||
            (string) (
                $binding['application_key']
                ?? ''
            ) !== $this->applicationKey
        ) {
            return [
                'status' =>
                    'failed',

                'error' =>
                    'scheduler_binding_not_found',
            ];
        }

        if (
            (int) (
                $binding['scope_available']
                ?? 0
            ) !== 1
        ) {
            return [
                'status' =>
                    'failed',

                'error' =>
                    'scheduler_scope_unavailable',
            ];
        }

        return
            $this->execute(
                $binding,
                'manual',
                $actorReference
            );
    }


    private function execute(
        array $binding,
        string $triggerCode,
        ?string $actorReference
    ): array {
        $jobKey =
            (string) (
                $binding['job_key']
                ?? ''
            );

        $job =
            $this->registry
                ->get(
                    $jobKey
                );

        if ($job === null) {
            return [
                'status' =>
                    'failed',

                'job' =>
                    $jobKey,

                'error' =>
                    'scheduler_job_not_registered',
            ];
        }

        $token =
            bin2hex(
                random_bytes(16)
            );

        if (
            !$this->repository
                ->claim(
                    $binding,
                    $triggerCode,
                    $token
                )
        ) {
            return [
                'status' =>
                    'skipped',

                'job' =>
                    $jobKey,

                'binding_id' =>
                    (int) $binding['binding_id'],

                'reason' =>
                    'locked_or_not_runnable',
            ];
        }

        $workerReference =
            (
                gethostname()
                ?: 'worker'
            )
            . ':'
            . getmypid();

        $runId =
            $this->repository
                ->startRun(
                    $binding,
                    $triggerCode,
                    $actorReference,
                    $workerReference
                );

        $startedAt =
            microtime(true);

        $scopeContext =
            json_decode(
                (string) (
                    $binding['scope_context_json']
                    ?? '{}'
                ),
                true
            );

        if (!is_array($scopeContext)) {
            $scopeContext = [];
        }

        try {

            $summary =
                $job->run([
                    'application_key' =>
                        $this->applicationKey,

                    'job_key' =>
                        $jobKey,

                    'binding_id' =>
                        (int) $binding['binding_id'],

                    'scope_type' =>
                        (string) $binding['scope_type'],

                    'scope_reference' =>
                        (string) $binding['scope_reference'],

                    'scope_title' =>
                        (string) (
                            $binding['scope_title_snapshot']
                            ?? ''
                        ),

                    'scope_context' =>
                        $scopeContext,

                    'trigger' =>
                        $triggerCode,

                    'actor_reference' =>
                        $actorReference,
                ]);

            $durationMs =
                (int) round(
                    (
                        microtime(true)
                        - $startedAt
                    )
                    * 1000
                );

            $this->repository
                ->finishSuccess(
                    $runId,
                    (int) $binding['binding_id'],
                    $token,
                    $summary,
                    $durationMs
                );

            return [
                'status' =>
                    'success',

                'job' =>
                    $jobKey,

                'binding_id' =>
                    (int) $binding['binding_id'],

                'summary' =>
                    $summary,
            ];

        } catch (Throwable $exception) {

            $durationMs =
                (int) round(
                    (
                        microtime(true)
                        - $startedAt
                    )
                    * 1000
                );

            $this->repository
                ->finishFailure(
                    $runId,
                    (int) $binding['binding_id'],
                    $token,
                    $exception->getMessage(),
                    $durationMs
                );

            return [
                'status' =>
                    'failed',

                'job' =>
                    $jobKey,

                'binding_id' =>
                    (int) $binding['binding_id'],

                'error' =>
                    $exception->getMessage(),
            ];
        }
    }
}

<?php

declare(strict_types=1);

namespace IPKF\Scheduler;

final class SchedulerControlPlaneService
{
    public function page(): array
    {
        $applications = [];

        $applicationRegistry =
            new SchedulerApplicationRegistry();

        foreach (
            $applicationRegistry->all()
            as $applicationKey => $definition
        ) {
            try {

                $engine =
                    SchedulerRuntime::engine(
                        $applicationKey
                    );

                $engine->synchronize();

                $repository =
                    SchedulerRuntime::repository(
                        $applicationKey
                    );

                $applications[] = [
                    'key' =>
                        $applicationKey,

                    'title' =>
                        (string) (
                            $definition['title']
                            ?? $applicationKey
                        ),

                    'status' =>
                        'ready',

                    'bindings' =>
                        $repository->bindings(
                            $applicationKey
                        ),

                    'runs' =>
                        $repository->recentRuns(
                            $applicationKey,
                            30
                        ),
                ];

            } catch (\Throwable $exception) {

                error_log(
                    'IPKF_SCHEDULER_CONTROL_PLANE '
                    . $applicationKey
                    . ' '
                    . get_class($exception)
                    . ': '
                    . $exception->getMessage()
                );

                $applications[] = [
                    'key' =>
                        $applicationKey,

                    'title' =>
                        (string) (
                            $definition['title']
                            ?? $applicationKey
                        ),

                    'status' =>
                        'unavailable',

                    'bindings' =>
                        [],

                    'runs' =>
                        [],
                ];
            }
        }

        return [
            'applications' =>
                $applications,
        ];
    }


    public function update(
        string $applicationKey,
        int $bindingId,
        array $input,
        ?string $actorReference
    ): bool {
        $applicationKey =
            strtolower(
                trim(
                    $applicationKey
                )
            );

        (new SchedulerApplicationRegistry())
            ->resolve(
                $applicationKey
            );

        $engine =
            SchedulerRuntime::engine(
                $applicationKey
            );

        $engine->synchronize();

        $repository =
            SchedulerRuntime::repository(
                $applicationKey
            );

        $binding =
            $repository->binding(
                $bindingId
            );

        if (
            !is_array($binding)
            ||
            (string) (
                $binding['application_key']
                ?? ''
            ) !== $applicationKey
        ) {
            return false;
        }

        return
            $repository->updateSchedule(
                $bindingId,

                strtolower(
                    trim(
                        (string) (
                            $input['state_code']
                            ?? ''
                        )
                    )
                ),

                strtolower(
                    trim(
                        (string) (
                            $input['schedule_type']
                            ?? 'interval'
                        )
                    )
                ),

                (int) (
                    $input['interval_minutes']
                    ?? 5
                ),

                $actorReference
            );
    }


    public function runNow(
        string $applicationKey,
        int $bindingId,
        ?string $actorReference
    ): array {
        $applicationKey =
            strtolower(
                trim(
                    $applicationKey
                )
            );

        (new SchedulerApplicationRegistry())
            ->resolve(
                $applicationKey
            );

        return
            SchedulerRuntime::engine(
                $applicationKey
            )->runNow(
                $bindingId,
                $actorReference
            );
    }
}

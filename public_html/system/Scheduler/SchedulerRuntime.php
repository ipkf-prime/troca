<?php

declare(strict_types=1);

namespace IPKF\Scheduler;

use IPKF\Database\Connections\ConnectionResolver;
use RuntimeException;

final class SchedulerRuntime
{
    public static function engine(
        string $applicationKey
    ): SchedulerEngine {
        $application =
            (new SchedulerApplicationRegistry())
                ->resolve(
                    $applicationKey
                );

        $factory =
            (string) $application['factory'];

        if (
            !class_exists($factory)
            ||
            !method_exists(
                $factory,
                'make'
            )
        ) {
            throw new RuntimeException(
                'scheduler_registry_factory_invalid'
            );
        }

        $registry =
            $factory::make();

        if (
            !$registry
                instanceof JobRegistry
        ) {
            throw new RuntimeException(
                'scheduler_registry_invalid'
            );
        }

        $db =
            (new ConnectionResolver())
                ->resolve(
                    (string) $application['connection']
                );

        return
            new SchedulerEngine(
                $applicationKey,
                new SchedulerRepository($db),
                $registry
            );
    }


    public static function repository(
        string $applicationKey
    ): SchedulerRepository {
        $application =
            (new SchedulerApplicationRegistry())
                ->resolve(
                    $applicationKey
                );

        $db =
            (new ConnectionResolver())
                ->resolve(
                    (string) $application['connection']
                );

        return
            new SchedulerRepository(
                $db
            );
    }
}

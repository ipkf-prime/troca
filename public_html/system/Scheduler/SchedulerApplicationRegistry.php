<?php

declare(strict_types=1);

namespace IPKF\Scheduler;

use RuntimeException;

final class SchedulerApplicationRegistry
{
    /*
     * Application registration is intentionally
     * code-controlled.
     *
     * Future modules only add another registry
     * declaration + their own Job factory.
     */
    private const APPLICATIONS = [
        'ticketing' => [
            'title' =>
                'تیکتینگ',

            'connection' =>
                'ticketing.primary',

            'factory' =>
                \App\Scheduler\TicketingSchedulerRegistryFactory::class,
        ],
    ];


    public function all(): array
    {
        return
            self::APPLICATIONS;
    }


    public function resolve(
        string $applicationKey
    ): array {
        $applicationKey =
            strtolower(
                trim(
                    $applicationKey
                )
            );

        $definition =
            self::APPLICATIONS[
                $applicationKey
            ]
            ?? null;

        if (!is_array($definition)) {
            throw new RuntimeException(
                'scheduler_application_unknown:'
                . $applicationKey
            );
        }

        return
            $definition;
    }
}

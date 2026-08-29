<?php

declare(strict_types=1);

namespace App\Scheduler;

use IPKF\Scheduler\JobRegistry;

final class TicketingSchedulerRegistryFactory
{
    public static function make(): JobRegistry
    {
        $registry =
            new JobRegistry();

        /*
         * Safe registration:
         * no executable class or command is read
         * from DB or entered by an administrator.
         */
        $registry->register(
            new TicketingSlaJob()
        );

        return
            $registry;
    }
}

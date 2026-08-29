<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $path
    ) use ($root): string {

        $content =
            file_get_contents(
                $root
                . '/'
                . $path
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Unreadable: '
                . $path
            );
        }

        return $content;
    };

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };


$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateSchedulerFoundation.php'
    );

$applicationRegistry =
    $read(
        'public_html/system/Scheduler/'
        . 'SchedulerApplicationRegistry.php'
    );

$repository =
    $read(
        'public_html/system/Scheduler/'
        . 'SchedulerRepository.php'
    );

$engine =
    $read(
        'public_html/system/Scheduler/'
        . 'SchedulerEngine.php'
    );

$control =
    $read(
        'public_html/system/Scheduler/'
        . 'SchedulerControlPlaneService.php'
    );

$adapter =
    $read(
        'public_html/app/Scheduler/'
        . 'TicketingSlaJob.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'system-scheduler.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'system-scheduler.php'
    );

$navigation =
    $read(
        'public_html/app/Services/'
        . 'DynamicAdminNavigationService.php'
    );

$routeLoader =
    $read(
        'public_html/system/Routing/'
        . 'RouteLoader.php'
    );

$slaRepository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketingSlaRepository.php'
    );

$migrationRegistry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


foreach ([
    'scheduler_job_definitions',
    'scheduler_job_bindings',
    'scheduler_schedules',
    'scheduler_job_runs',
] as $table) {

    $expect(
        str_contains(
            $migration,
            $table
        ),
        'Missing Scheduler table: '
        . $table
    );
}


$expect(
    str_contains(
        $applicationRegistry,
        "'ticketing' => ["
    )
    &&
    str_contains(
        $applicationRegistry,
        'TicketingSchedulerRegistryFactory::class'
    ),
    'Application registry incomplete.'
);


$expect(
    str_contains(
        $repository,
        'scope_type'
    )
    &&
    str_contains(
        $repository,
        'scope_reference'
    )
    &&
    str_contains(
        $repository,
        'scope_available'
    )
    &&
    str_contains(
        $engine,
        'runDue('
    )
    &&
    str_contains(
        $engine,
        'runNow('
    ),
    'Generic multi-scope engine incomplete.'
);


$expect(
    str_contains(
        $adapter,
        "'ticketing.sla.evaluate'"
    )
    &&
    str_contains(
        $adapter,
        "'project'"
    )
    &&
    str_contains(
        $adapter,
        'ticketing_support_projects'
    ),
    'Ticketing project adapter incomplete.'
);


$expect(
    str_contains(
        $slaRepository,
        '?int $projectId = null'
    )
    &&
    substr_count(
        $slaRepository,
        '$projectFilter'
    ) >= 4,
    'Ticketing SLA is not project scoped.'
);


$expect(
    str_contains(
        $routes,
        '/admin/system/scheduler'
    )
    &&
    !str_contains(
        $routes,
        '/admin/ticketing/scheduler'
    )
    &&
    str_contains(
        $routes,
        "'access.manage'"
    ),
    'Scheduler control plane is not System Management scoped.'
);


$expect(
    str_contains(
        $view,
        'مدیریت سامانه'
    )
    &&
    str_contains(
        $view,
        'مدیریت اجرای خودکار'
    )
    &&
    str_contains(
        $view,
        'همین الآن اجرا کن'
    ),
    'System Scheduler UI incomplete.'
);


$expect(
    str_contains(
        $navigation,
        "'system-scheduler'"
    )
    &&
    str_contains(
        $navigation,
        "'مدیریت سامانه'"
    ),
    'System Management navigation missing.'
);


$expect(
    str_contains(
        $routeLoader,
        'routes/system-scheduler.php'
    ),
    'System Scheduler route loader missing.'
);


$expect(
    str_contains(
        $control,
        'SchedulerApplicationRegistry'
    )
    &&
    str_contains(
        $control,
        'SchedulerRuntime'
    ),
    'Generic control plane incomplete.'
);


$expect(
    str_contains(
        $migrationRegistry,
        'CreateSchedulerFoundation::class'
    ),
    'Scheduler migration is not registered.'
);


/*
 * Generic layers must not know the current NP project.
 */
foreach ([
    $migration,
    $repository,
    $engine,
    $control,
] as $genericSource) {

    $lower =
        mb_strtolower(
            $genericSource,
            'UTF-8'
        );

    $expect(
        !str_contains(
            $lower,
            'tsp-nep'
        )
        &&
        !str_contains(
            $lower,
            "'np'"
        ),
        'Current project hardcoded into generic Scheduler.'
    );
}


echo
    "Generic System Scheduler structural tests passed.\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static fn (
        string $path
    ): string =>
        file_get_contents(
            $root . '/' . $path
        );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateTicketingSupportProjectFoundation.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'SupportProjectRepository.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

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


foreach ([
    'ticketing_support_projects',
    'ticketing_support_project_members',
    'ticketing_support_services',
    'support_project_id',
    'support_service_id',
    'support_project_title_snapshot',
    'support_service_title_snapshot',
] as $needle) {
    $expect(
        str_contains(
            $migration,
            $needle
        ),
        'T3A1 migration missing: '
        . $needle
    );
}


foreach ([
    'class SupportProjectRepository',
    'public function forUser(',
    'public function projectForUser(',
    'public function servicesForProject(',
    'public function serviceForUser(',
    'm.user_reference = ?',
    'm.left_at IS NULL',
] as $needle) {
    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Support project access contract missing: '
        . $needle
    );
}


$expect(
    !str_contains(
        $repository,
        "visibility_code = 'public'"
    ),
    'Ticketing must not expose public-project bypass.'
);


foreach ([
    'TSP-NEP',
    'TSVC-NEP',
    'TSP-NAP',
    'TSVC-NAP',
    'ensureInitialProject',
    'ensureInitialService',
    'backfillTickets',
    'backfillRequesterMemberships',
    'نهاده پخش',
] as $forbidden) {
    $expect(
        !str_contains(
            $migration,
            $forbidden
        ),
        'Business project hardcode remains: '
        . $forbidden
    );
}


$expect(
    !str_contains(
        $migration,
        'INSERT IGNORE INTO'
        . PHP_EOL
        . '                ticketing_support_projects'
    ),
    'Migration must not create a business support project.'
);


$expect(
    str_contains(
        $registry,
        'CreateTicketingSupportProjectFoundation::class'
    ),
    'T3A1 migration not registered.'
);


echo "TICKETING_MULTI_PROJECT_FOUNDATION_PASS\n";

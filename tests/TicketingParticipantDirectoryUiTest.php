<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static fn (
        string $path
    ): string =>
        file_get_contents(
            $root . '/' . $path
        );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'ParticipantDirectoryRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'ParticipantDirectoryService.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-participants.php'
    );

$projects =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-projects.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$rbac =
    $read(
        'public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );


$navigationMigration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'EnableTicketingParticipantDirectoryNavigation.php'
    );

$migrationRegistry =
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
    "'ticketing.primary'",
    "'core.primary'",
    'public function activeCoreUsers(',
    'public function coreProfilesByUserIds(',
    'public function activeCoreUser(',
    'public function createCoreParticipant(',
    'public function createManualParticipant(',
    'public function duplicateContact(',
    'ticketing_participants',
    'origin_code',
    "'core'",
    "'manual'",
] as $needle) {

    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Repository contract missing: '
        . $needle
    );
}


foreach ([
    'public function page(',
    'public function addCoreUser(',
    'public function addManual(',
    'enrichLinkedCoreProfiles',
    'normalizeEmail',
    'normalizeMobile',
] as $needle) {

    $expect(
        str_contains(
            $service,
            $needle
        ),
        'Service contract missing: '
        . $needle
    );
}


foreach ([
    'مخاطبان تیکتینگ',
    'افزودن از کاربران سامانه',
    'جستجوی کاربران سامانه',
    'نام و نام خانوادگی، نام کاربری، موبایل یا ایمیل',
    'تعریف مخاطب جدید',
    'ورود گروهی از فایل',
    'عضو سامانه',
    'تعریف دستی',
    'ورود از فایل',
] as $needle) {

    $expect(
        str_contains(
            $view,
            $needle
        ),
        'Participant UI contract missing: '
        . $needle
    );
}


$expect(
    str_contains(
        $projects,
        '/admin/ticketing/participants'
    ),
    'Projects page must expose participant directory.'
);


foreach ([
    '/admin/ticketing/participants',
    '/admin/ticketing/participants/core',
    '/admin/ticketing/participants/manual',
    "'core_q'",
    'ParticipantDirectoryService',
] as $needle) {

    $expect(
        str_contains(
            $routes,
            $needle
        ),
        'Route contract missing: '
        . $needle
    );
}


foreach ([
    "'/admin/ticketing/participants'",
    "'/admin/ticketing/participants/core'",
    "'/admin/ticketing/participants/manual'",
    "'ticketing.project.manage'",
] as $needle) {

    $expect(
        str_contains(
            $rbac,
            $needle
        ),
        'RBAC contract missing: '
        . $needle
    );
}


foreach ([
    'TSP-NEP',
    'TSVC-NEP',
    "'np'",
    "'nep'",
    'نهاده پخش',
] as $forbidden) {

    $expect(
        !str_contains(
            $repository,
            $forbidden
        )
        && !str_contains(
            $service,
            $forbidden
        )
        && !str_contains(
            $routes,
            $forbidden
        ),
        'Business hardcode leaked: '
        . $forbidden
    );
}


$expect(
    !preg_match(
        '/\bJOIN\s+(?:`?[A-Za-z0-9_]+`?\.)?`?users`?\b/i',
        $repository
    ),
    'Cross-database participant/user JOIN is forbidden.'
);


$expect(
    !str_contains(
        $view,
        '<th>سازمان</th>'
    ),
    'Organization column must not be visible.'
);

$expect(
    !str_contains(
        $view,
        'name="organization_name"'
    ),
    'Organization field must not be visible.'
);

$expect(
    str_contains(
        $view,
        'ticketing-success-notice'
    ),
    'Success operations must use success styling.'
);


$expect(
    preg_match(
        '/foreach\s*\(\s*'
        . '\$(?:originTitles|stateTitles)'
        . '\s+as\s+\$code\s*=>\s*\$title/s',
        $view
    ) !== 1,
    'Participant filters must not create a page title variable collision.'
);

$expect(
    substr_count(
        $view,
        '$filterTitle'
    ) >= 4,
    'Participant filters must use dedicated filter title variables.'
);



foreach ([
    "'ticketing-participants'",
    "'/admin/ticketing/participants'",
    "'ticketing.project.manage'",
    "'ticketing'",
    "'users'",
] as $needle) {
    $expect(
        str_contains(
            $navigationMigration,
            $needle
        ),
        'Participant navigation migration missing: '
        . $needle
    );
}

$expect(
    str_contains(
        $migrationRegistry,
        'EnableTicketingParticipantDirectoryNavigation::class'
    ),
    'Participant navigation migration is not registered.'
);


echo "TICKETING_PARTICIPANT_DIRECTORY_UI_PASS\n";

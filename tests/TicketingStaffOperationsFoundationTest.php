<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$read =
    static function (
        string $relative
    ) use ($root): string {
        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Cannot read '
                . $relative
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


$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketStaffOperationsRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketStaffOperationsService.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-staff.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'EnableTicketingStaffOperations.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


foreach ([
    'public function cartable(',
    'public function actionContext(',
    'public function takeOver(',
    'public function transfer(',
    'public function escalate(',

    'ticketing_assignments',
    'unassigned_at',

    'ticket_taken_over',
    'ticket_transferred',
    'ticket_escalated',

    'allow_escalation',

    'leastLoadedMember',
    'open_ticket_count',

    'FOR UPDATE',
] as $marker) {

    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Repository marker missing: '
        . $marker
    );
}


foreach ([
    'public function page(',
    'public function takeOver(',
    'public function transfer(',
    'public function escalate(',
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Service marker missing: '
        . $marker
    );
}


foreach ([
    'کارتابل پشتیبانی',
    'قابل رسیدگی',
    'تخصیص‌یافته به من',
    'بدون کارشناس',
    'تحویل گرفتن',
    'انتقال',
    'ارجاع به سطح بالاتر',
] as $marker) {

    $expect(
        str_contains(
            $view,
            $marker
        ),
        'UI marker missing: '
        . $marker
    );
}


$patterns = [
    [
        'GET',
        '/admin/ticketing/staff',
    ],

    [
        'POST',
        '/admin/ticketing/staff/'
        . '{public_reference}/takeover',
    ],

    [
        'POST',
        '/admin/ticketing/staff/'
        . '{public_reference}/transfer',
    ],

    [
        'POST',
        '/admin/ticketing/staff/'
        . '{public_reference}/escalate',
    ],
];


preg_match_all(
    '/\$router->(get|post)\(\s*'
    . "'([^']+)'/",
    $routes,
    $matches,
    PREG_SET_ORDER
);


$declared = [];

foreach ($matches as $match) {

    $key =
        strtoupper(
            $match[1]
        )
        . ' '
        . $match[2];

    $declared[$key] =
        ($declared[$key] ?? 0)
        + 1;
}


foreach ($patterns as [$method, $path]) {

    $key =
        $method
        . ' '
        . $path;

    $expect(
        ($declared[$key] ?? 0)
            === 1,
        'Route declaration mismatch: '
        . $key
    );
}


foreach ([
    'ticketing.staff.cartable.view',
    'ticketing.ticket.takeover',
    'ticketing.ticket.transfer',
    'ticketing.ticket.escalate',

    'ticketing.project.manage',

    'super_admin',

    'ticketing-staff',
    'کارتابل پشتیبانی',

    'role_permissions',
] as $marker) {

    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'RBAC marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $registry,
        'EnableTicketingStaffOperations::class'
    ),
    'A7 migration not registered.'
);


foreach ([
    "'np'",
    'سامانه نهاده',
    'اتحادیه ملی',
    'پشتیبانی مرکزی نپ',
] as $forbidden) {

    $expect(
        !str_contains(
            $repository
            . "\n"
            . $service
            . "\n"
            . $migration,
            $forbidden
        ),
        'Project-specific hardcode leaked: '
        . $forbidden
    );
}


echo
    "TICKETING_STAFF_OPERATIONS_FOUNDATION_PASS\n";

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
    'ticketing.t2.ticketing-staff.ui.230ea3cdf3f7fef6a92c',
    'ticketing.t2.ticketing-staff.ui.86380b0e00701b3eefa6',
    'ticketing.t2.ticketing-staff.ui.f5498dc9efa6256199dd',
    'ticketing.t2.ticketing-staff.ui.7278e1b50731394594b4',
    'ticketing.t2.ticketing-staff.ui.1896adc02cc861ae1fcb',
    'ticketing.t2.ticketing-staff.ui.bce60232b273b14b5dc1',
    '$escalationTooltip',
    'ticketing-icon-action',
    'TicketingIcon::svg',
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



/*
 * T2_TRANSFER_CAPABILITY_AUTHORIZATION_CONTRACT_V1
 *
 * Transfer is governed by can_transfer.
 * can_assign must not implicitly authorize ticket transfer.
 */
$transferRepositorySource =
    file_get_contents(
        __DIR__
        . '/../public_html/app/Repositories/TicketStaffOperationsRepository.php'
    );

$expect(
    is_string($transferRepositorySource),
    'Ticket staff operations repository source could not be loaded.'
);

$transferMethodStart =
    strpos(
        $transferRepositorySource,
        'public function transfer('
    );

$expect(
    $transferMethodStart !== false,
    'Ticket transfer method source contract missing.'
);

$transferMethodTail =
    substr(
        $transferRepositorySource,
        (int) $transferMethodStart
    );

$nextMethodOffset = null;

foreach (
    [
        "\n    public function ",
        "\n    protected function ",
        "\n    private function ",
    ]
    as $nextMethodToken
) {
    $candidateOffset =
        strpos(
            $transferMethodTail,
            $nextMethodToken,
            1
        );

    if (
        $candidateOffset !== false
        &&
        (
            $nextMethodOffset === null
            ||
            $candidateOffset < $nextMethodOffset
        )
    ) {
        $nextMethodOffset =
            $candidateOffset;
    }
}

$transferMethodSource =
    $nextMethodOffset === null
        ? $transferMethodTail
        : substr(
            $transferMethodTail,
            0,
            $nextMethodOffset
        );

$expect(
    substr_count(
        $transferMethodSource,
        "'can_transfer'"
    ) === 1,
    'Ticket transfer must require can_transfer exactly once.'
);

$expect(
    substr_count(
        $transferMethodSource,
        "'can_assign'"
    ) === 0,
    'Ticket transfer must not authorize through can_assign.'
);


echo
    "TICKETING_STAFF_OPERATIONS_FOUNDATION_PASS\n";

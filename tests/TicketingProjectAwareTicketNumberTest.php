<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $text =
            file_get_contents(
                $root . '/' . $relative
            );

        if (!is_string($text)) {
            throw new RuntimeException(
                'Cannot read ' . $relative
            );
        }

        return $text;
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
        . 'CreateTicketingProjectAwareTicketNumber.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$creation =
    $read(
        'public_html/app/Repositories/'
        . 'TicketCreateRoutingRepository.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );


foreach ([
    'ticket_number',
    'ticketing_tickets_number_unique',
    'ticketing_support_projects',
    'project_code',
    'str_pad',
] as $marker) {
    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'Migration marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $registry,
        'CreateTicketingProjectAwareTicketNumber::class'
    ),
    'Ticket-number migration is not registered.'
);


foreach ([
    '$ticketNumber',
    '$ticketPrefix',
    'SET ticket_number = ?',
    "'project_code'",
] as $marker) {
    $expect(
        str_contains(
            $creation,
            $marker
        ),
        'Create persistence marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $repository,
        't.ticket_number'
    ),
    'TicketRepository does not expose ticket_number.'
);


$expect(
    !str_contains(
        $service,
        "\$created['id']"
    ),
    'Post-commit service still dereferences created id.'
);


$expect(
    str_contains(
        $service,
        "\$ticket['ticket_number']"
    ),
    'Stored ticket number is not used by presentation.'
);


$expect(
    !str_contains(
        $routes,
        'کد پیگیری'
    ),
    'Ambiguous error tracking label remains.'
);

$expect(
    str_contains(
        $routes,
        'کد خطا'
    ),
    'Error incident label is missing.'
);


$engine =
    mb_strtolower(
        $migration
        . "\n"
        . $creation,
        'UTF-8'
    );

foreach ([
    'سامانه نهاده',
    'اتحادیه',
    'استان',
    "'np'",
] as $forbidden) {
    $expect(
        !str_contains(
            $engine,
            mb_strtolower(
                $forbidden,
                'UTF-8'
            )
        ),
        'Business hardcode leaked into generic engine: '
        . $forbidden
    );
}


echo
    "TICKETING_PROJECT_AWARE_TICKET_NUMBER_PASS\n";

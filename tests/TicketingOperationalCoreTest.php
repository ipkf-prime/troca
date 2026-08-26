<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$repository = file_get_contents(
    $root
    . '/public_html/app/Repositories/'
    . 'TicketRepository.php'
);

$service = file_get_contents(
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketService.php'
);

$expect = static function (
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
    "resolve('ticketing.primary')",
    'beginTransaction()',
    'commit()',
    'rollBack()',
    'INSERT INTO ticketing_tickets',
    'INSERT INTO ticketing_messages',
    'INSERT INTO ticketing_events',
    "'ticket_created'",
    "'initial'",
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


$expect(
    !str_contains(
        $repository,
        'Database::connect'
    ),
    'Ticket repository must not use Core DB.'
);


foreach ([
    "reference('TKT')",
    "reference('TMSG')",
    "reference('TEVT')",
    "'TKT-'",
    'random_bytes(10)',
    'public function create(',
    'public function detail(',
    'public function index(',
    'public function dashboard(',
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


$expect(
    !str_contains(
        $service,
        'troca_ticketing'
    ),
    'Service must not hardcode DB name.'
);


echo "TICKETING_OPERATIONAL_CORE_PASS\n";

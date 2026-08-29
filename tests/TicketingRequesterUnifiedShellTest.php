<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/public_html/app/Services/'
    . 'AdminPanelService.php';

$text =
    file_get_contents(
        $path
    );

if (!is_string($text)) {
    throw new RuntimeException(
        'AdminPanelService unreadable.'
    );
}


foreach ([
    'REQUESTER_TICKETING_UNIFIED_SHELL_RUNTIME',
    '$requesterTicketingShell',
    '$ticketingRoot',
    "'/admin/support/ticketing'",
    'REQUESTER_TICKETING_UNIFIED_NAVIGATION_RUNTIME',
    "'ticketing-dashboard'",
    "'ticketing-membership'",
    "'ticketing-my-tickets'",
    "'ticketing-create'",
    'hasMembership',
] as $marker) {

    if (
        !str_contains(
            $text,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Requester shell marker missing: '
            . $marker
        );
    }
}


$start =
    strpos(
        $text,
        '    public function ticketingNavigation(int $userId): array'
    );

$end =
    strpos(
        $text,
        '    public function moduleNavigation(int $userId): array',
        $start === false
            ? 0
            : $start
    );

if (
    $start === false
    ||
    $end === false
    ||
    $end <= $start
) {
    throw new RuntimeException(
        'ticketingNavigation boundaries unavailable.'
    );
}


$navigation =
    substr(
        $text,
        $start,
        $end - $start
    );


if (
    !str_contains(
        $navigation,
        "'support.view'"
    )
    ||
    !str_contains(
        $navigation,
        "'ticketing.ticket.view'"
    )
) {
    throw new RuntimeException(
        'Active-role Ticketing interface contract missing.'
    );
}


echo
    "TICKETING_REQUESTER_UNIFIED_SHELL_PASS\n";

<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/public_html/app/Services/'
    . 'DynamicAdminNavigationService.php';

$text =
    file_get_contents(
        $path
    );

if (!is_string($text)) {
    throw new RuntimeException(
        'DynamicAdminNavigationService unreadable.'
    );
}


$methodStart =
    strpos(
        $text,
        '    public function navigation(int $userId, string $shellKey): array'
    );

$methodEnd =
    strpos(
        $text,
        '    public function topbar(',
        $methodStart === false
            ? 0
            : $methodStart
    );

if (
    $methodStart === false
    ||
    $methodEnd === false
    ||
    $methodEnd <= $methodStart
) {
    throw new RuntimeException(
        'navigation() boundaries unavailable.'
    );
}


$method =
    substr(
        $text,
        $methodStart,
        $methodEnd - $methodStart
    );


foreach ([
    'GLOBAL_TICKETING_SIDEBAR_ENTRY_RUNTIME',
    "'ticketing'",
    "'support.view'",
    "'ticketing.ticket.view'",
    'ModuleRuntimeConfig',
    'ticketingLaunch',
    "'/admin/support/ticketing'",
    "'ticketing-dashboard'",
    "'ticketing-membership'",
    "'ticketing-my-tickets'",
    "'ticketing-create'",
] as $marker) {

    if (
        !str_contains(
            $method,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Navigation contract missing: '
            . $marker
        );
    }
}


if (
    str_contains(
        $method,
        "'/admin/ticketing/tickets/*'"
    )
) {
    throw new RuntimeException(
        'My Tickets wildcard still overlaps Create.'
    );
}


if (
    !str_contains(
        $method,
        "'active_paths' => [\n"
        . "                            '/admin/ticketing/tickets',\n"
        . "                        ]"
    )
) {
    throw new RuntimeException(
        'My Tickets exact active path missing.'
    );
}


echo
    "TICKETING_GLOBAL_SIDEBAR_ENTRY_PASS\n";

echo
    "TICKETING_SINGLE_ACTIVE_ITEM_PASS\n";

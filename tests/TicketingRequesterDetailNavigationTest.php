<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$text =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'DynamicAdminNavigationService.php'
    );

if (!is_string($text)) {
    throw new RuntimeException(
        'Navigation source unreadable.'
    );
}

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

$expect(
    str_contains(
        $text,
        'TICKETING_REQUESTER_DETAIL_NAVIGATION_ACTIVE'
    ),
    'Requester detail marker missing.'
);

$expect(
    str_contains(
        $text,
        '#^/admin/ticketing/tickets/[A-Za-z0-9_-]+$#'
    ),
    'Exact ticket-detail matcher missing.'
);

$expect(
    str_contains(
        $text,
        "'/admin/ticketing/tickets/create'"
    ),
    'Create exclusion missing.'
);

$expect(
    !str_contains(
        $text,
        "'/admin/ticketing/tickets/*'"
    ),
    'Generic ticket wildcard is forbidden.'
);

$expect(
    str_contains(
        $text,
        "'ticketing-create'"
    )
    &&
    str_contains(
        $text,
        "'/admin/ticketing/tickets/create',"
    ),
    'New Ticket exact active path missing.'
);

echo
    "TICKETING_REQUESTER_DETAIL_NAVIGATION_PASS\n";

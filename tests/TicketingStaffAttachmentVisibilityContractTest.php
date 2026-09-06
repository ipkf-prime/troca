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
                $root
                . '/'
                . $relative
            );

        if (!is_string($text)) {
            throw new RuntimeException(
                'Unreadable: '
                . $relative
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

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$staff =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketStaffOperationsService.php'
    );


foreach ([
    'public function attachmentForUser(',
    '$this->detailForUser(',
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'legacy_attachment_access_missing:'
        . $marker
    );
}


foreach ([
    'TICKETING_AUTHORIZED_ATTACHMENT_LOOKUP_V1',
    'public function attachmentForAuthorizedContext(',
    '->attachmentForTicket(',
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'authorized_attachment_lookup_missing:'
        . $marker
    );
}


$routeStart =
    strpos(
        $route,
        "'/admin/ticketing/tickets/{public_reference}/attachments/{attachment_id}'"
    );

$routeEnd =
    strpos(
        $route,
        " * Detail\n",
        is_int($routeStart)
            ? $routeStart
            : 0
    );

$expect(
    is_int($routeStart)
    && is_int($routeEnd)
    && $routeStart < $routeEnd,
    'secure_attachment_route_scope_missing'
);

$attachmentRoute =
    substr(
        $route,
        $routeStart,
        $routeEnd - $routeStart
    );


foreach ([
    'TICKETING_STAFF_ATTACHMENT_VISIBILITY_V1',
    'attachmentForUser(',
    ')->canViewTicket(',
    'attachmentForAuthorizedContext(',
] as $marker) {

    $expect(
        str_contains(
            $attachmentRoute,
            $marker
        ),
        'secure_attachment_route_marker_missing:'
        . $marker
    );
}


$first =
    strpos(
        $attachmentRoute,
        'attachmentForUser('
    );

$second =
    strpos(
        $attachmentRoute,
        ')->canViewTicket('
    );

$third =
    strpos(
        $attachmentRoute,
        'attachmentForAuthorizedContext('
    );

$expect(
    is_int($first)
    && is_int($second)
    && is_int($third)
    && $first < $second
    && $second < $third,
    'secure_attachment_authorization_order_invalid'
);


foreach ([
    'TICKETING_STAFF_DETAIL_VISIBILITY_V1',
    'public function canViewTicket(',
    '->cartable(',
] as $marker) {

    $expect(
        str_contains(
            $staff,
            $marker
        ),
        'staff_visibility_contract_missing:'
        . $marker
    );
}


echo
    "TICKETING_STAFF_ATTACHMENT_VISIBILITY_CONTRACT_PASS"
    . PHP_EOL;

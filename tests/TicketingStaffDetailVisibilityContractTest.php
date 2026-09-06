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
        . 'TicketStaffOperationsService.php'
    );

$route =
    $read(
        'public_html/routes/ticketing-runtime.php'
    );

$detail =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );


foreach ([
    'TICKETING_STAFF_DETAIL_VISIBILITY_V1',
    'public function canViewTicket(',
    '->cartable(',
    "'user:' . \$userId",
    "'all'",
    "'public_reference'",
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'service_marker_missing:'
        . $marker
    );
}


foreach ([
    'TICKETING_STAFF_DETAIL_CONTEXT_V1',
    '$ticketService->detailForUser(',
    ')->canViewTicket(',
    '$ticketService->detail(',
] as $marker) {

    $expect(
        str_contains(
            $route,
            $marker
        ),
        'route_marker_missing:'
        . $marker
    );
}


/*
 * TICKETING_STAFF_DETAIL_TEST_ROUTE_SCOPE_V1
 *
 * Authorization ordering must be inspected only inside
 * the generic Ticket Detail route. Other routes are free
 * to reuse the same service method names.
 */
$detailRouteStart =
    strpos(
        $route,
        "'/admin/ticketing/tickets/{public_reference}',"
    );

$detailRouteEnd =
    strpos(
        $route,
        'ticketing_staff_operations_a7',
        is_int($detailRouteStart)
            ? $detailRouteStart
            : 0
    );

$expect(
    is_int($detailRouteStart)
    && is_int($detailRouteEnd)
    && $detailRouteStart < $detailRouteEnd,
    'staff_detail_route_scope_missing'
);

$detailRoute =
    substr(
        $route,
        $detailRouteStart,
        $detailRouteEnd - $detailRouteStart
    );


$first =
    strpos(
        $detailRoute,
        '$ticketService->detailForUser('
    );

$second =
    strpos(
        $detailRoute,
        ')->canViewTicket('
    );

$third =
    strpos(
        $detailRoute,
        '$ticketService->detail('
    );


$expect(
    is_int($first)
    && is_int($second)
    && is_int($third)
    && $first < $second
    && $second < $third,
    'staff_detail_authorization_order_invalid'
);


/*
 * Existing UI ownership guard must remain.
 */
foreach ([
    '$lifecycleStaffOwnsReply',
    'data-ticketing-staff-reply-ownership',
    'این تیکت در اختیار شما نیست.',
    'رفتن به کارتابل پشتیبانی',
] as $marker) {

    $expect(
        str_contains(
            $detail,
            $marker
        ),
        'reply_ownership_marker_missing:'
        . $marker
    );
}


echo
    "TICKETING_STAFF_DETAIL_VISIBILITY_CONTRACT_PASS"
    . PHP_EOL;

<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


$paths = [
    'display' =>
        $root
        . '/public_html/app/Support/TicketingDisplay.php',

    'repository' =>
        $root
        . '/public_html/app/Repositories/TicketRepository.php',

    'service' =>
        $root
        . '/public_html/app/Services/Ticketing/TicketService.php',

    'staff_service' =>
        $root
        . '/public_html/app/Services/Ticketing/TicketStaffOperationsService.php',

    'view' =>
        $root
        . '/public_html/resources/views/admin/ticketing-ticket-detail.php',

    'partial' =>
        $root
        . '/public_html/resources/views/admin/ticketing-ticket-operational-detail.php',
];


$files = [];


foreach (
    $paths
    as $label => $path
) {

    if (!is_file($path)) {
        throw new RuntimeException(
            $label
            . '_missing'
        );
    }

    $content =
        file_get_contents(
            $path
        );

    if (!is_string($content)) {
        throw new RuntimeException(
            $label
            . '_read_failed'
        );
    }

    $files[$label] =
        $content;
}


/*
 * Event titles that previously fell through to
 * "رویداد سیستمی".
 */
foreach (
    [
        'ticket_requester_updated',
        'ticket_requester_resolved',
        'ticket_priority_changed',
    ]
    as $code
) {

    if (!str_contains(
        $files['display'],
        "'"
        . $code
        . "'"
    )) {
        throw new RuntimeException(
            'event_title_missing:'
            . $code
        );
    }
}


foreach (
    [
        'public static function slaEventTitle(',
        'public static function eventSummary(',
        'public static function slaEventSummary(',
        "'escalation' =>",
    ]
    as $marker
) {

    if (!str_contains(
        $files['display'],
        $marker
    )) {
        throw new RuntimeException(
            'display_marker_missing:'
            . $marker
        );
    }
}


/*
 * Read-only operational data sources.
 */
foreach (
    [
        'public function assignments(',
        'public function slaState(',
        'public function slaEvents(',
    ]
    as $marker
) {

    if (!str_contains(
        $files['repository'],
        $marker
    )) {
        throw new RuntimeException(
            'repository_marker_missing:'
            . $marker
        );
    }
}


/*
 * Operational datasets must exist only in unrestricted Staff detail().
 */
foreach (
    [
        "'assignments' =>",
        "'sla_state' =>",
        "'sla_events' =>",
    ]
    as $marker
) {

    if (!str_contains(
        $files['service'],
        $marker
    )) {
        throw new RuntimeException(
            'service_marker_missing:'
            . $marker
        );
    }
}


$requesterStart =
    strpos(
        $files['service'],
        '    public function detailForUser('
    );


$staffStart =
    strpos(
        $files['service'],
        '    public function detail('
    );


if (
    $requesterStart === false
    ||
    $staffStart === false
    ||
    $staffStart <= $requesterStart
) {
    throw new RuntimeException(
        'detail_method_bounds_invalid'
    );
}


$requesterSegment =
    substr(
        $files['service'],
        $requesterStart,
        $staffStart - $requesterStart
    );


foreach (
    [
        "'assignments' =>",
        "'sla_state' =>",
        "'sla_events' =>",
    ]
    as $marker
) {

    if (str_contains(
        $requesterSegment,
        $marker
    )) {
        throw new RuntimeException(
            'requester_operational_data_leak:'
            . $marker
        );
    }
}


/*
 * Staff Detail actions reuse the canonical cartable boundary
 * and actionContext instead of inventing new permissions.
 */
foreach (
    [
        'public function detailContext(',
        "->cartable(",
        "->actionContext(",
    ]
    as $marker
) {

    if (!str_contains(
        $files['staff_service'],
        $marker
    )) {
        throw new RuntimeException(
            'staff_context_missing:'
            . $marker
        );
    }
}


/*
 * Main View must include operational partial BEFORE ob_get_clean().
 */
$requireMarker =
    "require __DIR__ . '/ticketing-ticket-operational-detail.php';";


$requirePosition =
    strpos(
        $files['view'],
        $requireMarker
    );


$capturePosition =
    strpos(
        $files['view'],
        "\$content =\n    ob_get_clean()"
    );


if (
    $requirePosition === false
    ||
    $capturePosition === false
    ||
    $requirePosition >= $capturePosition
) {
    throw new RuntimeException(
        'operational_partial_include_order_invalid'
    );
}


/*
 * Partial is Staff-only and reuses existing operation endpoints.
 */
foreach (
    [
        'TICKETING_OPERATIONAL_DETAIL_HISTORY_T3C1_VIEW',
        "array_key_exists(\n        'assignments'",
        '->detailContext(',
        'data-ticketing-operational-audit',
        'data-ticketing-detail-staff-operations',
        '/takeover',
        '/transfer',
        '/escalate',
        'data-ticketing-operational-detail-history',
    ]
    as $marker
) {

    if (!str_contains(
        $files['partial'],
        $marker
    )) {
        throw new RuntimeException(
            'partial_marker_missing:'
            . $marker
        );
    }
}


echo
    "TICKETING_OPERATIONAL_DETAIL_HISTORY_CONTRACT_PASS\n";

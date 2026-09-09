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

    'css' =>
        $root
        . '/public_html/public/assets/admin/css/ticketing.css',

    'staff_view' =>
        $root
        . '/public_html/resources/views/admin/ticketing-staff.php',
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


/*
 * T3C2 operational placement contract.
 *
 * conversation => visible "تاریخچه"
 * history      => visible "جزئیات"
 *
 * SLA / assignment / operational audit belongs to Details.
 */
if (
    !str_contains(
        $files['partial'],
        'TICKETING_OPERATIONAL_AUDIT_TARGET_DETAILS_T3C2'
    )
) {
    throw new RuntimeException(
        'details_audit_marker_missing'
    );
}


if (
    !str_contains(
        $files['partial'],
        '[data-ticketing-detail-panel="history"]'
    )
) {
    throw new RuntimeException(
        'audit_not_targeting_details'
    );
}


if (
    str_contains(
        $files['partial'],
        '[data-ticketing-detail-panel="conversation"]'
    )
) {
    throw new RuntimeException(
        'audit_still_targeting_business_history'
    );
}


/*
 * Takeover remains on BOTH operational surfaces.
 */
if (
    !str_contains(
        $files['partial'],
        '/takeover'
    )
    ||
    !str_contains(
        $files['staff_view'],
        '/takeover'
    )
) {
    throw new RuntimeException(
        'dual_takeover_surface_missing'
    );
}


/*
 * Detail warning must point eligible Staff to the same-page Takeover.
 */
foreach (
    [
        'TICKETING_DETAIL_TAKEOVER_CONTEXT_T3C2',
        '$t3c2CanTakeoverHere',
        'از گزینه «تحویل گرفتن تیکت» در همین صفحه استفاده کنید.',
        'data-ticketing-detail-ownership-warning',
    ]
    as $marker
) {

    if (!str_contains(
        $files['view'],
        $marker
    )) {
        throw new RuntimeException(
            'same_page_takeover_contract_missing:'
            . $marker
        );
    }
}


foreach (
    [
        'برای پاسخ ابتدا باید آن را در کارتابل پشتیبانی در اختیار بگیرید.',
        'رفتن به کارتابل پشتیبانی',
    ]
    as $legacyText
) {

    if (str_contains(
        $files['view'],
        $legacyText
    )) {
        throw new RuntimeException(
            'legacy_cartable_only_warning_remains'
        );
    }
}


/*
 * Navigation context.
 */
foreach (
    [
        'TICKETING_DETAIL_CONTEXTUAL_BACK_NAV_T3C2',
        '$t3c2BackHref',
        "'/admin/ticketing/staff'",
        "'/admin/ticketing/tickets'",
    ]
    as $marker
) {

    if (!str_contains(
        $files['view'],
        $marker
    )) {
        throw new RuntimeException(
            'contextual_back_nav_missing:'
            . $marker
        );
    }
}


/*
 * Detail operation layout.
 */
foreach (
    [
        'id="ticketing-detail-staff-operations"',
        'ticketing-detail-staff-operations__actions',
        'ticketing-detail-staff-operations__transfer-form',
        'ticketing-operational-sla-details',
        'ticketing-operational-sla-summary',
    ]
    as $marker
) {

    if (!str_contains(
        $files['partial'],
        $marker
    )) {
        throw new RuntimeException(
            'partial_ux_marker_missing:'
            . $marker
        );
    }
}


/*
 * External CSS owns presentation.
 */
foreach (
    [
        'TICKETING_DETAIL_UX_T3C2',
        '.ticketing-detail-staff-operations__actions',
        'flex-wrap: nowrap;',
        '.ticketing-operational-audit',
        '.ticketing-operational-sla-details',
        '.ticketing-operational-sla-list',
    ]
    as $marker
) {

    if (!str_contains(
        $files['css'],
        $marker
    )) {
        throw new RuntimeException(
            'css_ux_marker_missing:'
            . $marker
        );
    }
}


if (
    !str_contains(
        $files['css'],
        ".ticketing-operational-audit\n.admin-table-wrap"
    )
) {
    throw new RuntimeException(
        'assignment_table_details_override_missing'
    );
}

echo
    "TICKETING_OPERATIONAL_DETAIL_HISTORY_CONTRACT_PASS\n";

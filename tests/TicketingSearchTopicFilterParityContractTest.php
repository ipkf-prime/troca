<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


$paths = [
    'routes' =>
        $root
        . '/public_html/routes/ticketing-runtime.php',

    'ticket_repo' =>
        $root
        . '/public_html/app/Repositories/TicketRepository.php',

    'ticket_service' =>
        $root
        . '/public_html/app/Services/Ticketing/TicketService.php',

    'staff_repo' =>
        $root
        . '/public_html/app/Repositories/TicketStaffOperationsRepository.php',

    'staff_service' =>
        $root
        . '/public_html/app/Services/Ticketing/TicketStaffOperationsService.php',

    'my_view' =>
        $root
        . '/public_html/resources/views/admin/ticketing-tickets.php',

    'staff_view' =>
        $root
        . '/public_html/resources/views/admin/ticketing-staff.php',
];


$source = [];

foreach (
    $paths
    as $name => $path
) {
    $content =
        file_get_contents(
            $path
        );

    if (!is_string($content)) {
        throw new RuntimeException(
            'T3G source unavailable: '
            . $name
        );
    }

    $source[$name] =
        $content;
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
    substr_count(
        $source['routes'],
        "'topic_id' =>"
    ) >= 2,
    'Topic route filter missing.'
);


$expect(
    substr_count(
        $source['routes'],
        "'topic',"
    ) >= 2,
    'Topic URL parameter missing.'
);


/*
 * Requester-only security boundary.
 */
foreach (
    [
        'TICKETING_REQUESTER_ONLY_VIEWER_BOUNDARY_V1',
        "'viewer_user_reference'",
        't.requester_user_reference = ?',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['ticket_repo'],
            $marker
        ),
        'Requester boundary missing: '
        . $marker
    );
}


/*
 * My Tickets semantic search.
 */
foreach (
    [
        "'t.subject LIKE ?'",
        "'t.support_topic_title_snapshot LIKE ?'",
        "'t.support_project_title_snapshot LIKE ?'",
        "'sp.title LIKE ?'",
        "'t.requester_display_name_snapshot LIKE ?'",
        "'t.requester_organization_snapshot LIKE ?'",
        "'apm.display_name_snapshot LIKE ?'",
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['ticket_repo'],
            $marker
        ),
        'My Tickets search field missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $source['ticket_repo'],
        "'t.support_topic_id = ?'"
    ),
    'My Tickets Topic filter missing.'
);


foreach (
    [
        'TICKETING_REQUESTER_TOPIC_OPTIONS_T3G',
        'viewerTopics(',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['ticket_repo'],
            $marker
        ),
        'Requester Topic option contract missing: '
        . $marker
    );
}


foreach (
    [
        "'topic_id' =>",
        "'topic_options' =>",
        'viewerTopics(',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['ticket_service'],
            $marker
        ),
        'My Tickets Service Topic contract missing: '
        . $marker
    );
}


/*
 * Staff semantic search / Topic filter.
 */
foreach (
    [
        'TICKETING_CARTABLE_UTF8_SEARCH_COLLATION_T3E',
        't.support_topic_title_snapshot',
        't.support_project_title_snapshot',
        'p.title',
        'assignee.display_name_snapshot',
        't.requester_display_name_snapshot',
        't.requester_organization_snapshot',
        'TICKETING_STAFF_TOPIC_FILTER_T3G',
        'TICKETING_STAFF_TOPIC_FILTER_OPTIONS_T3G',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['staff_repo'],
            $marker
        ),
        'Staff search/filter marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $source['staff_repo'],
        "'t.support_topic_id = ?'"
    ),
    'Staff Topic structured filter missing.'
);


/*
 * Staff scope remains canonical.
 */
foreach (
    [
        'cartableListContext(',
        'dataScopeClause(',
        'visibleNodeClause(',
        'TICKETING_STAFF_DATA_SCOPE_ACTION_CONTEXT_GUARD_V1',
        'TICKETING_SAME_NODE_TAKEOVER_V1',
        'takeoverMembership(',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['staff_repo'],
            $marker
        ),
        'Staff authorization contract missing: '
        . $marker
    );
}


/*
 * Views.
 */
$expect(
    str_contains(
        $source['my_view'],
        'name="topic"'
    ),
    'My Tickets Topic select missing.'
);

$expect(
    str_contains(
        $source['staff_view'],
        'name="topic"'
    ),
    'Staff Topic select missing.'
);

$expect(
    str_contains(
        $source['my_view'],
        "'topic',"
    ),
    'My Tickets Topic auto-submit missing.'
);

$expect(
    substr_count(
        $source['staff_view'],
        'data-ticketing-filter-auto-submit'
    ) === 7,
    'Staff auto-submit count must equal seven.'
);


foreach (
    [
        'موضوع',
        'پروژه',
        'درخواست‌کننده',
        'سازمان',
        'کارشناس',
    ]
    as $label
) {
    $expect(
        str_contains(
            $source['my_view'],
            $label
        ),
        'My Tickets search label missing: '
        . $label
    );

    $expect(
        str_contains(
            $source['staff_view'],
            $label
        ),
        'Staff search label missing: '
        . $label
    );
}


echo
    "TICKETING_SEARCH_TOPIC_FILTER_PARITY_CONTRACT_PASS"
    . PHP_EOL;

echo
    "MY_TICKETS_TOPIC_FILTER=PASS"
    . PHP_EOL;

echo
    "STAFF_CARTABLE_TOPIC_FILTER=PASS"
    . PHP_EOL;

echo
    "SEARCH_SEMANTIC_FIELD_PARITY=PASS"
    . PHP_EOL;

echo
    "REQUESTER_BOUNDARY=PRESERVED"
    . PHP_EOL;

echo
    "STAFF_DATA_SCOPE=PRESERVED"
    . PHP_EOL;

echo
    "TAKEOVER_AUTHORIZATION=PRESERVED"
    . PHP_EOL;
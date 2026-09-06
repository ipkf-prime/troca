<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$read =
    static function (
        string $relative
    ) use ($root): string {

        $value =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'Unreadable: '
                . $relative
            );
        }

        return $value;
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


$navigation =
    $read(
        'public_html/app/Services/'
        . 'DynamicAdminNavigationService.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketTopbarTargetService.php'
    );


foreach ([
    'TICKETING_CONTEXT_AWARE_TOPBAR_DISPATCH_LINK_V1',
    '/admin/ticketing/attention',
    'ticketing_unread_count',
] as $marker) {

    $expect(
        str_contains(
            $navigation,
            $marker
        ),
        'navigation_marker_missing:'
        . $marker
    );
}


foreach ([
    'TICKETING_CONTEXT_AWARE_TOPBAR_DISPATCHER_V1',
    'TICKETING_TOPBAR_NOTIFICATION_CONSUME_V1',
    'Do not mark every Ticketing notification read.',
    "'/admin/ticketing/attention'",
    'TicketTopbarTargetService',
    'NotificationInboxService',
    '->targetForUser(',
    '->markRead(',
    '$response->redirect(',
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


foreach ([
    'TICKETING_CONTEXT_AWARE_TOPBAR_TARGET_V1',
    'public function targetForUser(',
    'public function targetForTicket(',
    'latestUnreadNotificationContext(',
    'notification_reference',
    "'requester_ticket'",
    "'current_assignee'",
    "'scoped_non_owner_staff'",
    "'no_unread_ticketing_notification'",
    "'/admin/ticketing/tickets'",
    "'/admin/ticketing/staff'",
    "'my'",
    "'all'",
    '->canViewTicket(',
    'recipients.read_at IS NULL',
    "events.source_module =",
    "'ticketing'",
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'target_service_marker_missing:'
        . $marker
    );
}


/*
 * Own ticket must win over staff context.
 */
$requester =
    strpos(
        $service,
        'if ($isRequester)'
    );

$staffVisibility =
    strpos(
        $service,
        '$staffCanView ='
    );

$currentAssignee =
    strpos(
        $service,
        'if ($isCurrentAssignee)'
    );


$expect(
    is_int($requester)
    && is_int($staffVisibility)
    && is_int($currentAssignee)
    && $requester < $staffVisibility
    && $staffVisibility < $currentAssignee,
    'context_precedence_invalid'
);


/*
 * Exact notification is consumed before successful redirect.
 */
$routeStart =
    strpos(
        $route,
        'TICKETING_CONTEXT_AWARE_TOPBAR_DISPATCHER_V1'
    );

$routeEnd =
    strpos(
        $route,
        ' * Dashboard',
        $routeStart
    );


$expect(
    is_int($routeStart)
    && is_int($routeEnd)
    && $routeEnd > $routeStart,
    'dispatcher_scope_invalid'
);


$dispatcher =
    substr(
        $route,
        $routeStart,
        $routeEnd - $routeStart
    );


$markRead =
    strpos(
        $dispatcher,
        '->markRead('
    );

$redirect =
    strpos(
        $dispatcher,
        '$response->redirect('
    );


$expect(
    is_int($markRead)
    && is_int($redirect)
    && $markRead < $redirect,
    'notification_must_be_consumed_before_redirect'
);


echo
    "TICKETING_TOPBAR_CONTEXT_TARGET_CONTRACT_PASS"
    . PHP_EOL;

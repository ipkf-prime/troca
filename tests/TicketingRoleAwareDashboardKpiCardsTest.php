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
                'unreadable:'
                . $relative
            );
        }

        return $value;
    };

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketStaffOperationsRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketStaffOperationsService.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-dashboard.php'
    );


/*
 * =========================================================
 * Repository aggregate contract
 * =========================================================
 */
foreach ([
    'TICKETING_STAFF_DASHBOARD_STATUS_COUNTS_V1',
    'public function dashboardStatusCounts(',
    'actorMemberships(',
    'visibleNodesByProject(',
    'visibleNodeClause(',
    'current_assignee_project_member_id',
    'GROUP BY',
    't.status_code',
] as $marker) {

    if (
        strpos(
            $repository,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'repository_marker_missing:'
            . $marker
        );
    }
}


$repositoryDashboardStart =
    strpos(
        $repository,
        'public function dashboardStatusCounts('
    );

$repositoryDashboardEnd =
    strpos(
        $repository,
        'public function actionContext(',
        $repositoryDashboardStart
    );

if (
    $repositoryDashboardStart === false
    ||
    $repositoryDashboardEnd === false
    ||
    $repositoryDashboardStart
        >= $repositoryDashboardEnd
) {
    throw new RuntimeException(
        'repository_dashboard_scope_invalid'
    );
}

$repositoryDashboardBlock =
    substr(
        $repository,
        $repositoryDashboardStart,
        $repositoryDashboardEnd
        - $repositoryDashboardStart
    );

if (
    strpos(
        $repositoryDashboardBlock,
        'LIMIT 200'
    ) !== false
) {
    throw new RuntimeException(
        'dashboard_aggregate_inherits_cartable_limit'
    );
}


/*
 * =========================================================
 * Service KPI mapping
 * =========================================================
 */
foreach ([
    'public function dashboard(',
    "'status_counts' =>",
    "'kpis' =>",
    "'open' =>",
    "\$statusCounts['new']",
    "'in_progress' =>",
    "'resolved' =>",
    "'closed' =>",
] as $marker) {

    if (
        strpos(
            $service,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'service_marker_missing:'
            . $marker
        );
    }
}


$serviceDashboardStart =
    strpos(
        $service,
        'public function dashboard('
    );

$serviceDashboardEnd =
    strpos(
        $service,
        'public function page(',
        $serviceDashboardStart
    );

if (
    $serviceDashboardStart === false
    ||
    $serviceDashboardEnd === false
    ||
    $serviceDashboardStart
        >= $serviceDashboardEnd
) {
    throw new RuntimeException(
        'service_dashboard_scope_invalid'
    );
}

$serviceDashboardBlock =
    substr(
        $service,
        $serviceDashboardStart,
        $serviceDashboardEnd
        - $serviceDashboardStart
    );

if (
    strpos(
        $serviceDashboardBlock,
        '->cartable('
    ) !== false
) {
    throw new RuntimeException(
        'dashboard_service_uses_cartable_rows'
    );
}


/*
 * =========================================================
 * Dashboard route must use summary service.
 * =========================================================
 */
$routeStart =
    strpos(
        $route,
        "/*\n * ---------------------------------------------------------\n * Dashboard"
    );

$routeEnd =
    strpos(
        $route,
        "/*\n * ---------------------------------------------------------\n * Support Project Administration",
        $routeStart
    );

if (
    $routeStart === false
    ||
    $routeEnd === false
    ||
    $routeStart >= $routeEnd
) {
    throw new RuntimeException(
        'dashboard_route_scope_invalid'
    );
}

$dashboardRoute =
    substr(
        $route,
        $routeStart,
        $routeEnd - $routeStart
    );

if (
    strpos(
        $dashboardRoute,
        '->dashboard('
    ) === false
) {
    throw new RuntimeException(
        'dashboard_summary_service_call_missing'
    );
}

if (
    strpos(
        $dashboardRoute,
        '->page('
    ) !== false
) {
    throw new RuntimeException(
        'dashboard_route_still_uses_cartable_page'
    );
}


/*
 * =========================================================
 * Staff card UI
 * =========================================================
 */
foreach ([
    'تیکت‌های باز',
    'در حال بررسی',
    'حل‌شده',
    'بسته‌شده',
    'ticketing-dashboard-metric--staff',
    'ticketing-dashboard-metric__hint',
    'repeat(4,minmax(0,1fr))',
    'ورود به کارتابل',
] as $marker) {

    if (
        strpos(
            $view,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'view_kpi_marker_missing:'
            . $marker
        );
    }
}


$staffStart =
    strpos(
        $view,
        '<?php if ($isStaff): ?>'
    );

$requesterStart =
    strpos(
        $view,
        '<?php else: ?>',
        $staffStart
    );

if (
    $staffStart === false
    ||
    $requesterStart === false
    ||
    $staffStart >= $requesterStart
) {
    throw new RuntimeException(
        'staff_dashboard_branch_scope_invalid'
    );
}

$staffBranch =
    substr(
        $view,
        $staffStart,
        $requesterStart - $staffStart
    );

foreach ([
    'کارتابل پشتیبانی',
    '<table',
    '$staffRecent',
    '$staffItems',
    '$staffCounts',
    'ticketing-dashboard-table--staff',
] as $marker) {

    if (
        strpos(
            $staffBranch,
            $marker
        ) !== false
    ) {
        throw new RuntimeException(
            'obsolete_staff_branch_marker:'
            . $marker
        );
    }
}


/*
 * =========================================================
 * Requester branch remains available.
 * =========================================================
 */
foreach ([
    'ticketing-dashboard-metrics--requester',
    'همه درخواست‌ها',
    'آخرین درخواست‌های من',
    'درخواست جدید',
] as $marker) {

    if (
        strpos(
            $view,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'requester_marker_missing:'
            . $marker
        );
    }
}


echo
    "TICKETING_ROLE_AWARE_DASHBOARD_KPI_CARDS_PASS"
    . PHP_EOL;

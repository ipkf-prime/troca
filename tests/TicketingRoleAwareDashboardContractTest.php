<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$route =
    file_get_contents(
        $root
        . '/public_html/routes/ticketing-runtime.php'
    );

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/ticketing-dashboard.php'
    );

if (!is_string($route)) {
    throw new RuntimeException(
        'ticketing_runtime_unreadable'
    );
}

if (!is_string($view)) {
    throw new RuntimeException(
        'ticketing_dashboard_unreadable'
    );
}


/*
 * ---------------------------------------------------------
 * Role-aware route contract
 * ---------------------------------------------------------
 */
foreach ([
    'TicketStaffOperationsService()',
    '->dashboard(',
    '$isStaff',
    "'is_staff' =>",
    "'staff_dashboard' =>",
    'dashboardForUser(',
] as $marker) {

    if (
        strpos(
            $route,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'route_marker_missing:'
            . $marker
        );
    }
}


/*
 * ---------------------------------------------------------
 * Staff dashboard contract
 * ---------------------------------------------------------
 */
foreach ([
    '<h1>داشبورد پشتیبانی</h1>',
    'دسترسی فعال در ماژول تیکتینگ',
    'کارتابل کارشناسی',
    'تیکت‌های باز',
    'در حال بررسی',
    'حل‌شده',
    'بسته‌شده',
    'ورود به کارتابل',
    'ticketing-dashboard-metric--staff',
    'ticketing-dashboard-metric__hint',
    '/admin/ticketing/staff',
] as $marker) {

    if (
        strpos(
            $view,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'staff_view_marker_missing:'
            . $marker
        );
    }
}


/*
 * ---------------------------------------------------------
 * Requester dashboard contract
 * ---------------------------------------------------------
 */
foreach ([
    '<h1>پشتیبانی و تیکتینگ</h1>',
    'درخواست‌کننده',
    'همه درخواست‌ها',
    'آخرین درخواست‌های من',
    'درخواست‌های من',
    'درخواست جدید',
    'ticketing-dashboard-metrics--requester',
] as $marker) {

    if (
        strpos(
            $view,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'requester_view_marker_missing:'
            . $marker
        );
    }
}


/*
 * ---------------------------------------------------------
 * Old Staff dashboard clone/list must remain removed.
 * ---------------------------------------------------------
 */
foreach ([
    'کارتابل پشتیبانی',
    '$staffRecent',
    '$staffItems',
    '$staffCounts',
    'ticketing-dashboard-table--staff',
    'ticketing-dashboard-assignee',
    'ticketing-dashboard-status-cell',
    'ticketing-dashboard-actions',
    'قابل مشاهده',
    'تحت مسئولیت من',
    'بدون کارشناس',
    'ticketing-queue-card',
    'در مرحله عملیاتی بعدی فعال می‌شود',
    'آخرین تیکت‌های من',
] as $marker) {

    if (
        strpos(
            $view,
            $marker
        ) !== false
    ) {
        throw new RuntimeException(
            'obsolete_view_marker_present:'
            . $marker
        );
    }
}


echo
    "TICKETING_ROLE_AWARE_DASHBOARD_CONTRACT_PASS"
    . PHP_EOL;

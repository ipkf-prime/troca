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

foreach ([
    'TicketStaffOperationsService()',
    "'scope' => 'all'",
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

foreach ([
    '<h1>پشتیبانی و تیکتینگ</h1>',
    '<h1>داشبورد پشتیبانی</h1>',
    'دسترسی فعال در ماژول تیکتینگ',
    'کارتابل کارشناسی',
    'کارتابل پشتیبانی',
    'قابل مشاهده',
    'تحت مسئولیت من',
    'بدون کارشناس',
    'درخواست‌کننده',
    'آخرین درخواست‌های من',
    '/admin/ticketing/staff',
] as $marker) {

    if (
        strpos(
            $view,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'view_marker_missing:'
            . $marker
        );
    }
}

foreach ([
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

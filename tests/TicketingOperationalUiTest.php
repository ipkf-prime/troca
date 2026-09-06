<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static fn (string $path): string =>
        file_get_contents(
            $root . '/' . $path
        );


$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$rbac =
    $read(
        'public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );

$panel =
    $read(
        'public_html/app/Services/'
        . 'AdminPanelService.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'ExposeTicketingModuleShellNavigation.php'
    );

$css =
    $read(
        'public_html/public/assets/admin/css/'
        . 'ticketing.css'
    );

$dashboard =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-dashboard.php'
    );

$list =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-tickets.php'
    );

$form =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-form.php'
    );

$detail =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );


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


foreach ([
    'requester_user_reference = ?',
    "'requester_user_reference'",
] as $needle) {
    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Requester repository scope missing: '
        . $needle
    );
}


foreach ([
    'dashboardForUser(',
    'myTickets(',
    'detailForUser(',
    'userReference(',
] as $needle) {
    $expect(
        str_contains(
            $service,
            $needle
        ),
        'Requester service scope missing: '
        . $needle
    );
}


foreach ([
    "'/admin/ticketing/tickets'",
    "'/admin/ticketing/tickets/create'",
    "'/admin/ticketing/tickets/{public_reference}'",
    'ticketing.ticket.view',
] as $needle) {
    $expect(
        str_contains(
            $rbac,
            $needle
        ),
        'Ticketing RBAC missing: '
        . $needle
    );
}


foreach ([
    'dashboardForUser(',
    'myTickets(',
    'detailForUser(',
    '$router->post(',
    "'ticketing-ticket-form'",
    "'ticketing-ticket-detail'",
    "'ticketing-tickets'",
] as $needle) {
    $expect(
        str_contains(
            $routes,
            $needle
        ),
        'Ticketing route missing: '
        . $needle
    );
}


/*
 * Route files may legitimately invoke application
 * methods such as ->update().  That must not be
 * confused with a SQL UPDATE statement.
 *
 * Protect both sides of the boundary:
 * - no direct database APIs in routes;
 * - no actual SQL statement patterns in routes.
 */
foreach ([
    '->prepare(',
    '->query(',
    '->exec(',
    'PDO::',
    'Database::connect(',
    "->resolve('ticketing.primary')",
] as $databaseBoundaryNeedle) {
    $expect(
        !str_contains(
            $routes,
            $databaseBoundaryNeedle
        ),
        'Routes must not use database API directly: '
        . $databaseBoundaryNeedle
    );
}

$expect(
    preg_match(
        '/\b(?:'
        . 'SELECT\s+.+?\s+FROM'
        . '|INSERT\s+INTO'
        . '|UPDATE\s+[A-Za-z_][A-Za-z0-9_]*\s+SET'
        . '|DELETE\s+FROM'
        . ')\b/is',
        $routes
    ) !== 1,
    'Routes must not contain SQL statements.'
);


$expect(
    str_contains(
        $panel,
        "'/assets/admin/css/ticketing.css'"
    ),
    'Ticketing CSS asset missing.'
);


$expect(
    !str_contains(
        $panel,
        "'key' => 'ticketing-my-tickets'"
    ),
    'Ticketing child navigation must not '
    . 'be duplicated in AdminPanelService.'
);


foreach ([
    "'ticketing'",
    "'ticketing-dashboard'",
    "'ticketing-my-tickets'",
    "'ticketing-create'",
    "'/admin/ticketing'",
    "'/admin/ticketing/tickets'",
    "'/admin/ticketing/tickets/create'",
    "'ticketing.ticket.view'",
] as $needle) {
    $expect(
        str_contains(
            $migration,
            $needle
        ),
        'Dynamic navigation missing: '
        . $needle
    );
}


foreach ([
    '.ticketing-page-head',
    '.ticketing-metrics',
    '.ticketing-filter-toolbar',
    '.ticketing-filter-row',
    '.ticketing-create-form',
    '.ticketing-detail-page',
] as $needle) {
    $expect(
        str_contains(
            $css,
            $needle
        ),
        'Ticketing CSS contract missing: '
        . $needle
    );
}


foreach ([
    'ticketing-dashboard-page',
    'ticketing-dashboard-metrics',
    'ticketing-dashboard-metric__value',
    'ticketing-dashboard-role',
    'کارتابل کارشناسی',
    'تیکت‌های باز',
    'در حال بررسی',
    'حل‌شده',
    'بسته‌شده',
    'ticketing-dashboard-metric--staff',
    'ticketing-dashboard-metric__hint',
    'ورود به کارتابل',
    'آخرین درخواست‌های من',
] as $needle) {
    $expect(
        str_contains(
            $dashboard,
            $needle
        ),
        'Role-aware dashboard contract missing: '
        . $needle
    );
}

$expect(
    !str_contains(
        $dashboard,
        'کارتابل پشتیبانی'
    )
    && !str_contains(
        $dashboard,
        '$staffRecent'
    )
    && !str_contains(
        $dashboard,
        '$staffItems'
    )
    && !str_contains(
        $dashboard,
        '$staffCounts'
    ),
    'Obsolete Staff dashboard cartable clone remains.'
);


$expect(
    !str_contains(
        $dashboard,
        'ticketing-queue-card'
    )
    && !str_contains(
        $dashboard,
        'در مرحله عملیاتی بعدی فعال می‌شود'
    ),
    'Obsolete Ticketing queue placeholder remains.'
);


foreach ([
    'ticketing-list-page',
    'ticketing-filter-section',
    'ticketing-filter-grid',
    'status_options',
    'priority_options',
] as $needle) {
    $expect(
        str_contains(
            $list,
            $needle
        ),
        'List compact contract missing: '
        . $needle
    );
}


foreach ([
    'ticketing-form-page',
    'ticketing-form-section',
    'ticketing-create-form',
    'new \\IPKF\\Security\\Csrf()',
    'name="subject"',
    'name="body"',
    'name="priority_code"',
    'name="category_id"',
] as $needle) {
    $expect(
        str_contains(
            $form,
            $needle
        ),
        'Form contract missing: '
        . $needle
    );
}


foreach ([
    'ticketing-detail-page',
    'ticketing-detail-metrics',
    'ticketing-conversation',
    'ticketing-history',
    'گفتگو',
    'تاریخچه',
] as $needle) {
    $expect(
        str_contains(
            $detail,
            $needle
        ),
        'Detail compact contract missing: '
        . $needle
    );
}


foreach ([
    $dashboard,
    $list,
    $form,
    $detail,
] as $view) {
    $expect(
        !str_contains(
            $view,
            "\u{FFFD}"
        ),
        'Unicode replacement character '
        . 'detected in Ticketing UI.'
    );
}


echo "TICKETING_OPERATIONAL_UI_PASS\n";

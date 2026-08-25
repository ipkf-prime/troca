<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$admin = file_get_contents(
    $root
    . '/public_html/app/Services/AdminPanelService.php'
);

$loader = file_get_contents(
    $root
    . '/public_html/system/Routing/RouteLoader.php'
);

$route = file_get_contents(
    $root
    . '/public_html/routes/ticketing-runtime.php'
);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'ticketing-dashboard.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(
    str_contains(
        $admin,
        '$ticketingShell = $urls->isTicketingHost'
    ),
    'Ticketing host shell detection is required.'
);

$expect(
    str_contains(
        $admin,
        '$moduleShell = $automationShell'
    )
    && str_contains(
        $admin,
        '|| $ticketingShell'
    ),
    'Ticketing host must participate in module shell mode.'
);

$expect(
    str_contains(
        $admin,
        '$this->ticketingNavigation($userId)'
    ),
    'Ticketing shell must use dedicated navigation.'
);

$expect(
    str_contains(
        $admin,
        "'permission' => 'ticketing.ticket.view'"
    ),
    'Ticketing shell navigation permission is required.'
);

$expect(
    str_contains(
        $loader,
        "BASE_PATH . '/routes/ticketing-runtime.php'"
    ),
    'Ticketing runtime route file must be registered by RouteLoader.'
);

$expect(
    str_contains(
        $loader,
        'if (is_readable($routeFile))'
    )
    && str_contains(
        $loader,
        'require $routeFile;'
    ),
    'Ticketing runtime must use the standard optional route loader.'
);

$expect(
    str_contains(
        $route,
        "\$router->get("
    )
    && str_contains(
        $route,
        "'/admin/ticketing'"
    ),
    'Ticketing dashboard GET route is required.'
);

$expect(
    str_contains(
        $route,
        "\$adminGuard("
    )
    && str_contains(
        $route,
        "'/admin/ticketing'"
    ),
    'Ticketing route must pass through the admin guard.'
);

$expect(
    str_contains(
        $route,
        "'ticketing-dashboard'"
    ),
    'Ticketing route must render its dedicated dashboard view.'
);

$expect(
    str_contains(
        $route,
        "'ticketing.ticket.view'"
    ),
    'Ticketing runtime must expose its permission contract.'
);

$expect(
    str_contains(
        $view,
        'پشتیبانی و تیکتینگ'
    ),
    'Ticketing dashboard title is required.'
);

$expect(
    str_contains(
        $view,
        "require __DIR__ . '/layout.php'"
    ),
    'Ticketing dashboard must render inside standard Admin layout.'
);

$expect(
    !preg_match(
        '/\b(?:SELECT|INSERT|UPDATE|DELETE|FROM|JOIN)\b/i',
        $route
    ),
    'Initial Ticketing runtime route must not access a database.'
);

$expect(
    !str_contains(
        $route,
        'ticketing-dev.troca.ir'
    )
    && !str_contains(
        $view,
        'ticketing-dev.troca.ir'
    ),
    'Ticketing runtime must not hardcode deployment hostname.'
);

echo "Ticketing module shell runtime checks passed.\n";

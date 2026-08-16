<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$routes =
    file_get_contents(
        $root
        . '/public_html/routes/web.php'
    );

$rbac =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );

$permissions =
    file_get_contents(
        $root
        . '/public_html/system/Database/Seeds/'
        . 'AutomationCorrespondencePermissionsSeeder.php'
    );

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-correspondence-detail.php'
    );

foreach ([
    'routes' => $routes,
    'rbac' => $rbac,
    'permissions' => $permissions,
    'view' => $view,
] as $name => $source) {
    if (!is_string($source)) {
        throw new RuntimeException(
            "Unreadable {$name}."
        );
    }
}

$route =
    '/admin/automation/correspondences/'
    . '{public_reference}/dispatch';

if (
    !str_contains(
        $permissions,
        "'automation.correspondence.dispatch'"
    )
) {
    throw new RuntimeException(
        'Dispatch permission missing.'
    );
}

if (
    !str_contains(
        $rbac,
        "'{$route}' => "
        . "'automation.correspondence.dispatch'"
    )
) {
    throw new RuntimeException(
        'Dispatch RBAC mapping missing.'
    );
}

foreach ([
    $route,
    'CorrespondenceDispatchService',
    '->request(',
    'dispatch_requested',
    'dispatch_already_requested',
    '\\IPKF\\Security\\Csrf',
] as $required) {
    if (!str_contains(
        $routes,
        $required
    )) {
        throw new RuntimeException(
            'Request route missing: '
            . $required
        );
    }
}

foreach ([
    'ایجاد درخواست ارسال',
    'هنوز هیچ ارسال واقعی انجام نشده است',
    'value="postal"',
    'value="courier"',
    'value="hand_delivery"',
    'value="fax"',
    'value="email"',
    'value="system"',
    "=== 'outgoing'",
    "=== 'registered'",
    'dispatch_destination_unavailable',
] as $required) {
    if (!str_contains(
        $view,
        $required
    )) {
        throw new RuntimeException(
            'Request UI missing: '
            . $required
        );
    }
}

if (
    str_contains(
        $routes,
        '->dispatch('
    )
) {
    throw new RuntimeException(
        'Route still executes one-shot dispatch.'
    );
}

echo "Automation correspondence dispatch request UI checks passed.\n";

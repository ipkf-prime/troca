<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$coreFiles = [
    $root
        . '/public_html/app/Services/'
        . 'AdminNavigationRbacService.php',

    $root
        . '/public_html/app/Services/'
        . 'AdminPanelService.php',

    $root
        . '/public_html/resources/views/admin/'
        . 'access-control.php',

    $root
        . '/public_html/routes/web.php',
];

foreach ($coreFiles as $file) {

    $source =
        file_get_contents($file);

    if (
        !str_contains(
            $source,
            'راهنما و پشتیبانی سامانه'
        )
    ) {
        throw new RuntimeException(
            'Core support label missing: '
            . basename($file)
        );
    }
}

$rbac = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'AdminNavigationRbacService.php'
);

if (
    !str_contains(
        $rbac,
        "'/admin/support' => 'support.view'"
    )
) {
    throw new RuntimeException(
        'support.view technical contract changed.'
    );
}

$ticketingRegistry = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'ApplicationModuleRegistryService.php'
);

$ticketingDashboard = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'ticketing-dashboard.php'
);

if (
    !str_contains(
        $ticketingRegistry,
        "'name' => 'پشتیبانی و تیکتینگ'"
    )
    || !str_contains(
        $ticketingDashboard,
        '<h1>پشتیبانی و تیکتینگ</h1>'
    )
) {
    throw new RuntimeException(
        'Ticketing module display name changed.'
    );
}

echo "CORE_SUPPORT_NAMING_PASS\n";

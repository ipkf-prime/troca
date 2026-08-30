<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use (
        $root
    ): string {

        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Unreadable source: '
                . $relative
            );
        }

        return $content;
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


$layout =
    $read(
        'public_html/resources/views/admin/layout.php'
    );

$navigation =
    $read(
        'public_html/app/Services/'
        . 'DynamicAdminNavigationService.php'
    );

$sso =
    $read(
        'public_html/app/Services/'
        . 'ModuleSsoService.php'
    );

$rbac =
    $read(
        'public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-requester.php'
    );

$dashboard =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-requester-dashboard.php'
    );


foreach ([
    'DynamicAdminNavigationService',
    '$dynamicNavigation->navigation(',
    '$navigationShell',
] as $marker) {

    $expect(
        str_contains(
            $layout,
            $marker
        ),
        'Real sidebar source missing: '
        . $marker
    );
}


foreach ([
    'REQUESTER_TICKETING_WEB_NAVIGATION_BRIDGE_RUNTIME',
    "'ticketing-dashboard'",
    "'ticketing-membership'",
    "'ticketing-my-tickets'",
    "'ticketing-create'",
    "'support.view'",
    "'ticketing.ticket.view'",
    'TicketRequesterOnboardingService()',
    '/admin/support/ticketing/membership',
] as $marker) {

    $expect(
        str_contains(
            $navigation,
            $marker
        ),
        'Requester navigation missing: '
        . $marker
    );
}


foreach ([
    'REQUESTER_TICKETING_SSO_BRIDGE_RUNTIME',
    'isRequesterTicketingReturnPath',
    "'support.view'",
    'TicketRequesterOnboardingService()',
    "'/admin/ticketing/tickets'",
    "'/admin/ticketing/tickets/create'",
    "'error' => 'forbidden'",
] as $marker) {

    $expect(
        str_contains(
            $sso,
            $marker
        ),
        'Requester SSO bridge missing: '
        . $marker
    );
}


foreach ([
    "'/admin/support/ticketing/membership'",
    "'support.view'",
] as $marker) {

    $expect(
        str_contains(
            $rbac,
            $marker
        ),
        'Membership RBAC missing: '
        . $marker
    );
}


foreach ([
    "'/admin/support/ticketing/membership'",
    "'ticketing-requester-dashboard'",
    "'ticketing-requester-onboarding'",
    'T7A2_REQUESTER_MEMBERSHIP_WORKSPACE',
] as $marker) {

    $expect(
        str_contains(
            $routes,
            $marker
        ),
        'Requester route split missing: '
        . $marker
    );
}


foreach ([
    'داشبورد تیکتینگ',
    'پروژه‌های پشتیبانی من',
    'تیکت‌های من',
    'تیکت جدید',
    'عضویت در پروژه‌ها',
] as $marker) {

    $expect(
        str_contains(
            $dashboard,
            $marker
        ),
        'Requester dashboard UI missing: '
        . $marker
    );
}


echo
    "TICKETING_REQUESTER_WEB_ACCESS_BRIDGE_PASS\n";

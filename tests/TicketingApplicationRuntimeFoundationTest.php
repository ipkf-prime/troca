<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static fn (string $path): string =>
        file_get_contents(
            $root . '/' . $path
        );

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            fwrite(
                STDERR,
                "FAIL: {$message}\n"
            );

            exit(1);
        }
    };

$urls =
    $read(
        'public_html/system/Support/'
        . 'ApplicationUrlRegistry.php'
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


/*
 * Ticketing URL / host shell remains available.
 */
foreach ([
    "moduleUrl('ticketing', 'TICKETING_APP_URL'",
    'function ticketingLaunch',
    'function ticketingHost',
    'function isTicketingHost',
    '$ticketingPath',
    '$requestIsTicketing',
    '/admin/ticketing',
] as $needle) {

    $expect(
        str_contains(
            $urls,
            $needle
        ),
        'ApplicationUrlRegistry missing '
        . 'Ticketing contract: '
        . $needle
    );
}


/*
 * Module SSO is intentionally generic.
 *
 * Permission, route, callback, base URL and audience
 * must come from the active module registry rather
 * than a hardcoded Ticketing mapping.
 */
foreach ([
    'allActive()',
    "['permission_key']",
    "['route_path']",
    "['sso_callback_url']",
    "['base_url']",
    "['module_key']",
    'moduleForPath(',
    'moduleForHost(',
] as $needle) {

    $expect(
        str_contains(
            $sso,
            $needle
        ),
        'Generic Module SSO contract missing: '
        . $needle
    );
}


$expect(
    str_contains(
        $sso,
        'hasPermission('
    ),
    'Module SSO must enforce the '
    . 'registered module permission.'
);


$expect(
    !str_contains(
        $sso,
        "'ticketing' => 'ticketing.ticket.view'"
    ),
    'Ticketing permission must not be '
    . 'hardcoded inside ModuleSsoService.'
);


$expect(
    !str_contains(
        $sso,
        "'ticketing' =>"
    ),
    'Ticketing must not have a static '
    . 'SSO module mapping.'
);


$expect(
    !str_contains(
        $sso,
        'isTicketingHost('
    ),
    'Generic SSO audience resolution must '
    . 'not depend on fixed Ticketing host methods.'
);


/*
 * Ticketing route itself still requires
 * the Ticketing base permission at the admin shell.
 */
$expect(
    str_contains(
        $rbac,
        "'/admin/ticketing' => "
        . "'ticketing.ticket.view'"
    ),
    'Ticketing admin route must retain '
    . 'RBAC protection.'
);


/*
 * Deployment domains must stay configuration-driven.
 */
foreach ([
    $urls,
    $sso,
    $rbac,
] as $runtime) {

    $expect(
        !str_contains(
            $runtime,
            'ticketing-dev.troca.ir'
        ),
        'Ticketing runtime must not '
        . 'hardcode deployment domains.'
    );
}

echo
    "Ticketing application runtime foundation "
    . "dynamic structural checks passed.\n";

<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$panelPath =
    $root
    . '/public_html/app/Services/'
    . 'AdminPanelService.php';

$accessPath =
    $root
    . '/public_html/app/Services/'
    . 'AccessService.php';

$authorizationPath =
    $root
    . '/public_html/app/Services/'
    . 'AuthorizationService.php';


$panel =
    file_get_contents(
        $panelPath
    );

$access =
    file_get_contents(
        $accessPath
    );

$authorization =
    file_get_contents(
        $authorizationPath
    );

foreach ([
    'panel' =>
        $panel,

    'access' =>
        $access,

    'authorization' =>
        $authorization,

] as $name => $content) {

    if (!is_string($content)) {
        throw new RuntimeException(
            $name
            . ' source unreadable.'
        );
    }
}


$methodStart =
    strpos(
        $panel,
        '    public function dashboardModules(int $userId): array'
    );

$methodEnd =
    strpos(
        $panel,
        '    public function moduleHub(',
        $methodStart === false
            ? 0
            : $methodStart
    );

if (
    $methodStart === false
    ||
    $methodEnd === false
    ||
    $methodEnd <= $methodStart
) {
    throw new RuntimeException(
        'dashboardModules() boundaries unavailable.'
    );
}

$method =
    substr(
        $panel,
        $methodStart,
        $methodEnd - $methodStart
    );


foreach ([
    'UNIFIED_TICKETING_DASHBOARD_ENTRY_RUNTIME',
    '$staffInterfaceAllowed',
    '$requesterInterfaceAllowed',
    '$this->navigation->can',
    "'support.view'",
    "'/admin/support/ticketing'",
    'ACTIVE system role',
    'Project membership never selects the interface',
] as $marker) {

    if (
        !str_contains(
            $method,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Active-role entry marker missing: '
            . $marker
        );
    }
}


foreach ([
    'hasStaffMembership',
    '$staffMembership',
    '$staffAllowed',
] as $forbidden) {

    if (
        str_contains(
            $method,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'Project membership still controls interface: '
            . $forbidden
        );
    }
}


if (
    !str_contains(
        $access,
        "Session::put('active_role_assignment_id'"
    )
    ||
    !str_contains(
        $access,
        'public function switchTo('
    )
    ||
    !str_contains(
        $access,
        'public function activeAssignment('
    )
) {
    throw new RuntimeException(
        'AccessService active-role contract missing.'
    );
}


if (
    !str_contains(
        $authorization,
        "Session::get('active_role_assignment_id')"
    )
    ||
    !str_contains(
        $authorization,
        '$this->activeAssignmentId()'
    )
) {
    throw new RuntimeException(
        'AuthorizationService is not scoped '
        . 'to active role assignment.'
    );
}


echo
    "TICKETING_DASHBOARD_ACTIVE_ROLE_ENTRY_PASS\n";

<?php

declare(strict_types=1);


$root =
    dirname(
        __DIR__
    );


$files = [
    'service' =>
        $root
        . '/public_html/app/Services/Organization/OrganizationalAffiliationService.php',

    'routes' =>
        $root
        . '/public_html/routes/organizational-affiliation.php',

    'self_view' =>
        $root
        . '/public_html/resources/views/admin/profile-affiliation.php',

    'manager_view' =>
        $root
        . '/public_html/resources/views/admin/organization-affiliations.php',

    'web' =>
        $root
        . '/public_html/routes/web.php',

    'navigation' =>
        $root
        . '/public_html/app/Services/AdminNavigationRbacService.php',

    'panel' =>
        $root
        . '/public_html/app/Services/AdminPanelService.php',

    'account_nav' =>
        $root
        . '/public_html/resources/views/admin/partials/account-nav.php',

    'manifest' =>
        $root
        . '/scripts/ipkf-platform-shared-runtime-files.txt',
];


foreach (
    $files
    as $name => $path
) {

    if (
        !is_file(
            $path
        )
    ) {
        throw new RuntimeException(
            "missing_file:{$name}:{$path}"
        );
    }
}


$service =
    file_get_contents(
        $files['service']
    );

$routes =
    file_get_contents(
        $files['routes']
    );

$selfView =
    file_get_contents(
        $files['self_view']
    );

$managerView =
    file_get_contents(
        $files['manager_view']
    );

$web =
    file_get_contents(
        $files['web']
    );

$navigation =
    file_get_contents(
        $files['navigation']
    );

$panel =
    file_get_contents(
        $files['panel']
    );

$accountNav =
    file_get_contents(
        $files['account_nav']
    );

$manifest =
    file_get_contents(
        $files['manifest']
    );


foreach (
    [
        'organization_memberships',
        'organization_membership_verifications',
        'organization_appointments',
        'RoleAssignmentLifecycleService',
        'DynamicAccessService',
        'ScopedAuthorizationService',
        "'organizations.manage'",
        "'organization'",
        "'company'",
    ]
    as $needle
) {

    if (
        !str_contains(
            $service,
            $needle
        )
    ) {
        throw new RuntimeException(
            'service_contract_missing:'
            . $needle
        );
    }
}


foreach (
    [
        'CREATE TABLE',
        'DROP TABLE',
        'ticketing_projects',
        'support_projects',
        'NP-',
        'TSP-NEP',
    ]
    as $forbidden
) {

    if (
        str_contains(
            $service,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'service_genericity_failed:'
            . $forbidden
        );
    }
}


foreach (
    [
        '/admin/profile/affiliation',
        '/admin/organization-affiliations',
        '/admin/organization-affiliations/decision',
        'Csrf',
    ]
    as $needle
) {

    if (
        !str_contains(
            $routes,
            $needle
        )
    ) {
        throw new RuntimeException(
            'route_contract_missing:'
            . $needle
        );
    }
}


if (
    !str_contains(
        $web,
        'ORG_AFFILIATION_ROUTE_LOADER'
    )
    ||
    !str_contains(
        $web,
        'is_file($organizationalAffiliationRoutes)'
    )
    ||
    !str_contains(
        $web,
        'require $organizationalAffiliationRoutes'
    )
) {
    throw new RuntimeException(
        'guarded_route_loader_missing'
    );
}


/*
 * Core owns the route slice.
 * Shared web.php only has the guarded loader.
 */
if (
    str_contains(
        $manifest,
        'routes/organizational-affiliation.php'
    )
) {
    throw new RuntimeException(
        'core_only_route_must_not_be_shared'
    );
}


foreach (
    [
        "'/admin/profile/affiliation' => 'account.profile.view'",
        "'/admin/organization-affiliations' => 'organizations.manage'",
        "'/admin/organization-affiliations/decision' => 'organizations.manage'",
    ]
    as $needle
) {

    if (
        !str_contains(
            $navigation,
            $needle
        )
    ) {
        throw new RuntimeException(
            'navigation_contract_missing:'
            . $needle
        );
    }
}


if (
    !str_contains(
        $accountNav,
        "'href' => '/admin/profile/affiliation'"
    )
) {
    throw new RuntimeException(
        'account_navigation_link_missing'
    );
}


if (
    !str_contains(
        $panel,
        "'key' => 'organization-affiliations'"
    )
) {
    throw new RuntimeException(
        'organization_management_card_missing'
    );
}


foreach (
    [
        'organization_reference',
        'position_reference',
        'is_primary',
    ]
    as $needle
) {

    if (
        !str_contains(
            $selfView,
            $needle
        )
    ) {
        throw new RuntimeException(
            'self_form_contract_missing:'
            . $needle
        );
    }
}


foreach (
    [
        'membership_reference',
        'role_id',
        'include_descendants',
        'decision',
    ]
    as $needle
) {

    if (
        !str_contains(
            $managerView,
            $needle
        )
    ) {
        throw new RuntimeException(
            'manager_form_contract_missing:'
            . $needle
        );
    }
}


echo
    "ORG_AFFILIATION_WORKFLOW_FOUNDATION_TEST=PASS\n";

echo
    "PARALLEL_SCHEMA=NO\n";

echo
    "CORE_AUTHORITY=YES\n";

echo
    "SCOPED_MANAGER_ENFORCEMENT=YES\n";

echo
    "FIRST_CONSUMER_HARDCODE=NO\n";

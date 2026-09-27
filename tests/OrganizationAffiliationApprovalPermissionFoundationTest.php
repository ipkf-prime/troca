<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Organization/'
        . 'OrganizationalAffiliationService.php'
    );

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateOrganizationAffiliationApprovalPermissionFoundation.php'
    );

if (
    !is_string($service)
    || !is_string($migration)
) {
    throw new RuntimeException(
        'organization_affiliation_reviewer_source_unavailable'
    );
}

foreach (
    [
        'organizations.affiliations.manage',
        'organizations.manage',
    ]
    as $permission
) {
    if (
        !str_contains(
            $service,
            $permission
        )
    ) {
        throw new RuntimeException(
            'service_permission_missing:'
            . $permission
        );
    }
}

foreach (
    [
        'organizations.affiliations.manage',
        'access.manage',
        'system_admin',
        'central_admin',
        'province_admin',
        'county_admin',
        'company_admin',
        'admin_route_permissions',
    ]
    as $needle
) {
    if (
        !str_contains(
            $migration,
            $needle
        )
    ) {
        throw new RuntimeException(
            'migration_contract_missing:'
            . $needle
        );
    }
}

foreach (
    [
        'TSP-NEP',
        'cedfa0ee-646a-4219-87fd-36b68ad34714',
        '3d1d8abc-1a25-4ddd-a80a-77213861a05d',
    ]
    as $forbidden
) {
    if (
        str_contains(
            $service,
            $forbidden
        )
        ||
        str_contains(
            $migration,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'business_hardcode:'
            . $forbidden
        );
    }
}

echo "DEDICATED_AFFILIATION_REVIEW_PERMISSION=YES\n";
echo "LEGACY_ORGANIZATIONS_MANAGE_COMPATIBILITY=YES\n";
echo "SCOPED_AUTHORIZATION_PRESERVED=YES\n";
echo "ADMIN_ROUTE_PERMISSION_ALIGNMENT=YES\n";
echo "BUSINESS_HARDCODE=0\n";
echo "ORGANIZATION_AFFILIATION_APPROVAL_PERMISSION_FOUNDATION_TEST=PASS\n";

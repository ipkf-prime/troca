<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static fn (string $path): string =>
        file_get_contents(
            $root . '/' . $path
        );

$core =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateOrganizationCatalogMembershipFoundation.php'
    );

$ticketing =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateTicketingProjectOrganizationScopeFoundation.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$publicMigrate =
    $read(
        'public_html/public/migrate.php'
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
    'organization_catalogs',
    'organization_catalog_entries',
    'organization_identifier_schemes',
    'organization_external_identifiers',
    'organization_memberships',
    'organization_membership_verifications',
    'is_detachable',
    'source_catalog_id',
    'verification_state_code',
] as $needle) {
    $expect(
        str_contains($core, $needle),
        'Core commercial organization contract missing: '
        . $needle
    );
}


foreach ([
    'ticketing_project_catalog_bindings',
    'ticketing_support_project_member_scopes',
    'core_organization_membership_reference',
    'organization_reference',
    'organization_title_snapshot',
    'organization_role_code_snapshot',
    'scope_type_code',
    'scope_reference',
    'access_mode_code',
    'capabilities_json',
] as $needle) {
    $expect(
        str_contains($ticketing, $needle),
        'Ticketing organization scope contract missing: '
        . $needle
    );
}


$expect(
    str_contains(
        $registry,
        'CreateOrganizationCatalogMembershipFoundation::class'
    ),
    'Core organization catalog migration not registered.'
);

$expect(
    str_contains(
        $registry,
        'CreateTicketingProjectOrganizationScopeFoundation::class'
    ),
    'Ticketing organization scope migration not registered.'
);

$expect(
    str_contains(
        $publicMigrate,
        'CreateOrganizationCatalogMembershipFoundation()'
    ),
    'Core organization catalog migration missing from platform migration path.'
);


foreach ([
    'payesh',
    'cooperative',
    'rural_coop',
    'تعاونی',
    'تعاون روستایی',
    'کد پایش',
    'نهاده پخش',
] as $forbidden) {
    $expect(
        !str_contains(
            mb_strtolower(
                $core,
                'UTF-8'
            ),
            mb_strtolower(
                $forbidden,
                'UTF-8'
            )
        )
        &&
        !str_contains(
            mb_strtolower(
                $ticketing,
                'UTF-8'
            ),
            mb_strtolower(
                $forbidden,
                'UTF-8'
            )
        ),
        'Business-specific hardcode leaked into generic foundation: '
        . $forbidden
    );
}


$expect(
    !str_contains(
        $ticketing,
        'core.primary'
    ),
    'Ticketing schema must not contain Core DB coupling.'
);

$expect(
    !str_contains(
        $ticketing,
        'organizations(id)'
    ),
    'Cross-database organization FK is forbidden.'
);



foreach ([
    'org_catalog_entries_org_fk',
    'org_external_ids_org_fk',
    'org_memberships_org_fk',
    'org_memberships_person_fk',
    'org_memberships_user_fk',
    'org_memberships_approved_user_fk',
    'org_membership_verify_user_fk',
] as $legacyFk) {
    $expect(
        !str_contains(
            $core,
            $legacyFk
        ),
        'Commercial Core foundation must not hard-couple '
        . 'to legacy Core storage: '
        . $legacyFk
    );
}


echo "TICKETING_ORGANIZATION_SCOPE_FOUNDATION_PASS\n";

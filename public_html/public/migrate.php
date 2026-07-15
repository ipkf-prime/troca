<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

if (!\IPKF\Support\Env::isDebug()) {
    \IPKF\Support\Maintenance::deny('/migrate.php');
}

if (!\IPKF\Support\Maintenance::keyIsValid($_GET['key'] ?? null)) {
    \IPKF\Support\Maintenance::deny('/migrate.php');
}

try {
    $manager = new \IPKF\Database\DatabaseManager();
    $manager->migrations([
        new \IPKF\Database\Migrations\CreateRuntimeChecksTable(),
        new \IPKF\Database\Migrations\CreateAuthRbacSchemaTables(),
        new \IPKF\Database\Migrations\EnsureUtf8mb4AuthRbacTables(),
        new \IPKF\Database\Migrations\CreateIdentityAccessFoundationTables(),
        new \IPKF\Database\Migrations\CreateAdminPanelShellTables(),
        new \IPKF\Database\Migrations\AddScopedAdminThemeSettings(),
        new \IPKF\Database\Migrations\CreateAdminUsersOrganizationTables(),
        new \IPKF\Database\Migrations\CreateExtendedPersonDataTables(),
        new \IPKF\Database\Migrations\CreateDynamicOrganizationCoreTables(),
        new \IPKF\Database\Migrations\CreateDynamicGeographyTables(),
        new \IPKF\Database\Migrations\CreateMultiSourceCodingGeographyTables(),
        new \IPKF\Database\Migrations\CreateMinistryGeographyImportMetadata(),
        new \IPKF\Database\Migrations\CreateStatisticalCenterGeographyImportMetadata(),
        new \IPKF\Database\Migrations\CreateMinistrySciGeographyCrosswalkTables(),
        new \IPKF\Database\Migrations\CreateMinistryCanonicalGeographyTables(),
        new \IPKF\Database\Migrations\HardenMinistryCanonicalGeographyApply(),
        new \IPKF\Database\Migrations\CreateAutomationCorrespondenceFoundationTables(),
    ]);

    $manager->migrate();

    header('Content-Type: text/plain; charset=UTF-8');
    echo "MIGRATION DONE: ipkf_runtime_checks, auth_rbac_schema, identity_access_foundation, admin_panel_shell, scoped_admin_theme_settings, admin_users_organization, extended_person_data, dynamic_organization_core, dynamic_geography, multi_source_coding_geography, ministry_geography_import_metadata, statistical_center_geography_import_metadata, ministry_sci_geography_crosswalk, ministry_canonical_geography, ministry_canonical_geography_apply_recovery, automation_correspondence_foundation";
} catch (Throwable $exception) {
    http_response_code(500);
    echo "MIGRATION FAILED";
}

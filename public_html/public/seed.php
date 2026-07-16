<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

if (!\IPKF\Support\Env::isDebug()) {
    \IPKF\Support\Maintenance::deny('/seed.php');
}

if (!\IPKF\Support\Maintenance::keyIsValid($_GET['key'] ?? null)) {
    \IPKF\Support\Maintenance::deny('/seed.php');
}

try {
    $manager = new \IPKF\Database\DatabaseManager();
    $manager->seeders([
        new \IPKF\Database\Seeds\RuntimeCheckSeeder(),
        new \IPKF\Database\Seeds\AuthRbacSeeder(),
        new \IPKF\Database\Seeds\MultiSourceMetadataSeeder(),
        new \IPKF\Database\Seeds\AutomationCorrespondenceSeeder(),
        new \IPKF\Database\Seeds\PlatformCommercialFoundationSeeder(),
    ]);

    $manager->seed();

    header('Content-Type: text/plain; charset=UTF-8');
    echo "SEED DONE: foundation_v0_2, auth_rbac_schema, identity_access_foundation, admin_panel_shell, admin_users_organization, multi_source_metadata, ministry_geography_import_metadata, statistical_center_geography_import_metadata, ministry_canonical_geography_metadata, automation_correspondence_metadata, platform_commercial_metadata";
} catch (Throwable $exception) {
    http_response_code(500);
    echo "SEED FAILED";
}

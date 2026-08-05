<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

if (!\IPKF\Support\Env::isDebug()) {
    \IPKF\Support\Maintenance::deny('/migrate.php');
}

if (!\IPKF\Support\Maintenance::keyIsValid($_GET['key'] ?? null)) {
    \IPKF\Support\Maintenance::deny('/migrate.php');
}

$application = trim((string) ($_GET['application'] ?? ''));

if ($application !== '') {
    $allowedApplications = ['core', 'automation', 'work'];

    if (!in_array($application, $allowedApplications, true)) {
        \IPKF\Support\Maintenance::deny('/migrate.php');
    }

    try {
        $registry = new \IPKF\Database\Connections\ConnectionRegistry();
        $resolver = new \IPKF\Database\Connections\ConnectionResolver($registry);
        $health = new \IPKF\Database\Connections\ConnectionHealthChecker($resolver);
        $migrationRegistry = new \IPKF\Database\Application\ApplicationMigrationRegistry($resolver);
        $groups = $migrationRegistry->groups();
        $group = $groups[$application] ?? null;

        if (!is_array($group)) {
            throw new \RuntimeException('Application migration group is not available.');
        }

        $connectionName = (string) ($group['connection'] ?? '');
        $definition = $registry->get($connectionName);
        $runtimeMode = new \App\Services\Automation\AutomationRuntimeMode($registry);

        if ($application === 'automation') {
            if (!$runtimeMode->provisioningAllowed() || $definition === null
                || $definition->usesFallback() || !$definition->configured()) {
                throw new \RuntimeException('Dedicated application connection is required.');
            }
        }
        if ($application === 'work') {
            if ($definition === null || $definition->usesFallback() || !$definition->configured()) {
                throw new \RuntimeException('Dedicated application connection is required.');
            }
        }

        if ($definition === null
            || !$health->available($connectionName)
            || !$health->utf8mb4Ready($connectionName)
            || !$health->utcTimezoneApplied($connectionName)
        ) {
            throw new \RuntimeException('Application connection is not ready.');
        }

        if ($application === 'core') {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "APPLICATION MIGRATION DONE\n";
            echo "application=core\n";
            echo "applied_count=0";
            exit;
        }

        $pdo = $resolver->resolve($connectionName);
        $applied = (new \IPKF\Database\Application\ApplicationMigrationRunner())
            ->run($application, $connectionName, $migrationRegistry->migrationsFor($application), $pdo);

        header('Content-Type: text/plain; charset=UTF-8');
        echo "APPLICATION MIGRATION DONE\n";
        echo "application={$application}\n";
        echo "applied_count={$applied}";
    } catch (Throwable $exception) {
        $failedMigrationClass = "application_{$application}_migration";
        $privateException = $exception;

        if ($exception instanceof \IPKF\Database\Migrations\MigrationExecutionException) {
            $failedMigrationClass = $exception->migrationClass();
            $privateException = $exception->getPrevious() ?? $exception;
        }

        $failureReference = (new \IPKF\Database\Migrations\MigrationFailureLogger())
            ->log($failedMigrationClass, $privateException);

        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "APPLICATION MIGRATION FAILED\n";
        echo "failure_reference={$failureReference}";
    }

    exit;
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
        new \IPKF\Database\Migrations\CompleteOrganizationalIdentityAndSignatureFoundation(),
        new \IPKF\Database\Migrations\RepairLegacyJalaliAppointmentDates(),
        new \IPKF\Database\Migrations\CreateDynamicGeographyTables(),
        new \IPKF\Database\Migrations\CreateMultiSourceCodingGeographyTables(),
        new \IPKF\Database\Migrations\CreateMinistryGeographyImportMetadata(),
        new \IPKF\Database\Migrations\CreateStatisticalCenterGeographyImportMetadata(),
        new \IPKF\Database\Migrations\CreateMinistrySciGeographyCrosswalkTables(),
        new \IPKF\Database\Migrations\CreateMinistryCanonicalGeographyTables(),
        new \IPKF\Database\Migrations\HardenMinistryCanonicalGeographyApply(),
        new \IPKF\Database\Migrations\CreateAutomationCorrespondenceFoundationTables(),
        new \IPKF\Database\Migrations\CreateCorrespondenceDocumentTemplateTables(),
        new \IPKF\Database\Migrations\CreatePlatformCommercialFoundationTables(),
        new \IPKF\Database\Migrations\CreateApplicationMigrationHistoryTable(),
        new \IPKF\Database\Migrations\CreateApplicationModuleRegistryTable(),
        new \IPKF\Database\Migrations\ExtendApplicationModuleRegistryRuntimeConfig(),
        new \IPKF\Database\Migrations\CreateAuthenticationLoginHistoryTable(),
        new \IPKF\Database\Migrations\RepairPersonAddressReferenceDataAndGeography(),
        new \IPKF\Database\Migrations\CreateNotificationCoreFoundationTables(),
        new \IPKF\Database\Migrations\CreateCommunicationCenterFoundationTables(),
        new \IPKF\Database\Migrations\CreateSecureMessageExtensionTables(),
        new \IPKF\Database\Migrations\ExtendNotificationProviderManagement(),
        new \IPKF\Database\Migrations\EnableNotificationProviderManagementCrud(),
        new \IPKF\Database\Migrations\ExpandNotificationProviderCatalog(),
        new \IPKF\Database\Migrations\ExtendEmailProviderSenderIdentity(),
        new \IPKF\Database\Migrations\EnableNotificationProviderTestSend(),
        new \IPKF\Database\Migrations\EnableNotificationProviderExtendedTestSend(),
        new \IPKF\Database\Migrations\EnableNotificationProviderDefaultManagement(),
        new \IPKF\Database\Migrations\CreateNotificationGatewayFoundation(),
        new \IPKF\Database\Migrations\EnableNotificationSendCenterFoundation(),
    ]);

    $manager->migrate();

    header('Content-Type: text/plain; charset=UTF-8');
    echo "MIGRATION DONE: ipkf_runtime_checks, auth_rbac_schema, identity_access_foundation, admin_panel_shell, scoped_admin_theme_settings, admin_users_organization, extended_person_data, dynamic_organization_core, legacy_jalali_appointment_date_repair, dynamic_geography, multi_source_coding_geography, ministry_geography_import_metadata, statistical_center_geography_import_metadata, ministry_sci_geography_crosswalk, ministry_canonical_geography, ministry_canonical_geography_apply_recovery, automation_correspondence_foundation, correspondence_document_templates, platform_commercial_foundation, application_migration_history, application_module_registry, application_module_runtime_config, authentication_login_history, person_address_reference_data_and_geography, notification_core_foundation, communication_center_foundation, secure_message_extensions";
} catch (Throwable $exception) {
    $failedMigrationClass = 'unknown';
    $failedMigrationName = 'unknown';
    $privateException = $exception;

    if ($exception instanceof \IPKF\Database\Migrations\MigrationExecutionException) {
        $failedMigrationClass = $exception->migrationClass();
        $failedMigrationName = $exception->migrationBasename();
        $privateException = $exception->getPrevious() ?? $exception;
    }

    $failureReference = (new \IPKF\Database\Migrations\MigrationFailureLogger())
        ->log($failedMigrationClass, $privateException);

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "MIGRATION FAILED\n";
    echo "failure_reference={$failureReference}\n";
    echo "failed_migration={$failedMigrationName}";
}

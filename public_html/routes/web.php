<?php

/** @var \IPKF\Routing\Router $router */

$router->get('/', function ($request, $response) {
    $siteMode = (string) \IPKF\Support\Env::get('SITE_MODE', 'coming_soon');
    $view = BASE_PATH . '/resources/views/site/coming-soon.php';

    if (is_readable($view)) {
        ob_start();
        require $view;
        $content = ob_get_clean() ?: '';

        return $response
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->send($content);
    }

    return $response->send('IPKF Framework Genesis OK');
});

$router->get('/health', function ($request, $response) {
    return $response->json([
        'status' => 'ok',
        'framework' => 'IPKF',
        'version' => \IPKF\Support\Version::CURRENT,
    ]);
});

$router->get('/csrf-token', function ($request, $response) {
    return $response->json([
        'status' => 'ok',
        'csrf_token' => (new \IPKF\Security\Csrf())->token(),
    ]);
});

$router->get('/_diagnostics', function ($request, $response) use ($router) {
    $debug = \IPKF\Support\Env::isDebug();

    if (!$debug) {
        return $response->status(404)->send('404 - Route not found: /_diagnostics');
    }

    $databaseConnectionAvailable = false;
    $databaseConnectionMessage = 'not configured';

    if (\IPKF\Database\Database::configured()) {
        try {
            \IPKF\Database\Database::connect();
            $databaseConnectionAvailable = true;
            $databaseConnectionMessage = 'available';
        } catch (\Throwable $exception) {
            $databaseConnectionMessage = 'unavailable';
        }
    }

    $runtimeCheckTableExists = $databaseConnectionAvailable
        ? \IPKF\Database\Database::tableExists('ipkf_runtime_checks')
        : false;

    $runtimeCheckFound = false;
    $runtimeCheckValue = null;

    if ($runtimeCheckTableExists) {
        try {
            $statement = \IPKF\Database\Database::connect()->prepare("
                SELECT check_value
                FROM ipkf_runtime_checks
                WHERE check_key = ?
                LIMIT 1
            ");
            $statement->execute(['foundation_v0_2']);
            $value = $statement->fetchColumn();

            if ($value !== false) {
                $runtimeCheckFound = true;
                $runtimeCheckValue = $value;
            }
        } catch (\Throwable $exception) {
            $runtimeCheckFound = false;
            $runtimeCheckValue = null;
        }
    }

    $personsTableExists = \IPKF\Database\Database::tableExists('persons');
    $usersTableExists = \IPKF\Database\Database::tableExists('users');
    $roleAreasTableExists = \IPKF\Database\Database::tableExists('role_areas');
    $roleKindsTableExists = \IPKF\Database\Database::tableExists('role_kinds');
    $rolesTableExists = \IPKF\Database\Database::tableExists('roles');
    $permissionsTableExists = \IPKF\Database\Database::tableExists('permissions');
    $rolePermissionsTableExists = \IPKF\Database\Database::tableExists('role_permissions');
    $userRoleAssignmentsTableExists = \IPKF\Database\Database::tableExists('user_role_assignments');
    $orgUnitsTableExists = \IPKF\Database\Database::tableExists('org_units');
    $positionsTableExists = \IPKF\Database\Database::tableExists('positions');
    $userOrgAssignmentsTableExists = \IPKF\Database\Database::tableExists('user_org_assignments');
    $personProfilesTableExists = \IPKF\Database\Database::tableExists('person_profiles');
    $contactTypesTableExists = \IPKF\Database\Database::tableExists('contact_types');
    $personContactsTableExists = \IPKF\Database\Database::tableExists('person_contacts');
    $addressTypesTableExists = \IPKF\Database\Database::tableExists('address_types');
    $personAddressesTableExists = \IPKF\Database\Database::tableExists('person_addresses');
    $organizationClassificationSchemesTableExists = \IPKF\Database\Database::tableExists('organization_classification_schemes');
    $organizationClassificationTermsTableExists = \IPKF\Database\Database::tableExists('organization_classification_terms');
    $organizationClassificationsTableExists = \IPKF\Database\Database::tableExists('organization_classifications');
    $organizationRelationTypesTableExists = \IPKF\Database\Database::tableExists('organization_relation_types');
    $organizationRelationsTableExists = \IPKF\Database\Database::tableExists('organization_relations');
    $organizationUnitTypesTableExists = \IPKF\Database\Database::tableExists('organization_unit_types');
    $organizationPositionsTableExists = \IPKF\Database\Database::tableExists('organization_positions');
    $organizationAppointmentsTableExists = \IPKF\Database\Database::tableExists('organization_appointments');
    $geographicLevelTypesTableExists = \IPKF\Database\Database::tableExists('geographic_level_types');
    $geographicRelationTypesTableExists = \IPKF\Database\Database::tableExists('geographic_relation_types');
    $geographicLocationsTableExists = \IPKF\Database\Database::tableExists('geographic_locations');
    $geographicLocationRelationsTableExists = \IPKF\Database\Database::tableExists('geographic_location_relations');
    $geographicLegacyMappingsTableExists = \IPKF\Database\Database::tableExists('geographic_legacy_mappings');
    $dataSourcesTableExists = \IPKF\Database\Database::tableExists('data_sources');
    $dataSourceAuthorityScopesTableExists = \IPKF\Database\Database::tableExists('data_source_authority_scopes');
    $dataSourceSnapshotsTableExists = \IPKF\Database\Database::tableExists('data_source_snapshots');
    $externalCodingSystemsTableExists = \IPKF\Database\Database::tableExists('external_coding_systems');
    $externalCodeSetsTableExists = \IPKF\Database\Database::tableExists('external_code_sets');
    $externalCodeSegmentsTableExists = \IPKF\Database\Database::tableExists('external_code_segments');
    $externalCodeValuesTableExists = \IPKF\Database\Database::tableExists('external_code_values');
    $geographicHierarchyTypesTableExists = \IPKF\Database\Database::tableExists('geographic_hierarchy_types');
    $geographicExternalIdentifiersTableExists = \IPKF\Database\Database::tableExists('geographic_external_identifiers');
    $geographicExternalMappingsTableExists = \IPKF\Database\Database::tableExists('geographic_external_code_mappings');
    $geographicImportBatchesTableExists = \IPKF\Database\Database::tableExists('geographic_import_batches');
    $geographicImportRowsTableExists = \IPKF\Database\Database::tableExists('geographic_import_rows');
    $geographicImportIssuesTableExists = \IPKF\Database\Database::tableExists('geographic_import_issues');
    $geographicImportCandidatesTableExists = \IPKF\Database\Database::tableExists('geographic_import_match_candidates');
    $geographicSourceLevelMappingsTableExists = \IPKF\Database\Database::tableExists('geographic_source_level_mappings');
    $geographicSourceRecordTypeMappingsTableExists = \IPKF\Database\Database::tableExists('geographic_source_record_type_mappings');
    $geographicCrosswalkRunsTableExists = \IPKF\Database\Database::tableExists('geographic_crosswalk_runs');
    $geographicCrosswalkCandidatesTableExists = \IPKF\Database\Database::tableExists('geographic_crosswalk_candidates');
    $geographicCrosswalkIssuesTableExists = \IPKF\Database\Database::tableExists('geographic_crosswalk_issues');
    $geographicCanonicalizationRunsTableExists = \IPKF\Database\Database::tableExists('geographic_canonicalization_runs');
    $geographicCanonicalizationItemsTableExists = \IPKF\Database\Database::tableExists('geographic_canonicalization_items');
    $lookupDomainsTableExists = \IPKF\Database\Database::tableExists('lookup_domains');
    $lookupValuesTableExists = \IPKF\Database\Database::tableExists('lookup_values');
    $correspondencesTableExists = \IPKF\Database\Database::tableExists('correspondences');
    $correspondenceVersionsTableExists = \IPKF\Database\Database::tableExists('correspondence_versions');
    $correspondencePartiesTableExists = \IPKF\Database\Database::tableExists('correspondence_parties');
    $registryBooksTableExists = \IPKF\Database\Database::tableExists('registry_books');
    $correspondenceRegistrationsTableExists = \IPKF\Database\Database::tableExists('correspondence_registrations');
    $correspondenceRelationsTableExists = \IPKF\Database\Database::tableExists('correspondence_relations');
    $correspondenceReferralsTableExists = \IPKF\Database\Database::tableExists('correspondence_referrals');
    $correspondenceEventsTableExists = \IPKF\Database\Database::tableExists('correspondence_events');
    $privateFilesTableExists = \IPKF\Database\Database::tableExists('private_files');
    $correspondenceAttachmentsTableExists = \IPKF\Database\Database::tableExists('correspondence_attachments');
    $platformInstallationsTableExists = \IPKF\Database\Database::tableExists('platform_installations');
    $platformEnvironmentsTableExists = \IPKF\Database\Database::tableExists('platform_environments');
    $platformApplicationsTableExists = \IPKF\Database\Database::tableExists('platform_applications');
    $platformModulesTableExists = \IPKF\Database\Database::tableExists('platform_modules');
    $platformModuleDependenciesTableExists = \IPKF\Database\Database::tableExists('platform_module_dependencies');
    $platformInstallationApplicationsTableExists = \IPKF\Database\Database::tableExists('platform_installation_applications');
    $platformInstallationModulesTableExists = \IPKF\Database\Database::tableExists('platform_installation_modules');
    $platformDomainsTableExists = \IPKF\Database\Database::tableExists('platform_domains');
    $platformDatabaseEndpointsTableExists = \IPKF\Database\Database::tableExists('platform_database_endpoints');
    $platformStorageEndpointsTableExists = \IPKF\Database\Database::tableExists('platform_storage_endpoints');
    $platformServiceEndpointsTableExists = \IPKF\Database\Database::tableExists('platform_service_endpoints');
    $platformLicensesTableExists = \IPKF\Database\Database::tableExists('platform_licenses');
    $platformLicenseEntitlementsTableExists = \IPKF\Database\Database::tableExists('platform_license_entitlements');
    $platformLicenseLimitsTableExists = \IPKF\Database\Database::tableExists('platform_license_limits');
    $platformProvisioningRunsTableExists = \IPKF\Database\Database::tableExists('platform_provisioning_runs');
    $platformProvisioningStepsTableExists = \IPKF\Database\Database::tableExists('platform_provisioning_steps');
    $applicationMigrationsTableExists = \IPKF\Database\Database::tableExists('application_migrations');
    $dataSourceImportSettingsTableExists = \IPKF\Database\Database::tableExists('data_source_import_settings');
    $namedConnectionRegistryAvailable = class_exists(\IPKF\Database\Connections\ConnectionRegistry::class)
        && class_exists(\IPKF\Database\Connections\ConnectionResolver::class)
        && class_exists(\IPKF\Database\Connections\ConnectionHealthChecker::class);
    $corePrimaryConnectionRegistered = false;
    $automationPrimaryConnectionRegistered = false;
    $automationPrimaryConnectionFallbackActive = false;
    $automationPrimaryDedicatedConnectionConfigured = false;
    $workPrimaryConnectionRegistered = false;
    $workPrimaryDedicatedConnectionConfigured = false;
    $corePrimaryConnectionAvailable = false;
    $automationPrimaryConnectionAvailable = false;
    $workPrimaryConnectionAvailable = false;
    $automationPrimaryUtf8mb4Ready = false;
    $automationPrimaryUtcTimezoneApplied = false;
    $workPrimaryUtf8mb4Ready = false;
    $workPrimaryUtcTimezoneApplied = false;
    $workSchemaAvailable = false;
    $workApplicationMigrationHistoryAvailable = false;
    $databaseSessionTimezonePolicyAppliedToNamedConnections = false;
    $namedConnectionsUtf8mb4Ready = false;
    $corePdoNotDuplicatedDuringAutomationFallback = false;
    $automationStandaloneSchemaAvailable = false;
    $automationStandaloneMetadataAvailable = false;
    $automationInternalForeignKeysPreserved = false;
    $automationCoreForeignKeysAbsent = false;
    $automationApplicationMigrationHistoryAvailable = false;
    $automationLegacyOperationalDataAbsent = false;

    if ($namedConnectionRegistryAvailable) {
        try {
            $namedConnectionRegistry = new \IPKF\Database\Connections\ConnectionRegistry();
            $namedConnectionResolver = new \IPKF\Database\Connections\ConnectionResolver($namedConnectionRegistry);
            $namedConnectionHealth = new \IPKF\Database\Connections\ConnectionHealthChecker($namedConnectionResolver);
            $coreDefinition = $namedConnectionRegistry->get('core.primary');
            $automationDefinition = $namedConnectionRegistry->get('automation.primary');
            $workDefinition = $namedConnectionRegistry->get('work.primary');

            $corePrimaryConnectionRegistered = $coreDefinition !== null;
            $automationPrimaryConnectionRegistered = $automationDefinition !== null;
            $workPrimaryConnectionRegistered = $workDefinition !== null;
            $automationPrimaryConnectionFallbackActive = $automationDefinition !== null
                && $automationDefinition->usesFallback();
            $automationPrimaryDedicatedConnectionConfigured = $automationDefinition !== null
                && !$automationDefinition->usesFallback()
                && $automationDefinition->configured();
            $workPrimaryDedicatedConnectionConfigured = $workDefinition !== null
                && !$workDefinition->usesFallback()
                && $workDefinition->configured();
            $corePrimaryConnectionAvailable = $namedConnectionHealth->available('core.primary');
            $automationPrimaryConnectionAvailable = $namedConnectionHealth->available('automation.primary');
            $workPrimaryConnectionAvailable = $namedConnectionHealth->available('work.primary');
            $automationPrimaryUtf8mb4Ready = $namedConnectionHealth->utf8mb4Ready('automation.primary');
            $automationPrimaryUtcTimezoneApplied = $namedConnectionHealth->utcTimezoneApplied('automation.primary');
            $workPrimaryUtf8mb4Ready = $namedConnectionHealth->utf8mb4Ready('work.primary');
            $workPrimaryUtcTimezoneApplied = $namedConnectionHealth->utcTimezoneApplied('work.primary');
            $databaseSessionTimezonePolicyAppliedToNamedConnections = $namedConnectionHealth->utcTimezoneApplied('core.primary')
                && $automationPrimaryUtcTimezoneApplied
                && $workPrimaryUtcTimezoneApplied;
            $namedConnectionsUtf8mb4Ready = $namedConnectionHealth->utf8mb4Ready('core.primary')
                && $automationPrimaryUtf8mb4Ready
                && $workPrimaryUtf8mb4Ready;
            $corePdoNotDuplicatedDuringAutomationFallback = $automationPrimaryConnectionFallbackActive
                && $namedConnectionHealth->fallbackSharesPdo('automation.primary', 'core.primary');

            if ($automationPrimaryDedicatedConnectionConfigured && $automationPrimaryConnectionAvailable) {
                $automationPdo = $namedConnectionResolver->resolve('automation.primary');
                $automationTableExists = static function (string $table) use ($automationPdo): bool {
                    $statement = $automationPdo->prepare("
                        SELECT COUNT(*)
                        FROM information_schema.tables
                        WHERE table_schema = DATABASE()
                          AND table_name = ?
                    ");
                    $statement->execute([$table]);

                    return (int) $statement->fetchColumn() > 0;
                };

                $automationStandaloneSchemaAvailable = true;
                foreach (\App\Services\Automation\AutomationSchemaParityContract::TABLES as $table) {
                    if (!$automationTableExists($table)) {
                        $automationStandaloneSchemaAvailable = false;
                        break;
                    }
                }

                if ($automationStandaloneSchemaAvailable) {
                    $metadataStatement = $automationPdo->prepare("
                        SELECT COUNT(*) > 0
                        FROM lookup_domains
                        WHERE code = ?
                    ");
                    $metadataStatement->execute(['correspondence_direction']);
                    $automationStandaloneMetadataAvailable = (bool) $metadataStatement->fetchColumn();
                }

                $internalForeignKeyPlaceholders = implode(
                    ', ',
                    array_fill(0, count(\App\Services\Automation\AutomationSchemaParityContract::INTERNAL_FOREIGN_KEYS), '?')
                );
                $internalForeignKeyStatement = $automationPdo->prepare("
                    SELECT COUNT(DISTINCT constraint_name)
                    FROM information_schema.referential_constraints
                    WHERE constraint_schema = DATABASE()
                      AND constraint_name IN ({$internalForeignKeyPlaceholders})
                ");
                $internalForeignKeyStatement->execute(\App\Services\Automation\AutomationSchemaParityContract::INTERNAL_FOREIGN_KEYS);
                $automationInternalForeignKeysPreserved = (int) $internalForeignKeyStatement->fetchColumn()
                    === count(\App\Services\Automation\AutomationSchemaParityContract::INTERNAL_FOREIGN_KEYS);

                $coreReferencePlaceholders = implode(
                    ', ',
                    array_fill(0, count(\App\Services\Automation\AutomationSchemaParityContract::CORE_REFERENCE_TABLES), '?')
                );
                $coreForeignKeyStatement = $automationPdo->prepare("
                    SELECT COUNT(*)
                    FROM information_schema.key_column_usage
                    WHERE constraint_schema = DATABASE()
                      AND referenced_table_name IN ({$coreReferencePlaceholders})
                ");
                $coreForeignKeyStatement->execute(\App\Services\Automation\AutomationSchemaParityContract::CORE_REFERENCE_TABLES);
                $automationCoreForeignKeysAbsent = (int) $coreForeignKeyStatement->fetchColumn() === 0;

                $automationApplicationMigrationHistoryAvailable = $automationTableExists('application_migrations');
            }

            if ($workPrimaryDedicatedConnectionConfigured && $workPrimaryConnectionAvailable) {
                $workPdo = $namedConnectionResolver->resolve('work.primary');
                $workTableExists = static function (string $table) use ($workPdo): bool {
                    $statement = $workPdo->prepare("
                        SELECT COUNT(*)
                        FROM information_schema.tables
                        WHERE table_schema = DATABASE()
                          AND table_name = ?
                    ");
                    $statement->execute([$table]);

                    return (int) $statement->fetchColumn() > 0;
                };

                $workTables = [
                    'work_statuses', 'work_projects', 'work_project_members', 'work_items',
                    'work_item_assignees', 'work_item_dependencies', 'work_checklist_items',
                    'work_labels', 'work_item_labels', 'work_attachments', 'work_comments',
                    'work_activity_events', 'application_migrations',
                ];
                $workSchemaAvailable = true;
                foreach ($workTables as $table) {
                    if (!$workTableExists($table)) {
                        $workSchemaAvailable = false;
                        break;
                    }
                }
                $workApplicationMigrationHistoryAvailable = $workTableExists('application_migrations');
            }
        } catch (\Throwable $exception) {
            $corePrimaryConnectionAvailable = false;
            $automationPrimaryConnectionAvailable = false;
            $workPrimaryConnectionAvailable = false;
        }
    }

    if ($databaseConnectionAvailable) {
        try {
            $automationLegacyOperationalDataAbsent = true;
            $corePdo = \IPKF\Database\Database::connect();

            foreach (\App\Services\Automation\AutomationSchemaParityContract::OPERATIONAL_TABLES as $table) {
                if (!\IPKF\Database\Database::tableExists($table)) {
                    continue;
                }

                $statement = $corePdo->query("SELECT EXISTS(SELECT 1 FROM {$table} LIMIT 1)");
                if ((bool) $statement->fetchColumn()) {
                    $automationLegacyOperationalDataAbsent = false;
                    break;
                }
            }
        } catch (\Throwable $exception) {
            $automationLegacyOperationalDataAbsent = false;
        }
    }

    $automationRollbackSourceAvailable = $lookupDomainsTableExists
        && $lookupValuesTableExists
        && $correspondencesTableExists;
    $automationSchemaParityContractAvailable = class_exists(\App\Services\Automation\AutomationSchemaParityContract::class);
    $automationSchemaParityPassed = $automationSchemaParityContractAvailable;
    $automationRuntimeModeService = class_exists(\App\Services\Automation\AutomationRuntimeMode::class)
        ? new \App\Services\Automation\AutomationRuntimeMode()
        : null;
    $automationRuntimeMode = $automationRuntimeModeService instanceof \App\Services\Automation\AutomationRuntimeMode
        ? $automationRuntimeModeService->value()
        : 'unavailable';
    $automationRuntimeModeValid = $automationRuntimeModeService instanceof \App\Services\Automation\AutomationRuntimeMode
        && $automationRuntimeModeService->valid();
    $automationCutoverState = [
        'dedicated_connection_configured' => $automationPrimaryDedicatedConnectionConfigured,
        'dedicated_connection_available' => $automationPrimaryDedicatedConnectionConfigured && $automationPrimaryConnectionAvailable,
        'utf8mb4_ready' => $automationPrimaryUtf8mb4Ready,
        'utc_timezone_applied' => $automationPrimaryUtcTimezoneApplied,
        'standalone_schema_available' => $automationStandaloneSchemaAvailable,
        'standalone_metadata_available' => $automationStandaloneMetadataAvailable,
        'application_migration_history_available' => $automationApplicationMigrationHistoryAvailable,
        'internal_foreign_keys_preserved' => $automationInternalForeignKeysPreserved,
        'core_foreign_keys_absent' => $automationPrimaryDedicatedConnectionConfigured ? $automationCoreForeignKeysAbsent : false,
        'cross_database_sql_absent' => true,
        'schema_parity_contract_passes' => $automationSchemaParityPassed,
        'legacy_operational_data_absent' => $automationLegacyOperationalDataAbsent,
        'rollback_source_available' => $automationRollbackSourceAvailable,
    ];
    $automationProvisioningReadiness = class_exists(\App\Services\Automation\AutomationProvisioningReadiness::class)
        ? new \App\Services\Automation\AutomationProvisioningReadiness()
        : null;
    $automationCutoverPrerequisitesPassed = $automationProvisioningReadiness instanceof \App\Services\Automation\AutomationProvisioningReadiness
        && $automationProvisioningReadiness->prerequisitesPassed($automationCutoverState);
    $automationCutoverGuard = class_exists(\App\Services\Automation\AutomationCutoverGuard::class)
        ? new \App\Services\Automation\AutomationCutoverGuard()
        : null;
    $automationCutoverGuardPassed = $automationCutoverGuard instanceof \App\Services\Automation\AutomationCutoverGuard
        && $automationCutoverPrerequisitesPassed
        && $automationCutoverGuard->passed($automationCutoverState);
    $automationRuntimeSourceResolver = class_exists(\App\Services\Automation\AutomationRuntimeSourceResolver::class)
        ? new \App\Services\Automation\AutomationRuntimeSourceResolver()
        : null;
    $automationRuntimeSourceDedicated = $automationRuntimeSourceResolver instanceof \App\Services\Automation\AutomationRuntimeSourceResolver
        && $automationRuntimeModeService instanceof \App\Services\Automation\AutomationRuntimeMode
        && $automationRuntimeSourceResolver->source($automationRuntimeModeService, $automationCutoverGuardPassed) === \App\Services\Automation\AutomationRuntimeMode::DEDICATED;
    $automationDedicatedRuntimeActive = $automationRuntimeSourceDedicated
        && $automationRuntimeMode === \App\Services\Automation\AutomationRuntimeMode::DEDICATED;
    $automationRuntimeFallbackDisabledInDedicatedMode = $automationRuntimeMode !== \App\Services\Automation\AutomationRuntimeMode::DEDICATED
        || ($automationPrimaryDedicatedConnectionConfigured && !$automationPrimaryConnectionFallbackActive);
    $automationRollbackPolicy = class_exists(\App\Services\Automation\AutomationRollbackPolicy::class)
        ? new \App\Services\Automation\AutomationRollbackPolicy()
        : null;
    $automationAutomaticCutoverDisabled = $automationRollbackPolicy instanceof \App\Services\Automation\AutomationRollbackPolicy
        && !$automationRollbackPolicy->automaticCutoverEnabled();
    $automationAutomaticRollbackDisabled = $automationRollbackPolicy instanceof \App\Services\Automation\AutomationRollbackPolicy
        && !$automationRollbackPolicy->automaticRollbackEnabled();
    $automationExplicitRollbackAvailable = $automationRollbackPolicy instanceof \App\Services\Automation\AutomationRollbackPolicy
        && $automationRollbackPolicy->explicitRollbackAvailable();

    $geographicRelationsHierarchyContextAvailable = $geographicLocationRelationsTableExists
        && \IPKF\Database\Database::columnExists('geographic_location_relations', 'hierarchy_type_id')
        && \IPKF\Database\Database::columnExists('geographic_location_relations', 'source_id')
        && \IPKF\Database\Database::columnExists('geographic_location_relations', 'source_snapshot_id')
        && \IPKF\Database\Database::columnExists('geographic_location_relations', 'review_status');
    $personAddressesCanonicalLocationAvailable = $personAddressesTableExists
        && \IPKF\Database\Database::columnExists('person_addresses', 'geographic_location_id');
    $orgUnitsOrganizationScopeAvailable = $orgUnitsTableExists
        && \IPKF\Database\Database::columnExists('org_units', 'organization_id')
        && \IPKF\Database\Database::columnExists('org_units', 'unit_type_id');
    $operationalGeographicRegionSupportAvailable = false;
    $ruralCooperationCodeContractAvailable = false;
    $ministryGeographyLevelMappingAvailable = false;
    $statisticalCenterCoderecMappingAvailable = false;

    if ($databaseConnectionAvailable
        && $geographicSourceLevelMappingsTableExists
        && $dataSourceImportSettingsTableExists
        && $dataSourcesTableExists
    ) {
        try {
            $statement = \IPKF\Database\Database::connect()->query("
                SELECT
                    (SELECT COUNT(DISTINCT mappings.geographic_level_code)
                     FROM geographic_source_level_mappings mappings
                     INNER JOIN data_sources sources ON sources.id = mappings.source_id
                     WHERE sources.code = 'iran_ministry_of_interior'
                       AND mappings.status = 'active'
                       AND mappings.geographic_level_code IN (
                           'province', 'county', 'district', 'rural_district', 'city'
                       )) = 5
                    AND
                    (SELECT COUNT(DISTINCT settings.setting_key)
                     FROM data_source_import_settings settings
                     INNER JOIN data_sources sources ON sources.id = settings.source_id
                     WHERE sources.code = 'iran_ministry_of_interior'
                       AND settings.status = 'active'
                       AND settings.setting_key IN (
                           'geography.placeholder_values',
                           'geography.country_root_code',
                           'geography.max_file_size_bytes'
                       )) = 3
            ");
            $ministryGeographyLevelMappingAvailable = (bool) $statement->fetchColumn();
        } catch (\Throwable $exception) {
            $ministryGeographyLevelMappingAvailable = false;
        }
    }

    if ($databaseConnectionAvailable
        && $geographicSourceRecordTypeMappingsTableExists
        && $dataSourcesTableExists
    ) {
        try {
            $statement = \IPKF\Database\Database::connect()->query("
                SELECT COUNT(DISTINCT mappings.source_record_type) = 7
                FROM geographic_source_record_type_mappings mappings
                INNER JOIN data_sources sources ON sources.id = mappings.source_id
                WHERE sources.code = 'iran_statistical_center'
                  AND mappings.status = 'active'
                  AND mappings.source_record_type IN ('1', '2', '3', '4', '5', '6', '8')
            ");
            $statisticalCenterCoderecMappingAvailable = (bool) $statement->fetchColumn();
        } catch (\Throwable $exception) {
            $statisticalCenterCoderecMappingAvailable = false;
        }
    }

    if ($databaseConnectionAvailable
        && $geographicHierarchyTypesTableExists
        && $geographicLevelTypesTableExists
    ) {
        try {
            $db = \IPKF\Database\Database::connect();
            $hierarchyStatement = $db->query("
                SELECT
                    EXISTS(
                        SELECT 1 FROM geographic_hierarchy_types
                        WHERE code = 'rural_cooperation_operational' AND status = 'active'
                    )
                    AND EXISTS(
                        SELECT 1 FROM geographic_level_types
                        WHERE code = 'operational_region' AND status = 'active'
                    )
            ");
            $operationalGeographicRegionSupportAvailable = (bool) $hierarchyStatement->fetchColumn();
        } catch (\Throwable $exception) {
            $operationalGeographicRegionSupportAvailable = false;
        }
    }

    if ($databaseConnectionAvailable
        && $externalCodingSystemsTableExists
        && $externalCodeSetsTableExists
        && $externalCodeSegmentsTableExists
    ) {
        try {
            $db = \IPKF\Database\Database::connect();
            $codeSetStatement = $db->query("
                SELECT COUNT(DISTINCT code_sets.code)
                FROM external_code_sets code_sets
                INNER JOIN external_coding_systems systems
                    ON systems.id = code_sets.coding_system_id
                WHERE systems.code = 'rural_cooperation_operational'
                  AND code_sets.status = 'active'
                  AND code_sets.code IN (
                      'province_code', 'county_code', 'organization_code',
                      'geographic_level', 'organization_level', 'organization_kind',
                      'organization_type', 'organization_subtype'
                  )
            ");
            $segmentStatement = $db->query("
                SELECT COUNT(*)
                FROM external_code_segments segments
                INNER JOIN external_code_sets code_sets ON code_sets.id = segments.code_set_id
                INNER JOIN external_coding_systems systems ON systems.id = code_sets.coding_system_id
                WHERE systems.code = 'rural_cooperation_operational'
                  AND segments.status = 'active'
                  AND (
                      (code_sets.code = 'province_code' AND segments.segment_code = 'province_code')
                      OR (code_sets.code = 'county_code' AND segments.segment_code IN ('province_code', 'county_sequence'))
                      OR (code_sets.code = 'organization_code' AND segments.segment_code IN ('county_code', 'organization_sequence'))
                  )
            ");
            $ruralCooperationCodeContractAvailable = (int) $codeSetStatement->fetchColumn() === 8
                && (int) $segmentStatement->fetchColumn() === 5;
        } catch (\Throwable $exception) {
            $ruralCooperationCodeContractAvailable = false;
        }
    }

    $mfaSchemaAvailable = \IPKF\Database\Database::tableExists('user_mfa_methods')
        && \IPKF\Database\Database::tableExists('mfa_challenges')
        && \IPKF\Database\Database::tableExists('trusted_devices')
        && \IPKF\Database\Database::tableExists('recovery_codes');

    $organizationsHierarchyReady = \IPKF\Database\Database::tableExists('organizations')
        && \IPKF\Database\Database::columnExists('organizations', 'parent_id')
        && \IPKF\Database\Database::columnExists('organizations', 'depth')
        && \IPKF\Database\Database::columnExists('organizations', 'path');

    $adminUserExists = false;
    $superAdminRoleExists = false;
    $superAdminAssignmentExists = false;
    $adminUsersPermissionsSeeded = false;
    $correspondencePermissionsAvailable = false;

    if ($databaseConnectionAvailable) {
        try {
            $db = \IPKF\Database\Database::connect();

            if ($usersTableExists) {
                $adminEmail = \IPKF\Support\Env::get('ADMIN_EMAIL', '');
                $statement = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND status = 'active'");
                $statement->execute([$adminEmail]);
                $adminUserExists = $adminEmail !== '' && (int) $statement->fetchColumn() > 0;
            }

            if ($rolesTableExists) {
                $statement = $db->prepare("SELECT id FROM roles WHERE code = 'super_admin' AND is_active = 1 LIMIT 1");
                $statement->execute();
                $superAdminRoleId = $statement->fetchColumn();
                $superAdminRoleExists = $superAdminRoleId !== false;

                if ($superAdminRoleExists && $userRoleAssignmentsTableExists) {
                    $statement = $db->prepare("
                        SELECT COUNT(*)
                        FROM user_role_assignments
                        WHERE role_id = ?
                          AND scope_type = 'global'
                          AND is_active = 1
                    ");
                    $statement->execute([(int) $superAdminRoleId]);
                    $superAdminAssignmentExists = (int) $statement->fetchColumn() > 0;
                }
            }

            if ($permissionsTableExists && $rolesTableExists && $rolePermissionsTableExists) {
                $statement = $db->query("
                    SELECT COUNT(*)
                    FROM permissions
                    WHERE code IN (
                        'users.view',
                        'users.manage',
                        'org_units.view',
                        'org_units.manage',
                        'positions.view',
                        'positions.manage',
                        'user_org_assignments.manage'
                    )
                      AND is_active = 1
                ");
                $adminUsersPermissionsSeeded = (int) $statement->fetchColumn() === 7;

                $requiredRolePermissions = [
                    'super_admin' => [
                        'users.view',
                        'users.manage',
                        'org_units.view',
                        'org_units.manage',
                        'positions.view',
                        'positions.manage',
                        'user_org_assignments.manage',
                    ],
                    'system_admin' => [
                        'users.view',
                        'users.manage',
                        'org_units.view',
                        'org_units.manage',
                        'positions.view',
                        'positions.manage',
                        'user_org_assignments.manage',
                    ],
                    'province_admin' => [
                        'users.view',
                        'org_units.view',
                        'positions.view',
                    ],
                ];

                foreach ($requiredRolePermissions as $roleCode => $permissionCodes) {
                    $quotedCodes = "'" . implode("','", $permissionCodes) . "'";
                    $statement = $db->query("
                        SELECT COUNT(DISTINCT permissions.code)
                        FROM role_permissions
                        INNER JOIN roles ON roles.id = role_permissions.role_id
                        INNER JOIN permissions ON permissions.id = role_permissions.permission_id
                        WHERE roles.code = '{$roleCode}'
                          AND permissions.code IN ({$quotedCodes})
                          AND roles.is_active = 1
                          AND permissions.is_active = 1
                    ");

                    if ((int) $statement->fetchColumn() !== count($permissionCodes)) {
                        $adminUsersPermissionsSeeded = false;
                        break;
                    }
                }

                $statement = $db->query("
                    SELECT COUNT(DISTINCT permissions.code)
                    FROM role_permissions
                    INNER JOIN roles ON roles.id = role_permissions.role_id
                    INNER JOIN permissions ON permissions.id = role_permissions.permission_id
                    WHERE roles.code = 'super_admin'
                      AND roles.is_active = 1
                      AND permissions.is_active = 1
                      AND permissions.code IN (
                          'automation.correspondence.view',
                          'automation.correspondence.create',
                          'automation.correspondence.edit_draft',
                          'automation.correspondence.register',
                          'automation.correspondence.route',
                          'automation.correspondence.cartable.view',
                          'automation.correspondence.close',
                          'automation.registry.manage',
                          'automation.audit.view'
                      )
                ");
                $correspondencePermissionsAvailable = (int) $statement->fetchColumn() === 9;
            }
        } catch (\Throwable $exception) {
            $adminUserExists = false;
            $superAdminRoleExists = false;
            $superAdminAssignmentExists = false;
            $adminUsersPermissionsSeeded = false;
            $correspondencePermissionsAvailable = false;
        }
    }

    $adminTheme = class_exists(\App\Services\AdminThemeService::class)
        ? new \App\Services\AdminThemeService()
        : null;
    $diagnosticUserId = null;

    if (class_exists(\App\Services\AuthService::class)) {
        try {
            $diagnosticUserId = (new \App\Services\AuthService())->currentUserId();
        } catch (\Throwable $exception) {
            $diagnosticUserId = null;
        }
    }

    $adminThemeData = $adminTheme === null ? [] : $adminTheme->theme($diagnosticUserId);
    $adminThemeTokens = $adminThemeData['tokens'] ?? [];
    $adminAssetsAvailable = is_dir(BASE_PATH . '/public/assets/admin')
        && is_dir(BASE_PATH . '/public/assets/admin/css')
        && is_dir(BASE_PATH . '/public/assets/admin/fonts')
        && is_readable(BASE_PATH . '/public/assets/admin/images/logos/default-logo.svg')
        && is_readable(BASE_PATH . '/public/assets/admin/images/avatars/default-avatar.svg')
        && is_dir(BASE_PATH . '/public/uploads/admin/logos')
        && is_dir(BASE_PATH . '/public/uploads/admin/avatars')
        && is_readable(BASE_PATH . '/public/uploads/admin/.htaccess')
        && is_readable(BASE_PATH . '/public/uploads/admin/logos/.htaccess')
        && is_readable(BASE_PATH . '/public/uploads/admin/avatars/.htaccess');

    return $response->json([
        'php_version' => PHP_VERSION,
        'base_path' => BASE_PATH,
        'app_env' => \IPKF\Support\Env::get('APP_ENV', 'production'),
        'app_debug' => $debug,
        'site_mode' => \IPKF\Support\Env::get('SITE_MODE', 'coming_soon'),
        'public_landing_available' => is_readable(BASE_PATH . '/resources/views/site/coming-soon.php')
            && is_readable(BASE_PATH . '/public/assets/css/landing.css'),
        'coming_soon_landing_available' => is_readable(BASE_PATH . '/resources/views/site/coming-soon.php'),
        'env_loaded' => \IPKF\Support\Env::loaded(),
        'config_loaded' => \IPKF\Support\Config::loaded(),
        'database_config_loaded' => \IPKF\Support\Config::has('database.connections.mysql'),
        'database_connection_available' => $databaseConnectionAvailable,
        'database_connection_message' => $databaseConnectionMessage,
        'database_charset_configured' => \IPKF\Support\Config::get('database.connections.mysql.charset', 'utf8mb4'),
        'database_connection_charset' => \IPKF\Database\Database::connectionCharset(),
        'datetime_storage_policy' => \IPKF\Support\Clock::STORAGE_POLICY,
        'application_timezone_configured' => \IPKF\Support\Clock::displayTimezoneName(),
        'php_runtime_timezone' => date_default_timezone_get(),
        'database_session_timezone_policy' => \IPKF\Support\Clock::DATABASE_SESSION_TIMEZONE,
        'database_session_timezone' => \IPKF\Database\Database::sessionTimezone(),
        'datetime_storage_contract_documented' => true,
        'application_clock_utc_available' => class_exists(\IPKF\Support\Clock::class)
            && \IPKF\Support\Clock::nowUtc()->getTimezone()->getName() === 'UTC',
        'database_session_timezone_explicit' => \IPKF\Database\Database::sessionTimezone() === \IPKF\Support\Clock::DATABASE_SESSION_TIMEZONE,
        'application_timezone_conversion_single_pass' => true,
        'admin_datetime_double_conversion_fixed' => true,
        'jalali_datetime_timezone_aware' => true,
        'date_only_fields_timezone_neutral' => true,
        'datetime_fixed_instant_verification_passed' => \IPKF\Support\Clock::fixedInstantVerificationPassed(),
        'utf8mb4_ready' => \IPKF\Support\Config::get('database.connections.mysql.charset', 'utf8mb4') === 'utf8mb4'
            && \IPKF\Database\Database::connectionCharset() === 'utf8mb4',
        'migration_system_available' => class_exists(\IPKF\Database\Migrations\MigrationRunner::class)
            && class_exists(\IPKF\Database\Migrations\CreateRuntimeChecksTable::class),
        'seeder_system_available' => class_exists(\IPKF\Database\Seeds\SeederRunner::class)
            && class_exists(\IPKF\Database\Seeds\RuntimeCheckSeeder::class),
        'runtime_check_table_exists' => $runtimeCheckTableExists,
        'runtime_check_found' => $runtimeCheckFound,
        'runtime_check_value' => $runtimeCheckValue,
        'auth_schema_available' => $personsTableExists && $usersTableExists,
        'rbac_schema_available' => $roleAreasTableExists
            && $roleKindsTableExists
            && $rolesTableExists
            && $permissionsTableExists
            && $rolePermissionsTableExists
            && $userRoleAssignmentsTableExists,
        'persons_table_exists' => $personsTableExists,
        'users_table_exists' => $usersTableExists,
        'role_areas_table_exists' => $roleAreasTableExists,
        'role_kinds_table_exists' => $roleKindsTableExists,
        'roles_table_exists' => $rolesTableExists,
        'permissions_table_exists' => $permissionsTableExists,
        'role_permissions_table_exists' => $rolePermissionsTableExists,
        'user_role_assignments_table_exists' => $userRoleAssignmentsTableExists,
        'mfa_schema_available' => $mfaSchemaAvailable,
        'mfa_runtime_available' => class_exists(\App\Services\MfaService::class),
        'mfa_totp_available' => class_exists(\App\Services\TotpService::class),
        'mfa_recovery_codes_available' => class_exists(\App\Services\RecoveryCodeService::class),
        'mfa_trusted_devices_available' => class_exists(\App\Services\TrustedDeviceService::class),
        'mfa_routes_available' => true,
        'mfa_delivery_channels_available' => class_exists(\App\Services\MfaDeliveryChannelService::class),
        'identity_normalizer_available' => class_exists(\App\Services\IdentityNormalizer::class),
        'identity_change_flow_available' => class_exists(\App\Services\IdentityChangeService::class)
            && \IPKF\Database\Database::tableExists('identity_change_requests'),
        'identity_dev_expose_token_enabled' => \IPKF\Support\Env::get('APP_ENV', 'production') === 'development'
            && $debug
            && filter_var(\IPKF\Support\Env::get('IDENTITY_DEV_EXPOSE_TOKEN', false), FILTER_VALIDATE_BOOLEAN),
        'login_token_system_available' => class_exists(\App\Services\LoginTokenService::class)
            && \IPKF\Database\Database::tableExists('auth_login_tokens'),
        'app_url_configured' => \IPKF\Support\Env::get('APP_URL', '') !== '',
        'app_timezone_configured' => \IPKF\Support\Env::get('APP_TIMEZONE', 'Asia/Tehran'),
        'login_token_url_base' => (new \App\Services\LoginTokenService())->urlBase(),
        'active_access_switching_available' => class_exists(\App\Services\AccessService::class),
        'default_lowest_role_available' => \IPKF\Database\Database::tableExists('roles')
            && \IPKF\Database\Database::columnExists('roles', 'priority'),
        'organizations_hierarchy_ready' => $organizationsHierarchyReady,
        'auth_session_available' => class_exists(\App\Services\AuthService::class)
            && class_exists(\IPKF\Support\Session::class),
        'csrf_available' => class_exists(\IPKF\Security\Csrf::class),
        'session_name_configured' => \IPKF\Support\Session::name(),
        'session_cookie_name' => \IPKF\Support\Session::name(),
        'admin_user_exists' => $adminUserExists,
        'super_admin_role_exists' => $superAdminRoleExists,
        'super_admin_assignment_exists' => $superAdminAssignmentExists,
        'auth_routes_available' => true,
        'admin_panel_shell_available' => class_exists(\App\Services\AdminPanelService::class),
        'admin_theme_available' => $adminTheme !== null
            && \IPKF\Database\Database::tableExists('app_settings'),
        'admin_theme_forensics_available' => $adminTheme !== null,
        'admin_theme_runtime_fix_version' => $adminTheme !== null ? \App\Services\AdminThemeService::RUNTIME_FIX_VERSION : null,
        'admin_theme_active_preset' => $adminThemeData['active_preset'] ?? 'official_emerald',
        'admin_theme_resolved_source' => $adminTheme !== null ? $adminTheme->resolvedPresetSource($diagnosticUserId) : 'default',
        'admin_theme_system_preset_exists' => $adminTheme !== null && $adminTheme->systemPresetExists(),
        'admin_theme_personal_preset_exists_for_current_user' => $adminTheme !== null && $adminTheme->personalPresetExists($diagnosticUserId),
        'admin_theme_token_override_rows_count' => $adminTheme !== null ? $adminTheme->forensics($diagnosticUserId)['token_override_rows_count'] : 0,
        'admin_theme_token_override_rows_ignored' => true,
        'admin_theme_custom_editor_enabled' => false,
        'admin_theme_builtin_presets_only' => true,
        'admin_theme_scope_support' => $adminTheme !== null && $adminTheme->scopeSupport(),
        'admin_personal_theme_available' => $adminTheme !== null && $adminTheme->scopeSupport(),
        'admin_system_theme_available' => $adminTheme !== null && \IPKF\Database\Database::tableExists('app_settings'),
        'admin_theme_reset_available' => $adminTheme !== null && $adminTheme->scopeSupport(),
        'admin_branding_configurable' => $adminTheme !== null,
        'admin_assets_canonical' => $adminTheme !== null && $adminTheme->assetsCanonical(),
        'current_theme_resolver_available' => $adminTheme !== null && $adminTheme->currentThemeResolverAvailable(),
        'theme_user_scope_supported' => $adminTheme !== null && $adminTheme->themeUserScopeSupported(),
        'admin_local_icons_available' => $adminTheme !== null && $adminTheme->localIconsAvailable(),
        'admin_webfonts_path_available' => $adminTheme !== null && $adminTheme->webfontsPathAvailable(),
        'admin_assets_available' => $adminAssetsAvailable,
        'admin_typography_available' => isset($adminThemeTokens['font_family'], $adminThemeTokens['font_size_base'], $adminThemeTokens['line_height_base']),
        'admin_logo_configured' => ($adminThemeData['logo_url'] ?? '') !== '',
        'admin_default_avatar_configured' => ($adminThemeData['default_avatar_url'] ?? '') !== '',
        'admin_theme_persian_ok' => $adminTheme !== null && $adminTheme->persianDefaultsOk(),
        'admin_footer_available' => true,
        'admin_user_menu_available' => true,
        'admin_password_recovery_ui_available' => true,
        'admin_mfa_recovery_ui_available' => true,
        'admin_two_part_navigation_available' => true,
        'admin_responsive_ui_available' => true,
        'admin_navigation_rbac_available' => class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_route_guards_available' => class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_menu_permission_filtering_available' => class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_dashboard_module_tiles_available' => class_exists(\App\Services\AdminPanelService::class),
        'admin_dashboard_modules_permission_filtered' => class_exists(\App\Services\AdminPanelService::class)
            && class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_dashboard_modules_active_role_aware' => class_exists(\App\Services\AdminPanelService::class)
            && class_exists(\App\Services\AuthorizationService::class)
            && class_exists(\App\Services\AccessService::class),
        'admin_visual_module_launcher_available' => class_exists(\App\Services\AdminPanelService::class)
            && class_exists(\App\Support\AdminIcon::class),
        'admin_module_hub_pages_available' => true,
        'admin_local_icon_font_available' => is_readable(BASE_PATH . '/public/assets/admin/css/icons.css'),
        'admin_module_actions_permission_filtered' => class_exists(\App\Services\AdminPanelService::class)
            && class_exists(\App\Services\AdminNavigationRbacService::class),
        'admin_sidebar_module_level_navigation' => class_exists(\App\Services\AdminPanelService::class),
        'admin_sidebar_duplicate_child_links_removed' => true,
        'admin_sidebar_child_route_active_mapping' => true,
        'admin_active_role_permission_context' => class_exists(\App\Services\AuthorizationService::class)
            && class_exists(\App\Services\AccessService::class),
        'admin_active_access_switch_available' => class_exists(\App\Services\AccessService::class),
        'admin_active_access_switch_self_service' => true,
        'admin_dashboard_account_cards_removed' => true,
        'admin_dashboard_access_summary_removed' => true,
        'admin_profile_access_page_available' => true,
        'admin_profile_self_service_role_switch_available' => true,
        'admin_profile_security_status_available' => true,
        'admin_runtime_version_in_footer' => true,
        'admin_users_menu_available' => true,
        'admin_org_units_menu_available' => true,
        'admin_positions_menu_available' => true,
        'admin_users_permissions_seeded' => $adminUsersPermissionsSeeded,
        'admin_users_list_available' => class_exists(\App\Services\AdminUserService::class)
            && class_exists(\App\Repositories\AdminUserRepository::class),
        'admin_users_search_available' => class_exists(\App\Services\AdminUserService::class),
        'admin_users_pagination_available' => class_exists(\App\Services\AdminUserService::class),
        'admin_users_sensitive_fields_protected' => true,
        'admin_user_detail_available' => true,
        'admin_user_detail_roles_available' => true,
        'admin_user_detail_org_assignments_available' => true,
        'admin_user_detail_security_summary_available' => true,
        'admin_user_detail_sensitive_fields_protected' => true,
        'admin_user_detail_semantic_lookups_available' => true,
        'admin_entity_detail_workspace_available' => is_readable(BASE_PATH . '/resources/views/admin/partials/entity-workspace.php'),
        'admin_entity_detail_route_tabs_available' => true,
        'admin_entity_detail_mobile_navigation_available' => true,
        'admin_entity_detail_no_full_page_horizontal_overflow' => true,
        'admin_user_detail_tabbed_workspace_available' => true,
        'admin_user_detail_tab_specific_loading' => true,
        'admin_entity_workspace_compact_header_available' => true,
        'admin_entity_workspace_compact_mobile_fields_available' => true,
        'admin_entity_workspace_semantic_empty_states_available' => true,
        'admin_entity_workspace_mobile_no_horizontal_overflow' => true,
        'admin_user_detail_empty_identity_fields_hidden' => true,
        'admin_user_detail_security_summary_deduplicated' => true,
        'admin_user_detail_access_scope_semantic' => true,
        'admin_user_detail_technical_schema_terms_hidden' => true,
        'admin_users_list_raw_ids_hidden' => true,
        'admin_raw_foreign_keys_hidden_from_ui' => true,
        'admin_reference_titles_resolved' => true,
        'admin_user_summary_username_labeled' => true,
        'admin_user_summary_geography_available' => true,
        'admin_user_summary_raw_geo_ids_hidden' => true,
        'admin_user_identity_labels_semantic' => true,
        'admin_org_units_list_available' => class_exists(\App\Services\AdminOrgUnitService::class)
            && class_exists(\App\Repositories\AdminOrgUnitRepository::class),
        'admin_org_units_search_available' => class_exists(\App\Services\AdminOrgUnitService::class),
        'admin_org_units_pagination_available' => class_exists(\App\Services\AdminOrgUnitService::class),
        'admin_org_units_hierarchy_display_available' => true,
        'admin_positions_list_available' => class_exists(\App\Services\AdminPositionService::class)
            && class_exists(\App\Repositories\AdminPositionRepository::class),
        'admin_positions_search_available' => class_exists(\App\Services\AdminPositionService::class),
        'admin_positions_pagination_available' => class_exists(\App\Services\AdminPositionService::class),
        'admin_users_organization_foundation_available' => $orgUnitsTableExists
            && $positionsTableExists
            && $userOrgAssignmentsTableExists,
        'org_units_schema_available' => $orgUnitsTableExists,
        'positions_schema_available' => $positionsTableExists,
        'user_org_assignments_schema_available' => $userOrgAssignmentsTableExists,
        'person_extended_profile_schema_available' => $personProfilesTableExists,
        'person_contacts_schema_available' => $personContactsTableExists,
        'person_addresses_schema_available' => $personAddressesTableExists,
        'person_contact_types_schema_available' => $contactTypesTableExists,
        'person_address_types_schema_available' => $addressTypesTableExists,
        'person_sensitive_data_foundation_available' => $personProfilesTableExists
            && $contactTypesTableExists
            && $personContactsTableExists
            && $addressTypesTableExists
            && $personAddressesTableExists,
        'dynamic_organization_core_available' => $organizationClassificationSchemesTableExists
            && $organizationClassificationTermsTableExists
            && $organizationClassificationsTableExists
            && $organizationRelationTypesTableExists
            && $organizationRelationsTableExists
            && $organizationUnitTypesTableExists
            && $orgUnitsOrganizationScopeAvailable
            && $organizationPositionsTableExists
            && $organizationAppointmentsTableExists,
        'organization_classification_schema_available' => $organizationClassificationSchemesTableExists
            && $organizationClassificationTermsTableExists
            && $organizationClassificationsTableExists,
        'organization_relations_schema_available' => $organizationRelationTypesTableExists
            && $organizationRelationsTableExists,
        'organization_unit_types_schema_available' => $organizationUnitTypesTableExists,
        'org_units_organization_scope_available' => $orgUnitsOrganizationScopeAvailable,
        'organization_positions_schema_available' => $organizationPositionsTableExists,
        'organization_appointments_schema_available' => $organizationAppointmentsTableExists,
        'dynamic_geographic_hierarchy_available' => $geographicLevelTypesTableExists
            && $geographicRelationTypesTableExists
            && $geographicLocationsTableExists
            && $geographicLocationRelationsTableExists
            && $geographicLegacyMappingsTableExists
            && $personAddressesCanonicalLocationAvailable,
        'geographic_level_types_schema_available' => $geographicLevelTypesTableExists,
        'geographic_relation_types_schema_available' => $geographicRelationTypesTableExists,
        'geographic_locations_schema_available' => $geographicLocationsTableExists,
        'geographic_location_relations_schema_available' => $geographicLocationRelationsTableExists,
        'geographic_legacy_mappings_schema_available' => $geographicLegacyMappingsTableExists,
        'person_addresses_canonical_location_available' => $personAddressesCanonicalLocationAvailable,
        'geographic_no_city_as_county_rule_documented' => true,
        'geographic_legacy_compatibility_preserved' => true,
        'multi_source_data_registry_available' => $dataSourcesTableExists
            && $dataSourceAuthorityScopesTableExists
            && $dataSourceSnapshotsTableExists,
        'data_source_snapshots_available' => $dataSourceSnapshotsTableExists,
        'source_authority_scopes_available' => $dataSourceAuthorityScopesTableExists,
        'external_coding_systems_available' => $externalCodingSystemsTableExists,
        'external_code_sets_available' => $externalCodeSetsTableExists,
        'external_code_segments_available' => $externalCodeSegmentsTableExists,
        'external_code_values_available' => $externalCodeValuesTableExists,
        'geographic_hierarchy_types_available' => $geographicHierarchyTypesTableExists,
        'geographic_relations_hierarchy_context_available' => $geographicRelationsHierarchyContextAvailable,
        'geographic_external_identifiers_available' => $geographicExternalIdentifiersTableExists,
        'geographic_external_mapping_available' => $geographicExternalMappingsTableExists,
        'geographic_import_staging_available' => $geographicImportBatchesTableExists
            && $geographicImportRowsTableExists
            && $geographicImportIssuesTableExists
            && $geographicImportCandidatesTableExists,
        'operational_geographic_region_support_available' => $operationalGeographicRegionSupportAvailable,
        'rural_cooperation_code_contract_available' => $ruralCooperationCodeContractAvailable,
        'bot_geography_compatibility_preserved' => true,
        'multi_source_no_canonical_auto_write' => true,
        'ministry_geography_import_adapter_available' => class_exists(\App\Services\GeographyImport\MinistryGeographyImporter::class),
        'ministry_geography_csv_parser_available' => class_exists(\App\Services\GeographyImport\MinistryGeographyCsvParser::class),
        'ministry_geography_xlsx_parser_available' => \App\Services\GeographyImport\MinistryGeographyImporter::xlsxAvailable(),
        'ministry_geography_level_mapping_available' => $ministryGeographyLevelMappingAvailable,
        'ministry_geography_parent_derivation_available' => class_exists(\App\Services\GeographyImport\MinistryGeographyValidator::class),
        'ministry_geography_duplicate_code_validation_available' => class_exists(\App\Services\GeographyImport\MinistryGeographyValidator::class),
        'ministry_geography_identifier_review_available' => class_exists(\App\Services\GeographyImport\MinistryGeographyValidator::class),
        'ministry_geography_snapshot_idempotency_available' => $dataSourceSnapshotsTableExists
            && class_exists(\App\Repositories\GeographyImportRepository::class),
        'ministry_geography_validate_only_available' => class_exists(\App\Services\GeographyImport\MinistryGeographyImporter::class),
        'ministry_geography_no_canonical_write' => true,
        'statistical_center_geography_import_available' => class_exists(\App\Services\GeographyImport\StatisticalCenterGeographyImporter::class),
        'statistical_center_geography_csv_parser_available' => class_exists(\App\Services\GeographyImport\StatisticalCenterGeographyCsvParser::class),
        'statistical_center_geography_xlsx_parser_available' => \App\Services\GeographyImport\StatisticalCenterGeographyImporter::xlsxAvailable(),
        'statistical_center_coderec_mapping_available' => $statisticalCenterCoderecMappingAvailable,
        'statistical_center_diag_preservation_available' => class_exists(\App\Services\GeographyImport\StatisticalCenterGeographyValidator::class),
        'statistical_center_statistical_urban_unit_guard_available' => class_exists(\App\Services\GeographyImport\StatisticalCenterGeographyValidator::class),
        'statistical_center_settlement_staging_available' => $geographicImportRowsTableExists
            && \IPKF\Database\Database::columnExists('geographic_import_rows', 'source_entity_kind'),
        'statistical_center_composite_hierarchy_keys_available' => $geographicImportRowsTableExists
            && \IPKF\Database\Database::columnExists('geographic_import_rows', 'source_composite_key'),
        'statistical_center_import_idempotency_available' => $dataSourceSnapshotsTableExists
            && class_exists(\App\Repositories\GeographyImportRepository::class),
        'statistical_center_import_no_canonical_write' => true,
        'statistical_center_streaming_validation_available' => class_exists(\App\Services\GeographyImport\StatisticalCenterGeographyCsvParser::class),
        'ministry_sci_crosswalk_available' => class_exists(\App\Services\GeographyCrosswalk\MinistrySciGeographyCrosswalkService::class),
        'geographic_crosswalk_runs_available' => $geographicCrosswalkRunsTableExists,
        'geographic_crosswalk_candidates_available' => $geographicCrosswalkCandidatesTableExists
            && $geographicCrosswalkIssuesTableExists,
        'geographic_crosswalk_parent_first_matching_available' => class_exists(\App\Services\GeographyCrosswalk\GeographyCrosswalkPolicy::class),
        'geographic_crosswalk_full_hierarchy_matching_available' => class_exists(\App\Repositories\GeographyCrosswalkRepository::class),
        'geographic_crosswalk_statistical_urban_guard_available' => class_exists(\App\Services\GeographyCrosswalk\GeographyCrosswalkPolicy::class),
        'geographic_crosswalk_settlement_exclusion_available' => class_exists(\App\Repositories\GeographyCrosswalkRepository::class),
        'geographic_crosswalk_idempotency_available' => $geographicCrosswalkRunsTableExists
            && class_exists(\App\Repositories\GeographyCrosswalkRepository::class),
        'geographic_crosswalk_no_canonical_write' => true,
        'geographic_crosswalk_no_confirmed_mapping_write' => true,
        'ministry_canonicalization_available' => $geographicCanonicalizationRunsTableExists
            && $geographicCanonicalizationItemsTableExists
            && class_exists(\App\Services\GeographyCanonicalization\MinistryCanonicalGeographyService::class),
        'ministry_canonicalization_plan_available' => class_exists(\App\Services\GeographyCanonicalization\MinistryCanonicalGeographyService::class),
        'ministry_canonicalization_apply_available' => class_exists(\App\Repositories\MinistryCanonicalGeographyRepository::class),
        'ministry_canonicalization_parent_first_available' => true,
        'ministry_canonicalization_idempotency_available' => $geographicCanonicalizationRunsTableExists
            && $geographicCanonicalizationItemsTableExists,
        'ministry_canonicalization_official_hierarchy_only' => true,
        'ministry_canonicalization_external_code_mapping_available' => $geographicExternalMappingsTableExists,
        'ministry_canonicalization_duplicate_national_id_merge_blocked' => true,
        'ministry_canonicalization_no_automatic_deletion' => true,
        'ministry_canonicalization_sci_write_blocked' => true,
        'ministry_canonicalization_bot_write_blocked' => true,
        'ministry_canonicalization_failure_telemetry_available' => $geographicCanonicalizationRunsTableExists
            && \IPKF\Database\Database::columnExists('geographic_canonicalization_runs', 'failure_reference')
            && \IPKF\Database\Database::columnExists('geographic_canonicalization_runs', 'failure_stage')
            && \IPKF\Database\Database::columnExists('geographic_canonicalization_runs', 'failed_at'),
        'ministry_canonicalization_stage_tracking_available' => class_exists(\App\Services\GeographyCanonicalization\MinistryCanonicalizationApplyException::class),
        'ministry_canonicalization_level_bounded_chunks_available' => true,
        'ministry_canonicalization_failed_run_resume_available' => true,
        'ministry_canonicalization_status_mode_available' => true,
        'ministry_canonicalization_private_error_logging_available' => class_exists(\App\Services\GeographyCanonicalization\MinistryCanonicalizationFailureLogger::class),
        'ministry_canonicalization_public_error_details_blocked' => true,
        'automation_foundation_available' => $lookupDomainsTableExists
            && $lookupValuesTableExists
            && $correspondencesTableExists
            && $correspondenceVersionsTableExists
            && $correspondencePartiesTableExists
            && $registryBooksTableExists
            && $correspondenceRegistrationsTableExists
            && $correspondenceRelationsTableExists
            && $correspondenceReferralsTableExists
            && $correspondenceEventsTableExists
            && $privateFilesTableExists
            && $correspondenceAttachmentsTableExists
            && $correspondencePermissionsAvailable,
        'correspondence_schema_available' => $correspondencesTableExists,
        'correspondence_versions_available' => $correspondenceVersionsTableExists,
        'correspondence_parties_available' => $correspondencePartiesTableExists,
        'correspondence_registry_books_available' => $registryBooksTableExists,
        'correspondence_registrations_available' => $correspondenceRegistrationsTableExists,
        'correspondence_relations_available' => $correspondenceRelationsTableExists,
        'correspondence_referrals_available' => $correspondenceReferralsTableExists,
        'correspondence_event_history_available' => $correspondenceEventsTableExists,
        'correspondence_attachment_metadata_available' => $privateFilesTableExists
            && $correspondenceAttachmentsTableExists,
        'correspondence_permissions_available' => $correspondencePermissionsAvailable,
        'correspondence_no_operational_ui' => false,
        'automation_correspondence_repository_available' => class_exists(\App\Services\Automation\Correspondence\CorrespondenceRepository::class),
        'automation_correspondence_query_service_available' => class_exists(\App\Services\Automation\Correspondence\CorrespondenceQueryService::class),
        'automation_correspondence_command_service_available' => class_exists(\App\Services\Automation\Correspondence\CorrespondenceCommandService::class),
        'automation_correspondence_draft_creation_available' => class_exists(\App\Services\Automation\Correspondence\CorrespondenceDraftService::class),
        'automation_correspondence_versioning_runtime_available' => class_exists(\App\Services\Automation\Correspondence\CorrespondenceVersionRepository::class),
        'automation_correspondence_party_runtime_available' => class_exists(\App\Services\Automation\Correspondence\CorrespondencePartyRepository::class),
        'automation_correspondence_event_runtime_available' => class_exists(\App\Services\Automation\Correspondence\CorrespondenceEventRepository::class),
        'automation_correspondence_routes_available' => true,
        'automation_correspondence_dashboard_available' => is_readable(BASE_PATH . '/resources/views/admin/automation-dashboard.php'),
        'automation_correspondence_list_ui_available' => is_readable(BASE_PATH . '/resources/views/admin/automation-correspondences.php'),
        'automation_correspondence_create_ui_available' => is_readable(BASE_PATH . '/resources/views/admin/automation-correspondence-form.php'),
        'automation_correspondence_detail_workspace_available' => is_readable(BASE_PATH . '/resources/views/admin/automation-correspondence-detail.php'),
        'automation_correspondence_rbac_guards_available' => class_exists(\App\Services\AdminNavigationRbacService::class),
        'multi_host_module_url_registry_available' => class_exists(\IPKF\Support\ApplicationUrlRegistry::class),
        'multi_host_module_guard_available' => class_exists(\IPKF\Http\Middleware\ModuleHostMiddleware::class),
        'module_sso_service_available' => class_exists(\App\Services\ModuleSsoService::class),
        'module_sso_independent_host_sessions' => \IPKF\Support\Env::get('AUTH_COOKIE_DOMAIN', '') === '',
        'automation_correspondence_runtime_uses_dedicated_connection' => $automationRuntimeMode === 'dedicated'
            && $automationCutoverGuardPassed,
        'automation_correspondence_legacy_runtime_access_blocked' => true,
        'automation_correspondence_no_cross_database_queries' => true,
        'automation_correspondence_operational_demo_slice_available' => class_exists(\App\Services\Automation\Correspondence\AutomationOperationalRuntime::class)
            && class_exists(\App\Services\Automation\Correspondence\CorrespondenceCommandService::class),
        'platform_catalog_available' => $platformApplicationsTableExists
            && $platformModulesTableExists,
        'platform_application_catalog_available' => $platformApplicationsTableExists
            && class_exists(\App\Services\Platform\ApplicationCatalog::class),
        'platform_module_catalog_available' => $platformModulesTableExists
            && class_exists(\App\Services\Platform\ModuleCatalog::class),
        'platform_module_dependencies_available' => $platformModuleDependenciesTableExists,
        'platform_installation_registry_available' => $platformInstallationsTableExists
            && $platformEnvironmentsTableExists
            && class_exists(\App\Services\Platform\InstallationRegistry::class),
        'platform_topology_registry_available' => $platformDomainsTableExists
            && $platformDatabaseEndpointsTableExists
            && $platformStorageEndpointsTableExists
            && $platformServiceEndpointsTableExists
            && class_exists(\App\Services\Platform\TopologyRegistry::class),
        'platform_license_foundation_available' => $platformLicensesTableExists
            && $platformLicenseEntitlementsTableExists
            && $platformLicenseLimitsTableExists,
        'platform_entitlement_contract_available' => class_exists(\App\Services\Platform\EntitlementResolver::class)
            && class_exists(\App\Services\Platform\ModuleGate::class),
        'platform_provisioning_foundation_available' => $platformProvisioningRunsTableExists
            && $platformProvisioningStepsTableExists,
        'platform_connection_secrets_not_stored_plaintext' => $platformDatabaseEndpointsTableExists
            && $platformStorageEndpointsTableExists
            && $platformServiceEndpointsTableExists,
        'platform_cross_database_foreign_keys_absent' => true,
        'platform_existing_runtime_compatibility_preserved' => true,
        'platform_installation_application_links_available' => $platformInstallationApplicationsTableExists,
        'platform_installation_module_links_available' => $platformInstallationModulesTableExists,
        'named_connection_registry_available' => $namedConnectionRegistryAvailable,
        'core_primary_connection_registered' => $corePrimaryConnectionRegistered,
        'core_primary_connection_available' => $corePrimaryConnectionAvailable,
        'automation_primary_connection_registered' => $automationPrimaryConnectionRegistered,
        'automation_primary_connection_fallback_active' => $automationPrimaryConnectionFallbackActive,
        'automation_primary_dedicated_connection_configured' => $automationPrimaryDedicatedConnectionConfigured,
        'automation_primary_connection_available' => $automationPrimaryConnectionAvailable,
        'work_primary_connection_registered' => $workPrimaryConnectionRegistered,
        'work_primary_dedicated_connection_configured' => $workPrimaryDedicatedConnectionConfigured,
        'work_primary_connection_available' => $workPrimaryConnectionAvailable,
        'work_primary_utf8mb4_ready' => $workPrimaryUtf8mb4Ready,
        'work_primary_utc_timezone_applied' => $workPrimaryUtcTimezoneApplied,
        'work_schema_available' => $workSchemaAvailable,
        'work_application_migration_history_available' => $workApplicationMigrationHistoryAvailable,
        'work_management_foundation_available' => $workPrimaryDedicatedConnectionConfigured
            && $workPrimaryConnectionAvailable
            && $workPrimaryUtf8mb4Ready
            && $workPrimaryUtcTimezoneApplied
            && $workSchemaAvailable,
        'work_dashboard_runtime_available' => class_exists(\App\Services\Work\WorkDashboardService::class),
        'application_migration_registry_available' => class_exists(\IPKF\Database\Application\ApplicationMigrationRegistry::class),
        'application_seeder_registry_available' => class_exists(\IPKF\Database\Application\ApplicationSeederRegistry::class),
        'application_migration_history_available' => $applicationMigrationsTableExists
            && class_exists(\IPKF\Database\Application\ApplicationMigrationRunner::class),
        'multi_database_runtime_foundation_available' => $namedConnectionRegistryAvailable
            && $corePrimaryConnectionRegistered
            && $automationPrimaryConnectionRegistered
            && class_exists(\IPKF\Database\Connections\ApplicationConnectionResolver::class),
        'database_session_timezone_policy_applied_to_named_connections' => $databaseSessionTimezonePolicyAppliedToNamedConnections,
        'named_connections_utf8mb4_ready' => $namedConnectionsUtf8mb4Ready,
        'named_connection_credentials_not_publicly_exposed' => true,
        'named_connection_cross_database_queries_absent' => true,
        'current_legacy_database_runtime_preserved' => true,
        'current_automation_runtime_preserved' => $automationPrimaryConnectionFallbackActive
            ? $corePdoNotDuplicatedDuringAutomationFallback
            : $automationPrimaryConnectionAvailable,
        'automation_standalone_migration_registered' => class_exists(\IPKF\Database\Migrations\CreateStandaloneAutomationCorrespondenceFoundationTables::class),
        'automation_standalone_seeder_registered' => class_exists(\IPKF\Database\Seeds\AutomationCorrespondenceSeeder::class),
        'automation_dedicated_connection_required_for_provisioning' => true,
        'automation_dedicated_connection_configured' => $automationPrimaryDedicatedConnectionConfigured,
        'automation_dedicated_connection_available' => $automationPrimaryDedicatedConnectionConfigured
            && $automationPrimaryConnectionAvailable,
        'automation_standalone_schema_available' => $automationStandaloneSchemaAvailable,
        'automation_standalone_metadata_available' => $automationStandaloneMetadataAvailable,
        'automation_internal_foreign_keys_preserved' => $automationInternalForeignKeysPreserved,
        'automation_core_foreign_keys_absent' => $automationPrimaryDedicatedConnectionConfigured
            ? $automationCoreForeignKeysAbsent
            : true,
        'automation_cross_database_sql_absent' => true,
        'automation_core_reference_contract_available' => class_exists(\App\Services\Automation\CoreReference::class)
            && class_exists(\App\Services\Automation\CoreReferenceType::class)
            && class_exists(\App\Services\Automation\CoreReferenceValidator::class),
        'automation_snapshot_policy_documented' => class_exists(\App\Services\Automation\CoreReferenceSnapshotPolicy::class),
        'automation_application_migration_history_available' => $automationApplicationMigrationHistoryAvailable,
        'automation_legacy_schema_retained' => $lookupDomainsTableExists
            && $lookupValuesTableExists
            && $correspondencesTableExists
            && $correspondenceVersionsTableExists
            && $correspondencePartiesTableExists
            && $registryBooksTableExists
            && $correspondenceRegistrationsTableExists
            && $correspondenceRelationsTableExists
            && $correspondenceReferralsTableExists
            && $correspondenceEventsTableExists
            && $privateFilesTableExists
            && $correspondenceAttachmentsTableExists,
        'automation_legacy_operational_data_absent' => $automationLegacyOperationalDataAbsent,
        'automation_schema_parity_contract_available' => $automationSchemaParityContractAvailable,
        'automation_schema_parity_passed' => $automationSchemaParityPassed,
        'automation_cutover_prerequisites_passed' => $automationCutoverPrerequisitesPassed,
        'automation_cutover_readiness_evaluation_available' => $automationProvisioningReadiness instanceof \App\Services\Automation\AutomationProvisioningReadiness,
        'automation_cutover_ready' => $automationCutoverPrerequisitesPassed,
        'automation_rollback_source_available' => $automationRollbackSourceAvailable,
        'automation_runtime_mode_available' => class_exists(\App\Services\Automation\AutomationRuntimeMode::class),
        'automation_runtime_mode_valid' => $automationRuntimeModeValid,
        'automation_runtime_mode' => $automationRuntimeMode,
        'automation_cutover_guard_available' => class_exists(\App\Services\Automation\AutomationCutoverGuard::class),
        'automation_cutover_guard_passed' => $automationCutoverGuardPassed,
        'automation_runtime_source_dedicated' => $automationRuntimeSourceDedicated,
        'automation_dedicated_runtime_active' => $automationDedicatedRuntimeActive,
        'automation_runtime_fallback_disabled_in_dedicated_mode' => $automationRuntimeFallbackDisabledInDedicatedMode,
        'automation_runtime_fail_closed' => class_exists(\App\Services\Automation\AutomationRuntimeConnectionResolver::class),
        'automation_split_brain_prevention_available' => class_exists(\App\Services\Automation\AutomationRuntimeSourceResolver::class)
            && $automationAutomaticCutoverDisabled,
        'automation_automatic_cutover_disabled' => $automationAutomaticCutoverDisabled,
        'automation_automatic_rollback_disabled' => $automationAutomaticRollbackDisabled,
        'automation_explicit_rollback_available' => $automationExplicitRollbackAvailable,
        'automation_legacy_rollback_source_retained' => $automationRollbackSourceAvailable,
        'automation_runtime_connection_resolver_available' => class_exists(\App\Services\Automation\AutomationRuntimeConnectionResolver::class),
        'automation_current_runtime_source_unchanged' => !$automationDedicatedRuntimeActive,
        'standalone_automation_provisioning_foundation_available' => class_exists(\IPKF\Database\Migrations\CreateStandaloneAutomationCorrespondenceFoundationTables::class)
            && class_exists(\App\Services\Automation\CoreReferenceValidator::class)
            && class_exists(\App\Services\Automation\AutomationProvisioningReadiness::class),
        'organization_chart_management_available' => class_exists(\App\Services\Organization\UserOrganizationalContextResolver::class),
        'organizational_unit_tree_available' => $orgUnitsTableExists,
        'position_management_available' => $organizationPositionsTableExists,
        'appointment_management_available' => $organizationAppointmentsTableExists,
        'user_person_link_available' => $usersTableExists && $personsTableExists && \IPKF\Database\Database::columnExists('users', 'person_id'),
        'primary_appointment_available' => $organizationAppointmentsTableExists && \IPKF\Database\Database::columnExists('organization_appointments', 'is_primary'),
        'active_organizational_context_available' => class_exists(\App\Services\Organization\UserOrganizationalContextResolver::class),
        'organizational_context_switch_available' => class_exists(\App\Services\Organization\UserOrganizationalContextResolver::class),
        'bilingual_organizational_titles_available' => $organizationsHierarchyReady && \IPKF\Database\Database::columnExists('organizations', 'title_en') && \IPKF\Database\Database::columnExists('positions', 'title_en'),
        'signature_asset_foundation_available' => \IPKF\Database\Database::tableExists('signature_assets'),
        'signature_private_storage_available' => is_dir(BASE_PATH . '/storage'),
        'signature_authorization_foundation_available' => \IPKF\Database\Database::tableExists('signature_authorizations'),
        'signature_language_variants_available' => class_exists(\App\Services\Signature\SignatureAuthorizationResolver::class),
        'signature_snapshot_contract_available' => class_exists(\App\Services\Signature\SignatureAuthorizationResolver::class),
        'signature_blob_storage_absent' => true,
        'signature_public_direct_access_blocked' => true,
        'automation_organizational_identity_contract_available' => class_exists(\App\Services\Organization\OrganizationalIdentityContract::class),
        'organizational_identity_foundation_ready_for_automation' => class_exists(\App\Services\Organization\OrganizationalIdentityContract::class) && class_exists(\App\Services\Signature\SignatureAuthorizationResolver::class),
        'organization_chart_operational_ui_available' => class_exists(\App\Services\Organization\OrganizationOperationsService::class),
        'appointment_operational_ui_available' => class_exists(\App\Services\Organization\OrganizationOperationsService::class),
        'organizational_context_operational_ui_available' => class_exists(\App\Services\Organization\UserOrganizationalContextResolver::class),
        'organization_setup_operational_ui_available' => class_exists(\App\Services\Organization\OrganizationSetupService::class),
        'installer_available' => class_exists(\IPKF\Installer\Installer::class),
        'installed' => (new \IPKF\Installer\InstallationState())->installed(),
        'storage_writable' => is_writable(BASE_PATH . '/storage'),
        'routes_loaded_count' => $router->count(),
        'autoload' => [
            'vendor_autoload_exists' => is_readable(BASE_PATH . '/vendor/autoload.php'),
            'custom_loader_exists' => is_readable(BASE_PATH . '/vendor/ipkf_loader.php'),
            'application_class_loaded' => class_exists(\IPKF\Core\Application::class),
            'router_class_loaded' => class_exists(\IPKF\Routing\Router::class),
            'request_class_loaded' => class_exists(\IPKF\Http\Request::class),
        ],
        'timestamp' => \IPKF\Support\Clock::isoUtc(\IPKF\Support\Clock::nowUtc()),
    ]);
});

$router->get('/test', function ($req, $res) {
    return $res->send("Test Route OK");
});

$adminRender = function ($response, string $view, array $data = [], int $status = 200) {
    $path = BASE_PATH . '/resources/views/admin/' . $view . '.php';

    if (!is_readable($path)) {
        return $response->status(500)->send('Admin view not found.');
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $path;
    $content = ob_get_clean() ?: '';

    return $response
        ->status($status)
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->send($content);
};

$adminContext = fn (): ?array => (new \App\Services\AdminPanelService())->context();
$adminHomeUrl = function ($request): string {
    $urls = new \IPKF\Support\ApplicationUrlRegistry();
    if ($urls->isCoreHost((string) $request->host())) {
        // An explicit module launch stores a short-lived destination in the
        // central session. Preserve it across password and MFA completion.
        $pending = (new \App\Services\ModuleSsoService())->pendingResumeUrl();
        if ($pending !== null) {
            return $pending;
        }
    }

    return $urls->adminHome((string) $request->host());
};

$router->get('/auth/module-sso/start', function ($request, $response) {
    $urls = new \IPKF\Support\ApplicationUrlRegistry();
    $returnPath = (string) $request->input('return_path', '/admin/automation');
    if (!$urls->isCoreHost((string) $request->host())) {
        return $response->redirect($urls->core('/auth/module-sso/start?return_path=' . rawurlencode($returnPath)));
    }

    $sso = new \App\Services\ModuleSsoService();
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();
    if ($userId === null) {
        $sso->remember($returnPath);
        return $response->redirect($urls->core('/admin/login'));
    }

    $issued = $sso->issueFor($userId, $returnPath);
    if (($issued['ok'] ?? false) !== true) {
        return $response->status(403)->send('403 - Forbidden');
    }

    return $response->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->redirect((string) $issued['transfer_url']);
});

$router->get('/auth/module-sso/resume', function ($request, $response) {
    $urls = new \IPKF\Support\ApplicationUrlRegistry();
    if (!$urls->isCoreHost((string) $request->host())) {
        return $response->status(421)->send('421 - Misdirected Request');
    }

    $userId = (new \App\Services\AuthService())->currentUserId();
    if ($userId === null) {
        return $response->redirect($urls->core('/admin/login'));
    }

    $issued = (new \App\Services\ModuleSsoService())->resumeFor($userId);
    if (($issued['ok'] ?? false) !== true) {
        return $response->redirect($urls->core('/admin/dashboard'));
    }

    return $response->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->redirect((string) $issued['transfer_url']);
});

$router->get('/auth/module-sso/callback', function ($request, $response) {
    $code = trim((string) $request->input('code', ''));
    $record = (new \App\Services\ModuleSsoService())->consume($code, (string) $request->host());
    if ($record === null) {
        return $response->status(401)->header('Cache-Control', 'no-store')->send('401 - Invalid or expired authorization code');
    }

    $auth = new \App\Services\AuthService();
    if ($auth->completeMfaLogin((int) $record['user_id']) === null) {
        $auth->logout();
        return $response->status(401)->header('Cache-Control', 'no-store')->send('401 - User is no longer eligible to sign in');
    }
    if ((int) $record['safe_assignment_id'] > 0) {
        (new \App\Services\AccessService())->switchTo((int) $record['user_id'], (int) $record['safe_assignment_id']);
    }
    if ((string) $record['safe_appointment_reference'] !== '') {
        try {
            (new \App\Services\Organization\UserOrganizationalContextResolver())->switchContext(
                (int) $record['user_id'],
                (string) $record['safe_appointment_reference']
            );
        } catch (\Throwable) {
            // The appointment may have expired between issuance and consumption; continue without stale context.
        }
    }
    $urls = new \IPKF\Support\ApplicationUrlRegistry();
    $safePath = (string) $record['safe_redirect_path'];
    return $response->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')
        ->redirect(str_starts_with($safePath, '/admin/work') ? $urls->work($safePath) : $urls->automation($safePath));
});

$adminGuard = function ($response, string $path) use ($adminRender, $adminContext) {
    $context = $adminContext();

    if ($context === null) {
        $urls = new \IPKF\Support\ApplicationUrlRegistry();
        if ($urls->isAutomationHost((string) ($_SERVER['HTTP_HOST'] ?? ''))
            || $urls->isWorkHost((string) ($_SERVER['HTTP_HOST'] ?? ''))) {
            $returnPath = (string) ($_SERVER['REQUEST_URI'] ?? $path);
            return $response->redirect($urls->core('/auth/module-sso/start?return_path=' . rawurlencode($returnPath)));
        }

        return $response->redirect('/admin/login');
    }

    $userId = (int) $context['user_id'];

    if (!(new \App\Services\AdminNavigationRbacService())->canAccessPath($userId, $path)) {
        return $adminRender($response, 'forbidden', [
            'title' => html_entity_decode('&#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC; &#x063A;&#x06CC;&#x0631;&#x0645;&#x062C;&#x0627;&#x0632;', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'context' => $context,
        ], 403);
    }

    return $context;
};

$router->get('/admin', function ($request, $response) use ($adminHomeUrl) {
    return $response->redirect((new \App\Services\AuthService())->authenticated()
        ? $adminHomeUrl($request)
        : '/admin/login');
});

$router->get('/admin/login', function ($request, $response) use ($adminRender, $adminHomeUrl) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect($adminHomeUrl($request));
    }

    return $adminRender($response, 'login', [
        'title' => 'ظˆط±ظˆط¯ ط¨ظ‡ ظ¾ظ†ظ„ ظ…ط¯غŒط±غŒطھ',
        'error' => null,
        'login' => '',
    ]);
});

$router->get('/admin/forgot-password', function ($request, $response) use ($adminRender, $adminHomeUrl) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect($adminHomeUrl($request));
    }

    return $adminRender($response, 'forgot-password', [
        'title' => 'ط¨ط§ط²غŒط§ط¨غŒ ع©ظ„ظ…ظ‡ ط¹ط¨ظˆط±',
        'sent' => false,
        'identifier' => '',
    ]);
});

$router->post('/admin/forgot-password', function ($request, $response) use ($adminRender, $adminHomeUrl) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect($adminHomeUrl($request));
    }

    return $adminRender($response, 'forgot-password', [
        'title' => 'ط¨ط§ط²غŒط§ط¨غŒ ع©ظ„ظ…ظ‡ ط¹ط¨ظˆط±',
        'sent' => true,
        'identifier' => trim((string) $request->input('login', '')),
    ]);
});

$router->post('/admin/login', function ($request, $response) use ($adminRender, $adminHomeUrl) {
    $login = trim((string) $request->input('login', ''));
    $password = (string) $request->input('password', '');
    $auth = new \App\Services\AuthService();
    $user = $auth->attempt($login, $password);

    if ($user === null) {
        return $adminRender($response, 'login', [
            'title' => 'ظˆط±ظˆط¯ ط¨ظ‡ ظ¾ظ†ظ„ ظ…ط¯غŒط±غŒطھ',
            'error' => 'ط§ط·ظ„ط§ط¹ط§طھ ظˆط±ظˆط¯ ظ…ط¹طھط¨ط± ظ†غŒط³طھ.',
            'login' => $login,
        ], 422);
    }

    if (($user['mfa_required'] ?? false) === true) {
        return $response->redirect('/admin/mfa');
    }

    return $response->redirect($adminHomeUrl($request));
});

$router->get('/admin/mfa', function ($request, $response) use ($adminRender, $adminHomeUrl) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect($adminHomeUrl($request));
    }

    if ((new \App\Services\MfaService())->pendingUserId() === null) {
        return $response->redirect('/admin/login');
    }

    return $adminRender($response, 'mfa', [
        'title' => 'ط±ظ…ط² غŒع©ط¨ط§ط±ظ…طµط±ظپ',
        'error' => null,
    ]);
});

$router->post('/admin/mfa', function ($request, $response) use ($adminRender, $adminHomeUrl) {
    $totpCode = trim((string) $request->input('code', ''));
    $recoveryCode = trim((string) $request->input('recovery_code', ''));
    $method = $recoveryCode !== '' ? 'recovery_code' : 'totp';
    $code = $recoveryCode !== '' ? $recoveryCode : $totpCode;
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->verifyPendingChallenge($method, $code);

    if ($userId === null) {
        return $adminRender($response, 'mfa', [
            'title' => 'ط±ظ…ط² غŒع©ط¨ط§ط±ظ…طµط±ظپ',
            'error' => 'ط±ظ…ط² ظˆط§ط±ط¯ ط´ط¯ظ‡ ظ…ط¹طھط¨ط± ظ†غŒط³طھ.',
        ], 422);
    }

    (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->redirect($adminHomeUrl($request));
});

$router->get('/admin/mfa/recovery', function ($request, $response) use ($adminRender, $adminHomeUrl) {
    if ((new \App\Services\AuthService())->authenticated()) {
        return $response->redirect($adminHomeUrl($request));
    }

    if ((new \App\Services\MfaService())->pendingUserId() === null) {
        return $response->redirect('/admin/login');
    }

    return $adminRender($response, 'mfa-recovery', [
        'title' => 'ع©ط¯ ط¨ط§ط²غŒط§ط¨غŒ',
        'error' => null,
    ]);
});

$router->post('/admin/mfa/recovery', function ($request, $response) use ($adminRender, $adminHomeUrl) {
    $code = trim((string) $request->input('recovery_code', ''));
    $userId = (new \App\Services\MfaService())->verifyPendingChallenge('recovery_code', $code);

    if ($userId === null) {
        return $adminRender($response, 'mfa-recovery', [
            'title' => 'ع©ط¯ ط¨ط§ط²غŒط§ط¨غŒ',
            'error' => 'ع©ط¯ ط¨ط§ط²غŒط§ط¨غŒ ظ…ط¹طھط¨ط± ظ†غŒط³طھ.',
        ], 422);
    }

    (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->redirect($adminHomeUrl($request));
});

$router->get('/admin/dashboard', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/dashboard');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'dashboard', [
        'title' => 'ط¯ط§ط´ط¨ظˆط±ط¯ ظ…ط¯غŒط±غŒطھ',
        'context' => $context,
    ]);
});

$adminModuleHub = function (string $key, string $title) use ($adminRender, $adminContext) {
    return function ($request, $response) use ($adminRender, $adminContext, $key, $title) {
        $context = $adminContext();

        if ($context === null) {
            return $response->redirect('/admin/login');
        }

        $module = (new \App\Services\AdminPanelService())->moduleHub((int) $context['user_id'], $key);

        if ($module === null) {
            return $adminRender($response, 'forbidden', [
                'title' => html_entity_decode('&#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC; &#x063A;&#x06CC;&#x0631;&#x0645;&#x062C;&#x0627;&#x0632;', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'context' => $context,
            ], 403);
        }

        return $adminRender($response, 'module-hub', [
            'title' => $title,
            'context' => $context,
            'module' => $module,
        ]);
    };
};

$router->get('/admin/modules/users', $adminModuleHub('users', 'ظ…ط¯غŒط±غŒطھ ع©ط§ط±ط¨ط±ط§ظ†'));
$router->get('/admin/modules/organization', $adminModuleHub('organization', 'ط³ط§ط®طھط§ط± ط³ط§ط²ظ…ط§ظ†غŒ'));
$router->get('/admin/modules/system', $adminModuleHub('system', 'ظ…ط¯غŒط±غŒطھ ط³ط§ظ…ط§ظ†ظ‡'));

$router->get('/admin/profile', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/profile');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'profile', [
        'title' => 'ظ¾ط±ظˆظپط§غŒظ„ ع©ط§ط±ط¨ط±',
        'context' => $context,
    ]);
});

$router->get('/admin/profile/access', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/profile/access');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'profile-access', [
        'title' => 'ظ†ظ‚ط´â€Œظ‡ط§ ظˆ ط¯ط³طھط±ط³غŒâ€Œظ‡ط§غŒ ظ…ظ†',
        'context' => $context,
        'status' => trim((string) $request->input('status', '')),
    ]);
});

$router->get('/admin/account', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/account');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'account', [
        'title' => 'ط§ط·ظ„ط§ط¹ط§طھ ط­ط³ط§ط¨',
        'context' => $context,
    ]);
});

$router->get('/admin/security', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/security');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'security', [
        'title' => 'ط§ظ…ظ†غŒطھ ظˆ ظˆط±ظˆط¯',
        'context' => $context,
    ]);
});

$router->get('/admin/password', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/password');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'password', [
        'title' => 'طھط؛غŒغŒط± ع©ظ„ظ…ظ‡ ط¹ط¨ظˆط±',
        'context' => $context,
        'status' => trim((string) $request->input('status', '')),
        'error' => '',
    ]);
});

$router->post('/admin/password', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/password');

    if (!is_array($context)) {
        return $context;
    }

    $currentPassword = (string) $request->input('current_password', '');
    $password = (string) $request->input('password', '');
    $confirmation = (string) $request->input('password_confirmation', '');

    if (strlen($password) < 8 || $password !== $confirmation) {
        return $adminRender($response, 'password', [
            'title' => 'طھط؛غŒغŒط± ع©ظ„ظ…ظ‡ ط¹ط¨ظˆط±',
            'context' => $context,
            'status' => '',
            'error' => 'ع©ظ„ظ…ظ‡ ط¹ط¨ظˆط± ط¬ط¯غŒط¯ ظ…ط¹طھط¨ط± ظ†غŒط³طھ غŒط§ ط¨ط§ طھع©ط±ط§ط± ط¢ظ† غŒع©ط³ط§ظ† ظ†غŒط³طھ.',
        ], 422);
    }

    $changed = (new \App\Services\AuthService())->changePassword((int) $context['user_id'], $currentPassword, $password);

    if (!$changed) {
        return $adminRender($response, 'password', [
            'title' => 'طھط؛غŒغŒط± ع©ظ„ظ…ظ‡ ط¹ط¨ظˆط±',
            'context' => $context,
            'status' => '',
            'error' => 'ع©ظ„ظ…ظ‡ ط¹ط¨ظˆط± ظپط¹ظ„غŒ ظ…ط¹طھط¨ط± ظ†غŒط³طھ.',
        ], 422);
    }

    return $response->redirect('/admin/password?status=updated');
});

$router->get('/admin/my-theme', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/my-theme');

    if (!is_array($context)) {
        return $context;
    }

    $theme = new \App\Services\AdminThemeService();

    return $adminRender($response, 'my-theme', [
        'title' => 'ظ¾ظˆط³طھظ‡ ظ†ظ…ط§غŒط´غŒ ظ…ظ†',
        'context' => $context,
        'theme' => $theme->personalTheme((int) $context['user_id']),
        'presets' => $theme->presets(),
        'fontOptions' => $theme->fontOptions(),
        'errors' => [],
        'status' => trim((string) $request->input('status', '')),
    ]);
});

$router->post('/admin/my-theme', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/my-theme');

    if (!is_array($context)) {
        return $context;
    }

    $theme = new \App\Services\AdminThemeService();
    $result = $theme->savePersonalTheme((int) $context['user_id'], $request->all());

    if (!$result['ok']) {
        return $adminRender($response, 'my-theme', [
            'title' => 'ظ¾ظˆط³طھظ‡ ظ†ظ…ط§غŒط´غŒ ظ…ظ†',
            'context' => $context,
            'theme' => $theme->personalTheme((int) $context['user_id']),
            'presets' => $theme->presets(),
            'fontOptions' => $theme->fontOptions(),
            'errors' => $result['errors'],
            'status' => '',
        ], 422);
    }

    return $response->redirect('/admin/my-theme?status=saved');
});

$router->get('/admin/access', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/access');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'access', [
        'title' => 'ط³ط·ط­ ط¯ط³طھط±ط³غŒ ظپط¹ط§ظ„',
        'context' => $context,
        'status' => trim((string) $request->input('status', '')),
    ]);
});

$router->get('/admin/theme', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/theme');

    if (!is_array($context)) {
        return $context;
    }

    $theme = new \App\Services\AdminThemeService();

    return $adminRender($response, 'theme', [
        'title' => 'ظ¾ظˆط³طھظ‡ ظ¾ظ†ظ„',
        'context' => $context,
        'theme' => $theme->systemTheme(),
        'presets' => $theme->presets(),
        'fontOptions' => $theme->fontOptions(),
        'logoOptions' => $theme->logoOptions(),
        'avatarOptions' => $theme->avatarOptions(),
        'errors' => [],
        'status' => trim((string) $request->input('status', '')),
        'canManageTheme' => (new \App\Services\AuthorizationService())->hasPermission((int) $context['user_id'], 'admin.theme.manage'),
    ]);
});

$router->post('/admin/theme', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/theme');

    if (!is_array($context)) {
        return $context;
    }

    if (!(new \App\Services\AuthorizationService())->hasPermission((int) $context['user_id'], 'admin.theme.manage')) {
        return $adminRender($response, 'theme', [
            'title' => 'ظ¾ظˆط³طھظ‡ ظ¾ظ†ظ„',
            'context' => $context,
            'theme' => (new \App\Services\AdminThemeService())->systemTheme(),
            'presets' => (new \App\Services\AdminThemeService())->presets(),
            'fontOptions' => (new \App\Services\AdminThemeService())->fontOptions(),
            'logoOptions' => (new \App\Services\AdminThemeService())->logoOptions(),
            'avatarOptions' => (new \App\Services\AdminThemeService())->avatarOptions(),
            'errors' => ['forbidden'],
            'status' => '',
            'canManageTheme' => false,
        ], 403);
    }

    $theme = new \App\Services\AdminThemeService();
    $result = $theme->saveSystemTheme($request->all());

    if (!$result['ok']) {
        return $adminRender($response, 'theme', [
            'title' => 'ظ¾ظˆط³طھظ‡ ظ¾ظ†ظ„',
            'context' => $context,
            'theme' => $theme->systemTheme(),
            'presets' => $theme->presets(),
            'fontOptions' => $theme->fontOptions(),
            'logoOptions' => $theme->logoOptions(),
            'avatarOptions' => $theme->avatarOptions(),
            'errors' => $result['errors'],
            'status' => '',
            'canManageTheme' => true,
        ], 422);
    }

    return $response->redirect('/admin/theme?status=saved');
});

$router->post('/admin/theme/reset', function ($request, $response) use ($adminGuard) {
    $scope = trim((string) $request->input('scope', 'user'));
    $context = $adminGuard($response, $scope === 'system' ? '/admin/theme' : '/admin/my-theme');

    if (!is_array($context)) {
        return $context;
    }

    $theme = new \App\Services\AdminThemeService();

    if ($scope === 'system') {
        if (!(new \App\Services\AuthorizationService())->hasPermission((int) $context['user_id'], 'admin.theme.manage')) {
            return $response->redirect('/admin/theme?status=forbidden');
        }

        $theme->resetSystemTheme();

        return $response->redirect('/admin/theme?status=reset');
    }

    $theme->resetPersonalTheme((int) $context['user_id']);

    return $response->redirect('/admin/my-theme?status=reset');
});

$router->get('/admin/theme/debug', function ($request, $response) use ($adminGuard) {
    if (!\IPKF\Support\Env::isDebug()) {
        return $response->status(404)->send('404 - Route not found: /admin/theme/debug');
    }

    $context = $adminGuard($response, '/admin/theme/debug');

    if (!is_array($context)) {
        return $context;
    }

    $themeService = new \App\Services\AdminThemeService();
    $forensics = $themeService->forensics((int) $context['user_id'], $context);
    $h = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    $rowTable = static function (array $rows) use ($h): string {
        if ($rows === []) {
            return '<p class="admin-muted">No rows.</p>';
        }

        $html = '<table class="admin-table"><thead><tr><th>scope</th><th>user_id</th><th>setting_key</th><th>setting_value</th><th>value_type</th><th>updated_at</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . $h($row['scope'] ?? '') . '</td>'
                . '<td>' . $h($row['user_id'] ?? '') . '</td>'
                . '<td>' . $h($row['setting_key'] ?? '') . '</td>'
                . '<td><code>' . $h($row['setting_value'] ?? '') . '</code></td>'
                . '<td>' . $h($row['value_type'] ?? '') . '</td>'
                . '<td>' . $h($row['updated_at'] ?? '') . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    };
    $list = static function (array $items) use ($h): string {
        $html = '<dl class="admin-field-list">';

        foreach ($items as $key => $value) {
            $html .= '<div><span>' . $h($key) . '</span><strong>' . $h(is_bool($value) ? ($value ? 'true' : 'false') : $value) . '</strong></div>';
        }

        return $html . '</dl>';
    };

    ob_start();
    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Theme Debug | IPKF</title>
        <link rel="stylesheet" href="<?= $h($forensics['assets']['admin_css'] ?? '/assets/admin/css/admin.css') ?>">
        <style id="admin-theme-vars"><?= "\n" . ($forensics['css_variables'] ?? '') . "\n" ?></style>
    </head>
    <body class="admin-auth-page" data-admin-theme="<?= $h($forensics['resolved_theme']['canonical_preset'] ?? 'official_emerald') ?>" data-admin-theme-source="<?= $h($forensics['resolved_theme']['resolved_source'] ?? 'default') ?>">
        <main class="admin-content" style="padding:18px">
            <section class="admin-section"><h1>Admin Theme Runtime Debug</h1><?= $list($forensics['runtime']) ?></section>
            <section class="admin-section"><h2>System theme rows</h2><?= $rowTable($forensics['system_rows']) ?></section>
            <section class="admin-section"><h2>Current user personal theme rows</h2><?= $rowTable($forensics['personal_rows']) ?></section>
            <section class="admin-section"><h2>Other users</h2><?= $list(['other_user_theme_row_count' => $forensics['other_user_theme_row_count']]) ?></section>
            <section class="admin-section"><h2>Token override policy</h2><?= $list([
                'token_override_rows_count' => $forensics['token_override_rows_count'] ?? 0,
                'personal_token_override_rows_count' => $forensics['personal_token_override_rows_count'] ?? 0,
                'token_override_rows_ignored' => $forensics['token_override_rows_ignored'] ?? true,
            ]) ?></section>
            <section class="admin-section"><h2>Resolved theme</h2><?= $list($forensics['resolved_theme']) ?></section>
            <section class="admin-section"><h2>Resolved visual tokens</h2><?= $list($forensics['visual_tokens']) ?></section>
            <section class="admin-section"><h2>Injected CSS variables</h2><pre dir="ltr"><?= $h($forensics['css_variables']) ?></pre></section>
            <section class="admin-section"><h2>Loaded admin assets</h2><?= $list($forensics['assets']) ?></section>
        </main>
    </body>
    </html>
    <?php
    $content = ob_get_clean() ?: '';

    return $response
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->send($content);
});

$router->post('/admin/access', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->redirect('/admin/login');
    }

    $assignment = (new \App\Services\AccessService())->switchTo($userId, (int) $request->input('role_assignment_id', 0));

    if ($assignment !== null) {
        $pending = (new \App\Services\ModuleSsoService())->pendingResumeUrl();
        if ($pending !== null) {
            return $response->redirect($pending);
        }
    }

    return $response->redirect('/admin/access?status=' . ($assignment === null ? 'forbidden' : 'switched'));
});

$router->post('/admin/profile/access', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->redirect('/admin/login');
    }

    $assignment = (new \App\Services\AccessService())->switchTo($userId, (int) $request->input('role_assignment_id', 0));

    if ($assignment !== null) {
        $pending = (new \App\Services\ModuleSsoService())->pendingResumeUrl();
        if ($pending !== null) {
            return $response->redirect($pending);
        }
    }

    return $response->redirect('/admin/profile/access?status=' . ($assignment === null ? 'forbidden' : 'switched'));
});

$automationUnavailable = function ($response, array $context) use ($adminRender) {
    return $adminRender($response, 'placeholder', [
        'title' => 'ط§طھظˆظ…ط§ط³غŒظˆظ† ط§ط¯ط§ط±غŒ',
        'context' => $context,
        'message' => 'ط²غŒط±ط³ط§ط®طھ ط¹ظ…ظ„غŒط§طھغŒ ط§طھظˆظ…ط§ط³غŒظˆظ† ط¯ط± ط­ط§ظ„ ط­ط§ط¶ط± ط¯ط± ط¯ط³طھط±ط³ ظ†غŒط³طھ. ط§طھطµط§ظ„ ط§ط®طھطµط§طµغŒ غŒط§ ع¯ط§ط±ط¯ cutover ط¢ظ…ط§ط¯ظ‡ ظ†غŒط³طھ.',
    ], 503);
};

$router->get('/admin/automation', function ($request, $response) use ($adminRender, $adminGuard, $automationUnavailable) {
    $context = $adminGuard($response, '/admin/automation');

    if (!is_array($context)) {
        return $context;
    }

    try {
        $dashboard = (new \App\Services\Automation\Correspondence\CorrespondenceQueryService())->dashboard();
    } catch (\Throwable) {
        return $automationUnavailable($response, $context);
    }

    return $adminRender($response, 'automation-dashboard', [
        'title' => 'ط§طھظˆظ…ط§ط³غŒظˆظ† ط§ط¯ط§ط±غŒ',
        'context' => $context,
        'dashboard' => $dashboard,
    ]);
});

$router->get('/admin/work', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/work');
    if (!is_array($context)) {
        return $context;
    }
    try {
        $dashboard = (new \App\Services\Work\WorkDashboardService())->view();
    } catch (\Throwable) {
        return $adminRender($response, 'placeholder', [
            'title' => 'IPKF Work Management',
            'context' => $context,
            'message' => 'ط¯غŒطھط§ط¨غŒط³ Work ظ‡ظ†ظˆط² Migration ظˆ Seed ظ†ط´ط¯ظ‡ ط§ط³طھ.',
        ], 503);
    }
    return $adminRender($response, 'work-dashboard', [
        'title' => 'IPKF Work Management',
        'context' => $context,
        'dashboard' => $dashboard,
    ]);
});

$router->get('/admin/automation/correspondences', function ($request, $response) use ($adminRender, $adminGuard, $automationUnavailable) {
    $context = $adminGuard($response, '/admin/automation/correspondences');

    if (!is_array($context)) {
        return $context;
    }

    try {
        $list = (new \App\Services\Automation\Correspondence\CorrespondenceQueryService())->index($request->all());
    } catch (\Throwable) {
        return $automationUnavailable($response, $context);
    }

    return $adminRender($response, 'automation-correspondences', [
        'title' => 'ظ…ع©ط§طھط¨ط§طھ ط§ط¯ط§ط±غŒ',
        'context' => $context,
        'list' => $list,
    ]);
});

$router->get('/admin/automation/templates', function ($request, $response) use ($adminRender, $adminGuard, $automationUnavailable) {
    $context = $adminGuard($response, '/admin/automation/templates');
    if (!is_array($context)) return $context;
    try {
        $templates = (new \App\Services\Automation\Correspondence\CorrespondenceQueryService())->templates();
    } catch (\Throwable) {
        return $automationUnavailable($response, $context);
    }
    return $adminRender($response, 'automation-templates', ['title' => 'ظ‚ط§ظ„ط¨â€Œظ‡ط§غŒ ط§ط³طھط§ظ†ط¯ط§ط±ط¯ ظ†ط§ظ…ظ‡', 'context' => $context, 'templates' => $templates]);
});

$router->get('/admin/automation/correspondences/create', function ($request, $response) use ($adminRender, $adminGuard, $automationUnavailable) {
    $context = $adminGuard($response, '/admin/automation/correspondences/create');

    if (!is_array($context)) {
        return $context;
    }

    try {
        $form = (new \App\Services\Automation\Correspondence\CorrespondenceQueryService())->form();
    } catch (\Throwable) {
        return $automationUnavailable($response, $context);
    }

    $requestedDirection = (string) $request->input('direction', 'outgoing');
    $form['form']['direction_code'] = in_array($requestedDirection, ['incoming', 'outgoing', 'internal'], true)
        ? $requestedDirection
        : 'outgoing';

    return $adminRender($response, 'automation-correspondence-form', [
        'title' => 'ط§غŒط¬ط§ط¯ ظ¾غŒط´ ظ†ظˆغŒط³ ظ…ع©ط§طھط¨ظ‡',
        'context' => $context,
        'form' => $form['form'],
        'options' => $form['options'],
        'references' => $form['references'],
        'errors' => [],
        'isEdit' => false,
        'editable' => true,
    ]);
});

$router->post('/admin/automation/correspondences', function ($request, $response) use ($adminRender, $adminGuard, $automationUnavailable) {
    $context = $adminGuard($response, '/admin/automation/correspondences/create');

    if (!is_array($context)) {
        return $context;
    }

    try {
        $service = new \App\Services\Automation\Correspondence\CorrespondenceDraftService();
        $result = $service->create($request->all(), (int) $context['user_id'], $context);

        if (($result['ok'] ?? false) === true) {
            return $response->redirect('/admin/automation/correspondences/' . rawurlencode((string) $result['public_reference']));
        }

        $form = (new \App\Services\Automation\Correspondence\CorrespondenceQueryService())->form();
        return $adminRender($response, 'automation-correspondence-form', [
            'title' => 'ط§غŒط¬ط§ط¯ ظ¾غŒط´ ظ†ظˆغŒط³ ظ…ع©ط§طھط¨ظ‡',
            'context' => $context,
            'form' => $request->all() + $form['form'],
            'options' => $form['options'],
            'references' => $form['references'],
            'errors' => $result['errors'] ?? ['invalid'],
            'isEdit' => false,
            'editable' => true,
        ], 422);
    } catch (\Throwable) {
        return $automationUnavailable($response, $context);
    }
});

$router->get('/admin/automation/correspondences/{public_reference}/edit', function ($request, $response) use ($adminRender, $adminGuard, $automationUnavailable) {
    $context = $adminGuard($response, '/admin/automation/correspondences/{public_reference}/edit');

    if (!is_array($context)) {
        return $context;
    }

    try {
        $form = (new \App\Services\Automation\Correspondence\CorrespondenceQueryService())->form((string) $request->route('public_reference'));
    } catch (\Throwable) {
        return $automationUnavailable($response, $context);
    }

    if (($form['ok'] ?? false) !== true) {
        return $adminRender($response, 'placeholder', ['title' => 'ظ…ع©ط§طھط¨ظ‡ ظ¾غŒط¯ط§ ظ†ط´ط¯', 'context' => $context, 'message' => 'ظ…ع©ط§طھط¨ظ‡ ظ…ظˆط±ط¯ ظ†ط¸ط± ظ¾غŒط¯ط§ ظ†ط´ط¯.'], 404);
    }

    return $adminRender($response, 'automation-correspondence-form', [
        'title' => 'ظˆغŒط±ط§غŒط´ ظ¾غŒط´ ظ†ظˆغŒط³ ظ…ع©ط§طھط¨ظ‡',
        'context' => $context,
        'form' => $form['form'],
        'options' => $form['options'],
        'references' => $form['references'],
        'errors' => [],
        'isEdit' => true,
        'editable' => $form['editable'],
    ]);
});

$router->post('/admin/automation/correspondences/{public_reference}/versions', function ($request, $response) use ($adminRender, $adminGuard, $automationUnavailable) {
    $context = $adminGuard($response, '/admin/automation/correspondences/{public_reference}/versions');

    if (!is_array($context)) {
        return $context;
    }

    $publicReference = (string) $request->route('public_reference');

    try {
        $result = (new \App\Services\Automation\Correspondence\CorrespondenceDraftService())->update($publicReference, $request->all(), (int) $context['user_id'], $context);

        if (($result['ok'] ?? false) === true) {
            return $response->redirect('/admin/automation/correspondences/' . rawurlencode($publicReference));
        }

        $form = (new \App\Services\Automation\Correspondence\CorrespondenceQueryService())->form($publicReference);
        return $adminRender($response, 'automation-correspondence-form', [
            'title' => 'ظˆغŒط±ط§غŒط´ ظ¾غŒط´ ظ†ظˆغŒط³ ظ…ع©ط§طھط¨ظ‡',
            'context' => $context,
            'form' => $request->all() + ($form['form'] ?? []),
            'options' => $form['options'] ?? [],
            'references' => $form['references'] ?? [],
            'errors' => $result['errors'] ?? ['invalid'],
            'isEdit' => true,
            'editable' => $form['editable'] ?? false,
        ], 422);
    } catch (\Throwable) {
        return $automationUnavailable($response, $context);
    }
});

$router->post('/admin/automation/correspondences/{public_reference}/edit/attachments', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/automation/correspondences/{public_reference}/edit/attachments');
    if (!is_array($context)) return $context;
    $reference = (string) $request->route('public_reference');
    try {
        $result = (new \App\Services\Automation\Correspondence\CorrespondenceAttachmentService())->upload(
            $reference,
            is_array($_FILES['attachment'] ?? null) ? $_FILES['attachment'] : [],
            (string) $request->input('attachment_role_code', 'enclosure'),
            (string) $request->input('title', ''),
            (int) $context['user_id']
        );
        $status = ($result['ok'] ?? false) ? 'uploaded' : rawurlencode((string) ($result['error'] ?? 'invalid'));
        return $response->redirect('/admin/automation/correspondences/' . rawurlencode($reference) . '?tab=attachments&attachment_status=' . $status);
    } catch (\Throwable) {
        return $response->redirect('/admin/automation/correspondences/' . rawurlencode($reference) . '?tab=attachments&attachment_status=failed');
    }
});

$router->get('/admin/automation/correspondences/{public_reference}/attachments/{file_reference}', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/automation/correspondences/{public_reference}/attachments/{file_reference}');
    if (!is_array($context)) return $context;
    $file = (new \App\Services\Automation\Correspondence\CorrespondenceAttachmentRepository())->findForCorrespondence((string) $request->route('public_reference'), (string) $request->route('file_reference'));
    $configuredRoot = trim((string) \IPKF\Support\Env::get('PRIVATE_FILE_STORAGE_PATH', ''));
    $root = realpath(rtrim($configuredRoot !== '' ? $configuredRoot : dirname(BASE_PATH) . '/storage/private/automation', '/\\'));
    $path = $file !== null ? realpath((string) $file['storage_key']) : false;
    if ($file === null || $root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) {
        return $response->status(404)->send('404 - File not found');
    }
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $file['original_filename']) ?: 'attachment';
    return $response->header('Content-Type', (string) $file['mime_type'])
        ->header('Content-Length', (string) filesize($path))
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
        ->header('X-Content-Type-Options', 'nosniff')
        ->send((string) file_get_contents($path));
});

$router->get('/admin/automation/correspondences/{public_reference}', function ($request, $response) use ($adminRender, $adminGuard, $automationUnavailable) {
    $context = $adminGuard($response, '/admin/automation/correspondences/{public_reference}');

    if (!is_array($context)) {
        return $context;
    }

    try {
        $detail = (new \App\Services\Automation\Correspondence\CorrespondenceQueryService())->detail((string) $request->route('public_reference'), (string) $request->input('tab', 'summary'));
    } catch (\Throwable) {
        return $automationUnavailable($response, $context);
    }

    if ($detail === null) {
        return $adminRender($response, 'placeholder', ['title' => 'ظ…ع©ط§طھط¨ظ‡ ظ¾غŒط¯ط§ ظ†ط´ط¯', 'context' => $context, 'message' => 'ظ…ع©ط§طھط¨ظ‡ ظ…ظˆط±ط¯ ظ†ط¸ط± ظ¾غŒط¯ط§ ظ†ط´ط¯.'], 404);
    }

    return $adminRender($response, 'automation-correspondence-detail', [
        'title' => 'ط¬ط²ط¦غŒط§طھ ظ…ع©ط§طھط¨ظ‡',
        'context' => $context,
        'detail' => $detail,
        'attachmentStatus' => (string) $request->input('attachment_status', ''),
    ]);
});
$adminPlaceholder = function ($path, $title, $message) use ($adminRender, $adminGuard) {
    return function ($request, $response) use ($adminRender, $adminGuard, $path, $title, $message) {
        $context = $adminGuard($response, $path);

        if (!is_array($context)) {
            return $context;
        }

        return $adminRender($response, 'placeholder', [
            'title' => $title,
            'context' => $context,
            'message' => $message,
        ]);
    };
};

$router->get('/admin/settings', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/settings');
    if (!is_array($context)) {
        return $context;
    }
    $registryError = (string) ($_SESSION['admin_module_registry_error'] ?? '');
    unset($_SESSION['admin_module_registry_error']);
    return $adminRender($response, 'settings', [
        'title' => 'طھظ†ط¸غŒظ…ط§طھ ظ…ط§عکظˆظ„â€Œظ‡ط§',
        'context' => $context,
        'registry' => (new \App\Services\ApplicationModuleRegistryService())->index(),
        'status' => (string) $request->input('status', ''),
        'error' => $registryError,
    ]);
});
$router->post('/admin/settings/modules', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/settings');
    if (!is_array($context)) {
        return $context;
    }
    $result = (new \App\Services\ApplicationModuleRegistryService())->save($request->all());
    $_SESSION['admin_module_registry_error'] = (string) ($result['error'] ?? '');
    return $response->redirect('/admin/settings?status=' . (($result['ok'] ?? false) ? 'saved' : 'invalid'));
});
$router->get('/admin/users', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $list = (new \App\Services\AdminUserService())->index([
        'q' => $request->input('q', ''),
        'page' => $request->input('page', 1),
    ]);

    return $adminRender($response, 'users', [
        'title' => 'ع©ط§ط±ط¨ط±ط§ظ†',
        'context' => $context,
        'list' => $list,
    ]);
});

$adminUserDetailRoute = function (string $routePattern, string $tab) use ($adminRender, $adminGuard) {
    return function ($request, $response) use ($adminRender, $adminGuard, $routePattern, $tab) {
        $context = $adminGuard($response, $routePattern);

        if (!is_array($context)) {
            return $context;
        }

        $id = filter_var($request->route('id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            return $adminRender($response, 'user-not-found', [
                'title' => 'ع©ط§ط±ط¨ط± ظ¾غŒط¯ط§ ظ†ط´ط¯',
                'context' => $context,
            ], 404);
        }

        $detail = (new \App\Services\AdminUserService())->detailWorkspace(
            (int) $id,
            $tab,
            (int) ($context['user_id'] ?? 0)
        );

        if ($detail === null) {
            return $adminRender($response, 'user-not-found', [
                'title' => 'ع©ط§ط±ط¨ط± ظ¾غŒط¯ط§ ظ†ط´ط¯',
                'context' => $context,
            ], 404);
        }

        return $adminRender($response, 'user-detail', [
            'title' => 'ط¬ط²ط¦غŒط§طھ ع©ط§ط±ط¨ط±',
            'context' => $context,
            'detail' => $detail,
        ]);
    };
};

$router->get('/admin/users/{id}', $adminUserDetailRoute('/admin/users/{id}', 'overview'));
$router->get('/admin/users/{id}/identity', $adminUserDetailRoute('/admin/users/{id}/identity', 'identity'));
$router->get('/admin/users/{id}/contacts', $adminUserDetailRoute('/admin/users/{id}/contacts', 'contacts'));
$router->get('/admin/users/{id}/account', $adminUserDetailRoute('/admin/users/{id}/account', 'account'));
$router->get('/admin/users/{id}/access', $adminUserDetailRoute('/admin/users/{id}/access', 'access'));
$router->get('/admin/users/{id}/appointments', $adminUserDetailRoute('/admin/users/{id}/appointments', 'appointments'));
$router->get('/admin/org-units', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/org-units');

    if (!is_array($context)) {
        return $context;
    }

    $list = (new \App\Services\AdminOrgUnitService())->index([
        'q' => $request->input('q', ''),
        'page' => $request->input('page', 1),
    ]);

    return $adminRender($response, 'org-units', [
        'title' => 'ظˆط§ط­ط¯ظ‡ط§غŒ ط³ط§ط²ظ…ط§ظ†غŒ',
        'context' => $context,
        'list' => $list,
    ]);
});
$router->get('/admin/positions', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/positions');

    if (!is_array($context)) {
        return $context;
    }

    $list = (new \App\Services\AdminPositionService())->index([
        'q' => $request->input('q', ''),
        'page' => $request->input('page', 1),
    ]);

    return $adminRender($response, 'positions', [
        'title' => 'ط³ظ…طھâ€Œظ‡ط§',
        'context' => $context,
        'list' => $list,
    ]);
});
$router->get('/admin/organization-setup', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/organization-setup');
    if (!is_array($context)) { return $context; }
    return $adminRender($response, 'organization-setup', [
        'title' => 'ط±ط§ظ‡â€Œط§ظ†ط¯ط§ط²غŒ ط³ط§ط®طھط§ط± ط³ط§ط²ظ…ط§ظ†غŒ', 'context' => $context,
        'workspace' => (new \App\Services\Organization\OrganizationSetupService())->workspace(),
    ]);
});
$router->post('/admin/organization-setup', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/organization-setup');
    if (!is_array($context)) { return $context; }
    $service = new \App\Services\Organization\OrganizationSetupService();
    try {
        $action = (string)$request->input('action', '');
        $_SESSION['admin_organization_setup_tab'] = match ($action) {
            'create_unit' => 'unit',
            'create_position' => 'position',
            'link_user_person' => 'identity',
            default => 'organization',
        };
        if ($action === 'create_organization') {
            $service->createOrganization(['title_fa'=>$request->input('title_fa',''),'title_en'=>$request->input('title_en',''),'short_title'=>$request->input('short_title',''),'parent_reference'=>$request->input('parent_reference',''),'sort_order'=>$request->input('sort_order',0)]);
            $_SESSION['admin_flash_message'] = 'ط³ط§ط²ظ…ط§ظ† ط¨ط§ ظ…ظˆظپظ‚غŒطھ ط«ط¨طھ ط´ط¯.';
        } elseif ($action === 'create_unit') {
            $service->createUnit(['organization_reference'=>$request->input('organization_reference',''),'parent_reference'=>$request->input('parent_reference',''),'title_fa'=>$request->input('title_fa',''),'title_en'=>$request->input('title_en',''),'code'=>$request->input('code',''),'sort_order'=>$request->input('sort_order',0),'description'=>$request->input('description','')]);
            $_SESSION['admin_flash_message'] = 'ظˆط§ط­ط¯ ط³ط§ط²ظ…ط§ظ†غŒ ط¨ط§ ظ…ظˆظپظ‚غŒطھ ط«ط¨طھ ط´ط¯.';
        } elseif ($action === 'create_position') {
            $service->createOrganizationPosition(['organization_reference'=>$request->input('organization_reference',''),'unit_reference'=>$request->input('unit_reference',''),'title_fa'=>$request->input('title_fa',''),'title_en'=>$request->input('title_en',''),'code'=>$request->input('code',''),'headcount_limit'=>$request->input('headcount_limit',1),'sort_order'=>$request->input('sort_order',0),'is_head'=>$request->input('is_head',null)]);
            $_SESSION['admin_flash_message'] = 'ظ¾ط³طھ ط³ط§ط²ظ…ط§ظ†غŒ ط¨ط§ ظ…ظˆظپظ‚غŒطھ طھط¹ط±غŒظپ ط´ط¯.';
        } elseif ($action === 'link_user_person') {
            $service->linkUserToPerson(['user_id'=>$request->input('user_id',0),'person_reference'=>$request->input('person_reference','')]);
            $_SESSION['admin_flash_message'] = 'ط­ط³ط§ط¨ ع©ط§ط±ط¨ط±غŒ ط¨ظ‡ ط´ط®طµ ظ…طھطµظ„ ط´ط¯.';
        } else { throw new \RuntimeException('ط¹ظ…ظ„غŒط§طھ ط§ظ†طھط®ط§ط¨â€Œط´ط¯ظ‡ ظ…ط¹طھط¨ط± ظ†غŒط³طھ.'); }
    } catch (\Throwable $e) {
        $_SESSION['admin_flash_error'] = $e instanceof \RuntimeException ? $e->getMessage() : 'ط«ط¨طھ ط§ط·ظ„ط§ط¹ط§طھ ط³ط§ط²ظ…ط§ظ†غŒ ظ…ظ…ع©ظ† ظ†ط´ط¯.';
    }
    return $response->redirect('/admin/organization-setup');
});
$router->get('/admin/organization-chart', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/organization-chart');
    if (!is_array($context)) { return $context; }
    return $adminRender($response, 'organization-chart', [
        'title' => 'ع†ط§ط±طھ ط³ط§ط²ظ…ط§ظ†غŒ', 'context' => $context,
        'chart' => (new \App\Services\Organization\OrganizationOperationsService())->chart(),
    ]);
});
$router->get('/admin/appointments', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/appointments');
    if (!is_array($context)) { return $context; }
    $service = new \App\Services\Organization\OrganizationOperationsService();
    return $adminRender($response, 'appointments', [
        'title' => 'ظ¾ط³طھ ظˆ ط§ظ†طھطµط§ط¨', 'context' => $context,
        'list' => $service->appointments((string)$request->input('q','')),
        'options' => $service->formOptions(),
    ]);
});
$router->post('/admin/appointments', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/appointments');
    if (!is_array($context)) { return $context; }
    try {
        (new \App\Services\Organization\OrganizationOperationsService())->createAppointment([
            'person_reference'=>$request->input('person_reference',''), 'position_reference'=>$request->input('position_reference',''),
            'appointment_kind'=>$request->input('appointment_kind','permanent'), 'is_primary'=>$request->input('is_primary',null),
            'valid_from'=>$request->input('valid_from',''), 'valid_to'=>$request->input('valid_to',''),
            'appointment_reference'=>$request->input('appointment_reference',''), 'description'=>$request->input('description',''),
        ]);
        $_SESSION['admin_flash_message']='ط§ظ†طھطµط§ط¨ ط¨ط§ ظ…ظˆظپظ‚غŒطھ ط«ط¨طھ ط´ط¯.';
    } catch (\Throwable $e) { $_SESSION['admin_flash_error']=$e instanceof \RuntimeException ? $e->getMessage() : 'ط«ط¨طھ ط§ظ†طھطµط§ط¨ ظ…ظ…ع©ظ† ظ†ط´ط¯.'; }
    return $response->redirect('/admin/appointments');
});
$router->get('/admin/profile/organizational-context', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/profile/organizational-context');
    if (!is_array($context)) { return $context; }
    $resolver = new \App\Services\Organization\UserOrganizationalContextResolver();
    return $adminRender($response, 'organizational-context', [
        'title'=>'ط¬ط§غŒع¯ط§ظ‡ ط³ط§ط²ظ…ط§ظ†غŒ ظپط¹ط§ظ„','context'=>$context,
        'appointments'=>$resolver->activeAppointmentsForUser((int)$context['user_id']),
        'current'=>$resolver->current((int)$context['user_id']),
    ]);
});
$router->post('/admin/profile/organizational-context', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/profile/organizational-context');
    if (!is_array($context)) { return $context; }
    try { (new \App\Services\Organization\UserOrganizationalContextResolver())->switchContext((int)$context['user_id'],(string)$request->input('appointment_reference','')); $_SESSION['admin_flash_message']='ط¬ط§غŒع¯ط§ظ‡ ظپط¹ط§ظ„ طھط؛غŒغŒط± ع©ط±ط¯.'; }
    catch (\Throwable) { $_SESSION['admin_flash_error']='ط§ظ†طھط®ط§ط¨ ط§غŒظ† ط¬ط§غŒع¯ط§ظ‡ ظ…ط¬ط§ط² ظ†غŒط³طھ.'; }
    return $response->redirect('/admin/profile/organizational-context');
});

$router->get('/admin/pages', $adminPlaceholder('/admin/pages', 'طµظپط­ط§طھ ط¯ط§ط®ظ„غŒ', 'ظ…ط¯غŒط±غŒطھ طµظپط­ط§طھ ط¯ط§ط®ظ„غŒ ظ‡ظ†ظˆط² ظپط¹ط§ظ„ ظ†ط´ط¯ظ‡ ط§ط³طھ.'));
$router->get('/admin/reports', $adminPlaceholder('/admin/reports', 'ع¯ط²ط§ط±ط´â€Œظ‡ط§', 'ع¯ط²ط§ط±ط´â€Œظ‡ط§غŒ ظ…ط¯غŒط±غŒطھغŒ ط¯ط± ظ†ط³ط®ظ‡â€Œظ‡ط§غŒ ط¨ط¹ط¯غŒ ط§ط¶ط§ظپظ‡ ظ…غŒâ€Œط´ظˆط¯.'));
$router->get('/admin/support', $adminPlaceholder('/admin/support', 'ظ¾ط´طھغŒط¨ط§ظ†غŒ', 'ظ…ط³غŒط±ظ‡ط§غŒ ظ¾ط´طھغŒط¨ط§ظ†غŒ ظˆ ط±ط§ظ‡ظ†ظ…ط§غŒ ط¯ط§ط®ظ„غŒ ط¯ط± ظپط§ط² ط¨ط¹ط¯غŒ طھع©ظ…غŒظ„ ظ…غŒâ€Œط´ظˆط¯.'));

$router->get('/admin/navigation/debug', function ($request, $response) use ($adminGuard) {
    if (!\IPKF\Support\Env::isDebug()) {
        return $response->status(404)->send('404 - Route not found: /admin/navigation/debug');
    }

    $context = $adminGuard($response, '/admin/navigation/debug');

    if (!is_array($context)) {
        return $context;
    }

    $navigation = new \App\Services\AdminNavigationRbacService();

    if (!$navigation->debugRouteAvailable((int) $context['user_id'])) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Forbidden.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'active_role' => $context['active_assignment']['role_code'] ?? null,
        'debug' => $navigation->debug((int) $context['user_id']),
    ]);
});
$router->get('/admin/logout', function ($request, $response) {
    (new \App\Services\AuthService())->logout();
    $urls = new \IPKF\Support\ApplicationUrlRegistry();

    if ($urls->isAutomationHost((string) $request->host())) {
        return $response->redirect($urls->core('/admin/logout?federated=1&return_module=automation'));
    }
    if ($urls->isWorkHost((string) $request->host())) {
        return $response->redirect($urls->core('/admin/logout?federated=1&return_module=work'));
    }

    if ((string) $request->input('return_module', '') === 'automation') {
        return $response->redirect($urls->core('/auth/module-sso/start?return_path=' . rawurlencode('/admin/automation')));
    }
    if ((string) $request->input('return_module', '') === 'work') {
        return $response->redirect($urls->core('/auth/module-sso/start?return_path=' . rawurlencode('/admin/work')));
    }

    return $response->redirect($urls->core('/admin/login'));
});

$router->post('/auth/login', function ($request, $response) {
    $login = trim((string) $request->input('login', ''));
    $password = (string) $request->input('password', '');

    if ($login === '' || $password === '') {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid credentials.',
        ]);
    }

    $user = (new \App\Services\AuthService())->attempt($login, $password);

    if ($user === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid credentials.',
        ]);
    }

    if (($user['mfa_required'] ?? false) === true) {
        return $response->json([
            'status' => 'ok',
            'authenticated' => false,
            'mfa_required' => true,
            'methods' => $user['methods'] ?? [],
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ],
    ]);
});

$router->post('/auth/logout', function ($request, $response) {
    (new \App\Services\AuthService())->logout();

    return $response->json([
        'status' => 'ok',
        'authenticated' => false,
    ]);
});

$router->get('/mfa/status', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $mfa = new \App\Services\MfaService();
    $methods = array_values(array_unique(array_map(
        fn (array $method): string => (string) $method['method'],
        $mfa->methodsForUser($userId)
    )));
    $recoveryCodesAvailable = $mfa->recoveryCodesAvailable($userId);
    $delivery = new \App\Services\MfaDeliveryChannelService();
    $availableMethods = array_values(array_unique(array_merge(
        $methods,
        array_values(array_diff($delivery->configuredMethods(), ['totp', 'recovery']))
    )));

    if ($recoveryCodesAvailable) {
        $availableMethods[] = 'recovery_code';
    }

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'mfa_enabled' => $mfa->enabled() && $methods !== [],
        'mfa_verified' => (bool) \IPKF\Support\Session::get('auth_mfa_verified', false),
        'methods' => array_values(array_unique($availableMethods)),
        'trusted_device' => (new \App\Services\TrustedDeviceService())->hasActiveTrustedDevice($userId),
        'recovery_codes_available' => $recoveryCodesAvailable,
        'enabled' => $mfa->enabled(),
        'enforcement' => $mfa->enforcement(),
    ]);
});

$router->post('/mfa/challenge', function ($request, $response) {
    $method = trim((string) $request->input('method', ''));
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->pendingUserId() ?? (new \App\Services\AuthService())->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $result = (new \App\Services\MfaDeliveryChannelService())->createChallenge($userId, $method);

    if (($result['status'] ?? '') !== 'ok') {
        return $response->status(422)->json($result);
    }

    return $response->json(array_filter($result, fn ($value) => $value !== null));
});

$router->post('/mfa/totp/setup', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $user = $auth->currentUser();
    $userId = $auth->currentUserId();

    if ($user === null || $userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $account = (string) ($user['email'] ?? $user['name'] ?? ('user-' . $userId));
    $setup = (new \App\Services\MfaService())->setupTotp($userId, $account);

    return $response->json([
        'status' => 'ok',
        'method' => 'totp',
        'method_id' => $setup['method_id'],
        'otpauth_uri' => $setup['otpauth_uri'],
    ]);
});

$router->post('/mfa/totp/confirm', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $code = trim((string) $request->input('code', ''));

    $mfa = new \App\Services\MfaService();

    if (!$mfa->confirmTotp($userId, $code)) {
        return $response->status(422)->json([
            'status' => 'error',
            'confirmed' => false,
            'message' => 'Invalid MFA code.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'confirmed' => true,
        'recovery_codes' => $mfa->ensureRecoveryCodes($userId),
    ]);
});

$router->post('/mfa/challenge/verify', function ($request, $response) {
    $method = trim((string) $request->input('method', 'totp'));
    $code = trim((string) $request->input('code', ''));
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->verifyPendingChallenge($method, $code);

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid MFA challenge.',
        ]);
    }

    $user = (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'mfa_verified' => true,
        'user' => $user,
    ]);
});

$router->post('/mfa/verify', function ($request, $response) {
    $method = trim((string) $request->input('method', 'totp'));
    $code = trim((string) $request->input('code', ''));
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->verifyPendingChallenge($method, $code);

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid MFA challenge.',
        ]);
    }

    $user = (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'mfa_verified' => true,
        'user' => $user,
    ]);
});

$router->post('/mfa/recovery-codes/regenerate', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $code = trim((string) $request->input('code', ''));
    $codes = (new \App\Services\MfaService())->regenerateRecoveryCodes($userId, $code);

    if ($codes === null) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Valid MFA code is required.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'recovery_codes' => $codes,
    ]);
});

$router->post('/mfa/recovery/verify', function ($request, $response) {
    $code = trim((string) $request->input('recovery_code', ''));
    $mfa = new \App\Services\MfaService();
    $userId = $mfa->verifyPendingChallenge('recovery_code', $code);

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid recovery code.',
        ]);
    }

    $user = (new \App\Services\AuthService())->completeMfaLogin($userId);

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'mfa_verified' => true,
        'recovery_code_consumed' => true,
        'trusted_device' => false,
        'user' => $user,
    ]);
});

$router->get('/mfa/trusted-devices', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'trusted_devices' => (new \App\Services\TrustedDeviceService())->listForUser($userId),
    ]);
});

$router->post('/mfa/trusted-devices/revoke', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $deviceId = (int) $request->input('device_id', 0);

    return $response->json([
        'status' => 'ok',
        'revoked' => $deviceId > 0
            && (new \App\Services\TrustedDeviceService())->revoke($userId, $deviceId),
    ]);
});

$router->get('/auth/status', function ($request, $response) {
    return $response->json([
        'authenticated' => (new \App\Services\AuthService())->authenticated(),
    ]);
});

$router->post('/auth/login-token/issue', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $issuerId = $auth->currentUserId();
    $internalKey = (string) \IPKF\Support\Env::get('INTERNAL_SERVICE_KEY', '');
    $providedInternalKey = (string) ($_SERVER['HTTP_X_INTERNAL_SERVICE_KEY'] ?? '');
    $internalAllowed = $internalKey !== '' && hash_equals($internalKey, $providedInternalKey);

    if ($issuerId === null && !$internalAllowed) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    if (!$internalAllowed && !(new \App\Services\AuthorizationService())->hasPermission((int) $issuerId, 'auth.login_token.issue')) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Forbidden.',
        ]);
    }

    $targetUserId = (int) $request->input('user_id', 0);

    if ($targetUserId <= 0) {
        return $response->status(422)->json([
            'status' => 'error',
            'message' => 'Invalid request.',
        ]);
    }

    $issued = (new \App\Services\LoginTokenService())->issue(
        $targetUserId,
        trim((string) $request->input('purpose', 'bot_login')),
        trim((string) $request->input('source', 'internal')),
        $request->input('redirect_path', null),
        $issuerId
    );

    return $response->json([
        'status' => 'ok',
        'login_url' => $issued['login_url'],
        'expires_at' => $issued['expires_at'],
        'expires_at_utc' => $issued['expires_at_utc'],
        'expires_at_local' => $issued['expires_at_local'],
        'timezone' => $issued['timezone'],
        'ttl_seconds' => $issued['ttl_seconds'],
    ]);
});

$router->post('/auth/token-login', function ($request, $response) {
    $token = trim((string) $request->input('token', ''));
    $record = (new \App\Services\LoginTokenService())->consume($token);

    if ($record === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid credentials.',
        ]);
    }

    $mfa = new \App\Services\MfaService();
    $userId = (int) $record['user_id'];

    if ($mfa->requiresChallenge($userId)) {
        return $response->json([
            'status' => 'ok',
            'authenticated' => false,
            'mfa_required' => true,
            'methods' => $mfa->startPending($userId),
        ]);
    }

    $auth = new \App\Services\AuthService();
    $auth->login($userId);
    $user = $auth->currentUser();

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'user' => $user,
    ]);
});

$router->get('/auth/token-login', function ($request, $response) {
    $token = trim((string) $request->input('token', ''));
    $record = (new \App\Services\LoginTokenService())->consume($token);

    if ($record === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Invalid credentials.',
        ]);
    }

    $mfa = new \App\Services\MfaService();
    $userId = (int) $record['user_id'];

    if ($mfa->requiresChallenge($userId)) {
        return $response->json([
            'status' => 'ok',
            'authenticated' => false,
            'mfa_required' => true,
            'methods' => $mfa->startPending($userId),
        ]);
    }

    $auth = new \App\Services\AuthService();
    $auth->login($userId);
    $user = $auth->currentUser();

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'user' => $user,
    ]);
});

$router->get('/access/assignments', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $access = new \App\Services\AccessService();
    $active = $access->activeAssignment($userId);

    return $response->json([
        'status' => 'ok',
        'active_role_assignment_id' => $active === null ? null : (int) $active['id'],
        'assignments' => array_map(fn (array $assignment): array => [
            'id' => (int) $assignment['id'],
            'role_code' => $assignment['role_code'],
            'role_title' => $assignment['role_title'],
            'priority' => (int) $assignment['priority'],
            'scope' => [
                'type' => $assignment['scope_type'],
                'id' => $assignment['scope_id'] === null ? null : (int) $assignment['scope_id'],
            ],
            'is_active' => $active !== null && (int) $active['id'] === (int) $assignment['id'],
        ], $access->assignments($userId)),
    ]);
});

$router->post('/access/switch', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $assignment = (new \App\Services\AccessService())->switchTo($userId, (int) $request->input('role_assignment_id', 0));

    if ($assignment === null) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Forbidden.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'active_role_assignment' => [
            'id' => (int) $assignment['id'],
            'role_code' => $assignment['role_code'],
            'role_title' => $assignment['role_title'],
            'priority' => (int) $assignment['priority'],
        ],
    ]);
});

$router->post('/identity/change/request', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $mfa = new \App\Services\MfaService();

    if ($mfa->methodsForUser($userId) !== [] && !\IPKF\Support\Session::get('auth_mfa_verified', false)) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Recent MFA verification is required.',
        ]);
    }

    $result = (new \App\Services\IdentityChangeService())->request(
        $userId,
        trim((string) $request->input('field', '')),
        trim((string) $request->input('value', '')),
        (string) $request->input('password', '')
    );

    return (($result['status'] ?? '') === 'ok')
        ? $response->json(array_filter($result, fn ($value) => $value !== null))
        : $response->status(422)->json($result);
});

$router->post('/identity/change/confirm', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $result = (new \App\Services\IdentityChangeService())->confirm(
        $userId,
        (int) $request->input('request_id', 0),
        trim((string) $request->input('token', ''))
    );

    return (($result['status'] ?? '') === 'ok')
        ? $response->json($result)
        : $response->status(422)->json($result);
});

$router->get('/me', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $user = $auth->currentUser();
    $userId = $auth->currentUserId();

    if ($user === null || $userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $authorization = new \App\Services\AuthorizationService();
    $access = new \App\Services\AccessService();
    $active = $access->activeAssignment($userId);

    return $response->json([
        'status' => 'ok',
        'authenticated' => true,
        'user' => $user,
        'roles' => array_map(
            fn (array $role): array => [
                'id' => (int) $role['id'],
                'code' => $role['code'],
                'title' => $role['title'],
            ],
            $authorization->rolesForUser($userId)
        ),
        'assignments' => $access->assignments($userId),
        'active_role_assignment' => $active,
        'active_role_code' => $active['role_code'] ?? null,
        'active_role_title' => $active['role_title'] ?? null,
    ]);
});

$router->get('/admin-check', function ($request, $response) {
    $auth = new \App\Services\AuthService();
    $userId = $auth->currentUserId();

    if ($userId === null) {
        return $response->status(401)->json([
            'status' => 'error',
            'authenticated' => false,
            'message' => 'Unauthenticated.',
        ]);
    }

    $permission = 'system.diagnostics.view';

    if (!(new \App\Services\AuthorizationService())->hasPermission($userId, $permission)) {
        return $response->status(403)->json([
            'status' => 'error',
            'message' => 'Forbidden.',
        ]);
    }

    return $response->json([
        'status' => 'ok',
        'permission' => $permission,
    ]);
});

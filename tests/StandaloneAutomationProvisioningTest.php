<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migrationPath = $root . '/public_html/system/Database/Migrations/CreateAutomationCorrespondenceFoundationTables.php';
$standaloneMigrationPath = $root . '/public_html/system/Database/Migrations/CreateStandaloneAutomationCorrespondenceFoundationTables.php';
$coreSeederPath = $root . '/public_html/system/Database/Seeds/AutomationCorrespondencePermissionsSeeder.php';
$automationSeederPath = $root . '/public_html/system/Database/Seeds/AutomationCorrespondenceSeeder.php';
$migrationRegistryPath = $root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php';
$seederRegistryPath = $root . '/public_html/system/Database/Application/ApplicationSeederRegistry.php';
$migrationRunnerPath = $root . '/public_html/system/Database/Application/ApplicationMigrationRunner.php';
$seederRunnerPath = $root . '/public_html/system/Database/Application/ApplicationSeederRunner.php';
$migratePath = $root . '/public_html/public/migrate.php';
$seedPath = $root . '/public_html/public/seed.php';
$routesPath = $root . '/public_html/routes/web.php';
$automationServicesDir = $root . '/public_html/app/Services/Automation';
$docsPath = $root . '/docs/MULTI_DATABASE_RUNTIME.md';

$migration = file_get_contents($migrationPath);
$standaloneMigration = file_get_contents($standaloneMigrationPath);
$coreSeeder = file_get_contents($coreSeederPath);
$automationSeeder = file_get_contents($automationSeederPath);
$migrationRegistry = file_get_contents($migrationRegistryPath);
$seederRegistry = file_get_contents($seederRegistryPath);
$migrationRunner = file_get_contents($migrationRunnerPath);
$seederRunner = file_get_contents($seederRunnerPath);
$migrate = file_get_contents($migratePath);
$seed = file_get_contents($seedPath);
$routes = file_get_contents($routesPath);
$docs = file_get_contents($docsPath);
$diagnosticsStart = strpos($routes, "\$router->get('/_diagnostics'");
$diagnosticsEnd = strpos($routes, "\$router->get('/test'", $diagnosticsStart);
$diagnosticsRoute = $diagnosticsStart === false
    ? ''
    : substr($routes, $diagnosticsStart, ($diagnosticsEnd === false ? null : $diagnosticsEnd - $diagnosticsStart));

function expectStandaloneAutomation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    'CoreReference',
    'CoreReferenceType',
    'CoreReferenceValidator',
    'CoreReferenceSnapshotPolicy',
    'AutomationProvisioningReadiness',
    'AutomationSchemaParityContract',
] as $class) {
    expectStandaloneAutomation(is_readable("{$automationServicesDir}/{$class}.php"), "Missing automation contract: {$class}");
}

expectStandaloneAutomation(str_contains($migration, 'private bool $includeCoreForeignKeys = true'), 'Legacy automation migration must keep Core FK behavior as the default.');
expectStandaloneAutomation(str_contains($migration, 'addCoreForeignKeyIfAllowed'), 'Automation migration must gate Core-targeting foreign keys.');
expectStandaloneAutomation(str_contains($standaloneMigration, 'parent::__construct($db, false)'), 'Standalone migration must disable Core-targeting foreign keys.');
expectStandaloneAutomation(str_contains($migrationRegistry, 'CreateStandaloneAutomationCorrespondenceFoundationTables::class'), 'Application automation migrations must use the standalone schema profile.');
expectStandaloneAutomation(!preg_match('/\'automation\'\s*=>\s*\[[\s\S]*CreateAutomationCorrespondenceFoundationTables::class[\s\S]*?\]/', $migrationRegistry), 'Automation application group must not use the legacy Core-compatible migration.');

foreach ([
    'corr_versions_corr_fk',
    'corr_current_version_fk',
    'corr_parties_corr_fk',
    'corr_reg_corr_fk',
    'corr_reg_book_fk',
    'corr_rel_source_fk',
    'corr_rel_target_fk',
    'corr_ref_corr_fk',
    'corr_ref_parent_fk',
    'corr_events_corr_fk',
    'corr_events_referral_fk',
    'corr_attach_corr_fk',
    'corr_attach_version_fk',
    'corr_attach_file_fk',
] as $internalConstraint) {
    expectStandaloneAutomation(str_contains($migration, "'{$internalConstraint}'"), "Missing internal FK constraint: {$internalConstraint}");
}

foreach (['users', 'persons', 'organizations', 'org_units', 'positions', 'fiscal_years'] as $coreTable) {
    expectStandaloneAutomation(str_contains($migration, "'{$coreTable}'"), "Core reference table must be modeled as a gated scalar reference: {$coreTable}");
}

expectStandaloneAutomation(str_contains($automationSeeder, 'lookup_domains'), 'Automation metadata seeder must seed lookup domains.');
expectStandaloneAutomation(str_contains($automationSeeder, 'lookup_values'), 'Automation metadata seeder must seed lookup values.');
expectStandaloneAutomation(!str_contains($automationSeeder, 'INSERT INTO permissions'), 'Automation metadata seeder must not write Core permissions.');
expectStandaloneAutomation(!str_contains($automationSeeder, 'role_permissions'), 'Automation metadata seeder must not write Core role assignments.');
expectStandaloneAutomation(str_contains($coreSeeder, 'INSERT INTO permissions'), 'Core permissions seeder must own Automation RBAC permissions.');
expectStandaloneAutomation(str_contains($coreSeeder, 'role_permissions'), 'Core permissions seeder must own Automation RBAC grants.');
expectStandaloneAutomation(str_contains($seederRegistry, 'AutomationCorrespondencePermissionsSeeder::class'), 'Core seeder registry must include Automation RBAC permissions.');
expectStandaloneAutomation(str_contains($seederRegistry, "'automation'") && str_contains($seederRegistry, 'AutomationCorrespondenceSeeder::class'), 'Automation seeder registry must include Automation metadata only.');

foreach ([$migrate, $seed] as $endpoint) {
    expectStandaloneAutomation(str_contains($endpoint, "\$application = trim((string) (\$_GET['application'] ?? ''));"), 'Application mode must be explicit query input.');
    expectStandaloneAutomation(str_contains($endpoint, "\$allowedApplications = ['core', 'automation'];"), 'Application mode must be allowlisted.');
    expectStandaloneAutomation(str_contains($endpoint, "\$definition->usesFallback()"), 'Standalone automation provisioning must reject fallback mode.');
    expectStandaloneAutomation(str_contains($endpoint, "\$application === 'core'"), 'Core application mode must be explicit and non-duplicating.');
    expectStandaloneAutomation(str_contains($endpoint, 'Maintenance::keyIsValid'), 'Application endpoints must preserve maintenance-key protection.');
    expectStandaloneAutomation(!preg_match('/\$_GET\[[\'"](?:dsn|host|database|username|password|sql|class)[\'"]\]/i', $endpoint), 'Application endpoints must not accept topology, SQL, or class inputs.');
    expectStandaloneAutomation(!preg_match('/echo\s+\$exception|->getMessage\(\)/', $endpoint), 'Application endpoint failures must not expose exception details.');
}

expectStandaloneAutomation(str_contains($migrationRunner, 'return $applied;'), 'Application migration runner must return an aggregate applied count.');
expectStandaloneAutomation(str_contains($migrationRunner, 'MigrationExecutionException'), 'Application migration runner must retain failing migration class.');
expectStandaloneAutomation(str_contains($seederRunner, 'return $executed;'), 'Application seeder runner must return an aggregate executed count.');

foreach ([
    'automation_standalone_migration_registered',
    'automation_standalone_seeder_registered',
    'automation_dedicated_connection_required_for_provisioning',
    'automation_dedicated_connection_configured',
    'automation_dedicated_connection_available',
    'automation_standalone_schema_available',
    'automation_standalone_metadata_available',
    'automation_internal_foreign_keys_preserved',
    'automation_core_foreign_keys_absent',
    'automation_cross_database_sql_absent',
    'automation_core_reference_contract_available',
    'automation_snapshot_policy_documented',
    'automation_application_migration_history_available',
    'automation_legacy_schema_retained',
    'automation_legacy_operational_data_absent',
    'automation_schema_parity_contract_available',
    'automation_cutover_ready',
    'automation_rollback_source_available',
    'automation_current_runtime_source_unchanged',
    'standalone_automation_provisioning_foundation_available',
] as $diagnostic) {
    expectStandaloneAutomation(str_contains($routes, "'{$diagnostic}'"), "Missing standalone diagnostic: {$diagnostic}");
}

expectStandaloneAutomation(!preg_match('/\b(?:password|dsn|database_name|username|host|PDOException|getMessage\(\))\b/i', $diagnosticsRoute), 'Diagnostics must not expose credentials, topology, or exception details.');
expectStandaloneAutomation(!preg_match('/REFERENCES\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+/i', $migration . "\n" . $standaloneMigration), 'Standalone automation schema must not use cross-database foreign keys.');
expectStandaloneAutomation(!preg_match('/\b(?:FROM|JOIN|UPDATE|INTO|REFERENCES)\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+/i', $migration . "\n" . $migrate . "\n" . $seed), 'Standalone automation flow must not use schema-qualified SQL.');
expectStandaloneAutomation(!str_contains($migration . $migrate . $seed, 'FOREIGN_KEY_CHECKS'), 'Provisioning must not change global foreign-key checks.');
expectStandaloneAutomation(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $migration . "\n" . $migrate . "\n" . $seed), 'Provisioning must not delete, truncate, or drop legacy Automation data.');

foreach ([
    'Core versus Automation ownership',
    'Core reference contract',
    'Snapshot policy',
    'migrate.php?application=automation',
    'seed.php?application=automation',
    'fallback',
    'provisioning',
    'dedicated',
    'rollback',
] as $docNeedle) {
    expectStandaloneAutomation(str_contains($docs, $docNeedle), "Missing documentation topic: {$docNeedle}");
}

echo "Standalone automation provisioning structural tests passed.\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migrationPath = $root . '/public_html/system/Database/Migrations/CreatePlatformCommercialFoundationTables.php';
$seederPath = $root . '/public_html/system/Database/Seeds/PlatformCommercialFoundationSeeder.php';
$migrateRunnerPath = $root . '/public_html/public/migrate.php';
$seedRunnerPath = $root . '/public_html/public/seed.php';
$routesPath = $root . '/public_html/routes/web.php';
$servicesPath = $root . '/public_html/app/Services/Platform';

$migration = file_get_contents($migrationPath);
$seeder = file_get_contents($seederPath);
$migrateRunner = file_get_contents($migrateRunnerPath);
$seedRunner = file_get_contents($seedRunnerPath);
$routes = file_get_contents($routesPath);

function expectPlatform(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function platformTableFragment(string $migration, string $table): string
{
    $start = strpos($migration, "CREATE TABLE IF NOT EXISTS {$table} (");
    expectPlatform($start !== false, "Missing table definition: {$table}");
    $end = strpos($migration, 'ENGINE=InnoDB', $start);
    expectPlatform($end !== false, "Incomplete table definition: {$table}");

    return substr($migration, $start, $end - $start);
}

expectPlatform(is_string($migration), 'Platform migration source must be readable.');
expectPlatform(is_string($seeder), 'Platform seeder source must be readable.');
expectPlatform(is_string($migrateRunner), 'Migration runner source must be readable.');
expectPlatform(is_string($seedRunner), 'Seeder runner source must be readable.');
expectPlatform(is_string($routes), 'Diagnostics route source must be readable.');

$tables = [
    'platform_installations',
    'platform_environments',
    'platform_applications',
    'platform_modules',
    'platform_module_dependencies',
    'platform_installation_applications',
    'platform_installation_modules',
    'platform_domains',
    'platform_database_endpoints',
    'platform_storage_endpoints',
    'platform_service_endpoints',
    'platform_licenses',
    'platform_license_entitlements',
    'platform_license_limits',
    'platform_provisioning_runs',
    'platform_provisioning_steps',
];

foreach ($tables as $table) {
    expectPlatform(
        substr_count($migration, "CREATE TABLE IF NOT EXISTS {$table} (") === 1,
        "Table {$table} must be created idempotently exactly once."
    );
}

expectPlatform(
    substr_count($migrateRunner, 'CreatePlatformCommercialFoundationTables()') === 1,
    'Platform migration must be registered exactly once.'
);
expectPlatform(
    substr_count($seedRunner, 'PlatformCommercialFoundationSeeder()') === 1,
    'Platform seeder must be registered exactly once.'
);

$applications = platformTableFragment($migration, 'platform_applications');
expectPlatform(str_contains($applications, 'UNIQUE KEY platform_applications_code_unique (code)'), 'Application codes must be unique.');
expectPlatform(str_contains($applications, 'platform_applications_code_check'), 'Application code policy must be constrained.');
expectPlatform(str_contains($applications, "owner_scope IN ('platform_core', 'specialized')"), 'Application ownership model must be constrained.');

$modules = platformTableFragment($migration, 'platform_modules');
expectPlatform(str_contains($modules, 'UNIQUE KEY platform_modules_code_unique (code)'), 'Module codes must be globally unique.');
expectPlatform(str_contains($modules, 'UNIQUE KEY platform_modules_app_code_unique (application_id, code)'), 'Module codes must be unique within application scope.');
expectPlatform(str_contains($modules, 'platform_modules_code_check'), 'Module code policy must be constrained.');

$dependencies = platformTableFragment($migration, 'platform_module_dependencies');
expectPlatform(str_contains($dependencies, 'UNIQUE KEY platform_module_deps_unique (module_id, depends_on_module_id)'), 'Duplicate module dependencies must be rejected.');
expectPlatform(str_contains($dependencies, 'platform_module_deps_no_self_check CHECK (module_id <> depends_on_module_id)'), 'Module dependency self-reference must be rejected.');

$installationApplications = platformTableFragment($migration, 'platform_installation_applications');
expectPlatform(str_contains($installationApplications, 'UNIQUE KEY platform_install_apps_unique (installation_id, environment_id, application_id)'), 'Installation/application associations must be unique.');

$installationModules = platformTableFragment($migration, 'platform_installation_modules');
expectPlatform(str_contains($installationModules, 'UNIQUE KEY platform_install_modules_unique (installation_id, environment_id, module_id)'), 'Installation/module associations must be unique.');
expectPlatform(str_contains($installationModules, 'license_state'), 'Installed, enabled, and license state must be separate.');

$domains = platformTableFragment($migration, 'platform_domains');
expectPlatform(str_contains($domains, 'domain_type IN (\'primary\', \'alias\')'), 'Domain primary/alias type must be constrained.');
expectPlatform(str_contains($domains, 'platform_domains_primary_unique'), 'Primary domain rule must be deterministic.');
expectPlatform(str_contains($domains, 'requires_https'), 'Domain HTTPS requirement must be tracked.');
expectPlatform(str_contains($domains, 'verification_status'), 'Domain verification status must be tracked.');

foreach (['platform_database_endpoints', 'platform_storage_endpoints', 'platform_service_endpoints'] as $endpointTable) {
    $fragment = platformTableFragment($migration, $endpointTable);
    expectPlatform(str_contains($fragment, 'credential_secret_reference'), "{$endpointTable} must use credential secret references.");
    expectPlatform(!preg_match('/\b(password|secret_value|access_key|private_key|token)\b/i', $fragment), "{$endpointTable} must not store plaintext credentials.");
}

$databaseEndpoints = platformTableFragment($migration, 'platform_database_endpoints');
foreach (['primary', 'read_replica', 'reporting', 'archive'] as $purpose) {
    expectPlatform(str_contains($databaseEndpoints, "'{$purpose}'"), "Missing database endpoint purpose: {$purpose}");
}

$licenses = platformTableFragment($migration, 'platform_licenses');
foreach (['customer_reference', 'issued_at', 'valid_from', 'expires_at', 'grace_until', 'edition', 'signed_manifest_reference', 'activation_mode', 'revoked_at', 'revocation_reference'] as $column) {
    expectPlatform(str_contains($licenses, $column), "Missing license column: {$column}");
}
expectPlatform(str_contains($licenses, "activation_mode IN ('online', 'offline')"), 'License activation mode must support online and offline.');

$entitlements = platformTableFragment($migration, 'platform_license_entitlements');
expectPlatform(str_contains($entitlements, 'UNIQUE KEY platform_license_entitlements_unique (license_id, module_id)'), 'License entitlements must be unique by license and module.');
expectPlatform(str_contains($migration, "'platform_license_entitlements_module_fk'"), 'License entitlements must target known modules.');

$limits = platformTableFragment($migration, 'platform_license_limits');
expectPlatform(str_contains($limits, 'UNIQUE KEY platform_license_limits_metric_unique (entitlement_id, metric_code)'), 'Module limits must be unique by entitlement and metric.');

$runs = platformTableFragment($migration, 'platform_provisioning_runs');
$steps = platformTableFragment($migration, 'platform_provisioning_steps');
foreach (['pending', 'running', 'succeeded', 'failed', 'skipped', 'rolled_back'] as $status) {
    expectPlatform(str_contains($runs, "'{$status}'") && str_contains($steps, "'{$status}'"), "Provisioning status missing: {$status}");
}
expectPlatform(str_contains($steps, 'UNIQUE KEY platform_provisioning_steps_order_unique (provisioning_run_id, step_order)'), 'Provisioning step order must be deterministic.');
expectPlatform(str_contains($steps, 'UNIQUE KEY platform_provisioning_steps_code_unique (provisioning_run_id, step_code)'), 'Provisioning step codes must be unique per run.');

expectPlatform(!str_contains($migration, 'FOREIGN_KEY_CHECKS'), 'Migration must not disable foreign key checks globally.');
expectPlatform(preg_match('/REFERENCES\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+/i', $migration) === 0, 'No cross-database foreign keys may be introduced.');
expectPlatform(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $migration), 'Platform migration must not destructively repair schema or data.');

$applicationCodes = ['core', 'automation'];
foreach ($applicationCodes as $code) {
    expectPlatform(str_contains($seeder, "'{$code}'"), "Missing seeded application: {$code}");
}

$moduleCodes = [
    'core.identity',
    'core.access',
    'core.organization',
    'core.geography',
    'core.platform_registry',
    'core.licensing',
    'automation.core',
    'automation.correspondence',
    'automation.secretariat',
    'automation.cartable',
    'automation.workflow',
    'automation.forms',
    'automation.leave',
    'automation.mission',
    'automation.procurement_requests',
    'automation.payment_requests',
    'automation.check_requests',
    'automation.document_generation',
    'automation.archive',
    'automation.qr_verification',
    'automation.digital_signature',
    'automation.notifications',
];

foreach ($moduleCodes as $code) {
    expectPlatform(str_contains($seeder, "'{$code}'"), "Missing seeded module: {$code}");
}

foreach ([
    ['automation.correspondence', 'automation.core'],
    ['automation.secretariat', 'automation.correspondence'],
    ['automation.cartable', 'automation.correspondence'],
    ['automation.forms', 'automation.workflow'],
    ['automation.digital_signature', 'automation.document_generation'],
] as [$module, $dependency]) {
    expectPlatform(
        str_contains($seeder, "['{$module}', '{$dependency}']"),
        "Missing module dependency: {$module} -> {$dependency}"
    );
}

expectPlatform(str_contains($seeder, 'ON DUPLICATE KEY UPDATE'), 'Application and module seeds must be idempotent.');
expectPlatform(str_contains($seeder, 'INSERT IGNORE INTO platform_module_dependencies'), 'Dependency seeds must be idempotent.');
expectPlatform(!preg_match('/INSERT\s+INTO\s+platform_(installations|environments|domains|database_endpoints|storage_endpoints|service_endpoints|licenses|license_entitlements|license_limits|provisioning_runs|provisioning_steps)/i', $seeder), 'Seeder must not create operational installation, topology, license, or provisioning records.');

$diagnostics = [
    'platform_catalog_available',
    'platform_application_catalog_available',
    'platform_module_catalog_available',
    'platform_module_dependencies_available',
    'platform_installation_registry_available',
    'platform_topology_registry_available',
    'platform_license_foundation_available',
    'platform_entitlement_contract_available',
    'platform_provisioning_foundation_available',
    'platform_connection_secrets_not_stored_plaintext',
    'platform_cross_database_foreign_keys_absent',
    'platform_existing_runtime_compatibility_preserved',
];

foreach ($diagnostics as $diagnostic) {
    expectPlatform(str_contains($routes, "'{$diagnostic}'"), "Missing diagnostic: {$diagnostic}");
}

foreach (['ApplicationCatalog', 'ModuleCatalog', 'InstallationRegistry', 'TopologyRegistry', 'EntitlementResolver', 'ModuleGate'] as $service) {
    expectPlatform(is_readable("{$servicesPath}/{$service}.php"), "Missing platform service contract: {$service}");
}

$moduleGate = file_get_contents($servicesPath . '/ModuleGate.php');
foreach (['module_not_installed', 'module_disabled', 'module_unlicensed', 'license_expired', 'dependency_blocked', 'allowed'] as $outcome) {
    expectPlatform(str_contains($moduleGate, "'{$outcome}'"), "Missing ModuleGate outcome: {$outcome}");
}

expectPlatform(!str_contains($routes, 'ModuleGate()->'), 'ModuleGate must not be connected to existing runtime routes in this phase.');

echo "Platform commercial foundation structural tests passed.\n";

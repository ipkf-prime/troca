<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$connectionDir = $root . '/public_html/system/Database/Connections';
$applicationDir = $root . '/public_html/system/Database/Application';
$historyMigrationPath = $root . '/public_html/system/Database/Migrations/CreateApplicationMigrationHistoryTable.php';
$routesPath = $root . '/public_html/routes/web.php';
$migratePath = $root . '/public_html/public/migrate.php';
$seedPath = $root . '/public_html/public/seed.php';
$envExamplePath = $root . '/public_html/.env.example';

$registry = file_get_contents($connectionDir . '/ConnectionRegistry.php');
$resolver = file_get_contents($connectionDir . '/ConnectionResolver.php');
$factory = file_get_contents($connectionDir . '/ConnectionFactory.php');
$health = file_get_contents($connectionDir . '/ConnectionHealthChecker.php');
$appConnectionResolver = file_get_contents($connectionDir . '/ApplicationConnectionResolver.php');
$migrationRegistry = file_get_contents($applicationDir . '/ApplicationMigrationRegistry.php');
$seederRegistry = file_get_contents($applicationDir . '/ApplicationSeederRegistry.php');
$migrationRunner = file_get_contents($applicationDir . '/ApplicationMigrationRunner.php');
$historyMigration = file_get_contents($historyMigrationPath);
$routes = file_get_contents($routesPath);
$migrate = file_get_contents($migratePath);
$seed = file_get_contents($seedPath);
$envExample = file_get_contents($envExamplePath);
$diagnosticsStart = strpos($routes, "\$router->get('/_diagnostics'");
$diagnosticsEnd = strpos($routes, "\$router->get('/test'", $diagnosticsStart);
$diagnosticsRoute = $diagnosticsStart === false
    ? ''
    : substr($routes, $diagnosticsStart, ($diagnosticsEnd === false ? null : $diagnosticsEnd - $diagnosticsStart));

function expectMultiDb(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    'ConnectionDefinition',
    'ConnectionRegistry',
    'ConnectionFactory',
    'ConnectionResolver',
    'ApplicationConnectionResolver',
    'ConnectionHealthChecker',
] as $class) {
    expectMultiDb(is_readable("{$connectionDir}/{$class}.php"), "Missing connection runtime component: {$class}");
}

foreach ([
    'ApplicationMigrationRegistry',
    'ApplicationSeederRegistry',
    'ApplicationMigrationRunner',
    'ApplicationSeederRunner',
] as $class) {
    expectMultiDb(is_readable("{$applicationDir}/{$class}.php"), "Missing application runtime component: {$class}");
}

expectMultiDb(str_contains($registry, "'core.primary'"), 'Registry must register core.primary.');
expectMultiDb(str_contains($registry, "'automation.primary'"), 'Registry must register automation.primary.');
expectMultiDb(str_contains($registry, "'core.primary'") && str_contains($registry, "fallbackConnectionName"), 'Automation fallback must be modeled explicitly.');
expectMultiDb(str_contains($registry, 'AUTOMATION_DB_HOST'), 'Automation host env key must be supported.');
expectMultiDb(str_contains($registry, 'AUTOMATION_DB_DATABASE'), 'Automation database env key must be supported.');
expectMultiDb(str_contains($registry, 'AUTOMATION_DB_USERNAME'), 'Automation username env key must be supported.');
expectMultiDb(str_contains($registry, 'AUTOMATION_DB_PASSWORD'), 'Automation password env key must be supported.');
expectMultiDb(str_contains($registry, "trim((string) (\$config['charset'] ?? 'utf8mb4')) === 'utf8mb4'"), 'Dedicated automation config must require utf8mb4.');

foreach (['AUTOMATION_DB_HOST', 'AUTOMATION_DB_PORT', 'AUTOMATION_DB_DATABASE', 'AUTOMATION_DB_USERNAME', 'AUTOMATION_DB_PASSWORD', 'AUTOMATION_DB_CHARSET', 'AUTOMATION_DB_SSL_MODE', 'AUTOMATION_DB_CONNECTION_TIMEOUT'] as $key) {
    expectMultiDb(str_contains($envExample, $key . '='), "Missing env example key: {$key}");
}

expectMultiDb(str_contains($resolver, 'Database::connect()'), 'core.primary fallback must reuse the existing core Database connection.');
expectMultiDb(str_contains($resolver, "if (\$name === 'core.primary')"), 'core.primary must preserve existing Database::connect behavior.');
expectMultiDb(str_contains($factory, 'SET NAMES {$charset} COLLATE utf8mb4_unicode_ci'), 'Named connections must set utf8mb4.');
expectMultiDb(str_contains($factory, "SET time_zone = '\" . Clock::DATABASE_SESSION_TIMEZONE . \"'"), 'Named connections must apply UTC database timezone policy.');
expectMultiDb(!preg_match('/password|dsn/i', $health), 'ConnectionHealthChecker must not expose password or DSN wording.');
expectMultiDb(str_contains($health, 'fallbackSharesPdo'), 'Fallback should be able to prove PDO reuse.');
expectMultiDb(str_contains($appConnectionResolver, '{$applicationCode}.primary') || str_contains($appConnectionResolver, '"{$applicationCode}.primary"'), 'Application resolver must support future primary connection names.');

expectMultiDb(str_contains($migrationRegistry, "'core'") && str_contains($migrationRegistry, "'automation'"), 'Application migration registry must group core and automation.');
expectMultiDb(str_contains($migrationRegistry, "'connection' => 'core.primary'"), 'Core migrations must run on core.primary.');
expectMultiDb(str_contains($migrationRegistry, "'connection' => 'automation.primary'"), 'Automation migrations must run on automation.primary.');
expectMultiDb(str_contains($migrationRegistry, 'CreateStandaloneAutomationCorrespondenceFoundationTables::class'), 'Standalone automation correspondence migration must be registered in the application catalog.');
expectMultiDb(str_contains($migrationRegistry, 'CreatePlatformCommercialFoundationTables::class'), 'Platform commercial migration must be in core catalog.');

expectMultiDb(str_contains($seederRegistry, "'connection' => 'core.primary'"), 'Core seeders must run on core.primary.');
expectMultiDb(str_contains($seederRegistry, "'connection' => 'automation.primary'"), 'Automation seeders must run on automation.primary.');
expectMultiDb(str_contains($seederRegistry, 'AutomationCorrespondenceSeeder::class'), 'Automation correspondence seeder must be registered in the application catalog.');
expectMultiDb(str_contains($seederRegistry, 'new $class($pdo)'), 'Application seeder registry must pass the named PDO into seeders.');

expectMultiDb(str_contains($historyMigration, 'CREATE TABLE IF NOT EXISTS application_migrations'), 'Application migration history table must exist.');
expectMultiDb(str_contains($historyMigration, 'application_code'), 'Application migration history must include application code.');
expectMultiDb(str_contains($historyMigration, 'connection_name'), 'Application migration history must include connection name.');
expectMultiDb(str_contains($historyMigration, 'UNIQUE KEY application_migrations_unique (application_code, connection_name, migration)'), 'Application migration history must prevent cross-application collisions.');
expectMultiDb(str_contains($migrationRunner, 'application_migrations'), 'Application migration runner must use application-aware history.');
expectMultiDb(str_contains($migrationRunner, 'application_code') && str_contains($migrationRunner, 'connection_name'), 'Application runner must store application and connection.');
expectMultiDb(str_contains($migrationRunner, 'MigrationExecutionException'), 'Application migration failures must preserve the migration class.');

expectMultiDb(substr_count($migrate, 'CreateApplicationMigrationHistoryTable()') === 1, 'Legacy migrate endpoint must register application migration history once.');
expectMultiDb(str_contains($migrate, 'CreatePlatformCommercialFoundationTables()'), 'Legacy migrate endpoint must preserve platform foundation migration.');
expectMultiDb(str_contains($seed, 'PlatformCommercialFoundationSeeder()'), 'Legacy seed endpoint must preserve platform foundation seeder.');
expectMultiDb(str_contains($migrate, "\$application = trim((string) (\$_GET['application'] ?? ''));"), 'Application migration mode must be explicit and opt-in.');
expectMultiDb(str_contains($seed, "\$application = trim((string) (\$_GET['application'] ?? ''));"), 'Application seeder mode must be explicit and opt-in.');
expectMultiDb(str_contains($migrate, 'if ($application !== \'\')') && str_contains($seed, 'if ($application !== \'\')'), 'Legacy migrate and seed behavior must remain the no-application default path.');

$diagnostics = [
    'named_connection_registry_available',
    'core_primary_connection_registered',
    'core_primary_connection_available',
    'automation_primary_connection_registered',
    'automation_primary_connection_fallback_active',
    'automation_primary_dedicated_connection_configured',
    'automation_primary_connection_available',
    'application_migration_registry_available',
    'application_seeder_registry_available',
    'application_migration_history_available',
    'multi_database_runtime_foundation_available',
    'database_session_timezone_policy_applied_to_named_connections',
    'named_connections_utf8mb4_ready',
    'named_connection_credentials_not_publicly_exposed',
    'named_connection_cross_database_queries_absent',
    'current_legacy_database_runtime_preserved',
    'current_automation_runtime_preserved',
];

foreach ($diagnostics as $diagnostic) {
    expectMultiDb(str_contains($routes, "'{$diagnostic}'"), "Missing diagnostic: {$diagnostic}");
}

expectMultiDb(!preg_match('/\b(host|port|database_name|username|password|dsn|secret_reference|PDOException|getMessage\(\))\b/i', $diagnosticsRoute), 'Diagnostics must not expose connection topology or exception details.');

$allSources = implode("\n", [$registry, $resolver, $factory, $migrationRegistry, $seederRegistry, $migrationRunner, $historyMigration, $migrate, $seed]);
expectMultiDb(!preg_match('/REFERENCES\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+/i', $allSources), 'No cross-database foreign keys may be introduced.');
expectMultiDb(!preg_match('/\b(?:FROM|JOIN|UPDATE|INTO|REFERENCES)\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+/i', $allSources), 'No cross-database SQL may be introduced.');
expectMultiDb(!str_contains($allSources, 'FOREIGN_KEY_CHECKS'), 'No global foreign key check changes may be introduced.');
expectMultiDb(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $allSources), 'No automation table movement or destructive operation may be introduced.');

echo "Multi-database runtime foundation structural tests passed.\n";

<?php

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/public_html/system/Database/Migrations/CreateApplicationModuleRegistryTable.php');
$runtimeMigration = file_get_contents($root . '/public_html/system/Database/Migrations/ExtendApplicationModuleRegistryRuntimeConfig.php');
$service = file_get_contents($root . '/public_html/app/Services/ApplicationModuleRegistryService.php');
$routes = file_get_contents($root . '/public_html/routes/web.php');
$view = file_get_contents($root . '/public_html/resources/views/admin/settings.php');

$expect = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$expect(str_contains($migration, 'CREATE TABLE IF NOT EXISTS application_modules'), 'Module registry table is required.');
$expect(str_contains($migration, 'secret_reference') && !str_contains($migration, 'database_password'), 'Database credentials must use a secret reference, never a stored password.');
$expect(str_contains($service, 'FILTER_VALIDATE_URL') && str_contains($service, "=== 'https'"), 'Module endpoints must require valid HTTPS URLs.');
$expect(str_contains($routes, "get('/admin/settings'") && str_contains($routes, "post('/admin/settings/modules'"), 'Module registry settings routes are required.');
$expect(
    str_contains($view, 'اتصال دیتابیس')
    && str_contains($view, '<span>نام اتصال</span>')
    && str_contains($view, 'name="database_connection_name"')
    && str_contains($view, 'name="database_host"')
    && str_contains($view, 'name="database_port"')
    && str_contains($view, 'name="database_name"')
    && str_contains($view, 'data-module-field="database"')
    && str_contains($view, 'data-module-field="username"')
    && str_contains($view, 'data-module-field="charset"')
    && str_contains($view, 'data-module-field="ssl_mode"')
    && str_contains($view, 'data-module-field="timeout"')
    && str_contains($view, 'data-module-field="runtime_mode"'),
    'The central settings form must expose the complete non-secret connection metadata contract.'
);
$expect(str_contains($view, "require __DIR__ . '/layout.php'") && str_contains($view, 'ob_start()'), 'The module settings view must render inside the standard Admin layout.');
$expect(str_contains($view, 'new \\IPKF\\Security\\Csrf()'), 'The module settings form must include CSRF protection.');
$expect(str_contains($view, 'data-module-select') && str_contains($view, 'data-admin-tab="database"'), 'Module settings must use a catalog dropdown and a compact tabbed workspace.');
$expect(str_contains($service, "'accounting'") && str_contains($service, "'inventory'") && str_contains($service, "'crm'"), 'The central module catalog must be extensible beyond Automation.');
$expect(str_contains($view, 'data-module-context') && str_contains($view, 'registered-modules-data'), 'The selected module context must remain visible and load saved module data across all tabs.');
$expect(!str_contains($view, 'data-module-dependent-tab disabled'), 'Module settings tabs must remain available while module context is selected independently.');
$expect(str_contains($view, 'database_username') && str_contains($view, 'connection_timeout') && str_contains($view, 'runtime_mode'), 'The module registry form must cover the complete non-secret runtime connection contract.');
$expect(str_contains($runtimeMigration, 'database_username') && str_contains($runtimeMigration, 'database_charset') && str_contains($runtimeMigration, 'runtime_mode'), 'The runtime registry migration must persist all non-secret connection settings.');
$expect(str_contains($view, 'data-module-secret-status') && str_contains($view, "'********'"), 'Configured secrets must be represented only by a masked status in the Admin form.');
$expect(str_contains($service, "Env::get('AUTOMATION_DB_PASSWORD', '') !== ''") || str_contains($service, "Env::get('AUTOMATION_DB_PASSWORD', '')"), 'The module catalog may expose only whether the ENV secret is configured.');

echo "Application module registry checks passed.\n";

<?php

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/public_html/system/Database/Migrations/CreateApplicationModuleRegistryTable.php');
$service = file_get_contents($root . '/public_html/app/Services/ApplicationModuleRegistryService.php');
$routes = file_get_contents($root . '/public_html/routes/web.php');
$view = file_get_contents($root . '/public_html/resources/views/admin/settings.php');

$expect = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$expect(str_contains($migration, 'CREATE TABLE IF NOT EXISTS application_modules'), 'Module registry table is required.');
$expect(str_contains($migration, 'secret_reference') && !str_contains($migration, 'database_password'), 'Database credentials must use a secret reference, never a stored password.');
$expect(str_contains($service, 'FILTER_VALIDATE_URL') && str_contains($service, "=== 'https'"), 'Module endpoints must require valid HTTPS URLs.');
$expect(str_contains($routes, "get('/admin/settings'") && str_contains($routes, "post('/admin/settings/modules'"), 'Module registry settings routes are required.');
$expect(str_contains($view, 'نام اتصال دیتابیس') && str_contains($view, 'Secret Reference'), 'The central settings form must expose connection metadata without passwords.');

echo "Application module registry checks passed.\n";

<?php

$root = dirname(__DIR__);
$resolver = file_get_contents($root . '/public_html/system/Support/ModuleRuntimeConfig.php');
$connections = file_get_contents($root . '/public_html/system/Database/Connections/ConnectionRegistry.php');
$mode = file_get_contents($root . '/public_html/app/Services/Automation/AutomationRuntimeMode.php');
$urls = file_get_contents($root . '/public_html/system/Support/ApplicationUrlRegistry.php');

$checks = [
    'runtime resolver reads only an active module record' => str_contains($resolver, 'AND is_active = 1'),
    'runtime resolver fails safely when the core database is unavailable' => str_contains($resolver, 'catch (Throwable)'),
    'runtime database config is selected before the env fallback' => strpos($connections, "active('automation')") < strpos($connections, "'AUTOMATION_DB_HOST'"),
    'database password is resolved from a server-side secret reference' => str_contains($connections, "secret(\$module, 'AUTOMATION_DB_PASSWORD')"),
    'runtime mode is selected from the active module record' => str_contains($mode, "\$module['runtime_mode']"),
    'automation URL is selected from the active module record' => str_contains($urls, "moduleUrl('automation', 'AUTOMATION_APP_URL'"),
    'no database password column is introduced' => !str_contains($resolver . $connections, 'database_password'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, "FAILED:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "Dynamic module runtime configuration checks passed.\n";

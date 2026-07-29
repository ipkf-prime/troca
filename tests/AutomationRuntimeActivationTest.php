<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$servicesDir = $root . '/public_html/app/Services/Automation';
$routesPath = $root . '/public_html/routes/web.php';
$migratePath = $root . '/public_html/public/migrate.php';
$seedPath = $root . '/public_html/public/seed.php';
$envExamplePath = $root . '/public_html/.env.example';
$docsPath = $root . '/docs/MULTI_DATABASE_RUNTIME.md';
$projectContextPath = $root . '/docs/PROJECT_CONTEXT.md';
$checklistPath = $root . '/docs/RELEASE_CHECKLIST.md';

$runtimeMode = file_get_contents($servicesDir . '/AutomationRuntimeMode.php');
$readiness = file_get_contents($servicesDir . '/AutomationProvisioningReadiness.php');
$sourceResolver = file_get_contents($servicesDir . '/AutomationRuntimeSourceResolver.php');
$connectionResolver = file_get_contents($servicesDir . '/AutomationRuntimeConnectionResolver.php');
$cutoverGuard = file_get_contents($servicesDir . '/AutomationCutoverGuard.php');
$rollbackPolicy = file_get_contents($servicesDir . '/AutomationRollbackPolicy.php');
$routes = file_get_contents($routesPath);
$migrate = file_get_contents($migratePath);
$seed = file_get_contents($seedPath);
$envExample = file_get_contents($envExamplePath);
$docs = file_get_contents($docsPath);
$projectContext = file_get_contents($projectContextPath);
$checklist = file_get_contents($checklistPath);

function expectAutomationRuntime(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$diagnosticsStart = strpos($routes, "\$router->get('/_diagnostics'");
$diagnosticsEnd = strpos($routes, "\$router->get('/test'", $diagnosticsStart);
$diagnosticsRoute = $diagnosticsStart === false
    ? ''
    : substr($routes, $diagnosticsStart, ($diagnosticsEnd === false ? null : $diagnosticsEnd - $diagnosticsStart));

foreach ([
    'AutomationRuntimeMode',
    'AutomationRuntimeSourceResolver',
    'AutomationRuntimeConnectionResolver',
    'AutomationCutoverGuard',
    'AutomationRollbackPolicy',
] as $class) {
    expectAutomationRuntime(is_readable("{$servicesDir}/{$class}.php"), "Missing runtime activation component: {$class}");
}

expectAutomationRuntime(str_contains($envExample, 'AUTOMATION_DB_MODE=fallback'), 'Safe env example must include AUTOMATION_DB_MODE.');
expectAutomationRuntime(str_contains($runtimeMode, "self::FALLBACK") && str_contains($runtimeMode, "self::PROVISIONING") && str_contains($runtimeMode, "self::DEDICATED"), 'Runtime mode must model fallback, provisioning, and dedicated.');
expectAutomationRuntime(str_contains($runtimeMode, 'self::INVALID'), 'Invalid runtime mode must fail safely.');
expectAutomationRuntime(str_contains($runtimeMode, 'AUTOMATION_DB_MODE'), 'Runtime mode must read AUTOMATION_DB_MODE.');
expectAutomationRuntime(str_contains($runtimeMode, 'return self::PROVISIONING;') && str_contains($runtimeMode, 'return self::FALLBACK;'), 'Missing mode must default to provisioning only when dedicated config exists, otherwise fallback.');
expectAutomationRuntime(!str_contains($runtimeMode, 'return self::DEDICATED;'), 'Missing mode must never default to dedicated.');
expectAutomationRuntime(str_contains($runtimeMode, 'provisioningAllowed'), 'Application migration/seeder mode must distinguish provisioning and dedicated from fallback.');
expectAutomationRuntime(str_contains($readiness, 'prerequisitesPassed') && str_contains($readiness, 'requiredKeys'), 'Readiness must evaluate cutover prerequisites directly.');

foreach ([
    'dedicated_connection_configured',
    'dedicated_connection_available',
    'utf8mb4_ready',
    'utc_timezone_applied',
    'standalone_schema_available',
    'standalone_metadata_available',
    'application_migration_history_available',
    'internal_foreign_keys_preserved',
    'core_foreign_keys_absent',
    'cross_database_sql_absent',
    'schema_parity_contract_passes',
    'legacy_operational_data_absent',
    'rollback_source_available',
] as $requiredGuardKey) {
    expectAutomationRuntime(str_contains($cutoverGuard, "'{$requiredGuardKey}'"), "Cutover guard missing key: {$requiredGuardKey}");
    expectAutomationRuntime(str_contains($readiness, "'{$requiredGuardKey}'"), "Readiness missing prerequisite key: {$requiredGuardKey}");
}

expectAutomationRuntime(!preg_match('/current_runtime_source|runtime_source|dedicated_runtime_active|cutover_guard_passed|guard/i', $readiness), 'Readiness must not depend on runtime source, active dedicated mode, or guard state.');

expectAutomationRuntime(str_contains($sourceResolver, 'dedicatedRequested()') && str_contains($sourceResolver, '$cutoverGuardPassed'), 'Dedicated source must require explicit mode and passing guard.');
expectAutomationRuntime(str_contains($connectionResolver, 'Automation runtime is unavailable.'), 'Dedicated runtime failure must fail closed.');
expectAutomationRuntime(str_contains($connectionResolver, "resolve('automation.primary')"), 'Dedicated runtime must resolve automation.primary.');
expectAutomationRuntime(str_contains($connectionResolver, "resolve('core.primary')"), 'Fallback/provisioning runtime must preserve legacy Core source.');
expectAutomationRuntime(!preg_match('/fallbackSharesPdo|core\.primary[\s\S]{0,120}dedicatedRequested/', $connectionResolver), 'Dedicated mode must not silently fall back to Core.');
expectAutomationRuntime(str_contains($rollbackPolicy, 'return false;') && str_contains($rollbackPolicy, 'explicitRollbackAvailable'), 'Rollback policy must disable automatic cutover/rollback and allow explicit rollback.');

foreach ([$migrate, $seed] as $endpoint) {
    expectAutomationRuntime(str_contains($endpoint, 'AutomationRuntimeMode'), 'Application endpoint must check Automation runtime mode.');
    expectAutomationRuntime(str_contains($endpoint, 'provisioningAllowed()'), 'Application endpoint must allow only provisioning/dedicated for Automation execution.');
    expectAutomationRuntime(str_contains($endpoint, 'usesFallback()'), 'Application endpoint must reject fallback connection for Automation execution.');
}

foreach ([
    'automation_runtime_mode_available',
    'automation_runtime_mode_valid',
    'automation_runtime_mode',
    'automation_cutover_guard_available',
    'automation_cutover_guard_passed',
    'automation_runtime_source_dedicated',
    'automation_dedicated_runtime_active',
    'automation_runtime_fallback_disabled_in_dedicated_mode',
    'automation_runtime_fail_closed',
    'automation_split_brain_prevention_available',
    'automation_automatic_cutover_disabled',
    'automation_automatic_rollback_disabled',
    'automation_explicit_rollback_available',
    'automation_legacy_rollback_source_retained',
    'automation_runtime_connection_resolver_available',
    'automation_current_runtime_source_unchanged',
    'automation_schema_parity_passed',
    'automation_cutover_prerequisites_passed',
    'automation_cutover_readiness_evaluation_available',
] as $diagnostic) {
    expectAutomationRuntime(str_contains($routes, "'{$diagnostic}'"), "Missing runtime diagnostic: {$diagnostic}");
}

expectAutomationRuntime(
    str_contains($routes, "'automation_cutover_ready' => \$automationCutoverPrerequisitesPassed"),
    'automation_cutover_ready must be based on prerequisites, not guard or activation state.'
);
expectAutomationRuntime(
    strpos($routes, '$automationCutoverPrerequisitesPassed') < strpos($routes, '$automationCutoverGuardPassed'),
    'Cutover prerequisites must be evaluated before the guard.'
);
expectAutomationRuntime(
    strpos($routes, '$automationCutoverGuardPassed') < strpos($routes, '$automationRuntimeSourceDedicated'),
    'Runtime source activation must be evaluated after readiness and guard.'
);

expectAutomationRuntime(!preg_match('/\b(?:password|dsn|database_name|username|host|PDOException|getMessage\(\))\b/i', $diagnosticsRoute), 'Runtime diagnostics must not expose topology, credentials, or exception details.');
expectAutomationRuntime(!preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE)\b[\s\S]{0,120}\b(?:correspondences|correspondence_versions|correspondence_referrals|correspondence_events)\b/i', $sourceResolver . "\n" . $connectionResolver . "\n" . $rollbackPolicy), 'Runtime activation must not introduce dual writes or operational data writes.');
expectAutomationRuntime(!preg_match('/REFERENCES\s+[A-Za-z0-9_]+\.[A-Za-z0-9_]+|\b(?:FROM|JOIN|UPDATE|INTO)\s+(?!information_schema\.)[A-Za-z0-9_]+\.[A-Za-z0-9_]+/i', $sourceResolver . "\n" . $connectionResolver . "\n" . $cutoverGuard), 'Runtime activation must not introduce cross-database SQL or FK.');

foreach ([
    'fallback',
    'provisioning',
    'dedicated',
    'fail closed',
    'Split-brain prevention',
    'Explicit rollback',
    'AUTOMATION_DB_MODE=dedicated',
] as $needle) {
    expectAutomationRuntime(str_contains($docs, $needle), "Multi-database docs missing runtime topic: {$needle}");
}

expectAutomationRuntime(str_contains($projectContext, 'Guarded Automation runtime modes'), 'Project context must mention guarded runtime modes.');
expectAutomationRuntime(str_contains($checklist, 'Guarded Automation Runtime Activation'), 'Release checklist must include runtime activation checks.');
expectAutomationRuntime(str_contains(file_get_contents($root . '/public_html/VERSION'), '0.5.0-work-management-foundation-dev'), 'Runtime version must remain the active Work Management development marker.');

echo "Automation runtime activation structural tests passed.\n";

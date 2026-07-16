<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routesPath = $root . '/public_html/routes/web.php';
$navigationPath = $root . '/public_html/app/Services/AdminNavigationRbacService.php';
$panelPath = $root . '/public_html/app/Services/AdminPanelService.php';
$cssPath = $root . '/public_html/public/assets/admin/css/admin.css';
$servicesPath = $root . '/public_html/app/Services/Automation/Correspondence';
$viewsPath = $root . '/public_html/resources/views/admin';
$versionPath = $root . '/public_html/VERSION';

$routes = file_get_contents($routesPath);
$navigation = file_get_contents($navigationPath);
$panel = file_get_contents($panelPath);
$css = file_get_contents($cssPath);
$version = trim((string) file_get_contents($versionPath));

function expectAutomationDemo(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

expectAutomationDemo($version === '0.4.8-platform-commercial-foundation-dev', 'Runtime version must remain the v0.4.8 development marker.');

$serviceClasses = [
    'AutomationOperationalRuntime',
    'AutomationLookupRepository',
    'CoreReferenceOptions',
    'CorrespondenceRepository',
    'CorrespondenceVersionRepository',
    'CorrespondencePartyRepository',
    'CorrespondenceEventRepository',
    'CorrespondenceQueryService',
    'CorrespondenceCommandService',
    'CorrespondenceDraftService',
    'CorrespondenceViewModelBuilder',
];

foreach ($serviceClasses as $class) {
    $path = "{$servicesPath}/{$class}.php";
    expectAutomationDemo(is_readable($path), "Missing Automation correspondence service: {$class}");
}

$runtime = file_get_contents($servicesPath . '/AutomationOperationalRuntime.php');
expectAutomationDemo(str_contains($runtime, 'AutomationRuntimeConnectionResolver'), 'Operational runtime must use AutomationRuntimeConnectionResolver.');
expectAutomationDemo(str_contains($runtime, 'AutomationCutoverGuard'), 'Operational runtime must require the Automation cutover guard.');
expectAutomationDemo(str_contains($runtime, 'dedicatedRequested()'), 'Operational runtime must require dedicated mode.');
expectAutomationDemo(str_contains($runtime, 'resolve(true)'), 'Operational runtime must resolve only the dedicated Automation connection.');
expectAutomationDemo(!str_contains($runtime, 'Database::connect'), 'Operational runtime must not use the Core PDO helper.');
expectAutomationDemo(str_contains($runtime, 'usesFallback()'), 'Operational runtime must explicitly reject fallback connection definitions.');

foreach (['CorrespondenceRepository', 'CorrespondenceVersionRepository', 'CorrespondencePartyRepository', 'CorrespondenceEventRepository'] as $repository) {
    $source = file_get_contents("{$servicesPath}/{$repository}.php");
    expectAutomationDemo(!str_contains($source, 'IPKF\\Database\\Database'), "{$repository} must not import Core Database.");
    expectAutomationDemo(!str_contains($source, 'Database::connect'), "{$repository} must not use Core Database::connect.");
    expectAutomationDemo(preg_match('/\b[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+\b/', $source) === 0, "{$repository} must not use cross-database table names.");
}

$coreReferences = file_get_contents($servicesPath . '/CoreReferenceOptions.php');
expectAutomationDemo(str_contains($coreReferences, 'CoreReferenceValidator'), 'Core references must be validated before Automation writes.');
expectAutomationDemo(!preg_match('/\b(?:host|database|username|password|dsn)\b/i', $coreReferences), 'Core reference tokens must not expose topology or credentials.');

$commands = file_get_contents($servicesPath . '/CorrespondenceCommandService.php');
expectAutomationDemo(str_contains($commands, 'beginTransaction()'), 'Correspondence creation/editing must use an Automation transaction.');
expectAutomationDemo(str_contains($commands, 'correspondences->insert'), 'Correspondence aggregate must be created by the command service.');
expectAutomationDemo(str_contains($commands, 'versions->create'), 'Correspondence versions must be created by the command service.');
expectAutomationDemo(str_contains($commands, 'parties->insertMany'), 'Correspondence parties must be created by the command service.');
expectAutomationDemo(str_contains($commands, "events->append(") && str_contains($commands, "'created'") && str_contains($commands, "'revised'"), 'Created and revised events must be appended.');
expectAutomationDemo(str_contains($commands, 'lock_version'), 'Draft editing must include stale update protection.');
expectAutomationDemo(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $commands), 'Command service must not destructively repair schema or data.');

$routesExpected = [
    "get('/admin/automation'",
    "get('/admin/automation/correspondences'",
    "get('/admin/automation/correspondences/create'",
    "post('/admin/automation/correspondences'",
    "get('/admin/automation/correspondences/{public_reference}'",
    "get('/admin/automation/correspondences/{public_reference}/edit'",
    "post('/admin/automation/correspondences/{public_reference}/versions'",
];

foreach ($routesExpected as $route) {
    expectAutomationDemo(str_contains($routes, $route), "Missing route: {$route}");
}

expectAutomationDemo(!str_contains($routes, '/admin/automation/correspondences/{id}'), 'Public URLs must not expose numeric database IDs.');
expectAutomationDemo(!preg_match('/\$router->(?:delete|post)\([^\n]*(?:delete|destroy)/i', $routes), 'No destructive Automation correspondence route may be exposed.');
expectAutomationDemo(str_contains($navigation, 'automation.correspondence.view'), 'Automation routes must be guarded by correspondence permissions.');
expectAutomationDemo(str_contains($navigation, 'automation.correspondence.create'), 'Create route must require correspondence create permission.');
expectAutomationDemo(str_contains($navigation, 'automation.correspondence.edit_draft'), 'Edit route must require draft edit permission.');
expectAutomationDemo(str_contains($panel, "'automation'"), 'Automation module must be present in the admin launcher.');
expectAutomationDemo(str_contains($css, 'admin-module-hub--teal') && str_contains($css, 'automation-party-row'), 'Automation UI styling must be present.');

$viewsExpected = [
    'automation-dashboard.php',
    'automation-correspondences.php',
    'automation-correspondence-form.php',
    'automation-correspondence-detail.php',
];

foreach ($viewsExpected as $view) {
    expectAutomationDemo(is_readable("{$viewsPath}/{$view}"), "Missing Automation admin view: {$view}");
}

$diagnostics = [
    'automation_correspondence_repository_available',
    'automation_correspondence_query_service_available',
    'automation_correspondence_command_service_available',
    'automation_correspondence_draft_creation_available',
    'automation_correspondence_versioning_runtime_available',
    'automation_correspondence_party_runtime_available',
    'automation_correspondence_event_runtime_available',
    'automation_correspondence_routes_available',
    'automation_correspondence_dashboard_available',
    'automation_correspondence_list_ui_available',
    'automation_correspondence_create_ui_available',
    'automation_correspondence_detail_workspace_available',
    'automation_correspondence_rbac_guards_available',
    'automation_correspondence_runtime_uses_dedicated_connection',
    'automation_correspondence_legacy_runtime_access_blocked',
    'automation_correspondence_no_cross_database_queries',
    'automation_correspondence_operational_demo_slice_available',
];

foreach ($diagnostics as $diagnostic) {
    expectAutomationDemo(str_contains($routes, "'{$diagnostic}'"), "Missing safe diagnostic: {$diagnostic}");
}

expectAutomationDemo(!preg_match('/automation_correspondence_[^\n]+(?:count|id|dsn|host|database|sql|token|secret)/i', $routes), 'Automation demo diagnostics must remain boolean-only and secret-free.');
expectAutomationDemo(!preg_match('/\b(?:AUTOMATION_DB_PASSWORD|AUTOMATION_DB_USERNAME|AUTOMATION_DB_DATABASE|AUTOMATION_DB_HOST)\b/', $routes . $runtime), 'Automation demo must not expose dedicated database credentials or topology.');

$combinedOperationalSources = $runtime . $commands . file_get_contents($servicesPath . '/CorrespondenceRepository.php') . file_get_contents($servicesPath . '/CorrespondenceQueryService.php');
expectAutomationDemo(preg_match('/\b(?:JOIN|FROM|UPDATE|INSERT\s+INTO)\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+\b/i', $combinedOperationalSources) === 0, 'Automation demo must not use cross-database SQL.');

echo "Automation correspondence demo slice structural tests passed.\n";
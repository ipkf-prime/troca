<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$migration = $read('public_html/system/Database/Migrations/CreateModuleReferenceDataTables.php');
$migrationRegistry = $read('public_html/system/Database/Application/ApplicationMigrationRegistry.php');
$seederRegistry = $read('public_html/system/Database/Application/ApplicationSeederRegistry.php');
$seeder = $read('public_html/system/Database/Seeds/WorkReferenceDataSeeder.php');
$permissions = $read('public_html/system/Database/Seeds/WorkManagementPermissionsSeeder.php');
$referenceRepository = $read('public_html/app/Repositories/ModuleReferenceRepository.php');
$settingsRepository = $read('public_html/app/Repositories/WorkSettingsRepository.php');
$referenceService = $read('public_html/app/Services/Work/WorkReferenceDataService.php');
$settingsService = $read('public_html/app/Services/Work/WorkSettingsService.php');
$routes = $read('public_html/routes/work-settings.php');
$loader = $read('public_html/system/Routing/RouteLoader.php');
$view = $read('public_html/resources/views/admin/work-settings.php');
$projectService = $read('public_html/app/Services/Work/WorkProjectService.php');
$itemService = $read('public_html/app/Services/Work/WorkItemService.php');
$myItemsService = $read('public_html/app/Services/Work/WorkMyItemsService.php');
$itemDetailService = $read('public_html/app/Services/Work/WorkItemDetailService.php');

foreach ([
    'module_reference_groups',
    'module_reference_items',
    'module_reference_audit_events',
] as $table) {
    $expect(
        str_contains($migration, "CREATE TABLE IF NOT EXISTS {$table}"),
        "Missing module reference table: {$table}"
    );
}

foreach (['project_status', 'project_visibility', 'item_priority', 'item_type'] as $group) {
    $expect(str_contains($seeder, "'{$group}'"), "Missing Work reference group: {$group}");
}

$expect(
    str_contains($migrationRegistry, 'CreateModuleReferenceDataTables::class'),
    'Reference data migration is not registered for Work.'
);
$expect(
    str_contains($seederRegistry, 'WorkReferenceDataSeeder::class'),
    'Work reference data seeder is not registered.'
);
$expect(
    str_contains($permissions, "'work.settings.view'")
        && str_contains($permissions, "'work.settings.manage'"),
    'Work settings permissions are incomplete.'
);
$expect(
    str_contains($settingsRepository, 'module_reference_audit_events'),
    'Settings changes must be audited.'
);
$expect(
    str_contains($settingsService, "management_mode")
        && str_contains($settingsService, "'structural'"),
    'Settings management modes are missing.'
);
$expect(
    str_contains($routes, '/admin/work/settings')
        && str_contains($routes, 'work.settings.manage'),
    'Work settings routes or guards are missing.'
);
$expect(
    str_contains($loader, 'work-settings.php'),
    'Work settings route file is not loaded.'
);
$expect(
    str_contains($view, 'تنظیمات مدیریت کار')
        && str_contains($view, 'افزودن گزینه جدید'),
    'Work settings operational UI is incomplete.'
);
$expect(
    str_contains($referenceService, 'projectStatuses')
        && str_contains($referenceService, 'itemPriorities')
        && str_contains($referenceService, 'itemTypes'),
    'Runtime reference data resolver is incomplete.'
);

foreach ([
    $projectService,
    $itemService,
    $myItemsService,
    $itemDetailService,
] as $service) {
    $expect(
        str_contains($service, 'WorkReferenceDataService'),
        'A Work service still uses isolated hard-coded dropdown definitions.'
    );
}

$expect(
    !preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $migration),
    'Reference data migration must be non-destructive.'
);

echo "Work module settings slice checks passed.\n";

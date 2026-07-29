<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$migration = $read('public_html/system/Database/Migrations/CreateWorkManagementFoundationTables.php');
$permissions = $read('public_html/system/Database/Seeds/WorkManagementPermissionsSeeder.php');
$registry = $read('public_html/system/Database/Connections/ConnectionRegistry.php');
$migrationRegistry = $read('public_html/system/Database/Application/ApplicationMigrationRegistry.php');
$seederRegistry = $read('public_html/system/Database/Application/ApplicationSeederRegistry.php');
$migrate = $read('public_html/public/migrate.php');
$seed = $read('public_html/public/seed.php');
$catalog = $read('public_html/app/Services/ApplicationModuleRegistryService.php');
$foundationSeeder = $read('public_html/system/Database/Seeds/WorkManagementFoundationSeeder.php');
$dashboardRepository = $read('public_html/app/Repositories/WorkDashboardRepository.php');
$routes = $read('public_html/routes/web.php');

foreach (['work_statuses', 'work_projects', 'work_project_members', 'work_items', 'work_item_assignees', 'work_item_dependencies', 'work_checklist_items', 'work_labels', 'work_item_labels', 'work_attachments', 'work_comments', 'work_activity_events'] as $table) {
    $expect(str_contains($migration, "CREATE TABLE IF NOT EXISTS {$table}"), "Missing Work table: {$table}");
}

foreach (['work', 'milestone', 'task', 'subtask'] as $type) {
    $expect(str_contains($migration, "'{$type}'"), "Missing Work hierarchy type: {$type}");
}

$expect(str_contains($registry, "'work.primary'"), 'work.primary must be registered.');
$expect(str_contains($migrationRegistry, "'work' =>") && str_contains($migrationRegistry, "'connection' => 'work.primary'"), 'Work migration group is missing.');
$expect(str_contains($seederRegistry, 'WorkManagementFoundationSeeder::class'), 'Work metadata seeder is missing.');
$expect(str_contains($migrate, "['core', 'automation', 'work']"), 'Work migration endpoint must be allowlisted.');
$expect(str_contains($seed, "['core', 'automation', 'work']"), 'Work seed endpoint must be allowlisted.');
$expect(str_contains($catalog, '$urls->work()') && str_contains($catalog, "'connection' => 'work.primary'"), 'Environment-driven Work module catalog entry is missing.');
$expect(str_contains($foundationSeeder, 'work_statuses') && str_contains($foundationSeeder, 'status_code') && str_contains($foundationSeeder, 'status_id'), 'Work foundation seeder must match the Work schema.');
$expect(!str_contains($foundationSeeder, 'INSERT INTO work_projects
                (public_reference, code, title, description, status,'), 'Work seeder must not write the removed project status column.');
$expect(str_contains($foundationSeeder, 'ON DUPLICATE KEY UPDATE'), 'Work seeder must be idempotent.');
$expect(str_contains($dashboardRepository, 'FROM work_items') && str_contains($dashboardRepository, 'work_statuses'), 'Work dashboard repository must query the canonical work_items schema.');
$expect(!str_contains($dashboardRepository, 'work_tasks'), 'Work dashboard repository must not query a non-existent work_tasks table.');
$expect(str_contains($seed, "Standalone work schema is not ready."), 'Work application seeding must fail safely before schema migration.');
$expect(str_contains($routes, "'work_primary_connection_available'") && str_contains($routes, "'work_schema_available'"), 'Work connection/schema diagnostics are missing.');
$expect(!str_contains($catalog, 'work-dev.troca.ir'), 'Work catalog must not hardcode a deployment domain.');
$expect(str_contains($permissions, "'work.project.manage'") && str_contains($permissions, "'work.item.assign'"), 'Work RBAC permissions are incomplete.');
$expect(!preg_match('/\b(?:FROM|JOIN|UPDATE|INTO|REFERENCES)\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+/i', $migration), 'Work migration must not use cross-database SQL.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $migration), 'Work migration must be non-destructive.');

echo "Work Management foundation structural tests passed.\n";

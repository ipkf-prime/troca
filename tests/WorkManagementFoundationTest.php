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
$expect(!str_contains($catalog, 'work-dev.troca.ir'), 'Work catalog must not hardcode a deployment domain.');
$expect(str_contains($permissions, "'work.project.manage'") && str_contains($permissions, "'work.item.assign'"), 'Work RBAC permissions are incomplete.');
$expect(!preg_match('/\b(?:FROM|JOIN|UPDATE|INTO|REFERENCES)\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+/i', $migration), 'Work migration must not use cross-database SQL.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $migration), 'Work migration must be non-destructive.');

echo "Work Management foundation structural tests passed.\n";

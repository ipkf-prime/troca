<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/public_html/system/Database/Migrations/CreateWorkManagementFoundationTables.php');
$seeder = file_get_contents($root . '/public_html/system/Database/Seeds/WorkManagementFoundationSeeder.php');
$migrationRegistry = file_get_contents($root . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php');
$seederRegistry = file_get_contents($root . '/public_html/system/Database/Application/ApplicationSeederRegistry.php');
$connectionRegistry = file_get_contents($root . '/public_html/system/Database/Connections/ConnectionRegistry.php');
$moduleRegistry = file_get_contents($root . '/public_html/app/Services/ApplicationModuleRegistryService.php');
$env = file_get_contents($root . '/.env.example');

function expectWork(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([
    'work_projects', 'work_items', 'work_milestones', 'work_tasks', 'work_members',
    'work_task_assignees', 'work_task_checklist_items', 'work_task_comments',
    'work_attachments', 'work_activity_log',
] as $table) {
    expectWork(substr_count($migration, "CREATE TABLE IF NOT EXISTS {$table} (") === 1, "Missing work table: {$table}");
}

expectWork(str_contains($migrationRegistry, "'work' =>"), 'Work migration group is missing.');
expectWork(str_contains($migrationRegistry, 'CreateWorkManagementFoundationTables::class'), 'Work migration is not registered.');
expectWork(str_contains($seederRegistry, 'WorkManagementFoundationSeeder::class'), 'Work seeder is not registered.');
expectWork(str_contains($connectionRegistry, "'work.primary'"), 'Dedicated work connection is missing.');
expectWork(str_contains($moduleRegistry, "'work' =>"), 'Work module catalog entry is missing.');
expectWork(str_contains($moduleRegistry, 'https://work-dev.troca.ir'), 'Work development domain is missing.');
expectWork(str_contains($env, 'WORK_DB_DATABASE=troca_work'), 'Work database example is missing.');
expectWork(str_contains($seeder, "'ipkf-management'"), 'Initial IPKF Management project is missing.');
expectWork(!preg_match('/REFERENCES\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+/i', $migration), 'Cross-database foreign keys are forbidden.');

echo "Work Management foundation structural tests passed.\n";

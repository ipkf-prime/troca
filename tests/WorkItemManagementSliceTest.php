<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$repository = $read('public_html/app/Repositories/WorkItemRepository.php');
$service = $read('public_html/app/Services/Work/WorkItemService.php');
$routes = $read('public_html/routes/web.php');
$projectRepository = $read('public_html/app/Repositories/WorkProjectRepository.php');
$dashboard = $read('public_html/resources/views/admin/work-dashboard.php');
$projectShow = $read('public_html/resources/views/admin/work-project-show.php');

foreach (['work', 'milestone', 'task', 'subtask'] as $type) {
    $expect(str_contains($service, "'{$type}'"), "Missing item type: {$type}");
}

foreach (['/items', '/items/create', '/items/{item_reference}/edit', '/items/{item_reference}/archive'] as $route) {
    $expect(str_contains($routes, $route), "Missing Work item route fragment: {$route}");
}

$expect(str_contains($repository, 'work_item_assignees'), 'Responsible assignment persistence is missing.');
$expect(str_contains($repository, 'work_activity_events'), 'Work item activity logging is missing.');
$expect(str_contains($service, 'wouldCreateCycle'), 'Hierarchy cycle prevention is missing.');
$expect(str_contains($projectRepository, "'1 = 1'"), 'All-project filter must include current and archived projects.');
$expect(!str_contains($dashboard, 'work-dashboard__intro'), 'Dashboard intro text must be removed.');
$expect(str_contains($projectShow, "'/items'"), 'Project item management link is missing.');

echo "Work item management slice structural tests passed.\n";
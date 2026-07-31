<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$sort = $read('public_html/app/Support/AdminTableSort.php');
$projects = $read('public_html/app/Repositories/WorkProjectAccessRepository.php');
$projectService = $read('public_html/app/Services/Work/WorkProjectAccessService.php');
$items = $read('public_html/app/Repositories/WorkItemRepository.php');
$myItems = $read('public_html/app/Repositories/WorkMyItemsRepository.php');
$projectsView = $read('public_html/resources/views/admin/work-projects.php');
$itemsView = $read('public_html/resources/views/admin/work-items.php');
$dashboardView = $read('public_html/resources/views/admin/work-dashboard.php');
$settingsView = $read('public_html/resources/views/admin/work-settings.php');
$stage5Ui = $read('public_html/resources/views/admin/work-stage5-ui.php');

$expect(str_contains($sort, "in_array(\$direction, ['asc', 'desc'], true)"), 'Sort direction allowlist is missing.');
$expect(str_contains($sort, 'array_key_exists($column, $allowedColumns)'), 'Sort column allowlist is missing.');
$expect(str_contains($sort, 'PHP_QUERY_RFC3986'), 'Sortable URLs must be safely encoded.');
$expect(str_contains($projects, 'AdminTableSort::resolve') && str_contains($projects, "ORDER BY {\$sort['sql']}"), 'Project server-side sorting is missing.');
$expect(str_contains($projectService, "'sort' => \$sort['column']") && str_contains($projectService, "'dir' => \$sort['direction']"), 'Project sort state is not returned to the view.');
$expect(str_contains($items, 'AdminTableSort::resolve') && str_contains($myItems, 'AdminTableSort::resolve'), 'Work item sorting is incomplete.');
$expect(str_contains($projectsView, "'open_items' => 'باز'") && str_contains($projectsView, 'AdminTableSort::url'), 'Project sortable headers are missing.');
$expect(str_contains($itemsView, "'assignee' => 'مسئول'") && str_contains($itemsView, 'AdminTableSort::url'), 'Item sortable headers are missing.');
$expect(str_contains($dashboardView, "'project' => 'پروژه'") && str_contains($dashboardView, 'AdminTableSort::url'), 'My Work sortable headers are missing.');
$expect(str_contains($dashboardView, 'data-admin-client-sort'), 'Recent task client sorting is missing.');
$expect(str_contains($settingsView, 'work-settings-layout--minimal') && str_contains($settingsView, '<details class="work-settings-create--minimal">'), 'Minimal settings layout is missing.');
$expect(str_contains($stage5Ui, 'data-admin-client-table-sort') && str_contains($stage5Ui, '.work-settings-form--minimal'), 'Stage 5 shared UI contract is incomplete.');
$expect(str_contains($projectService, "'observer'") && str_contains($projectService, 'can_manage_members'), 'Project role matrix regressed.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $projects . $items . $myItems), 'Release candidate contains destructive SQL.');

echo "Work v0.5.1 release candidate checks passed.\n";

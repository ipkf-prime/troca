<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$repository = $read('public_html/app/Repositories/WorkProjectAccessRepository.php');
$service = $read('public_html/app/Services/Work/WorkProjectAccessService.php');
$dashboard = $read('public_html/app/Repositories/WorkDashboardRepository.php');
$routes = $read('public_html/routes/work-project-access.php');
$routeLoader = $read('public_html/system/Routing/RouteLoader.php');
$permissions = $read('public_html/system/Database/Seeds/WorkManagementPermissionsSeeder.php');

$expect(str_contains($permissions, "'work.project.admin'"), 'Global Work project admin permission is missing.');
$expect(str_contains($repository, 'work_project_members') && str_contains($repository, 'left_at IS NULL'), 'Active project membership scope is missing.');
$expect(str_contains($repository, "visibility_code = 'public'"), 'Public project view scope is missing.');
$expect(str_contains($service, "'owner', 'manager', 'member', 'observer'"), 'Project role matrix is incomplete.');
$expect(str_contains($service, 'can_manage_project') && str_contains($service, 'can_manage_members'), 'Project management capabilities are missing.');
$expect(str_contains($service, 'can_edit_item') && str_contains($service, 'is_assignee') && str_contains($service, 'is_creator'), 'Assignee/creator item editing rule is missing.');
$expect(str_contains($dashboard, 'work_project_members') && str_contains($dashboard, 'projectScope'), 'Dashboard project scoping is missing.');
$expect(str_contains($routes, 'workAccessService') && str_contains($routes, 'can_view_item'), 'Project-scoped route guards are missing.');
$expect(str_contains($routes, "status = 403") || str_contains($routes, 'int $status = 403'), 'Forbidden response path is missing.');
$expect(str_contains($routeLoader, "work-project-access.php"), 'Project-scoped routes are not loaded.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $repository . $dashboard), 'Stage 4 must not contain destructive SQL.');

echo "Work project access slice checks passed.\n";

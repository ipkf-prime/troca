<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$repository = $read('public_html/app/Repositories/WorkMyItemsRepository.php');
$service = $read('public_html/app/Services/Work/WorkMyItemsService.php');
$dashboardService = $read('public_html/app/Services/Work/WorkDashboardService.php');
$dashboardView = $read('public_html/resources/views/admin/work-dashboard.php');

foreach (['open', 'today', 'overdue', 'unassigned', 'completed'] as $scope) {
    $expect(str_contains($service, "'{$scope}'"), "Missing Work scope: {$scope}");
}

$expect(str_contains($repository, 'work_item_assignees'), 'My Work must read active assignments.');
$expect(str_contains($repository, 'work_project_members'), 'Unassigned Work must be limited to project members.');
$expect(str_contains($repository, 'UTC_DATE()'), 'Today filter is missing.');
$expect(str_contains($repository, 'UTC_TIMESTAMP()'), 'Overdue filter is missing.');
$expect(str_contains($dashboardService, "'my_work'"), 'Dashboard must expose My Work data.');
$expect(str_contains($dashboardView, 'id="my-work"'), 'Dashboard My Work section is missing.');
$expect(str_contains($dashboardView, 'scope=overdue'), 'Dashboard overdue shortcut is missing.');
$expect(str_contains($dashboardView, 'مشاهده و ویرایش'), 'My Work operation link is missing.');

echo "Work My Items slice checks passed.\n";

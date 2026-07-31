<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$repository = $read('public_html/app/Repositories/WorkItemDetailRepository.php');
$service = $read('public_html/app/Services/Work/WorkItemDetailService.php');
$routes = $read('public_html/routes/work-item-detail.php');
$loader = $read('public_html/system/Routing/RouteLoader.php');
$view = $read('public_html/resources/views/admin/work-item-show.php');
$list = $read('public_html/resources/views/admin/work-items.php');

foreach (['work_comments', 'work_checklist_items', 'work_attachments', 'work_activity_events'] as $table) {
    $expect(str_contains($repository, $table), "Missing operational table: {$table}");
}

$expect(str_contains($service, 'MAX_ATTACHMENT_BYTES'), 'Attachment size guard is missing.');
$expect(str_contains($service, 'hash_file'), 'Attachment checksum validation is missing.');
$expect(str_contains($routes, '/comments'), 'Comment route is missing.');
$expect(str_contains($routes, '/checklist'), 'Checklist route is missing.');
$expect(str_contains($routes, '/attachments'), 'Attachment route is missing.');
$expect(str_contains($loader, 'work-item-detail.php'), 'Work detail route loader is missing.');
$expect(str_contains($view, 'تاریخچه فعالیت'), 'Activity timeline is missing.');
$expect(str_contains($view, 'دیدگاه‌ها'), 'Comments section is missing.');
$expect(str_contains($list, '$detailUrl'), 'Work list must link to detail page.');

echo "Work item detail slice checks passed.\n";

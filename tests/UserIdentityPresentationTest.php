<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$service = $read('public_html/app/Services/UserIdentityLabelService.php');
$repository = $read('public_html/app/Repositories/UserIdentityLabelRepository.php');
$base = $read('public_html/app/Services/BaseService.php');
$projectList = $read('public_html/resources/views/admin/work-projects.php');
$projectShow = $read('public_html/resources/views/admin/work-project-show.php');
$members = $read('public_html/resources/views/admin/work-project-members.php');
$itemForm = $read('public_html/resources/views/admin/work-item-form.php');
$itemList = $read('public_html/resources/views/admin/work-items.php');
$itemShow = $read('public_html/resources/views/admin/work-item-show.php');

$expect(str_contains($repository, "resolve('core.primary')"), 'Identity labels must resolve from Core.');
$expect(
    strpos($service, "'email',") < strpos($service, "'mobile',"),
    'Email must have priority over mobile.'
);
$expect(str_contains($service, 'looksLikeInternalId'), 'Internal-id fallback suppression is missing.');
$expect(str_contains($service, 'return $this->labelForReference('), 'User-id labels must resolve from Core before context fallback.');
$expect(!str_contains($service, "'کاربر #'"), 'Identity resolver must never generate a numbered user label.');
$expect(str_contains($base, 'userIdentityLabel'), 'Shared BaseService identity contract is missing.');
$expect(str_contains($projectList, 'UserIdentityLabelService'), 'Project list identity enrichment is missing.');
$expect(str_contains($projectShow, 'owner_display_name'), 'Project owner identity enrichment is missing.');
$expect(str_contains($members, 'ایمیل / موبایل'), 'Member table must replace user IDs with contact labels.');
$expect(!str_contains($members, "admin_h(\$member['user_reference']"), 'Member user reference is still rendered.');
$expect(str_contains($itemForm, 'مسئول (ایمیل یا موبایل)'), 'Assignee form label is not contact-oriented.');
$expect(str_contains($itemList, 'assignee_reference'), 'Item list assignee enrichment is missing.');
$expect(str_contains($itemShow, 'author_user_reference') && str_contains($itemShow, 'actor_user_reference'), 'Comment/activity identity enrichment is missing.');

echo "IPKF user identity presentation checks passed.\n";

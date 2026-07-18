<?php

$root = dirname(__DIR__);
$form = file_get_contents($root . '/public_html/resources/views/admin/automation-correspondence-form.php');
$detail = file_get_contents($root . '/public_html/resources/views/admin/automation-correspondence-detail.php');
$routes = file_get_contents($root . '/public_html/routes/web.php');
$command = file_get_contents($root . '/public_html/app/Services/Automation/Correspondence/CorrespondenceCommandService.php');
$attachment = file_get_contents($root . '/public_html/app/Services/Automation/Correspondence/CorrespondenceAttachmentService.php');
$templates = file_get_contents($root . '/public_html/resources/views/admin/automation-templates.php');

$expect = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$expect(str_contains($form, 'data-template-select') && str_contains($form, '/admin/automation/templates'), 'Drafts must select templates from a visible catalog.');
$expect(str_contains($form, 'relation_type_code[]') && str_contains($form, 'related_correspondence_reference[]'), 'Drafts must support typed letter relations.');
$expect(str_contains($form, 'رونوشت و رونوشت مخفی'), 'Copy recipients must be explicit in the draft UI.');
$expect(str_contains($command, 'normalizeRelations') && str_contains($command, 'replaceForDraft'), 'Letter relations must be validated and saved transactionally.');
$expect(str_contains($detail, 'tab=attachments') || str_contains($detail, "activeTab === 'attachments'"), 'Correspondence workspace must expose attachments.');
$expect(str_contains($routes, "get('/admin/automation/templates'") && str_contains($templates, 'قالب‌های استاندارد نامه'), 'A template catalog route and view are required.');
$expect(str_contains($attachment, 'MAX_BYTES = 10485760') && str_contains($attachment, 'is_uploaded_file') && str_contains($attachment, 'finfo'), 'Private uploads must enforce size, origin and MIME validation.');
$expect(str_contains($routes, 'X-Content-Type-Options') && str_contains($routes, "str_starts_with(\$path, \$root . DIRECTORY_SEPARATOR)"), 'Private downloads must prevent MIME sniffing and path escape.');

echo "Correspondence operational document UI checks passed.\n";

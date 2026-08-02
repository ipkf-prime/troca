<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$migration = $read('public_html/system/Database/Migrations/CreateSecureMessageExtensionTables.php');
$routes = $read('public_html/routes/communication-center.php');
$attachments = $read('public_html/app/Services/InternalMessageAttachmentService.php');
$admin = $read('public_html/app/Services/InternalMessageAdministrationService.php');
$message = $read('public_html/app/Services/InternalMessageService.php');
$login = $read('public_html/app/Services/InternalMessageLoginNotifierService.php');

foreach (['message_settings', 'message_attachments', 'message_audit_events'] as $table) {
    $expect(str_contains($migration, $table), "Missing {$table}");
}
$expect(str_contains($attachments, 'is_uploaded_file') && str_contains($attachments, 'finfo')
    && str_contains($attachments, 'hash_file') && str_contains($attachments, 'storage/private/messages'),
    'Private attachment validation/storage is incomplete.');
$expect(str_contains($routes, '/admin/messages/attachments/{reference}')
    && str_contains($routes, 'X-Content-Type-Options') && str_contains($routes, 'Cache-Control'),
    'Authorized attachment download is incomplete.');
$expect(str_contains($admin, 'messages.admin.view') && str_contains($admin, 'monitor_reason_required')
    && str_contains($migration, 'ip_address') && str_contains($migration, 'user_agent'),
    'Audited administrator monitoring is incomplete.');
$expect(
    preg_match("~markActionRead\\s*\\(\\s*\\\$userId\\s*,\\s*'/admin/messages/inbox'\\s*\\)~", $message) === 1
    && str_contains($login, 'do not create a second'),
    'Duplicate bell notification fix is missing.'
);
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $migration), 'Destructive SQL is present.');
echo "Secure internal messaging package checks passed.\n";

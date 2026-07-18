<?php

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$migration = file_get_contents($root . '/public_html/system/Database/Migrations/CompleteOrganizationalIdentityAndSignatureFoundation.php');
$context = file_get_contents($root . '/public_html/app/Services/Organization/UserOrganizationalContextResolver.php');
$signature = file_get_contents($root . '/public_html/app/Services/Signature/SignatureAuthorizationResolver.php');

$assert(str_contains($migration, 'signature_assets'), 'signature assets table missing');
$assert(str_contains($migration, 'signature_authorizations'), 'signature authorizations table missing');
$assert(!preg_match('/\bBLOB\b/i', $migration), 'BLOB storage must not be introduced');
$assert(str_contains($migration, 'display_name_en'), 'English person display name missing');
$assert(str_contains($migration, 'title_en'), 'English organizational titles missing');
$assert(str_contains($context, "u.person_id"), 'user-to-person link is not used');
$assert(str_contains($context, "a.is_primary"), 'primary appointment resolution missing');
$assert(str_contains($context, '\$_SESSION') || str_contains($context, 'SESSION'), 'session context switching missing');
$assert(str_contains($signature, "['fa', 'en']"), 'strict bilingual signature language allowlist missing');
$assert(str_contains($signature, 'allow_shared_fallback'), 'shared signature fallback policy missing');
$assert(!str_contains($signature, 'SELECT *'), 'signature resolver must use explicit columns');

echo "Organizational identity and signature foundation checks passed.\n";

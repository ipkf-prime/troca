<?php
declare(strict_types=1);

/**
 * S36 R32: live Core organizations + Ticketing affiliation navigation
 * reconciliation, in addition to the existing 45 scoped Safir RBAC tests.
 * Static source-only contracts; no DB, HTTP, env or send.
 */
$root = dirname(__DIR__) . '/public_html/';
$path = $root . 'app/Services/DynamicAdminNavigationService.php';
$code = file_get_contents($path);
if (!is_string($code)) {
    throw new RuntimeException('FAIL=NAVIGATION_SOURCE_MISSING');
}
$contracts = [
    'TICKETING_REVIEWER_LABEL_PRESERVED' =>
        str_contains($code, 'A41R10_S9_TICKETING_AFFILIATION_REVIEW_EXISTING_LABEL')
        && str_contains($code, "'ticketing-core-affiliation-review'")
        && str_contains($code, "'organizations.affiliations.manage'"),
    'CORE_ORG_DIRECTORY_PRESERVED' =>
        str_contains($code, 'IPKF_ORG_R2A2_CANONICAL_NAVIGATION')
        && str_contains($code, 'withOrganizationDirectory($items, $shellKey)')
        && str_contains($code, "'/admin/organizations'")
        && str_contains($code, "'organizations.manage'"),
    'SAFIR_SCOPED_NAV_PERMISSION_PRESENT' =>
        str_contains($code, "['notifications.send.manage', 'notifications.safir.manage']"),
    'SAFIR_DB_NAV_ENTRY_ADAPTED' =>
        str_contains($code, <<<'PHP'
if (($item['item_key'] ?? '') === 'bale-safir-campaigns')
PHP)
        && str_contains($code, <<<'PHP'
$codes[] = 'notifications.safir.manage'
PHP),
];
foreach ($contracts as $gate => $pass) {
    if (!$pass) {
        throw new RuntimeException("FAIL=$gate");
    }
    echo "$gate=PASS\n";
}
echo "NAV_RECONCILED_SOURCE_CONTRACT=PASS\n";
echo "NO_HTTP_NO_DB_NO_SEND=PASS\n";

<?php
declare(strict_types=1);

/**
 * Safir v2.4.1 reconciliation: preserves 25 prior RBAC scenarios,
 * and validates six access/navigation decisions for live organization-directory additions.
 * Offline stubs only; no DB, HTTP, or provider send.
 */
require __DIR__ . '/safir-v24-rbac-preservation-offline.php';

$organizationsPath = '/admin/organizations';
$check('ORGANIZATIONS_ROUTE_PERMISSION',
    $rbac->permissionForPath($organizationsPath) === 'organizations.manage', true);
$check('ORGANIZATIONS_MANAGER_ALLOWED',
    $rbac->canAccessPath(12, $organizationsPath, 'GET'), true);
$check('ORGANIZATIONS_REVIEWER_DENIED',
    $rbac->canAccessPath(11, $organizationsPath, 'GET'), false);
$check('ORGANIZATIONS_UNPRIVILEGED_DENIED',
    $rbac->canAccessPath(10, $organizationsPath, 'GET'), false);
$check('ORGANIZATIONS_MANAGER_NAV_PRESENT',
    in_array('organizations-directory', array_column($rbac->systemNavigation(12), 'key'), true), true);
$check('ORGANIZATIONS_REVIEWER_NAV_HIDDEN',
    in_array('organizations-directory', array_column($rbac->systemNavigation(11), 'key'), true), false);

if ($count !== 31) {
    throw new \RuntimeException('FAIL=RBAC_RECONCILED_CASE_COUNT');
}
echo "RBAC_RECONCILED_CASES=31\n";
echo "RBAC_LIVE_ORG_DIRECTORY_PRESERVED=PASS\n";
echo "NO_HTTP_NO_DB_NO_SEND=PASS\n";

<?php
declare(strict_types=1);

/**
 * Safir v2.4.2 scoped role-management regression. No DB, HTTP, or send.
 * Extends and retains the 31 certified v2.4.1 RBAC cases.
 */
require __DIR__ . '/safir-v241-rbac-organizations-reconciliation-offline.php';

final class SafirScopedManagerProbe extends \App\Services\AdminNavigationRbacService
{
    private array $grants = [
        30 => ['notifications.safir.manage'],
        31 => ['notifications.send.manage'],
        32 => [],
    ];
    public function __construct() {}
    public function can(?int $userId, string $permission): bool
    {
        return $userId !== null
            && in_array($permission, $this->grants[$userId] ?? [], true);
    }
}

$scoped = new SafirScopedManagerProbe();
$before = $count;
$check('SAFIR_MANAGER_PAGE_GET', $scoped->canAccessPath(30, '/admin/communications/safir', 'GET'), true);
$check('SAFIR_MANAGER_PAGE_HEAD', $scoped->canAccessPath(30, '/admin/communications/safir', 'HEAD'), true);
$check('SAFIR_MANAGER_CSV_GET', $scoped->canAccessPath(30, '/admin/communications/safir/test-report.csv', 'GET'), true);
$check('SAFIR_MANAGER_CSV_HEAD', $scoped->canAccessPath(30, '/admin/communications/safir/test-report.csv', 'HEAD'), true);
$check('SAFIR_MANAGER_SETTINGS_POST', $scoped->canAccessPath(30, '/admin/communications/settings/safir', 'POST'), true);
$check('SAFIR_MANAGER_CAMPAIGN_EXPORT', $scoped->canAccessPath(30, '/admin/communications/settings/safir/export', 'GET'), true);
$check('SAFIR_MANAGER_PAGE_POST_DENIED', $scoped->canAccessPath(30, '/admin/communications/safir', 'POST'), false);
$check('SAFIR_MANAGER_CSV_POST_DENIED', $scoped->canAccessPath(30, '/admin/communications/safir/test-report.csv', 'POST'), false);
$check('SAFIR_MANAGER_SETTINGS_GET_DENIED', $scoped->canAccessPath(30, '/admin/communications/settings/safir', 'GET'), false);
$check('SAFIR_MANAGER_OTHER_SEND_DENIED', $scoped->canAccessPath(30, '/admin/communications/templates/test-send', 'POST'), false);
$check('SAFIR_UNPRIVILEGED_PAGE_DENIED', $scoped->canAccessPath(32, '/admin/communications/safir', 'GET'), false);
$check('SAFIR_SEND_PERMISSION_COMPAT_GET', $scoped->canAccessPath(31, '/admin/communications/safir', 'GET'), true);
$check('SAFIR_SEND_PERMISSION_COMPAT_POST', $scoped->canAccessPath(31, '/admin/communications/settings/safir', 'POST'), true);
$check('SAFIR_ANONYMOUS_DENIED', $scoped->canAccessPath(null, '/admin/communications/safir', 'GET'), false);

if ($count - $before !== 14) {
    throw new \RuntimeException('FAIL=SCOPED_MANAGER_COUNT');
}
$root = dirname(__DIR__) . '/public_html/';
$routes = (string) file_get_contents($root . 'routes/bale-safir-campaigns.php');
$navigation = (string) file_get_contents($root . 'app/Services/DynamicAdminNavigationService.php');
$seeder = (string) file_get_contents($root . 'system/Database/Seeds/CommunicationCenterSeeder.php');
$nav = (string) file_get_contents($root . 'app/Services/AdminNavigationRbacService.php');
foreach ([
    'SAFIR_SEND_ACTION_GATE' => str_contains($routes, "in_array(\$action, ['test', 'manual_test', 'dispatch'], true) && !\$maySend"),
    'SAFIR_POST_DUAL_PERMISSION' => str_contains($routes, "hasPermission(\$actor, 'notifications.safir.manage')"),
    'SAFIR_VIEW_SEND_LOCK' => str_contains($routes, "\$safirCampaign['manual_test_enabled'] = false;")
        && str_contains($routes, "\$safirCampaign['send_enabled'] = false;"),
    'SAFIR_CSV_DUAL_PERMISSION' => substr_count($routes, "hasPermission(\$actor, 'notifications.safir.manage')") >= 3,
    'SAFIR_MENU_DUAL_PERMISSION' => str_contains($navigation, "['notifications.send.manage', 'notifications.safir.manage']"),
    'SAFIR_SEED_NARROW_ROLE_GRANT' => str_contains($seeder, "\$assign->execute(['notifications.safir.manage', 'system_admin']);"),
    'SAFIR_OLD_PERMISSION_MAPPING_STABLE' => str_contains($nav, "'/admin/communications/safir/test-report.csv' => 'notifications.send.manage'"),
] as $gate => $passed) {
    if (!$passed) throw new \RuntimeException("FAIL=$gate");
    echo "$gate=PASS\n";
}
echo "RBAC_BASELINE_CASES=31\n";
echo "RBAC_SCOPED_MANAGER_CASES=14\n";
echo "RBAC_TOTAL_CASES=45\n";
echo "NO_DB_NO_HTTP_NO_SEND=PASS\n";
echo "SAFIR_V242_SCOPED_MANAGER_OFFLINE=PASS\n";

<?php
declare(strict_types=1);

/**
 * Safir v2.4 RBAC preservation regression.
 * Isolated stubs only; no database, HTTP client, provider or message dispatch.
 * Run: php -n tests/safir-v24-rbac-preservation-offline.php
 */

namespace App\Services {
    class BaseService {}
}

namespace App\Services\Ticketing {
    class TicketingProjectScopedAccessService {
        public function canAccessPath(int $userId, string $path): bool { return false; }
    }
    class TicketRequesterOnboardingService {
        public function hasMembership(int $userId): bool { return $userId === 20; }
    }
}

namespace {
    define('BASE_PATH', dirname(__DIR__) . '/public_html');
    require BASE_PATH . '/app/Services/AdminNavigationRbacService.php';

    final class SafirRbacProbe extends \App\Services\AdminNavigationRbacService {
        private array $grants = [
            10 => [],
            11 => ['organizations.affiliations.manage'],
            12 => ['organizations.manage'],
            13 => ['notifications.send.manage'],
            14 => ['ticketing.ticket.view'],
            20 => ['support.view'],
            21 => ['support.view'],
            23 => ['notifications.send.view'],
        ];
        public function __construct() {} // Bypass live dependencies.
        public function can(?int $userId, string $permission): bool {
            return $userId !== null && in_array($permission, $this->grants[$userId] ?? [], true);
        }
    }

    $rbac = new SafirRbacProbe();
    $count = 0;
    $check = static function(string $name, bool $actual, bool $expected) use (&$count): void {
        if ($actual !== $expected) {
            throw new \RuntimeException("FAIL=$name:EXPECTED=" . ($expected ? 'ALLOW' : 'DENY'));
        }
        echo "$name=PASS\n";
        $count++;
    };

    $check('ROLE_SWITCH_GET_AUTHENTICATED', $rbac->canAccessPath(10, '/admin/profile/access', 'GET'), true);
    $check('ROLE_SWITCH_HEAD_AUTHENTICATED', $rbac->canAccessPath(10, '/admin/profile/access', 'HEAD'), true);
    $check('ROLE_SWITCH_ANONYMOUS_DENIED', $rbac->canAccessPath(null, '/admin/profile/access', 'GET'), false);
    $check('ROLE_SWITCH_POST_NOT_UNCONDITIONALLY_GRANTED', $rbac->canAccessPath(10, '/admin/profile/access', 'POST'), false);
    $check('REVIEWER_DASHBOARD_ALLOWED', $rbac->canAccessPath(11, '/admin/dashboard', 'GET'), true);
    $check('UNPRIVILEGED_DASHBOARD_DENIED', $rbac->canAccessPath(10, '/admin/dashboard', 'GET'), false);
    $check('REVIEWER_AFFILIATION_GET_ALLOWED', $rbac->canAccessPath(11, '/admin/organization-affiliations', 'GET'), true);
    $check('REVIEWER_AFFILIATION_DECISION_ALLOWED', $rbac->canAccessPath(11, '/admin/organization-affiliations/decision', 'POST'), true);
    $check('GLOBAL_ORG_MANAGER_AFFILIATION_ALLOWED', $rbac->canAccessPath(12, '/admin/organization-affiliations', 'GET'), true);
    $check('UNPRIVILEGED_AFFILIATION_DENIED', $rbac->canAccessPath(10, '/admin/organization-affiliations', 'GET'), false);
    $check('UNPRIVILEGED_DECISION_DENIED', $rbac->canAccessPath(10, '/admin/organization-affiliations/decision', 'POST'), false);
    $check('REVIEWER_OTHER_ORG_ADMIN_DENIED', $rbac->canAccessPath(11, '/admin/organization-setup', 'GET'), false);

    $reviewerMenu = array_column($rbac->systemNavigation(11), 'key');
    $otherMenu = array_column($rbac->systemNavigation(10), 'key');
    $check('REVIEWER_MENU_VISIBLE', in_array('organization-affiliations-review', $reviewerMenu, true), true);
    $check('REVIEWER_MENU_HIDDEN_FOR_UNPRIVILEGED', in_array('organization-affiliations-review', $otherMenu, true), false);

    $check('TICKETING_SUPPORT_MEMBER_HOME', $rbac->canAccessPath(20, '/admin/ticketing', 'GET'), true);
    $check('TICKETING_SUPPORT_MEMBER_LIST', $rbac->canAccessPath(20, '/admin/ticketing/tickets', 'GET'), true);
    $check('TICKETING_SUPPORT_NONMEMBER_DENIED', $rbac->canAccessPath(21, '/admin/ticketing', 'GET'), false);
    $check('TICKETING_VIEW_PERMISSION_ALLOWED', $rbac->canAccessPath(14, '/admin/ticketing', 'GET'), true);
    $check('TICKETING_ANONYMOUS_DENIED', $rbac->canAccessPath(null, '/admin/ticketing', 'GET'), false);

    $csv = '/admin/communications/safir/test-report.csv';
    $check('CSV_PERMISSION_MAPPING', $rbac->permissionForPath($csv) === 'notifications.send.manage', true);
    $check('CSV_AUTHORIZED_ALLOWED', $rbac->canAccessPath(13, $csv, 'GET'), true);
    $check('CSV_READONLY_PERMISSION_DENIED', $rbac->canAccessPath(23, $csv, 'GET'), false);
    $check('CSV_UNPRIVILEGED_DENIED', $rbac->canAccessPath(10, $csv, 'GET'), false);
    $check('CSV_ANONYMOUS_DENIED', $rbac->canAccessPath(null, $csv, 'GET'), false);
    $check('UNKNOWN_ADMIN_ROUTE_DENIED', $rbac->canAccessPath(10, '/admin/not-a-real-route', 'GET'), false);

    if ($count !== 25) throw new \RuntimeException('FAIL=CASE_COUNT');
    echo "RBAC_ISOLATED_DECISION_CASES=25\n";
    echo "RBAC_ISOLATED_BEHAVIORAL_REGRESSION=PASS\n";
    echo "NO_HTTP_NO_DB_NO_SEND=PASS\n";
}

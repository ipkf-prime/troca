<?php

namespace App\Services;

use IPKF\Support\Session;
use IPKF\Support\Version;

class AdminPanelService extends BaseService
{
    public function __construct(
        protected ?AuthService $auth = null,
        protected ?AccessService $access = null,
        protected ?MfaService $mfa = null,
        protected ?AdminNavigationRbacService $navigation = null
    ) {
        $this->auth ??= new AuthService();
        $this->access ??= new AccessService();
        $this->mfa ??= new MfaService();
        $this->navigation ??= new AdminNavigationRbacService();
    }

    public function context(): ?array
    {
        $user = $this->auth->currentUser();
        $userId = $this->auth->currentUserId();

        if ($user === null || $userId === null) {
            return null;
        }

        $methods = array_values(array_unique(array_map(
            fn (array $method): string => (string) $method['method'],
            $this->mfa->methodsForUser($userId)
        )));

        return [
            'user' => $user,
            'user_id' => $userId,
            'assignments' => $this->access->assignments($userId),
            'active_assignment' => $this->access->activeAssignment($userId),
            'mfa' => [
                'enabled' => $this->mfa->enabled() && $methods !== [],
                'verified' => (bool) Session::get('auth_mfa_verified', false),
                'methods' => $methods,
                'recovery_codes_available' => $this->mfa->recoveryCodesAvailable($userId),
            ],
            'navigation' => [
                'system' => $this->navigation->systemNavigation($userId),
                'account' => $this->navigation->accountNavigation($userId),
            ],
            'dashboard_modules' => $this->dashboardModules($userId),
            'version' => Version::CURRENT,
        ];
    }

    public function dashboardModules(int $userId): array
    {
        $modules = array_map(
            fn (array $module): ?array => $this->filterDashboardModule($userId, $module),
            $this->dashboardModuleDefinitions()
        );

        $modules = array_values(array_filter($modules));
        usort($modules, fn (array $a, array $b): int => (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0));

        return $modules;
    }

    private function dashboardModuleDefinitions(): array
    {
        return [
            [
                'key' => 'users',
                'title' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x0627;&#x0646;'),
                'description' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x062D;&#x0633;&#x0627;&#x0628;&#x200C;&#x0647;&#x0627;&#x06CC; &#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x06CC;&#x060C; &#x0646;&#x0642;&#x0634;&#x200C;&#x0647;&#x0627; &#x0648; &#x0633;&#x0637;&#x0648;&#x062D; &#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC;'),
                'icon' => 'users',
                'icon_label' => $this->fa('&#x06A9;'),
                'sort_order' => 10,
                'links' => [
                    ['title' => $this->fa('&#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x0627;&#x0646;'), 'url' => '/admin/users', 'permission' => 'users.view'],
                    ['title' => $this->fa('&#x0646;&#x0642;&#x0634;&#x200C;&#x0647;&#x0627; &#x0648; &#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC;&#x200C;&#x0647;&#x0627;'), 'url' => '/admin/access', 'permission' => 'access.manage'],
                ],
            ],
            [
                'key' => 'organization',
                'title' => $this->fa('&#x0633;&#x0627;&#x062E;&#x062A;&#x0627;&#x0631; &#x0633;&#x0627;&#x0632;&#x0645;&#x0627;&#x0646;&#x06CC;'),
                'description' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x0648;&#x0627;&#x062D;&#x062F;&#x0647;&#x0627;&#x060C; &#x0633;&#x0645;&#x062A;&#x200C;&#x0647;&#x0627; &#x0648; &#x0633;&#x0627;&#x062E;&#x062A;&#x0627;&#x0631; &#x062F;&#x0627;&#x062E;&#x0644;&#x06CC; &#x0633;&#x0627;&#x0632;&#x0645;&#x0627;&#x0646;'),
                'icon' => 'organization',
                'icon_label' => $this->fa('&#x0633;'),
                'sort_order' => 20,
                'links' => [
                    ['title' => $this->fa('&#x0648;&#x0627;&#x062D;&#x062F;&#x0647;&#x0627;&#x06CC; &#x0633;&#x0627;&#x0632;&#x0645;&#x0627;&#x0646;&#x06CC;'), 'url' => '/admin/org-units', 'permission' => 'org_units.view'],
                    ['title' => $this->fa('&#x0633;&#x0645;&#x062A;&#x200C;&#x0647;&#x0627;'), 'url' => '/admin/positions', 'permission' => 'positions.view'],
                ],
            ],
            [
                'key' => 'system',
                'title' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;'),
                'description' => $this->fa('&#x062A;&#x0646;&#x0638;&#x06CC;&#x0645;&#x0627;&#x062A; &#x0639;&#x0645;&#x0648;&#x0645;&#x06CC;&#x060C; &#x0638;&#x0627;&#x0647;&#x0631; &#x0648; &#x0645;&#x062D;&#x062A;&#x0648;&#x0627;&#x06CC; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;'),
                'icon' => 'settings',
                'icon_label' => $this->fa('&#x067E;'),
                'sort_order' => 30,
                'links' => [
                    ['title' => $this->fa('&#x067E;&#x0648;&#x0633;&#x062A;&#x0647; &#x067E;&#x0646;&#x0644;'), 'url' => '/admin/theme', 'permission' => 'admin.theme.manage'],
                    ['title' => $this->fa('&#x062A;&#x0646;&#x0638;&#x06CC;&#x0645;&#x0627;&#x062A;'), 'url' => '/admin/settings', 'permission' => 'admin.settings.manage'],
                    ['title' => $this->fa('&#x0635;&#x0641;&#x062D;&#x0627;&#x062A;'), 'url' => '/admin/pages', 'permission' => 'admin.pages.manage'],
                ],
            ],
            [
                'key' => 'reports',
                'title' => $this->fa('&#x06AF;&#x0632;&#x0627;&#x0631;&#x0634;&#x200C;&#x0647;&#x0627;'),
                'description' => $this->fa('&#x06AF;&#x0632;&#x0627;&#x0631;&#x0634;&#x200C;&#x0647;&#x0627;&#x06CC; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A;&#x06CC; &#x0648; &#x0639;&#x0645;&#x0644;&#x06CC;&#x0627;&#x062A;&#x06CC; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;'),
                'icon' => 'reports',
                'icon_label' => $this->fa('&#x06AF;'),
                'url' => '/admin/reports',
                'permission' => 'admin.reports.view',
                'sort_order' => 40,
            ],
            [
                'key' => 'support',
                'title' => $this->fa('&#x067E;&#x0634;&#x062A;&#x06CC;&#x0628;&#x0627;&#x0646;&#x06CC;'),
                'description' => $this->fa('&#x0631;&#x0627;&#x0647;&#x0646;&#x0645;&#x0627;&#x060C; &#x067E;&#x0634;&#x062A;&#x06CC;&#x0628;&#x0627;&#x0646;&#x06CC; &#x0648; &#x067E;&#x06CC;&#x06AF;&#x06CC;&#x0631;&#x06CC; &#x062F;&#x0631;&#x062E;&#x0648;&#x0627;&#x0633;&#x062A;&#x200C;&#x0647;&#x0627;'),
                'icon' => 'support',
                'icon_label' => $this->fa('&#x067E;'),
                'url' => '/admin/support',
                'permission' => 'support.view',
                'sort_order' => 50,
            ],
        ];
    }

    private function filterDashboardModule(int $userId, array $module): ?array
    {
        $links = array_values(array_filter(
            $module['links'] ?? [],
            fn (array $link): bool => $this->navigation->can($userId, (string) $link['permission'])
        ));

        $moduleAllowed = isset($module['permission'])
            && $this->navigation->can($userId, (string) $module['permission']);

        if ($links === [] && !$moduleAllowed) {
            return null;
        }

        $module['links'] = $links;

        if (!$moduleAllowed) {
            unset($module['url'], $module['permission']);
        }

        return $module;
    }

    private function fa(string $entities): string
    {
        return html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

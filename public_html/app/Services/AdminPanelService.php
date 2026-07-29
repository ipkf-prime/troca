<?php

namespace App\Services;

use IPKF\Support\Session;
use IPKF\Support\Version;
use IPKF\Support\ApplicationUrlRegistry;

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

        $urls = new ApplicationUrlRegistry();
        $automationShell = $urls->isAutomationHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $workShell = $urls->isWorkHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $moduleShell = $automationShell || $workShell;
        $moduleShellContext = null;

        if ($workShell) {
            $moduleShellContext = AdminModuleUiContract::shell(
                'work',
                'IPKF Work Management',
                $this->fa('&#x067E;&#x0631;&#x0648;&#x0698;&#x0647;&#x200C;&#x0647;&#x0627;&#x060C; &#x06A9;&#x0627;&#x0631;&#x0647;&#x0627; &#x0648; &#x062A;&#x0633;&#x06A9;&#x200C;&#x0647;&#x0627;'),
                '/admin/work',
                $urls->core('/admin/dashboard')
            );
        }

        if ($automationShell) {
            $moduleShellContext = AdminModuleUiContract::shell(
                'automation',
                $this->fa('&#x0627;&#x062A;&#x0648;&#x0645;&#x0627;&#x0633;&#x06CC;&#x0648;&#x0646; &#x0627;&#x062F;&#x0627;&#x0631;&#x06CC; &#x062A;&#x0631;&#x0648;&#x06A9;&#x0627;'),
                $this->fa('&#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0627;&#x062A; &#x0648; &#x062F;&#x0628;&#x06CC;&#x0631;&#x062E;&#x0627;&#x0646;&#x0647;'),
                '/admin/automation',
                $urls->core('/admin/dashboard'),
                ['css' => ['/assets/admin/css/automation.css']]
            );
        }

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
                'system' => $automationShell ? $this->automationNavigation($userId) : ($workShell ? $this->workNavigation($userId) : $this->moduleNavigation($userId)),
                'account' => $moduleShell ? [
                    ['key' => 'core-panel', 'title' => $this->fa('&#x0628;&#x0627;&#x0632;&#x06AF;&#x0634;&#x062A; &#x0628;&#x0647; &#x067E;&#x0646;&#x0644; &#x0627;&#x0635;&#x0644;&#x06CC;'), 'url' => $urls->core('/admin/dashboard'), 'permission' => null],
                    ['key' => 'logout', 'title' => $this->fa('&#x062E;&#x0631;&#x0648;&#x062C;'), 'url' => '/admin/logout', 'permission' => null],
                ] : $this->navigation->accountNavigation($userId),
            ],
            'module_shell' => $moduleShellContext,
            'dashboard_modules' => $this->dashboardModules($userId),
            'version' => Version::CURRENT,
        ];
    }

    public function automationNavigation(int $userId): array
    {
        $items = [
            ['key' => 'automation-dashboard', 'title' => $this->fa('&#x062F;&#x0627;&#x0634;&#x0628;&#x0648;&#x0631;&#x062F; &#x0627;&#x062A;&#x0648;&#x0645;&#x0627;&#x0633;&#x06CC;&#x0648;&#x0646;'), 'url' => '/admin/automation', 'icon' => 'dashboard', 'permission' => 'automation.correspondence.view', 'active_paths' => ['/admin/automation']],
            ['key' => 'automation-correspondences', 'title' => $this->fa('&#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0627;&#x062A;'), 'url' => '/admin/automation/correspondences', 'icon' => 'file-lines', 'permission' => 'automation.correspondence.view', 'active_paths' => ['/admin/automation/correspondences']],
            ['key' => 'automation-create', 'title' => $this->fa('&#x0627;&#x06CC;&#x062C;&#x0627;&#x062F; &#x067E;&#x06CC;&#x0634;&#x200C;&#x0646;&#x0648;&#x06CC;&#x0633;'), 'url' => '/admin/automation/correspondences/create', 'icon' => 'circle-check', 'permission' => 'automation.correspondence.create', 'active_paths' => ['/admin/automation/correspondences/create']],
            ['key' => 'automation-templates', 'title' => $this->fa('&#x0642;&#x0627;&#x0644;&#x0628;&#x200C;&#x0647;&#x0627;&#x06CC; &#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0647;'), 'url' => '/admin/automation/templates', 'icon' => 'palette', 'permission' => 'automation.correspondence.view', 'active_paths' => ['/admin/automation/templates']],
        ];

        return array_values(array_filter($items, fn (array $item): bool => $this->navigation->can($userId, (string) $item['permission'])));
    }

    public function workNavigation(int $userId): array
    {
        $items = [
            ['key' => 'work-dashboard', 'title' => $this->fa('&#x062F;&#x0627;&#x0634;&#x0628;&#x0648;&#x0631;&#x062F; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x06A9;&#x0627;&#x0631;'), 'url' => '/admin/work', 'icon' => 'dashboard', 'permission' => 'work.project.view', 'active_paths' => ['/admin/work']],
        ];

        return array_values(array_filter($items, fn (array $item): bool => $this->navigation->can($userId, (string) $item['permission'])));
    }

    public function moduleNavigation(int $userId): array
    {
        $items = [];

        if ($this->navigation->can($userId, 'admin.dashboard.view')) {
            $items[] = [
                'key' => 'dashboard',
                'title' => $this->fa('&#x062F;&#x0627;&#x0634;&#x0628;&#x0648;&#x0631;&#x062F;'),
                'url' => '/admin/dashboard',
                'icon' => 'dashboard',
                'sort_order' => 0,
                'active_paths' => ['/admin/dashboard'],
            ];
        }

        foreach ($this->moduleDefinitions() as $module) {
            $navItem = $this->resolveModuleNavigationItem($userId, $module);

            if ($navItem !== null) {
                $items[] = $navItem;
            }
        }

        usort($items, fn (array $a, array $b): int => (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0));

        return $items;
    }

    public function dashboardModules(int $userId): array
    {
        $modules = array_map(
            fn (array $module): ?array => $this->resolveDashboardModule($userId, $module),
            $this->moduleDefinitions()
        );

        $modules = array_values(array_filter($modules));
        usort($modules, fn (array $a, array $b): int => (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0));

        return $modules;
    }

    public function moduleHub(int $userId, string $key): ?array
    {
        foreach ($this->moduleDefinitions() as $module) {
            if (($module['key'] ?? '') !== $key) {
                continue;
            }

            $actions = $this->permittedActions($userId, $module);

            if ($actions === []) {
                return null;
            }

            $module['actions'] = $actions;

            return $module;
        }

        return null;
    }

    private function moduleDefinitions(): array
    {
        $definitions = [
            [
                'key' => 'users',
                'title' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x0627;&#x0646;'),
                'description' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x062D;&#x0633;&#x0627;&#x0628;&#x200C;&#x0647;&#x0627;&#x06CC; &#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x06CC;&#x060C; &#x0646;&#x0642;&#x0634;&#x200C;&#x0647;&#x0627; &#x0648; &#x0633;&#x0637;&#x0648;&#x062D; &#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC;'),
                'subtitle' => $this->fa('&#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x0627;&#x0646;&#x060C; &#x0646;&#x0642;&#x0634;&#x200C;&#x0647;&#x0627; &#x0648; &#x0633;&#x0637;&#x0648;&#x062D; &#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC;'),
                'icon' => 'users',
                'color' => 'blue',
                'url' => '/admin/modules/users',
                'sort_order' => 10,
                'actions' => [
                    ['key' => 'users-list', 'title' => $this->fa('&#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x0627;&#x0646;'), 'description' => $this->fa('&#x0645;&#x0634;&#x0627;&#x0647;&#x062F;&#x0647; &#x0648; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x062D;&#x0633;&#x0627;&#x0628;&#x200C;&#x0647;&#x0627;&#x06CC; &#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x06CC;'), 'icon' => 'user-group', 'color' => 'blue', 'url' => '/admin/users', 'permission' => 'users.view', 'sort_order' => 10],
                    ['key' => 'access', 'title' => $this->fa('&#x0646;&#x0642;&#x0634;&#x200C;&#x0647;&#x0627; &#x0648; &#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC;&#x200C;&#x0647;&#x0627;'), 'description' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x0646;&#x0642;&#x0634;&#x200C;&#x0647;&#x0627;&#x060C; &#x0645;&#x062C;&#x0648;&#x0632;&#x0647;&#x0627; &#x0648; &#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC;&#x200C;&#x0647;&#x0627;'), 'icon' => 'user-shield', 'color' => 'indigo', 'url' => '/admin/access', 'permission' => 'access.manage', 'sort_order' => 20],
                ],
            ],
            [
                'key' => 'organization',
                'title' => $this->fa('&#x0633;&#x0627;&#x062E;&#x062A;&#x0627;&#x0631; &#x0633;&#x0627;&#x0632;&#x0645;&#x0627;&#x0646;&#x06CC;'),
                'description' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x0648;&#x0627;&#x062D;&#x062F;&#x0647;&#x0627;&#x060C; &#x0633;&#x0645;&#x062A;&#x200C;&#x0647;&#x0627; &#x0648; &#x0633;&#x0627;&#x062E;&#x062A;&#x0627;&#x0631; &#x062F;&#x0627;&#x062E;&#x0644;&#x06CC; &#x0633;&#x0627;&#x0632;&#x0645;&#x0627;&#x0646;'),
                'subtitle' => $this->fa('&#x0648;&#x0627;&#x062D;&#x062F;&#x0647;&#x0627;&#x060C; &#x0633;&#x0645;&#x062A;&#x200C;&#x0647;&#x0627; &#x0648; &#x0633;&#x0627;&#x062E;&#x062A;&#x0627;&#x0631; &#x062F;&#x0627;&#x062E;&#x0644;&#x06CC;'),
                'icon' => 'organization',
                'color' => 'teal',
                'url' => '/admin/modules/organization',
                'sort_order' => 20,
                'actions' => [
                    ['key' => 'organization-setup', 'title' => 'راه‌اندازی ساختار', 'description' => 'ثبت سازمان، واحد، پست و اتصال کاربر به شخص', 'icon' => 'building', 'color' => 'teal', 'url' => '/admin/organization-setup', 'permission' => 'organizations.manage', 'sort_order' => 10],
                    ['key' => 'organization-chart', 'title' => 'چارت سازمانی', 'description' => 'نمایش سلسله‌مراتب واحدها، پست‌ها و متصدیان', 'icon' => 'organization', 'color' => 'teal', 'url' => '/admin/organization-chart', 'permission' => 'org_units.manage', 'sort_order' => 20],
                    ['key' => 'appointments', 'title' => 'پست و انتصاب', 'description' => 'ثبت و مدیریت انتصاب اشخاص در جایگاه‌های سازمانی', 'icon' => 'id-badge', 'color' => 'cyan', 'url' => '/admin/appointments', 'permission' => 'appointments.manage', 'sort_order' => 30],
                    ['key' => 'org-units', 'title' => $this->fa('&#x0641;&#x0647;&#x0631;&#x0633;&#x062A; &#x0648;&#x0627;&#x062D;&#x062F;&#x0647;&#x0627;'), 'description' => $this->fa('&#x0645;&#x0634;&#x0627;&#x0647;&#x062F;&#x0647; &#x0648;&#x0627;&#x062D;&#x062F;&#x0647;&#x0627; &#x0648; &#x0633;&#x0644;&#x0633;&#x0644;&#x0647;&#x200C;&#x0645;&#x0631;&#x0627;&#x062A;&#x0628;'), 'icon' => 'building', 'color' => 'teal', 'url' => '/admin/org-units', 'permission' => 'org_units.view', 'sort_order' => 40],
                    ['key' => 'positions', 'title' => $this->fa('&#x0641;&#x0647;&#x0631;&#x0633;&#x062A; &#x0639;&#x0646;&#x0627;&#x0648;&#x06CC;&#x0646; &#x067E;&#x0633;&#x062A;'), 'description' => $this->fa('&#x0645;&#x0634;&#x0627;&#x0647;&#x062F;&#x0647; &#x0639;&#x0646;&#x0627;&#x0648;&#x06CC;&#x0646; &#x067E;&#x0627;&#x06CC;&#x0647; &#x067E;&#x0633;&#x062A;&#x200C;&#x0647;&#x0627;'), 'icon' => 'id-badge', 'color' => 'cyan', 'url' => '/admin/positions', 'permission' => 'positions.view', 'sort_order' => 50],
                ],
            ],
            [
                'key' => 'system',
                'title' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;'),
                'description' => $this->fa('&#x062A;&#x0646;&#x0638;&#x06CC;&#x0645;&#x0627;&#x062A; &#x0639;&#x0645;&#x0648;&#x0645;&#x06CC;&#x060C; &#x0638;&#x0627;&#x0647;&#x0631; &#x0648; &#x0645;&#x062D;&#x062A;&#x0648;&#x0627;&#x06CC; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;'),
                'subtitle' => $this->fa('&#x062A;&#x0646;&#x0638;&#x06CC;&#x0645;&#x0627;&#x062A;&#x060C; &#x0638;&#x0627;&#x0647;&#x0631; &#x0648; &#x0645;&#x062D;&#x062A;&#x0648;&#x0627;&#x06CC; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;'),
                'icon' => 'system',
                'color' => 'purple',
                'url' => '/admin/modules/system',
                'sort_order' => 30,
                'actions' => [
                    ['key' => 'theme', 'title' => $this->fa('&#x067E;&#x0648;&#x0633;&#x062A;&#x0647; &#x067E;&#x0646;&#x0644;'), 'description' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x0638;&#x0627;&#x0647;&#x0631; &#x0648; &#x0647;&#x0648;&#x06CC;&#x062A; &#x0628;&#x0635;&#x0631;&#x06CC; &#x067E;&#x0646;&#x0644;'), 'icon' => 'palette', 'color' => 'purple', 'url' => '/admin/theme', 'permission' => 'admin.theme.manage', 'sort_order' => 10],
                    ['key' => 'settings', 'title' => $this->fa('&#x062A;&#x0646;&#x0638;&#x06CC;&#x0645;&#x0627;&#x062A;'), 'description' => $this->fa('&#x062A;&#x0646;&#x0638;&#x06CC;&#x0645;&#x0627;&#x062A; &#x0639;&#x0645;&#x0648;&#x0645;&#x06CC; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;'), 'icon' => 'sliders', 'color' => 'violet', 'url' => '/admin/settings', 'permission' => 'admin.settings.manage', 'sort_order' => 20],
                    ['key' => 'pages', 'title' => $this->fa('&#x0635;&#x0641;&#x062D;&#x0627;&#x062A;'), 'description' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x0635;&#x0641;&#x062D;&#x0627;&#x062A; &#x0648; &#x0645;&#x062D;&#x062A;&#x0648;&#x0627;&#x06CC; &#x0639;&#x0645;&#x0648;&#x0645;&#x06CC;'), 'icon' => 'file-lines', 'color' => 'fuchsia', 'url' => '/admin/pages', 'permission' => 'admin.pages.manage', 'sort_order' => 30],
                ],
            ],
            [
                'key' => 'work',
                'title' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x06A9;&#x0627;&#x0631;'),
                'description' => $this->fa('&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x067E;&#x0631;&#x0648;&#x0698;&#x0647;&#x200C;&#x0647;&#x0627;&#x060C; Work&#x0647;&#x0627;&#x060C; &#x062A;&#x0633;&#x06A9;&#x200C;&#x0647;&#x0627; &#x0648; &#x067E;&#x06CC;&#x06AF;&#x06CC;&#x0631;&#x06CC; &#x062A;&#x06CC;&#x0645;'),
                'subtitle' => $this->fa('&#x067E;&#x0631;&#x0648;&#x0698;&#x0647;&#x060C; Work &#x0648; &#x062A;&#x0633;&#x06A9;'),
                'icon' => 'circle-check',
                'color' => 'green',
                'url' => '/admin/work',
                'permission' => 'work.project.view',
                'sort_order' => 34,
                'actions' => [
                    ['key' => 'work-dashboard', 'title' => $this->fa('&#x062F;&#x0627;&#x0634;&#x0628;&#x0648;&#x0631;&#x062F; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x06A9;&#x0627;&#x0631;'), 'description' => $this->fa('&#x0646;&#x0645;&#x0627;&#x06CC; &#x06A9;&#x0644;&#x06CC; &#x067E;&#x0631;&#x0648;&#x0698;&#x0647;&#x200C;&#x0647;&#x0627; &#x0648; &#x062A;&#x0633;&#x06A9;&#x200C;&#x0647;&#x0627;'), 'icon' => 'dashboard', 'color' => 'green', 'url' => '/admin/work', 'permission' => 'work.project.view', 'sort_order' => 10],
                ],
            ],
            [
                'key' => 'automation',
                'title' => $this->fa('&#x0627;&#x062A;&#x0648;&#x0645;&#x0627;&#x0633;&#x06CC;&#x0648;&#x0646; &#x0627;&#x062F;&#x0627;&#x0631;&#x06CC;'),
                'description' => $this->fa('&#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0627;&#x062A; &#x0627;&#x062F;&#x0627;&#x0631;&#x06CC;&#x060C; &#x067E;&#x06CC;&#x0634; &#x0646;&#x0648;&#x06CC;&#x0633;&#x200C;&#x0647;&#x0627;&#x060C; &#x0646;&#x0633;&#x062E;&#x0647;&#x200C;&#x0647;&#x0627;&#x060C; &#x0637;&#x0631;&#x0641;&#x200C;&#x0647;&#x0627; &#x0648; &#x062A;&#x0627;&#x0631;&#x06CC;&#x062E;&#x0686;&#x0647; &#x0631;&#x0648;&#x06CC;&#x062F;&#x0627;&#x062F;&#x0647;&#x0627;'),
                'subtitle' => $this->fa('&#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0627;&#x062A;&#x060C; &#x0646;&#x0633;&#x062E;&#x0647;&#x200C;&#x0647;&#x0627; &#x0648; &#x0637;&#x0631;&#x0641;&#x200C;&#x0647;&#x0627;&#x06CC; &#x0646;&#x0627;&#x0645;&#x0647;'),
                'icon' => 'file-lines',
                'color' => 'indigo',
                'url' => '/admin/automation',
                'permission' => 'automation.correspondence.view',
                'sort_order' => 35,
                'actions' => [
                    ['key' => 'automation-dashboard', 'title' => $this->fa('&#x062F;&#x0627;&#x0634;&#x0628;&#x0648;&#x0631;&#x062F; &#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0627;&#x062A;'), 'description' => $this->fa('&#x0646;&#x0645;&#x0627;&#x06CC; &#x06A9;&#x0644;&#x06CC; &#x067E;&#x06CC;&#x0634; &#x0646;&#x0648;&#x06CC;&#x0633;&#x200C;&#x0647;&#x0627; &#x0648; &#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0627;&#x062A; &#x0627;&#x062E;&#x06CC;&#x0631;'), 'icon' => 'dashboard', 'color' => 'indigo', 'url' => '/admin/automation', 'permission' => 'automation.correspondence.view', 'sort_order' => 10],
                    ['key' => 'automation-correspondences', 'title' => $this->fa('&#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0627;&#x062A;'), 'description' => $this->fa('&#x062C;&#x0633;&#x062A;&#x062C;&#x0648;&#x060C; &#x0641;&#x06CC;&#x0644;&#x062A;&#x0631; &#x0648; &#x0645;&#x0634;&#x0627;&#x0647;&#x062F;&#x0647; &#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0627;&#x062A;'), 'icon' => 'file-lines', 'color' => 'blue', 'url' => '/admin/automation/correspondences', 'permission' => 'automation.correspondence.view', 'sort_order' => 20],
                    ['key' => 'automation-create', 'title' => $this->fa('&#x0627;&#x06CC;&#x062C;&#x0627;&#x062F; &#x067E;&#x06CC;&#x0634; &#x0646;&#x0648;&#x06CC;&#x0633;'), 'description' => $this->fa('&#x062B;&#x0628;&#x062A; &#x0645;&#x06A9;&#x0627;&#x062A;&#x0628;&#x0647; &#x0648; &#x0646;&#x0633;&#x062E;&#x0647; &#x0627;&#x0648;&#x0644;'), 'icon' => 'circle-check', 'color' => 'green', 'url' => '/admin/automation/correspondences/create', 'permission' => 'automation.correspondence.create', 'sort_order' => 30],
                ],
            ],
            [
                'key' => 'reports',
                'title' => $this->fa('&#x06AF;&#x0632;&#x0627;&#x0631;&#x0634;&#x200C;&#x0647;&#x0627;'),
                'description' => $this->fa('&#x06AF;&#x0632;&#x0627;&#x0631;&#x0634;&#x200C;&#x0647;&#x0627;&#x06CC; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A;&#x06CC; &#x0648; &#x0639;&#x0645;&#x0644;&#x06CC;&#x0627;&#x062A;&#x06CC; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;'),
                'subtitle' => $this->fa('&#x06AF;&#x0632;&#x0627;&#x0631;&#x0634;&#x200C;&#x0647;&#x0627;&#x06CC; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A;&#x06CC; &#x0648; &#x0639;&#x0645;&#x0644;&#x06CC;&#x0627;&#x062A;&#x06CC;'),
                'icon' => 'reports',
                'color' => 'amber',
                'url' => '/admin/reports',
                'permission' => 'admin.reports.view',
                'sort_order' => 40,
            ],
            [
                'key' => 'support',
                'title' => $this->fa('&#x067E;&#x0634;&#x062A;&#x06CC;&#x0628;&#x0627;&#x0646;&#x06CC;'),
                'description' => $this->fa('&#x0631;&#x0627;&#x0647;&#x0646;&#x0645;&#x0627;&#x060C; &#x067E;&#x0634;&#x062A;&#x06CC;&#x0628;&#x0627;&#x0646;&#x06CC; &#x0648; &#x067E;&#x06CC;&#x06AF;&#x06CC;&#x0631;&#x06CC; &#x062F;&#x0631;&#x062E;&#x0648;&#x0627;&#x0633;&#x062A;&#x200C;&#x0647;&#x0627;'),
                'subtitle' => $this->fa('&#x0631;&#x0627;&#x0647;&#x0646;&#x0645;&#x0627; &#x0648; &#x067E;&#x06CC;&#x06AF;&#x06CC;&#x0631;&#x06CC; &#x062F;&#x0631;&#x062E;&#x0648;&#x0627;&#x0633;&#x062A;&#x200C;&#x0647;&#x0627;'),
                'icon' => 'support',
                'color' => 'rose',
                'url' => '/admin/support',
                'permission' => 'support.view',
                'sort_order' => 50,
            ],
        ];

        $urls = new ApplicationUrlRegistry();
        foreach ($definitions as &$module) {
            $moduleKey = (string) ($module['key'] ?? '');
            $qualify = match ($moduleKey) {
                'automation' => fn (string $path): string => $urls->automationLaunch($path),
                'work' => fn (string $path): string => $urls->workLaunch($path),
                default => fn (string $path): string => $urls->core($path),
            };
            $module['url'] = $qualify((string) ($module['url'] ?? '/'));
            foreach ($module['actions'] ?? [] as &$action) {
                $action['url'] = $qualify((string) ($action['url'] ?? '/'));
            }
            unset($action);
        }
        unset($module);

        return $definitions;
    }

    private function resolveDashboardModule(int $userId, array $module): ?array
    {
        $actions = $this->permittedActions($userId, $module);
        $moduleAllowed = isset($module['permission'])
            && $this->navigation->can($userId, (string) $module['permission']);

        if ($actions === [] && !$moduleAllowed) {
            return null;
        }

        unset($module['actions'], $module['permission']);

        return $module;
    }

    private function resolveModuleNavigationItem(int $userId, array $module): ?array
    {
        $resolved = $this->resolveDashboardModule($userId, $module);

        if ($resolved === null) {
            return null;
        }

        $activePaths = [(string) (parse_url((string) ($resolved['url'] ?? '#'), PHP_URL_PATH) ?: '#')];

        foreach ($this->permittedActions($userId, $module) as $action) {
            if (($action['url'] ?? '') !== '') {
                $actionPath = (string) (parse_url((string) $action['url'], PHP_URL_PATH) ?: $action['url']);
                $activePaths[] = $actionPath;

                if ($actionPath === '/admin/users') {
                    $activePaths[] = '/admin/users/*';
                }

                if (str_starts_with($actionPath, '/admin/automation')) {
                    $activePaths[] = '/admin/automation/*';
                }
            }
        }

        return [
            'key' => $resolved['key'] ?? '',
            'title' => $resolved['title'] ?? '',
            'url' => $resolved['url'] ?? '#',
            'icon' => $resolved['icon'] ?? 'dashboard',
            'color' => $resolved['color'] ?? 'blue',
            'sort_order' => $resolved['sort_order'] ?? 0,
            'active_paths' => array_values(array_unique($activePaths)),
        ];
    }

    private function permittedActions(int $userId, array $module): array
    {
        $links = array_values(array_filter(
            $module['actions'] ?? [],
            fn (array $link): bool => $this->navigation->can($userId, (string) $link['permission'])
        ));

        usort($links, fn (array $a, array $b): int => (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0));

        return $links;
    }

    private function fa(string $entities): string
    {
        return html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

<?php

namespace App\Services;

use IPKF\Support\Env;

class AdminNavigationRbacService extends BaseService
{
    public function __construct(
        protected ?AuthService $auth = null,
        protected ?AuthorizationService $authorization = null
    ) {
        $this->auth ??= new AuthService();
        $this->authorization ??= new AuthorizationService();
    }

    public function routePermissions(): array
    {
        return [
            '/admin/dashboard' => 'admin.dashboard.view',
            '/admin/profile' => 'account.profile.view',
            '/admin/profile/access' => 'account.profile.view',
            '/admin/account' => 'account.profile.view',
            '/admin/security' => 'account.security.view',
            '/admin/password' => 'account.password.change',
            '/admin/my-theme' => 'account.theme.manage',
            '/admin/access' => 'access.manage',
            '/admin/automation' => 'automation.correspondence.view',
            '/admin/automation/secretariat' => 'automation.registry.manage',
            '/admin/automation/secretariat/desks' => 'automation.registry.manage',
            '/admin/automation/secretariat/periods' => 'automation.registry.manage',
            '/admin/automation/secretariat/sequences' => 'automation.registry.manage',
            '/admin/automation/secretariat/books' => 'automation.registry.manage',
            '/admin/automation/secretariat/memberships' => 'automation.registry.manage',
            '/admin/automation/secretariat/memberships/deactivate' => 'automation.registry.manage',
            '/admin/automation/external-organizations' => 'automation.external_directory.manage',
            '/admin/automation/external-organizations/save' => 'automation.external_directory.manage',
            '/admin/automation/external-organizations/quick-create' => 'automation.external_directory.manage',
            '/admin/automation/external-organizations/deactivate' => 'automation.external_directory.manage',
            '/admin/automation/external-organizations/contact-points/save' => 'automation.external_directory.manage',
            '/admin/automation/external-organizations/contact-points/deactivate' => 'automation.external_directory.manage',
            '/admin/automation/external-organizations/contact-methods/save' => 'automation.external_directory.manage',
            '/admin/automation/external-organizations/contact-methods/deactivate' => 'automation.external_directory.manage',
            '/admin/automation/external-organizations/addresses/save' => 'automation.external_directory.manage',
            '/admin/automation/external-organizations/addresses/deactivate' => 'automation.external_directory.manage',
            '/admin/ticketing' => 'ticketing.ticket.view',
            '/admin/work' => 'work.project.view',
            '/admin/work/projects' => 'work.project.view',
            '/admin/work/projects/create' => 'work.project.manage',
            '/admin/work/projects/{public_reference}' => 'work.project.view',
            '/admin/work/projects/{public_reference}/edit' => 'work.project.manage',
            '/admin/work/projects/{public_reference}/members' => 'work.project.manage',
            '/admin/work/projects/{public_reference}/items' => 'work.item.view',
            '/admin/work/projects/{public_reference}/items/create' => 'work.item.create',
            '/admin/work/projects/{public_reference}/items/{item_reference}/edit' => 'work.item.update',
            '/admin/automation/correspondences' => 'automation.correspondence.view',
            '/admin/automation/correspondences/create' => 'automation.correspondence.create',
            '/admin/automation/correspondences/{public_reference}' => 'automation.correspondence.view',
            '/admin/automation/correspondences/{public_reference}/edit' => 'automation.correspondence.edit_draft',
            '/admin/automation/correspondences/{public_reference}/versions' => 'automation.correspondence.edit_draft',
            '/admin/automation/correspondences/{public_reference}/register' => 'automation.correspondence.register',
            '/admin/automation/correspondences/{public_reference}/dispatch' => 'automation.correspondence.dispatch',
            '/admin/automation/correspondences/{public_reference}/edit/attachments' => 'automation.correspondence.edit_draft',
            '/admin/automation/correspondences/{public_reference}/attachments/{file_reference}' => 'automation.correspondence.view',
            '/admin/automation/templates' => 'automation.correspondence.view',
            '/admin/users' => 'users.view',
            '/admin/users/{id}' => 'users.view',
            '/admin/users/{id}/identity' => 'users.view',
            '/admin/users/{id}/contacts' => 'users.view',
            '/admin/users/{id}/account' => 'users.view',
            '/admin/users/{id}/access' => 'users.view',
            '/admin/users/{id}/appointments' => 'users.view',
            '/admin/org-units' => 'org_units.view',
            '/admin/positions' => 'positions.view',
            '/admin/organization-setup' => 'organizations.manage',
            '/admin/organization-chart' => 'org_units.manage',
            '/admin/appointments' => 'appointments.manage',
            '/admin/profile/organizational-context' => 'organizational_context.switch',
            '/admin/theme' => 'admin.theme.manage',
            '/admin/theme/debug' => 'admin.theme.manage',
            '/admin/navigation/debug' => 'admin.navigation.debug',
            '/admin/settings' => 'admin.settings.manage',
            '/admin/pages' => 'admin.pages.manage',
            '/admin/reports' => 'admin.reports.view',
            '/admin/support' => 'support.view',
        ];
    }

    public function permissionForPath(string $path): ?string
    {
        $path = rtrim($path, '/') ?: '/';

        if (isset($this->routePermissions()[$path])) {
            return $this->routePermissions()[$path];
        }

        if (preg_match('#^/admin/users/[1-9][0-9]*$#', $path) === 1) {
            return $this->routePermissions()['/admin/users/{id}'] ?? null;
        }

        if (preg_match('#^/admin/users/[1-9][0-9]*/(identity|contacts|account|access|appointments)$#', $path, $matches) === 1) {
            return $this->routePermissions()['/admin/users/{id}/' . $matches[1]] ?? null;
        }

        return null;
    }

    public function canAccessPath(?int $userId, string $path): bool
    {
        $permission = $this->permissionForPath($path);

        return $permission === null || $this->can($userId, $permission);
    }

    public function can(?int $userId, string $permission): bool
    {
        if ($userId === null) {
            return false;
        }

        return $this->authorization->hasPermission($userId, $permission);
    }

    public function systemNavigation(?int $userId): array
    {
        return $this->filterNavigation($userId, [
            ['key' => 'dashboard', 'title' => $this->fa('&#x062F;&#x0627;&#x0634;&#x0628;&#x0648;&#x0631;&#x062F;'), 'url' => '/admin/dashboard', 'permission' => 'admin.dashboard.view'],
            ['key' => 'access', 'title' => $this->fa('&#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC;&#x200C;&#x0647;&#x0627;'), 'url' => '/admin/access', 'permission' => 'access.manage'],
            ['key' => 'users', 'title' => $this->fa('&#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x0627;&#x0646;'), 'url' => '/admin/users', 'permission' => 'users.view', 'badge' => $this->fa('&#x0628;&#x0647;&#x200C;&#x0632;&#x0648;&#x062F;&#x06CC;')],
            ['key' => 'org-units', 'title' => $this->fa('&#x0648;&#x0627;&#x062D;&#x062F;&#x0647;&#x0627;&#x06CC; &#x0633;&#x0627;&#x0632;&#x0645;&#x0627;&#x0646;&#x06CC;'), 'url' => '/admin/org-units', 'permission' => 'org_units.view', 'badge' => $this->fa('&#x0628;&#x0647;&#x200C;&#x0632;&#x0648;&#x062F;&#x06CC;')],
            ['key' => 'positions', 'title' => $this->fa('&#x0633;&#x0645;&#x062A;&#x200C;&#x0647;&#x0627;'), 'url' => '/admin/positions', 'permission' => 'positions.view'],
            ['key' => 'organization-setup', 'title' => 'راه‌اندازی ساختار', 'url' => '/admin/organization-setup', 'permission' => 'organizations.manage'],
            ['key' => 'organization-chart', 'title' => 'چارت سازمانی', 'url' => '/admin/organization-chart', 'permission' => 'org_units.manage'],
            ['key' => 'appointments', 'title' => 'پست و انتصاب', 'url' => '/admin/appointments', 'permission' => 'appointments.manage'],
            ['key' => 'theme', 'title' => $this->fa('&#x067E;&#x0648;&#x0633;&#x062A;&#x0647; &#x067E;&#x0646;&#x0644;'), 'url' => '/admin/theme', 'permission' => 'admin.theme.manage'],
            ['key' => 'settings', 'title' => $this->fa('&#x062A;&#x0646;&#x0638;&#x06CC;&#x0645;&#x0627;&#x062A;'), 'url' => '/admin/settings', 'permission' => 'admin.settings.manage', 'badge' => $this->fa('&#x0628;&#x0647;&#x200C;&#x0632;&#x0648;&#x062F;&#x06CC;')],
            ['key' => 'pages', 'title' => $this->fa('&#x0635;&#x0641;&#x062D;&#x0627;&#x062A; &#x062F;&#x0627;&#x062E;&#x0644;&#x06CC;'), 'url' => '/admin/pages', 'permission' => 'admin.pages.manage', 'badge' => $this->fa('&#x0628;&#x0647;&#x200C;&#x0632;&#x0648;&#x062F;&#x06CC;')],
            ['key' => 'reports', 'title' => $this->fa('&#x06AF;&#x0632;&#x0627;&#x0631;&#x0634;&#x200C;&#x0647;&#x0627;'), 'url' => '/admin/reports', 'permission' => 'admin.reports.view', 'badge' => $this->fa('&#x0628;&#x0647;&#x200C;&#x0632;&#x0648;&#x062F;&#x06CC;')],
            ['key' => 'support', 'title' => 'راهنما و پشتیبانی سامانه', 'url' => '/admin/support', 'permission' => 'support.view', 'badge' => $this->fa('&#x0628;&#x0647;&#x200C;&#x0632;&#x0648;&#x062F;&#x06CC;')],
        ]);
    }

    public function accountNavigation(?int $userId): array
    {
        return $this->filterNavigation($userId, [
            ['key' => 'profile', 'title' => $this->fa('&#x067E;&#x0631;&#x0648;&#x0641;&#x0627;&#x06CC;&#x0644; &#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;&#x06CC;'), 'url' => '/admin/profile', 'permission' => 'account.profile.view'],
            ['key' => 'account', 'title' => $this->fa('&#x0627;&#x0637;&#x0644;&#x0627;&#x0639;&#x0627;&#x062A; &#x062D;&#x0633;&#x0627;&#x0628;'), 'url' => '/admin/account', 'permission' => 'account.profile.view'],
            ['key' => 'security', 'title' => $this->fa('&#x0627;&#x0645;&#x0646;&#x06CC;&#x062A; &#x0648; &#x0648;&#x0631;&#x0648;&#x062F;'), 'url' => '/admin/security', 'permission' => 'account.security.view'],
            ['key' => 'profile-access', 'title' => 'نقش‌ها و دسترسی‌های من', 'url' => '/admin/profile/access', 'permission' => 'account.profile.view'],
            ['key' => 'organizational-context', 'title' => 'جایگاه سازمانی فعال', 'url' => '/admin/profile/organizational-context', 'permission' => 'organizational_context.switch'],
            ['key' => 'password', 'title' => $this->fa('&#x062A;&#x063A;&#x06CC;&#x06CC;&#x0631; &#x06A9;&#x0644;&#x0645;&#x0647; &#x0639;&#x0628;&#x0648;&#x0631;'), 'url' => '/admin/password', 'permission' => 'account.password.change'],
            ['key' => 'my-theme', 'title' => $this->fa('&#x067E;&#x0648;&#x0633;&#x062A;&#x0647; &#x0646;&#x0645;&#x0627;&#x06CC;&#x0634;&#x06CC; &#x0645;&#x0646;'), 'url' => '/admin/my-theme', 'permission' => 'account.theme.manage'],
            ['key' => 'logout', 'title' => $this->fa('&#x062E;&#x0631;&#x0648;&#x062C;'), 'url' => '/admin/logout', 'permission' => null],
        ]);
    }

    public function debug(?int $userId): array
    {
        $system = $this->navigationDebugRows($userId, [
            ['key' => 'dashboard', 'url' => '/admin/dashboard', 'permission' => 'admin.dashboard.view'],
            ['key' => 'access', 'url' => '/admin/access', 'permission' => 'access.manage'],
            ['key' => 'users', 'url' => '/admin/users', 'permission' => 'users.view'],
            ['key' => 'org-units', 'url' => '/admin/org-units', 'permission' => 'org_units.view'],
            ['key' => 'positions', 'url' => '/admin/positions', 'permission' => 'positions.view'],
            ['key' => 'theme', 'url' => '/admin/theme', 'permission' => 'admin.theme.manage'],
            ['key' => 'settings', 'url' => '/admin/settings', 'permission' => 'admin.settings.manage'],
            ['key' => 'pages', 'url' => '/admin/pages', 'permission' => 'admin.pages.manage'],
            ['key' => 'reports', 'url' => '/admin/reports', 'permission' => 'admin.reports.view'],
            ['key' => 'support', 'url' => '/admin/support', 'permission' => 'support.view'],
        ]);

        return [
            'user_id' => $userId,
            'visible_menu_keys' => array_values(array_map(fn (array $row): string => $row['key'], array_filter($system, fn (array $row): bool => $row['visible']))),
            'hidden_menu' => array_values(array_filter($system, fn (array $row): bool => !$row['visible'])),
            'route_permission_map' => $this->routePermissions(),
        ];
    }

    public function debugRouteAvailable(?int $userId): bool
    {
        return Env::isDebug()
            && $this->can($userId, 'admin.navigation.debug');
    }

    private function filterNavigation(?int $userId, array $items): array
    {
        return array_values(array_filter($items, function (array $item) use ($userId): bool {
            $permission = $item['permission'] ?? null;

            return $permission === null || $this->can($userId, $permission);
        }));
    }

    private function navigationDebugRows(?int $userId, array $items): array
    {
        return array_map(function (array $item) use ($userId): array {
            $visible = $item['permission'] === null || $this->can($userId, $item['permission']);

            return $item + [
                'visible' => $visible,
                'reason' => $visible ? 'allowed' : 'missing_permission',
            ];
        }, $items);
    }

    private function fa(string $entities): string
    {
        return html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

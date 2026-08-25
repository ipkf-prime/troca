<?php

namespace App\Services;

use IPKF\Support\ModuleRuntimeConfig;

use App\Repositories\AdminNavigationRegistryRepository;
use IPKF\Support\ApplicationUrlRegistry;
use Throwable;

class DynamicAdminNavigationService extends BaseService
{
    public function __construct(
        private ?AdminNavigationRegistryRepository $repository = null,
        private ?AuthorizationService $authorization = null,
        private ?NavigationBadgeResolverService $badges = null
    ) {
        $this->repository ??= new AdminNavigationRegistryRepository();
        $this->authorization ??= new AuthorizationService();
        $this->badges ??= new NavigationBadgeResolverService();
    }

    public function navigation(int $userId, string $shellKey): array
    {
        $items = $this->items($shellKey);
        $result = [];

        foreach ($items as $item) {
            if (
                $item['parent_id'] !== null
                || (string) ($item['placement_code'] ?? 'sidebar')
                    !== 'sidebar'
                || !$this->allowed($item, $userId)
            ) {
                continue;
            }

            $presented = $this->present($item, $userId);
            $presented['children'] = $this->childrenByParentId(
                $items,
                $userId,
                (int) $item['id'],
                'sidebar'
            );
            $result[] = $presented;
        }

        return $result;
    }

    public function topbar(int $userId, string $shellKey): array
    {
        $items = $this->items($shellKey);
        $result = [];

        foreach ($items as $item) {
            if (
                $item['parent_id'] !== null
                || (string) ($item['placement_code'] ?? 'sidebar')
                    !== 'topbar'
                || !$this->allowed($item, $userId)
            ) {
                continue;
            }

            $presented = $this->present($item, $userId);

            if (
                !empty($item['hide_when_badge_empty'])
                && ($presented['badge'] ?? '') === ''
            ) {
                continue;
            }

            $result[] = $presented;
        }

        return $result;
    }

    public function account(
        int $userId,
        string $shellKey
    ): array {
        $items = $this->items($shellKey);
        $result = [];

        foreach ($items as $item) {
            if (
                $item['parent_id'] !== null
                || (string) (
                    $item['placement_code'] ?? 'sidebar'
                ) !== 'account'
                || !$this->allowed($item, $userId)
            ) {
                continue;
            }

            $result[] = $this->present(
                $item,
                $userId
            );
        }

        return $result;
    }

    public function children(
        int $userId,
        string $shellKey,
        string $parentKey
    ): array {
        $items = $this->items($shellKey);
        $parentId = null;

        foreach ($items as $item) {
            if ((string) $item['item_key'] === $parentKey) {
                $parentId = (int) $item['id'];
                break;
            }
        }

        return $parentId === null
            ? []
            : $this->childrenByParentId(
                $items,
                $userId,
                $parentId,
                'sidebar'
            );
    }

    private function childrenByParentId(
        array $items,
        int $userId,
        int $parentId,
        string $placement
    ): array {
        $result = [];

        foreach ($items as $item) {
            if (
                (int) ($item['parent_id'] ?? 0) !== $parentId
                || (string) ($item['placement_code'] ?? 'sidebar')
                    !== $placement
                || !$this->allowed($item, $userId)
            ) {
                continue;
            }

            $result[] = $this->present($item, $userId);
        }

        return $result;
    }

    private function items(string $shellKey): array
    {
        try {
            return $this->repository->items($shellKey);
        } catch (Throwable) {
            return [];
        }
    }

    private function present(array $item, int $userId): array
    {
        $badgeSource = trim((string) ($item['badge_source'] ?? ''));

        return [
            'key' => (string) $item['item_key'],
            'title' => (string) $item['title'],
            'description' => (string) ($item['description'] ?? ''),
            'url' => $this->qualifyUrl($item),
            'icon' => (string) ($item['icon_code'] ?? 'dashboard'),
            'color' => (string) ($item['color_code'] ?? ''),
            'sort_order' => (int) ($item['sort_order'] ?? 0),
            'active_paths' => $this->jsonArray(
                $item['active_paths_json'] ?? null
            ),
            'badge' => $badgeSource !== ''
                ? $this->badges->value($badgeSource, $userId)
                : '',
        ];
    }

    private function allowed(array $item, int $userId): bool
    {
        $permissions = $this->jsonArray(
            $item['permission_codes_json'] ?? null
        );

        if ($permissions === []) {
            return true;
        }

        $results = array_map(
            fn (string $permission): bool =>
                $this->authorization->hasPermission(
                    $userId,
                    $permission
                ),
            $permissions
        );

        return ($item['permission_mode'] ?? 'any') === 'all'
            ? !in_array(false, $results, true)
            : in_array(true, $results, true);
    }

    private function qualifyUrl(array $item): string
    {
        $path = (string) ($item['route_path'] ?? '/');
        $urls = new ApplicationUrlRegistry();

        return match ((string) ($item['target_application'] ?? 'core')) {
            'work' => $urls->workLaunch($path),
            'automation' => $urls->automationLaunch($path),
            'ticketing' => $urls->ticketingLaunch($path),
            default => $urls->core($path),
        };
    }

    private function jsonArray(mixed $value): array
    {
        $decoded = is_array($value)
            ? $value
            : json_decode((string) ($value ?? ''), true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', $decoded),
            static fn (string $item): bool => trim($item) !== ''
        ));
    }


    public function sync(): void
    {
        if (
            !Database::tableExists(
                'admin_navigation_items'
            )
            || !Database::tableExists(
                'application_modules'
            )
        ) {
            return;
        }

        $db = Database::connect();

        /*
         * This synchronizer owns ONLY the root navigation row
         * whose item_key equals application_modules.module_key.
         *
         * Child navigation belonging to modules
         * (work-projects, automation-correspondences, ...)
         * must never be modified here.
         */
        $db->exec("
            UPDATE admin_navigation_items
            SET is_active = 0
            WHERE item_key IN
            (
                SELECT module_key
                FROM application_modules
            )
        ");

        $runtime =
            new ModuleRuntimeConfig();

        foreach (
            $runtime->allActive()
            as $module
        ) {
            if (
                (int) (
                    $module['sidebar_enabled']
                    ?? 1
                ) !== 1
            ) {
                continue;
            }

            $moduleKey = trim(
                (string) (
                    $module['module_key']
                    ?? ''
                )
            );

            $route = trim(
                (string) (
                    $module['route_path']
                    ?? ''
                )
            );

            if (
                $moduleKey === ''
                || $route === ''
            ) {
                continue;
            }

            $statement = $db->prepare("
                INSERT INTO admin_navigation_items
                (
                    item_key,
                    title,
                    description,
                    route_path,
                    target_application,
                    icon_code,
                    is_active
                )
                VALUES
                (
                    :key,
                    :title,
                    :description,
                    :route,
                    :application,
                    :icon,
                    1
                )
                ON DUPLICATE KEY UPDATE
                    title =
                        VALUES(title),

                    description =
                        VALUES(description),

                    route_path =
                        VALUES(route_path),

                    target_application =
                        VALUES(target_application),

                    icon_code =
                        VALUES(icon_code),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

            $statement->execute([
                'key' =>
                    $moduleKey,

                'title' =>
                    (string) (
                        $module['display_name']
                        ?? $moduleKey
                    ),

                'description' =>
                    (string) (
                        $module[
                            'dashboard_description'
                        ]
                        ?? ''
                    ),

                'route' =>
                    $route,

                'application' =>
                    $moduleKey,

                'icon' =>
                    (string) (
                        $module['icon_code']
                        ?? 'apps'
                    ),
            ]);
        }
    }
}

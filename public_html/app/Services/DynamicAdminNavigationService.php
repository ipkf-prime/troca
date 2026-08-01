<?php

namespace App\Services;

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
}

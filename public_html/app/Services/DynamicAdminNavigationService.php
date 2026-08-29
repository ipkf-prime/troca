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

        /*
         * REQUESTER_TICKETING_NAVIGATION_RUNTIME
         */
        if (
            $shellKey === 'ticketing'
            &&
            $this->authorization->hasPermission(
                $userId,
                'support.view'
            )
        ) {
            $hasMembership =
                false;

            try {
                $hasMembership =
                    (new \App\Services\Ticketing\TicketRequesterOnboardingService())
                        ->hasMembership(
                            $userId
                        );
            } catch (\Throwable) {
                $hasMembership =
                    false;
            }

            if ($hasMembership) {

                foreach ($items as &$requesterItem) {

                    if (
                        !in_array(
                            (string) (
                                $requesterItem[
                                    'item_key'
                                ]
                                ?? ''
                            ),
                            [
                                'ticketing-my-tickets',
                                'ticketing-create',
                            ],
                            true
                        )
                    ) {
                        continue;
                    }

                    $requesterItem[
                        'permission_mode'
                    ] = 'any';

                    $requesterItem[
                        'permission_codes_json'
                    ] =
                        json_encode(
                            [
                                'ticketing.ticket.view',
                                'support.view',
                            ],
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        );
                }

                unset($requesterItem);
            }
        }

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

        /*
         * Ticketing topbar indicator is runtime metadata.
         *
         * Core Development and Production currently share
         * the same Core database, therefore this item must
         * not be persisted in admin_navigation_items yet.
         *
         * Identity comes from application_modules so the
         * module title, icon, route and permission remain
         * centrally configurable.
         */
        if ($shellKey === 'core') {

            $ticketing =
                (
                    new \IPKF\Support\ModuleRuntimeConfig()
                )->active(
                    'ticketing'
                );

            if (is_array($ticketing)) {

                $permission =
                    trim(
                        (string) (
                            $ticketing[
                                'permission_key'
                            ]
                            ?? ''
                        )
                    );

                $items[] = [
                    'id' => 0,
                    'parent_id' => null,

                    'shell_key' =>
                        'core',

                    'item_key' =>
                        'ticketing-unread-alert',

                    'item_type' =>
                        'link',

                    'placement_code' =>
                        'topbar',

                    'hide_when_badge_empty' =>
                        0,

                    'title' =>
                        trim(
                            (string) (
                                $ticketing[
                                    'display_name'
                                ]
                                ?? 'تیکتینگ'
                            )
                        ),

                    'description' =>
                        'تیکت‌ها و اعلان‌های نیازمند توجه',

                    'route_path' =>
                        trim(
                            (string) (
                                $ticketing[
                                    'route_path'
                                ]
                                ?? '/admin/ticketing'
                            )
                        ),

                    'target_application' =>
                        'ticketing',

                    'icon_code' =>
                        trim(
                            (string) (
                                $ticketing[
                                    'icon_code'
                                ]
                                ?? 'headset'
                            )
                        ),

                    'color_code' =>
                        trim(
                            (string) (
                                $ticketing[
                                    'color_code'
                                ]
                                ?? ''
                            )
                        ),

                    'permission_mode' =>
                        'any',

                    'permission_codes_json' =>
                        $permission !== ''
                            ? json_encode(
                                [$permission],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            )
                            : '[]',

                    'badge_source' =>
                        'ticketing_unread_count',

                    'active_paths_json' =>
                        json_encode(
                            [
                                '/admin/ticketing',
                                '/admin/ticketing/*',
                            ],
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        ),

                    'sort_order' =>
                        13,
                ];
            }
        }

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
            $items =
                $this->repository
                    ->items($shellKey);

            return
                $this->withSystemScheduler(
                    $items,
                    $shellKey
                );

        } catch (Throwable) {
            return [];
        }
    }


    private function withSystemScheduler(
        array $items,
        string $shellKey
    ): array {
        if ($shellKey !== 'core') {
            return $items;
        }

        foreach ($items as $item) {
            if (
                (string) (
                    $item['item_key']
                    ?? ''
                ) === 'system-scheduler'
            ) {
                return $items;
            }
        }

        $parentId = null;

        foreach ($items as $item) {

            $itemKey =
                (string) (
                    $item['item_key']
                    ?? ''
                );

            $title =
                (string) (
                    $item['title']
                    ?? ''
                );

            if (
                $title === 'مدیریت سامانه'
                ||
                in_array(
                    $itemKey,
                    [
                        'system-management',
                        'system-settings',
                        'system',
                        'admin',
                    ],
                    true
                )
            ) {
                $candidate =
                    (int) (
                        $item['id']
                        ?? 0
                    );

                if ($candidate > 0) {
                    $parentId =
                        $candidate;

                    break;
                }
            }
        }

        if ($parentId === null) {
            return $items;
        }

        /*
         * Virtual Core navigation item.
         *
         * We intentionally do not write this row into
         * the shared Core DB while Dev and Production
         * still share that database.
         */
        $items[] = [
            'id' =>
                -900001,

            'parent_id' =>
                $parentId,

            'shell_key' =>
                'core',

            'item_key' =>
                'system-scheduler',

            'item_type' =>
                'link',

            'placement_code' =>
                'sidebar',

            'hide_when_badge_empty' =>
                0,

            'title' =>
                'مدیریت اجرای خودکار',

            'description' =>
                'مدیریت Jobها، Scopeها، زمان‌بندی و تاریخچه اجرا',

            'route_path' =>
                '/admin/system/scheduler',

            'target_application' =>
                'core',

            'icon_code' =>
                'settings',

            'color_code' =>
                null,

            'permission_mode' =>
                'any',

            'permission_codes_json' =>
                json_encode(
                    [
                        'access.manage',
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'badge_source' =>
                null,

            'active_paths_json' =>
                json_encode(
                    [
                        '/admin/system/scheduler',
                        '/admin/system/scheduler/*',
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'sort_order' =>
                45,

            'is_active' =>
                1,
        ];

        return $items;
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

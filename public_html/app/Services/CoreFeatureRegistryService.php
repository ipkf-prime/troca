<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\AdminIcon;
use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Support\ApplicationUrlRegistry;
use PDO;

final class CoreFeatureRegistryService
{
    private PDO $db;

    private AdminNavigationRbacService $navigation;


    public function __construct(
        ?PDO $db = null,
        ?AdminNavigationRbacService $navigation = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve('core.primary');

        $this->navigation =
            $navigation
            ?? new AdminNavigationRbacService();
    }


    public function index(): array
    {
        if (
            !$this->tableExists(
                'admin_navigation_items'
            )
            || !$this->columnExists(
                'admin_navigation_items',
                'dashboard_enabled'
            )
        ) {
            return [
                'available' => false,
                'items' => [],
                'icons' => AdminIcon::codes(),
            ];
        }

        $statement =
            $this->db->query("
                SELECT
                    id,
                    item_key,
                    title,
                    description,
                    route_path,
                    icon_code,
                    color_code,
                    permission_mode,
                    permission_codes_json,
                    sort_order,
                    is_active,
                    dashboard_enabled
                FROM admin_navigation_items
                WHERE shell_key = 'core'
                  AND parent_id IS NULL
                  AND placement_code = 'sidebar'
                  AND target_application = 'core'
                  AND item_key <> 'dashboard'
                ORDER BY sort_order, id
            ");

        $items =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        foreach ($items as &$item) {
            $item['permission_codes'] =
                $this->permissions(
                    $item[
                        'permission_codes_json'
                    ]
                    ?? null
                );

            $item['color_hex'] =
                $this->normalizeColor(
                    (string) (
                        $item['color_code']
                        ?? ''
                    )
                );
        }

        unset($item);

        return [
            'available' => true,
            'items' => $items,
            'icons' => AdminIcon::codes(),
        ];
    }


    public function appearanceMap(): array
    {
        $map = [];

        foreach (
            $this->index()['items']
            ?? []
            as $item
        ) {
            $key = trim(
                (string) (
                    $item['item_key']
                    ?? ''
                )
            );

            if ($key === '') {
                continue;
            }

            $map[$key] = [
                'title' =>
                    (string) (
                        $item['title']
                        ?? ''
                    ),

                'description' =>
                    (string) (
                        $item['description']
                        ?? ''
                    ),

                'icon' =>
                    (string) (
                        $item['icon_code']
                        ?? 'dashboard'
                    ),

                'color' =>
                    (string) (
                        $item['color_code']
                        ?? '#2563eb'
                    ),

                'sort_order' =>
                    (int) (
                        $item['sort_order']
                        ?? 0
                    ),

                'sidebar_enabled' =>
                    (int) (
                        $item['is_active']
                        ?? 0
                    ),

                'dashboard_enabled' =>
                    (int) (
                        $item['dashboard_enabled']
                        ?? 0
                    ),
            ];
        }

        return $map;
    }


    public function dashboardCards(
        int $userId
    ): array {
        $index =
            $this->index();

        if (
            ($index['available'] ?? false)
            !== true
        ) {
            return [];
        }

        $urls =
            new ApplicationUrlRegistry();

        $cards = [];

        foreach (
            $index['items']
            as $item
        ) {
            if (
                (int) (
                    $item['dashboard_enabled']
                    ?? 0
                ) !== 1
            ) {
                continue;
            }

            if (
                !$this->allowed(
                    $userId,
                    $item
                )
            ) {
                continue;
            }

            $route = trim(
                (string) (
                    $item['route_path']
                    ?? ''
                )
            );

            if (
                $route === ''
                || !str_starts_with(
                    $route,
                    '/admin'
                )
            ) {
                continue;
            }

            $cards[] = [
                'key' =>
                    (string) $item['item_key'],

                'title' =>
                    (string) $item['title'],

                'description' =>
                    (string) (
                        $item['description']
                        ?? ''
                    ),

                'subtitle' =>
                    (string) (
                        $item['description']
                        ?? ''
                    ),

                'icon' =>
                    (string) (
                        $item['icon_code']
                        ?? 'dashboard'
                    ),

                'color' =>
                    (string) (
                        $item['color_code']
                        ?? '#2563eb'
                    ),

                'url' =>
                    $urls->core(
                        $route
                    ),

                'permission' => null,

                'sort_order' =>
                    (int) (
                        $item['sort_order']
                        ?? 0
                    ),
            ];
        }

        /*
         * REQUESTER_TICKETING_SEPARATE_CARD_RUNTIME
         *
         * Keep the original Help / Support feature intact.
         * Ticketing is exposed as an independent entry card.
         *
         * Normal platform users receive the Ticketing entry
         * when the existing support feature is visible.
         *
         * Existing Ticketing staff/admin users also receive
         * the entry even if support.view is not part of their
         * active role.
         */
        $supportVisible =
            false;

        foreach ($cards as $existingCard) {

            if (
                (string) (
                    $existingCard['key']
                    ?? ''
                ) === 'support'
            ) {
                $supportVisible =
                    true;

                break;
            }
        }

        $ticketingRoleAccess =
            (
                new AuthorizationService()
            )->hasPermission(
                $userId,
                'ticketing.ticket.view'
            );

        if (
            $supportVisible
            ||
            $ticketingRoleAccess
        ) {
            $ticketingExists =
                false;

            foreach ($cards as $existingCard) {

                if (
                    (string) (
                        $existingCard['key']
                        ?? ''
                    ) === 'ticketing-entry'
                ) {
                    $ticketingExists =
                        true;

                    break;
                }
            }

            if (!$ticketingExists) {
                $cards[] = [
                    'key' =>
                        'ticketing-entry',

                    'title' =>
                        'پشتیبانی و تیکتینگ',

                    'description' =>
                        'ثبت، پیگیری و رسیدگی به درخواست‌های پشتیبانی',

                    'subtitle' =>
                        'ثبت، پیگیری و رسیدگی به درخواست‌های پشتیبانی',

                    'icon' =>
                        'headset',

                    'color' =>
                        '#258843',

                    'url' =>
                        $urls->core(
                            '/admin/support/ticketing'
                        ),

                    'permission' =>
                        null,

                    'sort_order' =>
                        51,
                ];
            }
        }

        return $cards;
    }


    public function save(
        array $input
    ): array {
        if (
            !$this->columnExists(
                'admin_navigation_items',
                'dashboard_enabled'
            )
        ) {
            return [
                'ok' => false,
                'error' =>
                    'زیرساخت بخش‌های پنل آماده نیست.',
            ];
        }

        $featureKey =
            strtolower(
                trim(
                    (string) (
                        $input['feature_key']
                        ?? ''
                    )
                )
            );

        if (
            preg_match(
                '/^[a-z][a-z0-9_-]{1,119}$/',
                $featureKey
            ) !== 1
        ) {
            return [
                'ok' => false,
                'error' =>
                    'کلید بخش معتبر نیست.',
            ];
        }

        $current =
            $this->feature(
                $featureKey
            );

        if ($current === null) {
            return [
                'ok' => false,
                'error' =>
                    'بخش انتخاب‌شده قابل مدیریت نیست.',
            ];
        }

        $title = trim(
            (string) (
                $input['title']
                ?? ''
            )
        );

        $description = trim(
            (string) (
                $input['description']
                ?? ''
            )
        );

        $iconCode =
            strtolower(
                trim(
                    (string) (
                        $input['icon_code']
                        ?? ''
                    )
                )
            );

        if (
            $title === ''
            || mb_strlen(
                $title,
                'UTF-8'
            ) > 190
        ) {
            return [
                'ok' => false,
                'error' =>
                    'عنوان بخش معتبر نیست.',
            ];
        }

        if (
            mb_strlen(
                $description,
                'UTF-8'
            ) > 500
        ) {
            return [
                'ok' => false,
                'error' =>
                    'توضیح بخش بیش از حد مجاز است.',
            ];
        }

        if (
            mb_strlen(
                $iconCode,
                'UTF-8'
            ) > 60
            || !AdminIcon::supports(
                $iconCode
            )
        ) {
            return [
                'ok' => false,
                'error' =>
                    'کد آیکون معتبر نیست.',
            ];
        }

        $color =
            $this->normalizeColor(
                (string) (
                    $input['color_code']
                    ?? ''
                )
            );

        $sortOrder =
            max(
                0,
                min(
                    10000,
                    (int) (
                        $input['sort_order']
                        ?? 0
                    )
                )
            );

        $sidebarEnabled =
            isset(
                $input['sidebar_enabled']
            )
                ? 1
                : 0;

        $dashboardEnabled =
            isset(
                $input['dashboard_enabled']
            )
                ? 1
                : 0;

        /*
         * Editable presentation fields only.
         *
         * feature key, route, target application,
         * permission mode and permission codes are
         * intentionally not writable here.
         */
        $statement =
            $this->db->prepare("
                UPDATE admin_navigation_items
                SET
                    title = ?,
                    description = ?,
                    icon_code = ?,
                    color_code = ?,
                    sort_order = ?,
                    is_active = ?,
                    dashboard_enabled = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND shell_key = 'core'
                  AND parent_id IS NULL
                  AND placement_code = 'sidebar'
                  AND target_application = 'core'
            ");

        $statement->execute([
            $title,
            $description !== ''
                ? $description
                : null,
            $iconCode,
            $color,
            $sortOrder,
            $sidebarEnabled,
            $dashboardEnabled,
            (int) $current['id'],
        ]);

        return [
            'ok' => true,
            'error' => '',
        ];
    }


    private function feature(
        string $featureKey
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM admin_navigation_items
                WHERE shell_key = 'core'
                  AND parent_id IS NULL
                  AND placement_code = 'sidebar'
                  AND target_application = 'core'
                  AND item_key = ?
                  AND item_key <> 'dashboard'
                LIMIT 1
            ");

        $statement->execute([
            $featureKey,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }


    private function allowed(
        int $userId,
        array $item
    ): bool {
        $permissions =
            $item['permission_codes']
            ?? $this->permissions(
                $item[
                    'permission_codes_json'
                ]
                ?? null
            );

        if ($permissions === []) {
            return true;
        }

        $mode =
            strtolower(
                trim(
                    (string) (
                        $item['permission_mode']
                        ?? 'any'
                    )
                )
            );

        if ($mode === 'all') {
            foreach (
                $permissions
                as $permission
            ) {
                if (
                    !$this->navigation->can(
                        $userId,
                        $permission
                    )
                ) {
                    return false;
                }
            }

            return true;
        }

        foreach (
            $permissions
            as $permission
        ) {
            if (
                $this->navigation->can(
                    $userId,
                    $permission
                )
            ) {
                return true;
            }
        }

        return false;
    }


    private function permissions(
        mixed $value
    ): array {
        if (is_array($value)) {
            $decoded = $value;
        } elseif (
            is_string($value)
            && trim($value) !== ''
        ) {
            $decoded =
                json_decode(
                    $value,
                    true
                );
        } else {
            $decoded = [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (
                            mixed $permission
                        ): string =>
                            trim(
                                (string) $permission
                            ),
                        $decoded
                    ),
                    static fn (
                        string $permission
                    ): bool =>
                        $permission !== ''
                )
            )
        );
    }


    private function normalizeColor(
        string $value
    ): string {
        $value =
            strtolower(
                trim($value)
            );

        if (
            preg_match(
                '/^#[0-9a-f]{6}$/',
                $value
            ) === 1
        ) {
            return $value;
        }

        $legacy = [
            'blue' => '#2563eb',
            'teal' => '#0f766e',
            'cyan' => '#0891b2',
            'purple' => '#7c3aed',
            'violet' => '#6d28d9',
            'fuchsia' => '#c026d3',
            'indigo' => '#4f46e5',
            'amber' => '#d97706',
            'orange' => '#f97316',
            'rose' => '#e11d48',
            'green' => '#16a34a',
        ];

        return
            $legacy[$value]
            ?? '#2563eb';
    }


    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}

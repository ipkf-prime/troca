<?php

namespace IPKF\Database\Migrations;

class EnableNotificationApprovalManagement extends Migration
{
    private const VIEW_PERMISSION =
        'notifications.approvals.view';

    private const DECIDE_PERMISSION =
        'notifications.approvals.decide';

    public function up(): void
    {
        $this->mergeSettingsGetRoute();

        $this->ensureDecisionRoute(
            '/admin/communications/settings/'
            . 'approvals/{reference}/approve'
        );

        $this->ensureDecisionRoute(
            '/admin/communications/settings/'
            . 'approvals/{reference}/reject'
        );

        $this->ensureNavigationItem();
    }

    public function down(): void
    {
    }

    private function mergeSettingsGetRoute(): void
    {
        if (!$this->tableExists(
            'admin_route_permissions'
        )) {
            return;
        }

        $select = $this->db->prepare("
            SELECT permission_codes_json
            FROM admin_route_permissions
            WHERE route_pattern = ?
              AND http_method = 'GET'
            LIMIT 1
        ");

        $select->execute([
            '/admin/communications/settings',
        ]);

        $existing = $select->fetchColumn();

        $permissions = is_string($existing)
            ? json_decode($existing, true)
            : [];

        if (!is_array($permissions)) {
            $permissions = [];
        }

        $permissions[] =
            self::VIEW_PERMISSION;

        $permissions = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'strval',
                        $permissions
                    ),
                    static fn (
                        string $code
                    ): bool =>
                        trim($code) !== ''
                )
            )
        );

        $statement = $this->db->prepare("
            INSERT INTO admin_route_permissions (
                route_pattern,
                http_method,
                permission_mode,
                permission_codes_json,
                priority,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                ?,
                'GET',
                'any',
                ?,
                50,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                permission_mode = 'any',
                permission_codes_json =
                    VALUES(permission_codes_json),
                priority = GREATEST(
                    priority,
                    VALUES(priority)
                ),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        $statement->execute([
            '/admin/communications/settings',
            $this->permissionsJson(
                $permissions
            ),
        ]);
    }

    private function ensureDecisionRoute(
        string $route
    ): void {
        if (!$this->tableExists(
            'admin_route_permissions'
        )) {
            return;
        }

        $statement = $this->db->prepare("
            INSERT INTO admin_route_permissions (
                route_pattern,
                http_method,
                permission_mode,
                permission_codes_json,
                priority,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                ?,
                'POST',
                'any',
                ?,
                90,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                permission_mode =
                    VALUES(permission_mode),
                permission_codes_json =
                    VALUES(permission_codes_json),
                priority =
                    VALUES(priority),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        $statement->execute([
            $route,
            $this->permissionsJson([
                self::DECIDE_PERMISSION,
            ]),
        ]);
    }

    private function ensureNavigationItem(): void
    {
        if (!$this->tableExists(
            'admin_navigation_items'
        )) {
            return;
        }

        $parent = $this->db->prepare("
            SELECT id
            FROM admin_navigation_items
            WHERE shell_key = 'core'
              AND item_key = 'communications'
            LIMIT 1
        ");

        $parent->execute();

        $parentId =
            (int) $parent->fetchColumn();

        if ($parentId < 1) {
            return;
        }

        $statement = $this->db->prepare("
            INSERT INTO admin_navigation_items (
                parent_id,
                shell_key,
                item_key,
                item_type,
                placement_code,
                hide_when_badge_empty,
                title,
                description,
                route_path,
                target_application,
                icon_code,
                color_code,
                permission_mode,
                permission_codes_json,
                badge_source,
                active_paths_json,
                sort_order,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                ?,
                'core',
                'notification-approval-queue',
                'link',
                'sidebar',
                0,
                'تأیید اعلان‌ها',
                'بررسی، تأیید یا رد درخواست‌های ارسال اعلان',
                '/admin/communications/settings?section=approvals',
                'core',
                'check',
                NULL,
                'any',
                ?,
                NULL,
                ?,
                47,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                parent_id =
                    VALUES(parent_id),
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
                permission_mode =
                    VALUES(permission_mode),
                permission_codes_json =
                    VALUES(permission_codes_json),
                active_paths_json =
                    VALUES(active_paths_json),
                sort_order =
                    VALUES(sort_order),
                is_active = 1,
                updated_at =
                    CURRENT_TIMESTAMP
        ");

        $route =
            '/admin/communications/settings'
            . '?section=approvals';

        $statement->execute([
            $parentId,
            $this->permissionsJson([
                self::VIEW_PERMISSION,
            ]),
            json_encode(
                [$route],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    private function permissionsJson(
        array $permissions
    ): string {
        return json_encode(
            array_values($permissions),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }

    private function tableExists(
        string $table
    ): bool {
        $statement = $this->db->prepare("
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
}

<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;

final class ExtendAdminNavigationCoreFeatureMetadata
    extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists(
            'admin_navigation_items'
        )) {
            return;
        }

        if (!$this->columnExists(
            'admin_navigation_items',
            'dashboard_enabled'
        )) {
            $this->db->exec("
                ALTER TABLE admin_navigation_items
                ADD COLUMN dashboard_enabled
                    TINYINT(1)
                    NOT NULL
                    DEFAULT 0
                    AFTER is_active
            ");
        }

        /*
         * Existing Core dashboard cards become registry-driven.
         * Communications remains sidebar-only by default.
         */
        $statement =
            $this->db->prepare("
                UPDATE admin_navigation_items
                SET
                    dashboard_enabled = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE shell_key = 'core'
                  AND parent_id IS NULL
                  AND target_application = 'core'
                  AND item_key IN
                  (
                    'users',
                    'organization',
                    'system',
                    'reports',
                    'support'
                  )
            ");

        $statement->execute();

        /*
         * Help/Support and Ticketing must have
         * separate visual identities.
         */
        $statement =
            $this->db->prepare("
                UPDATE admin_navigation_items
                SET
                    title = 'راهنما و پشتیبانی سامانه',
                    icon_code = 'book-open',
                    updated_at = CURRENT_TIMESTAMP
                WHERE shell_key = 'core'
                  AND item_key = 'support'
                  AND target_application = 'core'
            ");

        $statement->execute();

        /*
         * Work is an application module. Only its icon changes;
         * all user-selected colors and other module metadata
         * remain untouched.
         */
        if ($this->tableExists(
            'application_modules'
        )) {
            $statement =
                $this->db->prepare("
                    UPDATE application_modules
                    SET
                        icon_code = 'list-check',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE module_key = 'work'
                ");

            $statement->execute();
        }

        $statement =
            $this->db->prepare("
                UPDATE admin_navigation_items
                SET
                    icon_code = 'list-check',
                    updated_at = CURRENT_TIMESTAMP
                WHERE shell_key = 'core'
                  AND item_key = 'work'
                  AND target_application = 'work'
            ");

        $statement->execute();

        $this->ensureSettingsNavigation();
    }


    public function down(): void
    {
    }


    private function ensureSettingsNavigation(): void
    {
        $parent =
            $this->db->prepare("
                SELECT id
                FROM admin_navigation_items
                WHERE shell_key = 'core'
                  AND item_key = 'system'
                  AND parent_id IS NULL
                LIMIT 1
            ");

        $parent->execute();

        $parentId =
            (int) $parent->fetchColumn();

        if ($parentId < 1) {
            return;
        }

        $statement =
            $this->db->prepare("
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
                    dashboard_enabled,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?,
                    'core',
                    'core-feature-settings',
                    'link',
                    'sidebar',
                    0,
                    'بخش‌های پنل',
                    'ظاهر و نحوه نمایش بخش‌های داخلی پنل',
                    '/admin/settings/core-features',
                    'core',
                    'palette',
                    'purple',
                    'any',
                    ?,
                    NULL,
                    ?,
                    25,
                    1,
                    0,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    parent_id = VALUES(parent_id),
                    title = VALUES(title),
                    description = VALUES(description),
                    route_path = VALUES(route_path),
                    target_application =
                        VALUES(target_application),
                    icon_code = VALUES(icon_code),
                    permission_mode =
                        VALUES(permission_mode),
                    permission_codes_json =
                        VALUES(permission_codes_json),
                    active_paths_json =
                        VALUES(active_paths_json),
                    sort_order = VALUES(sort_order),
                    is_active = 1,
                    dashboard_enabled = 0,
                    updated_at = CURRENT_TIMESTAMP
            ");

        $statement->execute([
            $parentId,
            $this->permissionsJson([
                'admin.settings.manage',
            ]),
            json_encode(
                [
                    '/admin/settings/core-features',
                ],
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

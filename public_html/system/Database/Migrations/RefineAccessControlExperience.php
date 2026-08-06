<?php

namespace IPKF\Database\Migrations;

class RefineAccessControlExperience extends Migration
{
    public function up(): void
    {
        $this->alignNavigation();
        $this->alignRoutes();
    }

    public function down(): void
    {
    }

    private function alignNavigation(): void
    {
        if (!$this->tableExists('admin_navigation_items')) {
            return;
        }

        $parent = $this->db->query("
            SELECT id
            FROM admin_navigation_items
            WHERE shell_key = 'core'
              AND is_active = 1
              AND (
                    title = 'مدیریت سامانه'
                    OR item_key IN (
                        'system-management',
                        'system-settings',
                        'system',
                        'admin'
                    )
              )
            ORDER BY
                CASE
                    WHEN title = 'مدیریت سامانه' THEN 0
                    ELSE 1
                END,
                id
            LIMIT 1
        ");
        $parentId = (int) $parent->fetchColumn();

        if ($parentId < 1) {
            return;
        }

        $permissions = $this->permissionsJson([
            'access.manage',
            'access.roles.manage',
            'access.users.search',
            'access.audit.view',
        ]);
        $activePaths = json_encode(
            [
                '/admin/access-control',
                '/admin/access-control/*',
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

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
                'access-control',
                'link',
                'sidebar',
                0,
                'سطوح و نقش‌های دسترسی',
                'مدیریت نقش‌ها، مجوزها و استثناهای کاربران',
                '/admin/access-control',
                'core',
                'shield',
                NULL,
                'any',
                ?,
                NULL,
                ?,
                35,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                item_type = VALUES(item_type),
                placement_code = VALUES(placement_code),
                hide_when_badge_empty = 0,
                title = VALUES(title),
                description = VALUES(description),
                route_path = VALUES(route_path),
                target_application = VALUES(target_application),
                icon_code = VALUES(icon_code),
                permission_mode = VALUES(permission_mode),
                permission_codes_json =
                    VALUES(permission_codes_json),
                badge_source = NULL,
                active_paths_json = VALUES(active_paths_json),
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute([
            $parentId,
            $permissions,
            $activePaths,
        ]);
    }

    private function alignRoutes(): void
    {
        if (!$this->tableExists('admin_route_permissions')) {
            return;
        }

        $this->upsertRoute(
            '/admin/access-control',
            'GET',
            [
                'access.manage',
                'access.roles.manage',
                'access.users.search',
                'access.audit.view',
            ],
            100
        );

        $this->upsertRoute(
            '/admin/access-control/roles',
            'POST',
            [
                'access.manage',
                'access.roles.manage',
            ],
            110
        );

        $this->upsertRoute(
            '/admin/access-control/users',
            'POST',
            [
                'access.manage',
                'access.users.manage',
            ],
            120
        );
    }

    private function upsertRoute(
        string $path,
        string $method,
        array $permissions,
        int $priority
    ): void {
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
                ?,
                'any',
                ?,
                ?,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                permission_mode = 'any',
                permission_codes_json =
                    VALUES(permission_codes_json),
                priority = VALUES(priority),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute([
            $path,
            $method,
            $this->permissionsJson($permissions),
            $priority,
        ]);
    }

    private function permissionsJson(array $permissions): string
    {
        return json_encode(
            array_values($permissions),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }
}

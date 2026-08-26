<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;

final class EnableTicketingProjectManagement
    extends Migration
{
    private const VIEW_PERMISSION =
        'ticketing.ticket.view';

    private const MANAGE_PERMISSION =
        'ticketing.project.manage';


    public function up(): void
    {
        $this->ensurePermission();
        $this->ensureRoleAssignments();
        $this->ensureRoutePermissions();
        $this->ensureNavigation();
    }


    public function down(): void
    {
    }


    private function ensurePermission(): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        $statement =
            $this->db->prepare("
                INSERT INTO permissions
                (
                    code,
                    module,
                    resource,
                    action,
                    title,
                    description,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'ticketing',
                    'project',
                    'manage',
                    'مدیریت پروژه‌های پشتیبانی',
                    'ایجاد و ویرایش پروژه‌های پشتیبانی تیکتینگ',
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    module = VALUES(module),
                    resource = VALUES(resource),
                    action = VALUES(action),
                    title = VALUES(title),
                    description = VALUES(description),
                    updated_at = CURRENT_TIMESTAMP
            ");

        $statement->execute([
            self::MANAGE_PERMISSION,
        ]);
    }


    private function ensureRoleAssignments(): void
    {
        if (
            !$this->tableExists('roles')
            || !$this->tableExists('permissions')
            || !$this->tableExists('role_permissions')
        ) {
            return;
        }

        $assign =
            $this->db->prepare("
                INSERT IGNORE INTO role_permissions
                (
                    role_id,
                    permission_id,
                    created_at
                )
                SELECT
                    r.id,
                    p.id,
                    CURRENT_TIMESTAMP
                FROM roles r
                INNER JOIN permissions p
                    ON p.code = ?
                WHERE r.code = ?
            ");

        foreach ([
            'super_admin',
            'system_admin',
        ] as $roleCode) {

            /*
             * Module-entry permission.
             */
            $assign->execute([
                self::VIEW_PERMISSION,
                $roleCode,
            ]);

            /*
             * Project administration permission.
             */
            $assign->execute([
                self::MANAGE_PERMISSION,
                $roleCode,
            ]);
        }
    }


    private function ensureRoutePermissions(): void
    {
        if (
            !$this->tableExists(
                'admin_route_permissions'
            )
        ) {
            return;
        }

        $statement =
            $this->db->prepare("
                INSERT INTO admin_route_permissions
                (
                    route_pattern,
                    http_method,
                    permission_mode,
                    permission_codes_json,
                    priority,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
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

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $permissions =
            $this->permissionsJson([
                self::MANAGE_PERMISSION,
            ]);

        foreach ([
            [
                'GET',
                '/admin/ticketing/projects',
            ],
            [
                'GET',
                '/admin/ticketing/projects/create',
            ],
            [
                'POST',
                '/admin/ticketing/projects',
            ],
            [
                'GET',
                '/admin/ticketing/projects/{public_reference}/edit',
            ],
            [
                'POST',
                '/admin/ticketing/projects/{public_reference}',
            ],
        ] as [$method, $route]) {

            $statement->execute([
                $route,
                $method,
                $permissions,
            ]);
        }
    }


    private function ensureNavigation(): void
    {
        if (
            !$this->tableExists(
                'admin_navigation_items'
            )
        ) {
            return;
        }

        $select =
            $this->db->prepare("
                SELECT id
                FROM admin_navigation_items
                WHERE shell_key = 'ticketing'
                  AND item_key = 'ticketing-projects'
                ORDER BY id
            ");

        $select->execute();

        $ids =
            $select->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

        if (count($ids) > 1) {
            throw new \RuntimeException(
                'Duplicate ticketing-projects navigation items.'
            );
        }

        $permissions =
            $this->permissionsJson([
                self::MANAGE_PERMISSION,
            ]);

        $activePaths =
            json_encode(
                [
                    '/admin/ticketing/projects',
                    '/admin/ticketing/projects/create',
                    '/admin/ticketing/projects/{public_reference}',
                    '/admin/ticketing/projects/{public_reference}/edit',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );

        if ($ids !== []) {

            /*
             * Existing presentation choices are preserved.
             * Only security/runtime identity is synchronized.
             */
            $update =
                $this->db->prepare("
                    UPDATE admin_navigation_items
                    SET
                        route_path =
                            '/admin/ticketing/projects',

                        target_application =
                            'ticketing',

                        permission_mode =
                            'any',

                        permission_codes_json =
                            ?,

                        active_paths_json =
                            ?,

                        updated_at =
                            CURRENT_TIMESTAMP

                    WHERE id = ?
                ");

            $update->execute([
                $permissions,
                $activePaths,
                (int) $ids[0],
            ]);

            return;
        }

        $insert =
            $this->db->prepare("
                INSERT INTO admin_navigation_items
                (
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
                VALUES
                (
                    NULL,
                    'ticketing',
                    'ticketing-projects',
                    'link',
                    'sidebar',
                    0,
                    'پروژه‌های پشتیبانی',
                    'تعریف و مدیریت پروژه‌های پشتیبانی',
                    '/admin/ticketing/projects',
                    'ticketing',
                    'sitemap',
                    'green',
                    'any',
                    ?,
                    NULL,
                    ?,
                    40,
                    1,
                    0,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

        $insert->execute([
            $permissions,
            $activePaths,
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
}

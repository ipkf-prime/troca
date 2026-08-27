<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;

final class EnableTicketingParticipantDirectoryNavigation
    extends Migration
{
    private const PERMISSION =
        'ticketing.project.manage';


    public function up(): void
    {
        $this->ensureRoutePermissions();
        $this->ensureNavigation();
    }


    public function down(): void
    {
    }


    private function ensureRoutePermissions(): void
    {
        if (!$this->tableExists(
            'admin_route_permissions'
        )) {
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
                self::PERMISSION,
            ]);

        foreach ([
            [
                'GET',
                '/admin/ticketing/participants',
            ],
            [
                'POST',
                '/admin/ticketing/participants/core',
            ],
            [
                'POST',
                '/admin/ticketing/participants/manual',
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
        if (!$this->tableExists(
            'admin_navigation_items'
        )) {
            return;
        }

        $select =
            $this->db->prepare("
                SELECT id
                FROM admin_navigation_items
                WHERE shell_key = 'ticketing'
                  AND item_key = 'ticketing-participants'
                ORDER BY id
            ");

        $select->execute();

        $ids =
            $select->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

        if (count($ids) > 1) {
            throw new \RuntimeException(
                'Duplicate ticketing-participants navigation items.'
            );
        }

        $permissions =
            $this->permissionsJson([
                self::PERMISSION,
            ]);

        $activePaths =
            json_encode(
                [
                    '/admin/ticketing/participants',
                    '/admin/ticketing/participants/*',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );

        if ($ids !== []) {
            $update =
                $this->db->prepare("
                    UPDATE admin_navigation_items
                    SET
                        route_path =
                            '/admin/ticketing/participants',

                        target_application =
                            'ticketing',

                        permission_mode =
                            'any',

                        permission_codes_json =
                            ?,

                        active_paths_json =
                            ?,

                        is_active = 1,

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
                    created_at,
                    updated_at
                )
                VALUES
                (
                    NULL,
                    'ticketing',
                    'ticketing-participants',
                    'link',
                    'sidebar',
                    0,
                    'مخاطبان تیکتینگ',
                    'مدیریت مخاطبان و کاربران قابل عضویت در پروژه‌های پشتیبانی',
                    '/admin/ticketing/participants',
                    'ticketing',
                    'users',
                    'green',
                    'any',
                    ?,
                    NULL,
                    ?,
                    50,
                    1,
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

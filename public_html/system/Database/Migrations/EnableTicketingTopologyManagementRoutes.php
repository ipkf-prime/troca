<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;

final class EnableTicketingTopologyManagementRoutes
    extends Migration
{
    private const PERMISSION =
        'ticketing.project.manage';


    public function up(): void
    {
        $this->ensureRoutePermissions();
        $this->extendProjectNavigationActivePaths();
    }


    public function down(): void
    {
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
                    95,
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
            json_encode(
                [
                    self::PERMISSION,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );

        foreach ([
            [
                'GET',
                '/admin/ticketing/projects/{public_reference}/topology',
            ],
            [
                'POST',
                '/admin/ticketing/projects/{public_reference}/topology',
            ],
        ] as [$method, $route]) {

            $statement->execute([
                $route,
                $method,
                $permissions,
            ]);
        }
    }


    private function extendProjectNavigationActivePaths(): void
    {
        if (
            !$this->tableExists(
                'admin_navigation_items'
            )
        ) {
            return;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    active_paths_json
                FROM admin_navigation_items
                WHERE shell_key = 'ticketing'
                  AND item_key = 'ticketing-projects'
                ORDER BY id
            ");

        $statement->execute();

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        if (count($rows) !== 1) {
            throw new \RuntimeException(
                'Expected exactly one ticketing-projects navigation item.'
            );
        }

        $paths =
            json_decode(
                (string) (
                    $rows[0][
                        'active_paths_json'
                    ]
                    ?? '[]'
                ),
                true
            );

        if (!is_array($paths)) {
            $paths = [];
        }

        $required = [
            '/admin/ticketing/projects',
            '/admin/ticketing/projects/create',
            '/admin/ticketing/projects/{public_reference}',
            '/admin/ticketing/projects/{public_reference}/edit',
            '/admin/ticketing/projects/{public_reference}/topology',
        ];

        foreach ($required as $path) {
            if (
                !in_array(
                    $path,
                    $paths,
                    true
                )
            ) {
                $paths[] = $path;
            }
        }

        $update =
            $this->db->prepare("
                UPDATE admin_navigation_items
                SET
                    active_paths_json = ?,
                    updated_at =
                        CURRENT_TIMESTAMP
                WHERE id = ?
            ");

        $update->execute([
            json_encode(
                array_values($paths),
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            (int) $rows[0]['id'],
        ]);
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

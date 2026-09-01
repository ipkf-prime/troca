<?php

namespace IPKF\Database\Migrations;

class EnableTicketingStatusTitleManagement extends Migration
{
    private const PERMISSION =
        'ticketing.project.manage';

    private const ROUTE =
        '/admin/ticketing/statuses';


    public function up(): void
    {
        if (
            !$this->tableExists(
                'admin_route_permissions'
            )
        ) {
            return;
        }

        $this->upsertRoute(
            'GET',
            115
        );

        $this->upsertRoute(
            'POST',
            116
        );
    }


    public function down(): void
    {
    }


    private function upsertRoute(
        string $method,
        int $priority
    ): void {
        $statement =
            $this->db->prepare("
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
                    priority =
                        VALUES(priority),
                    is_active = 1,
                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            self::ROUTE,
            $method,
            json_encode(
                [
                    self::PERMISSION,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            $priority,
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
            (int) $statement
                ->fetchColumn()
            > 0;
    }
}

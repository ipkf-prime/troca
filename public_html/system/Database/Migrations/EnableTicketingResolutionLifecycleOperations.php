<?php

namespace IPKF\Database\Migrations;

class EnableTicketingResolutionLifecycleOperations extends Migration
{
    public function up(): void
    {
        if (
            !$this->tableExists(
                'admin_route_permissions'
            )
        ) {
            return;
        }

        /*
         * Coarse route permissions only.
         *
         * Exact lifecycle authorization remains inside
         * TicketLifecycleTransitionRepository.
         */
        $this->upsertRoute(
            '/admin/ticketing/tickets/{public_reference}/resolve',
            [
                'ticketing.ticket.reply',
            ],
            112
        );

        $this->upsertRoute(
            '/admin/ticketing/tickets/{public_reference}/close',
            [
                'ticketing.ticket.reply',
                'ticketing.project.manage',
            ],
            113
        );

        $this->upsertRoute(
            '/admin/ticketing/tickets/{public_reference}/reopen',
            [
                'ticketing.ticket.view',
                'ticketing.project.manage',
            ],
            114
        );
    }


    public function down(): void
    {
    }


    private function upsertRoute(
        string $path,
        array $permissions,
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
                    'POST',
                    'any',
                    ?,
                    ?,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    permission_mode =
                        'any',

                    permission_codes_json =
                        VALUES(
                            permission_codes_json
                        ),

                    priority =
                        VALUES(priority),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            $path,
            $this->permissionsJson(
                $permissions
            ),
            $priority,
        ]);
    }


    private function permissionsJson(
        array $permissions
    ): string {
        return
            json_encode(
                array_values(
                    $permissions
                ),
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

                WHERE table_schema =
                        DATABASE()

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

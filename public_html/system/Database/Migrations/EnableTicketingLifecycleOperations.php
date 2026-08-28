<?php

namespace IPKF\Database\Migrations;

class EnableTicketingLifecycleOperations
    extends Migration
{
    private const PERMISSION =
        'ticketing.ticket.reply';

    private const ROUTE =
        '/admin/ticketing/tickets/'
        . '{public_reference}/reply';


    public function up(): void
    {
        $this->ensurePermission();

        $this->grantFrom(
            'ticketing.staff.cartable.view',
            [
                self::PERMISSION,
            ]
        );

        $this->ensureRoute();
    }


    public function down(): void
    {
    }


    private function ensurePermission(): void
    {
        if (
            !$this->tableExists(
                'permissions'
            )
        ) {
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
                    'ticket',
                    'reply',
                    'ثبت پاسخ کارشناس',
                    'ثبت پاسخ عمومی کارشناس پشتیبانی برای تیکت',
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )

                ON DUPLICATE KEY UPDATE
                    module =
                        VALUES(module),

                    resource =
                        VALUES(resource),

                    action =
                        VALUES(action),

                    title =
                        VALUES(title),

                    description =
                        VALUES(description),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            self::PERMISSION,
        ]);
    }


    private function grantFrom(
        string $source,
        array $targets
    ): void {
        if (
            !$this->tableExists(
                'permissions'
            )
            ||
            !$this->tableExists(
                'role_permissions'
            )
        ) {
            return;
        }

        if ($targets === []) {
            return;
        }

        $marks =
            implode(
                ', ',
                array_fill(
                    0,
                    count($targets),
                    '?'
                )
            );

        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO
                    role_permissions
                    (
                        role_id,
                        permission_id,
                        created_at
                    )

                SELECT DISTINCT
                    current.role_id,
                    target.id,
                    CURRENT_TIMESTAMP

                FROM role_permissions
                    AS current

                INNER JOIN permissions
                    AS source_permission
                  ON source_permission.id =
                        current.permission_id

                CROSS JOIN permissions
                    AS target

                WHERE source_permission.code = ?
                  AND target.code
                        IN ({$marks})
                  AND target.is_active = 1
            ");

        $statement->execute([
            $source,
            ...$targets,
        ]);
    }


    private function ensureRoute(): void
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
                INSERT INTO
                    admin_route_permissions
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
                    'POST',
                    'any',
                    ?,
                    110,
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
            self::ROUTE,

            json_encode(
                [
                    self::PERMISSION,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
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
            (int) $statement->fetchColumn()
            > 0;
    }
}

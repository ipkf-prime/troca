<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use RuntimeException;

final class CreateAdminUserImpersonationFoundation
    extends Migration
{
    private const PERMISSION =
        'users.impersonate';


    public function up(): void
    {
        if (
            !$this->columnExists(
                'permissions',
                'is_sensitive'
            )
        ) {
            throw new RuntimeException(
                'impersonation_sensitive_permission_column_missing'
            );
        }

        $this->createAuditStore();
        $this->seedPermission();
        $this->grantBootstrapPermission();
    }


    public function down(): void
    {
    }


    private function createAuditStore(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                auth_impersonation_events
            (
                id
                    BIGINT UNSIGNED
                    AUTO_INCREMENT
                    PRIMARY KEY,

                public_reference
                    VARCHAR(48)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                actor_user_id
                    BIGINT UNSIGNED
                    NOT NULL,

                effective_user_id
                    BIGINT UNSIGNED
                    NOT NULL,

                event_code
                    VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,

                reason_code
                    VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                request_id
                    VARCHAR(128)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                correlation_id
                    VARCHAR(128)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                ip_address
                    VARCHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,

                user_agent
                    VARCHAR(512)
                    NULL,

                metadata_json
                    LONGTEXT
                    NULL,

                occurred_at
                    DATETIME
                    NOT NULL,

                created_at
                    TIMESTAMP
                    NULL
                    DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY
                    auth_impersonation_events_reference_unique
                    (
                        public_reference
                    ),

                INDEX
                    auth_impersonation_events_actor_time_idx
                    (
                        actor_user_id,
                        occurred_at,
                        id
                    ),

                INDEX
                    auth_impersonation_events_effective_time_idx
                    (
                        effective_user_id,
                        occurred_at,
                        id
                    ),

                INDEX
                    auth_impersonation_events_event_time_idx
                    (
                        event_code,
                        occurred_at,
                        id
                    ),

                INDEX
                    auth_impersonation_events_request_idx
                    (
                        request_id,
                        id
                    ),

                INDEX
                    auth_impersonation_events_correlation_idx
                    (
                        correlation_id,
                        id
                    )
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }


    private function seedPermission(): void
    {
        $statement =
            $this->db->prepare("
                INSERT INTO permissions
                (
                    code,
                    module,
                    resource,
                    action,
                    title,
                    is_sensitive,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'core',
                    'users',
                    'impersonate',
                    'ورود مدیریتی به حساب کاربر',
                    1,
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
                    is_sensitive = 1,
                    is_active = 1,
                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            self::PERMISSION,
        ]);
    }


    private function grantBootstrapPermission(): void
    {
        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO role_permissions
                (
                    role_id,
                    permission_id,
                    created_at
                )
                SELECT
                    roles.id,
                    permissions.id,
                    CURRENT_TIMESTAMP
                FROM roles
                INNER JOIN permissions
                  ON permissions.code = ?
                 AND permissions.is_active = 1
                WHERE roles.is_active = 1
                  AND roles.can_manage_other_users = 1
            ");

        $statement->execute([
            self::PERMISSION,
        ]);
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
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

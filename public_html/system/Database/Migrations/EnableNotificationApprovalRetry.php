<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;

class EnableNotificationApprovalRetry extends Migration
{
    private const MANAGE_PERMISSION =
        'notifications.approvals.manage';

    public function up(): void
    {
        if (!Database::tableExists(
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
                updated_at =
                    CURRENT_TIMESTAMP
        ");

        $statement->execute([
            '/admin/communications/settings/'
            . 'approvals/{reference}/retry',

            json_encode(
                [
                    self::MANAGE_PERMISSION,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    public function down(): void
    {
    }
}

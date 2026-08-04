<?php

namespace IPKF\Database\Migrations;

class EnableNotificationProviderTestSend extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('admin_route_permissions')) {
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
                50,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                permission_mode = VALUES(permission_mode),
                permission_codes_json =
                    VALUES(permission_codes_json),
                priority = VALUES(priority),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        $statement->execute([
            '/admin/communications/settings/providers/{reference}/test-email',
            json_encode(
                ['notifications.providers.manage'],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    public function down(): void
    {
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

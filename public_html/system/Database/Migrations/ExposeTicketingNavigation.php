<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class ExposeTicketingNavigation extends Migration
{
    private const PERMISSION = 'ticketing.ticket.view';

    public function up(): void
    {
        if (!$this->tableExists('admin_navigation_items')) {
            return;
        }

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
                NULL,
                'core',
                'ticketing',
                'link',
                'sidebar',
                0,
                'پشتیبانی و تیکتینگ',
                'تیکت‌ها و درخواست‌های پشتیبانی',
                '/admin/ticketing',
                'ticketing',
                'headset',
                'cyan',
                'any',
                ?,
                NULL,
                ?,
                36,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                parent_id = NULL,
                item_type = VALUES(item_type),
                placement_code = VALUES(placement_code),
                hide_when_badge_empty = 0,
                title = VALUES(title),
                description = VALUES(description),
                route_path = VALUES(route_path),
                target_application = VALUES(target_application),
                icon_code = VALUES(icon_code),
                color_code = VALUES(color_code),
                permission_mode = VALUES(permission_mode),
                permission_codes_json = VALUES(permission_codes_json),
                badge_source = NULL,
                active_paths_json = VALUES(active_paths_json),
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        $statement->execute([
            $this->permissionsJson([
                self::PERMISSION,
            ]),
            json_encode(
                [
                    '/admin/ticketing',
                    '/admin/ticketing/*',
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

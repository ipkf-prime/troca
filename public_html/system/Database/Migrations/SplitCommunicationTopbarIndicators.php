<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;

class SplitCommunicationTopbarIndicators extends Migration
{
    public function up(): void
    {
        if (!Database::tableExists(
            'admin_navigation_items'
        )) {
            return;
        }

        $this->configureMessages();
        $this->configureNotifications();
        $this->configureApprovals();
    }

    public function down(): void
    {
    }

    private function configureMessages(): void
    {
        $statement = $this->db->prepare("
            UPDATE admin_navigation_items
            SET
                parent_id = NULL,
                item_type = 'link',
                placement_code = 'topbar',
                hide_when_badge_empty = 0,
                title = 'پیام‌ها',
                description =
                    'پیام‌های داخلی خوانده‌نشده',
                route_path =
                    '/admin/messages/inbox',
                target_application = 'core',
                icon_code = 'envelope',
                permission_mode = 'any',
                permission_codes_json = ?,
                badge_source =
                    'messages_unread_count',
                active_paths_json = ?,
                sort_order = 10,
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE shell_key = 'core'
              AND item_key =
                    'messages-unread-alert'
        ");

        $statement->execute([
            $this->json([
                'messages.view',
            ]),
            $this->json([
                '/admin/messages/inbox',
                '/admin/messages/thread/*',
            ]),
        ]);
    }

    private function configureNotifications(): void
    {
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
                'notifications-unread-alert',
                'link',
                'topbar',
                0,
                'اعلان‌ها',
                'اعلان‌های سامانه خوانده‌نشده',
                '/admin/notifications',
                'core',
                'bell',
                NULL,
                'any',
                ?,
                'notifications_unread_count',
                ?,
                11,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                parent_id = NULL,
                item_type = 'link',
                placement_code = 'topbar',
                hide_when_badge_empty = 0,
                title = VALUES(title),
                description =
                    VALUES(description),
                route_path = VALUES(route_path),
                target_application =
                    VALUES(target_application),
                icon_code = VALUES(icon_code),
                permission_mode =
                    VALUES(permission_mode),
                permission_codes_json =
                    VALUES(permission_codes_json),
                badge_source =
                    VALUES(badge_source),
                active_paths_json =
                    VALUES(active_paths_json),
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        $statement->execute([
            $this->json([
                'notifications.view',
            ]),
            $this->json([
                '/admin/notifications',
                '/admin/notifications/*',
            ]),
        ]);
    }

    private function configureApprovals(): void
    {
        $statement = $this->db->prepare("
            UPDATE admin_navigation_items
            SET sort_order = 12,
                updated_at = CURRENT_TIMESTAMP
            WHERE shell_key = 'core'
              AND item_key =
                    'notification-approval-topbar'
        ");

        $statement->execute();
    }

    private function json(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }
}

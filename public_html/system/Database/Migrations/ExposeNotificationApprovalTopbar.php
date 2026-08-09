<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;

class ExposeNotificationApprovalTopbar extends Migration
{
    private const VIEW_PERMISSION =
        'notifications.approvals.view';

    private const BADGE_SOURCE =
        'notification_approval_pending_count';

    public function up(): void
    {
        if (!Database::tableExists(
            'admin_navigation_items'
        )) {
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
                'notification-approval-topbar',
                'link',
                'topbar',
                0,
                'تأیید',
                'درخواست‌های اعلان نیازمند بررسی و تأیید',
                '/admin/communications/settings?section=approvals',
                'core',
                'check',
                NULL,
                'any',
                ?,
                ?,
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
                description = VALUES(description),
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

        $route =
            '/admin/communications/settings'
            . '?section=approvals';

        $statement->execute([
            json_encode(
                [self::VIEW_PERMISSION],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            self::BADGE_SOURCE,
            json_encode(
                [$route],
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

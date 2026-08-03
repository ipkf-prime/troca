<?php

namespace IPKF\Database\Seeds;

class NotificationCoreSeeder extends Seeder
{
    private const PERMISSIONS = [
        [
            'notifications.view',
            'notification',
            'view',
            'مشاهده اعلان‌های شخصی',
        ],
        [
            'notifications.manage',
            'notification',
            'manage',
            'مدیریت زیرساخت اعلان‌ها',
        ],
        [
            'notifications.templates.manage',
            'template',
            'manage',
            'مدیریت قالب‌های اعلان',
        ],
        [
            'notifications.deliveries.view',
            'delivery',
            'view',
            'مشاهده گزارش تحویل اعلان‌ها',
        ],
    ];

    public function run(): void
    {
        $this->seedChannels();
        $this->seedTemplates();
        $this->seedPermissions();
    }

    private function seedChannels(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO notification_channels (
                code,
                title,
                driver_code,
                is_internal,
                supports_subject,
                sort_order,
                is_active,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                driver_code = VALUES(driver_code),
                is_internal = VALUES(is_internal),
                supports_subject = VALUES(supports_subject),
                sort_order = VALUES(sort_order),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ([
            ['in_app', 'اعلان داخل سامانه', 'in_app', 1, 0, 10, 1],
            ['email', 'ایمیل', 'email', 0, 1, 20, 0],
            ['sms', 'پیامک', 'sms', 0, 0, 30, 0],
            ['bale', 'پیام‌رسان بله', 'bale', 0, 0, 40, 0],
        ] as $channel) {
            $statement->execute($channel);
        }
    }

    private function seedTemplates(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO notification_templates (
                code,
                event_type,
                channel_code,
                locale,
                title_template,
                body_template,
                action_url_template,
                format_code,
                version,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                'system.test',
                'system.test',
                'in_app',
                'fa',
                '{{title}}',
                '{{body}}',
                '{{action_url}}',
                'plain',
                1,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                title_template = VALUES(title_template),
                body_template = VALUES(body_template),
                action_url_template = VALUES(action_url_template),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute();
    }

    private function seedPermissions(): void
    {
        $permission = $this->db->prepare("
            INSERT INTO permissions (
                code,
                module,
                resource,
                action,
                title,
                is_active,
                created_at,
                updated_at
            )
            VALUES (?, 'notifications', ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach (self::PERMISSIONS as [$code, $resource, $action, $title]) {
            $permission->execute([$code, $resource, $action, $title]);
        }

        $assign = $this->db->prepare("
            INSERT IGNORE INTO role_permissions (
                role_id,
                permission_id,
                created_at
            )
            SELECT roles.id, permissions.id, CURRENT_TIMESTAMP
            FROM roles
            INNER JOIN permissions ON permissions.code = ?
            WHERE roles.code = ?
            LIMIT 1
        ");

        $assign->execute(['notifications.view', 'user']);

        foreach (self::PERMISSIONS as [$code]) {
            $assign->execute([$code, 'super_admin']);
            $assign->execute([$code, 'system_admin']);
        }
    }
}

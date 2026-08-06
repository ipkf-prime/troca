<?php

namespace IPKF\Database\Migrations;

class CreateAccessControlFoundation extends Migration
{
    public function up(): void
    {
        $this->createOverrideTable();
        $this->createAuditTable();
        $this->extendPermissionCatalog();
        $this->seedPermissions();
        $this->seedDefaultGrants();
        $this->alignCommunicationRoutes();
        $this->alignSendNavigation();
    }

    public function down(): void
    {
    }

    private function createOverrideTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_permission_overrides (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                permission_id BIGINT UNSIGNED NOT NULL,
                role_assignment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                effect_code VARCHAR(10)
                    CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                reason VARCHAR(500) NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                updated_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY user_permission_overrides_unique
                    (user_id, permission_id, role_assignment_id),
                INDEX user_permission_overrides_user_index
                    (user_id, role_assignment_id),
                INDEX user_permission_overrides_permission_index
                    (permission_id, effect_code)
            )
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createAuditTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS access_control_change_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                actor_user_id BIGINT UNSIGNED NOT NULL,
                target_type VARCHAR(30)
                    CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                target_id BIGINT UNSIGNED NOT NULL,
                role_assignment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                change_type VARCHAR(60)
                    CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                old_value LONGTEXT NULL,
                new_value LONGTEXT NULL,
                reason VARCHAR(500) NULL,
                request_ip VARCHAR(64)
                    CHARACTER SET ascii COLLATE ascii_bin NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX access_control_change_logs_actor_index
                    (actor_user_id, id),
                INDEX access_control_change_logs_target_index
                    (target_type, target_id, id),
                INDEX access_control_change_logs_created_index
                    (created_at, id)
            )
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function extendPermissionCatalog(): void
    {
        $columns = [
            'parent_code' => "VARCHAR(150)
                CHARACTER SET ascii COLLATE ascii_bin NULL",
            'display_group' => "VARCHAR(150) NULL",
            'display_type' => "VARCHAR(30)
                CHARACTER SET ascii COLLATE ascii_bin
                NOT NULL DEFAULT 'operation'",
            'sort_order' => "INT NOT NULL DEFAULT 0",
            'is_sensitive' => "TINYINT(1) NOT NULL DEFAULT 0",
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('permissions', $column)) {
                $this->db->exec(
                    "ALTER TABLE permissions
                     ADD COLUMN {$column} {$definition}"
                );
            }
        }
    }

    private function seedPermissions(): void
    {
        $items = [
            ['access.roles.manage', 'access', 'roles', 'manage',
                'مدیریت نقش‌ها و مجوزها',
                'مدیریت تمام مجوزهای منو، صفحه، تب و عملیات.',
                'نقش‌ها', 'operation', 10, 1],
            ['access.users.search', 'access', 'users', 'search',
                'جستجوی کاربران در مدیریت دسترسی',
                'جستجو با نام، نام کاربری، کد ملی، موبایل، نقش و سازمان.',
                'کاربران', 'search', 20, 1],
            ['access.users.manage', 'access', 'users', 'manage',
                'دسترسی اختصاصی کاربران',
                'ثبت اجازه یا ممانعت اختصاصی برای کاربر.',
                'کاربران', 'operation', 30, 1],
            ['access.audit.view', 'access', 'audit', 'view',
                'مشاهده تاریخچه دسترسی',
                'مشاهده تغییرات نقش و دسترسی کاربران.',
                'تاریخچه', 'page', 40, 1],
            ['notifications.send.view', 'communications',
                'notification_send', 'view',
                'مشاهده فرم ارسال اعلان',
                'نمایش منو و فرم ارسال اعلان.',
                'ارسال اعلان', 'page', 100, 0],
            ['notifications.recipients.search', 'communications',
                'notification_recipients', 'search',
                'جستجوی اشخاص و گیرندگان',
                'جستجو و انتخاب کاربران به عنوان گیرنده.',
                'ارسال اعلان', 'search', 110, 1],
            ['notifications.recipients.details', 'communications',
                'notification_recipients', 'view_details',
                'مشاهده مشخصات گیرندگان',
                'مشاهده نقش، سازمان، شهر و کانال گیرنده.',
                'ارسال اعلان', 'view', 120, 1],
            ['notifications.manual_targets.use', 'communications',
                'notification_manual_targets', 'use',
                'استفاده از مقصد دستی',
                'ورود مستقیم ایمیل، موبایل یا شناسه مقصد.',
                'ارسال اعلان', 'operation', 130, 1],
            ['notifications.send.request', 'communications',
                'notification_send', 'request',
                'ارسال اعلان با تأیید',
                'ثبت درخواست ارسال برای تأیید مدیر مجاز.',
                'ارسال اعلان', 'workflow', 140, 1],
            ['notifications.send.direct', 'communications',
                'notification_send', 'direct',
                'ارسال مستقیم اعلان',
                'ارسال واقعی اعلان بدون تأیید.',
                'ارسال اعلان', 'workflow', 150, 1],
            ['notifications.approvals.manage', 'communications',
                'notification_approval', 'manage',
                'تأیید یا رد ارسال اعلان',
                'بررسی درخواست ارسال اعلان دیگران.',
                'تأیید اعلان', 'workflow', 160, 1],
        ];

        $statement = $this->db->prepare("
            INSERT INTO permissions (
                code, module, resource, action, title, description,
                display_group, display_type, sort_order,
                is_sensitive, is_active, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                resource = VALUES(resource),
                action = VALUES(action),
                title = VALUES(title),
                description = VALUES(description),
                display_group = VALUES(display_group),
                display_type = VALUES(display_type),
                sort_order = VALUES(sort_order),
                is_sensitive = VALUES(is_sensitive),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($items as $item) {
            $statement->execute($item);
        }
    }

    private function seedDefaultGrants(): void
    {
        $this->db->exec("
            INSERT IGNORE INTO role_permissions
                (role_id, permission_id, created_at)
            SELECT roles.id, permissions.id, CURRENT_TIMESTAMP
            FROM roles CROSS JOIN permissions
            WHERE roles.code = 'super_admin'
              AND roles.is_active = 1
              AND permissions.is_active = 1
        ");

        $this->grantFrom(
            'access.manage',
            [
                'access.roles.manage',
                'access.users.search',
                'access.users.manage',
                'access.audit.view',
            ]
        );

        $this->grantFrom(
            'notifications.send.manage',
            [
                'notifications.send.view',
                'notifications.recipients.search',
                'notifications.recipients.details',
                'notifications.manual_targets.use',
                'notifications.send.request',
                'notifications.send.direct',
            ]
        );
    }

    private function grantFrom(string $source, array $targets): void
    {
        $marks = implode(', ', array_fill(0, count($targets), '?'));

        $statement = $this->db->prepare("
            INSERT IGNORE INTO role_permissions
                (role_id, permission_id, created_at)
            SELECT DISTINCT current.role_id, target.id,
                CURRENT_TIMESTAMP
            FROM role_permissions AS current
            INNER JOIN permissions AS source_permission
                ON source_permission.id = current.permission_id
            CROSS JOIN permissions AS target
            WHERE source_permission.code = ?
              AND target.code IN ({$marks})
              AND target.is_active = 1
        ");
        $statement->execute([$source, ...$targets]);
    }

    private function alignCommunicationRoutes(): void
    {
        if (!$this->tableExists('admin_route_permissions')) {
            return;
        }

        $this->upsertRoute(
            '/admin/communications/settings',
            'GET',
            [
                'notifications.providers.manage',
                'notifications.routing.manage',
                'notifications.preferences.self',
                'notifications.send.manage',
                'notifications.send.view',
                'notifications.reports.view',
                'messages.admin.manage',
            ],
            80
        );

        $this->upsertRoute(
            '/admin/communications/settings/send',
            'POST',
            [
                'notifications.send.manage',
                'notifications.send.direct',
                'notifications.send.request',
            ],
            90
        );
    }

    private function upsertRoute(
        string $path,
        string $method,
        array $permissions,
        int $priority
    ): void {
        $statement = $this->db->prepare("
            INSERT INTO admin_route_permissions (
                route_pattern, http_method, permission_mode,
                permission_codes_json, priority, is_active,
                created_at, updated_at
            )
            VALUES (?, ?, 'any', ?, ?, 1,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                permission_mode = 'any',
                permission_codes_json = VALUES(permission_codes_json),
                priority = VALUES(priority),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute([
            $path,
            $method,
            json_encode(
                $permissions,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            $priority,
        ]);
    }

    private function alignSendNavigation(): void
    {
        if (!$this->tableExists('admin_navigation_items')) {
            return;
        }

        $statement = $this->db->prepare("
            UPDATE admin_navigation_items
            SET permission_mode = 'any',
                permission_codes_json = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE route_path LIKE '%section=send%'
               OR title = 'ارسال اعلان'
        ");
        $statement->execute([
            json_encode(
                [
                    'notifications.send.view',
                    'notifications.send.manage',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ? AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }
}

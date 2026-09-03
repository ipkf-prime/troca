<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;

class CreateDynamicMessageTemplateManagement extends Migration
{
    private const MANAGE_PERMISSION =
        'admin.settings.manage';

    private const SEND_PERMISSION =
        'notifications.send.manage';

    public function up(): void
    {
        $this->createDefinitionTable();
        $this->createAuditTable();
        $this->seedDefinitions();
        $this->ensureRoutes();
        $this->ensureNavigation();
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         *
         * Template definitions, history and administrator
         * edits are operational/audit data.
         */
    }

    private function createDefinitionTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_template_definitions (
                    id BIGINT UNSIGNED
                        AUTO_INCREMENT PRIMARY KEY,

                    code VARCHAR(100)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL,

                    event_type VARCHAR(100)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL,

                    channel_code VARCHAR(40)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL,

                    locale VARCHAR(15)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL DEFAULT 'fa',

                    display_title VARCHAR(255)
                        NOT NULL,

                    description VARCHAR(1000)
                        NULL,

                    allowed_variables_json
                        LONGTEXT NOT NULL,

                    sample_variables_json
                        LONGTEXT NOT NULL,

                    is_system TINYINT(1)
                        NOT NULL DEFAULT 1,

                    sort_order INT
                        NOT NULL DEFAULT 0,

                    is_active TINYINT(1)
                        NOT NULL DEFAULT 1,

                    created_at TIMESTAMP NULL
                        DEFAULT CURRENT_TIMESTAMP,

                    updated_at TIMESTAMP NULL
                        DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,

                    UNIQUE KEY
                        notification_template_definitions_identity_unique (
                            code,
                            channel_code,
                            locale
                        ),

                    INDEX
                        notification_template_definitions_sort_index (
                            is_active,
                            sort_order,
                            id
                        )
                )
                ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createAuditTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_template_change_log (
                    id BIGINT UNSIGNED
                        AUTO_INCREMENT PRIMARY KEY,

                    code VARCHAR(100)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL,

                    channel_code VARCHAR(40)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL,

                    locale VARCHAR(15)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL,

                    action_code VARCHAR(40)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL,

                    previous_version INT UNSIGNED
                        NULL,

                    new_version INT UNSIGNED
                        NULL,

                    actor_user_id BIGINT UNSIGNED
                        NULL,

                    snapshot_json LONGTEXT
                        NOT NULL,

                    created_at TIMESTAMP NULL
                        DEFAULT CURRENT_TIMESTAMP,

                    INDEX
                        notification_template_change_log_identity_index (
                            code,
                            channel_code,
                            locale,
                            id
                        ),

                    INDEX
                        notification_template_change_log_actor_index (
                            actor_user_id,
                            id
                        )
                )
                ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function seedDefinitions(): void
    {
        $rows = [
            [
                'auth.identity.mobile_verification',
                'auth.identity.mobile_verification',
                'sms',
                'تأیید شماره همراه',
                'کد تأیید شماره همراه کاربر',
                [
                    'brand_name',
                    'code',
                    'expires_minutes',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'code' =>
                        '۱۲۳۴۵۶',
                    'expires_minutes' =>
                        '۵',
                ],
                10,
            ],

            [
                'auth.identity.email_verification',
                'auth.identity.email_verification',
                'email',
                'تأیید ایمیل',
                'کد تأیید نشانی ایمیل کاربر',
                [
                    'brand_name',
                    'code',
                    'expires_minutes',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'code' =>
                        '۱۲۳۴۵۶',
                    'expires_minutes' =>
                        '۵',
                ],
                20,
            ],

            [
                'auth.registration.mobile_otp',
                'auth.registration.mobile_otp',
                'sms',
                'کد ثبت‌نام',
                'کد تأیید شماره همراه هنگام ثبت‌نام',
                [
                    'brand_name',
                    'code',
                    'expires_minutes',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'code' =>
                        '۱۲۳۴۵۶',
                    'expires_minutes' =>
                        '۵',
                ],
                30,
            ],

            [
                'auth.password_reset.mobile_otp',
                'auth.password_reset.mobile_otp',
                'sms',
                'بازیابی کلمه عبور',
                'کد تأیید برای بازیابی کلمه عبور',
                [
                    'brand_name',
                    'code',
                    'expires_minutes',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'code' =>
                        '۱۲۳۴۵۶',
                    'expires_minutes' =>
                        '۵',
                ],
                40,
            ],

            [
                'auth.bale.enrollment',
                'auth.bale.enrollment',
                'sms',
                'دعوت اتصال بله',
                'پیام دعوت برای اتصال حساب سامانه به بله',
                [
                    'brand_name',
                    'link',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'link' =>
                        'https://ble.ir/example?start=sample',
                ],
                50,
            ],

            [
                'membership.request.received',
                'membership.request.received',
                'in_app',
                'دریافت درخواست عضویت',
                'اعلان ثبت درخواست عضویت',
                [
                    'brand_name',
                    'scope_title',
                    'action_url',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'scope_title' =>
                        'پشتیبانی سامانه',
                    'action_url' =>
                        '/admin/dashboard',
                ],
                100,
            ],

            [
                'membership.request.approved',
                'membership.request.approved',
                'in_app',
                'تأیید عضویت',
                'اعلان تأیید درخواست عضویت',
                [
                    'brand_name',
                    'scope_title',
                    'role_title',
                    'action_url',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'scope_title' =>
                        'پشتیبانی سامانه',
                    'role_title' =>
                        'کاربر',
                    'action_url' =>
                        '/admin/dashboard',
                ],
                110,
            ],

            [
                'membership.request.rejected',
                'membership.request.rejected',
                'in_app',
                'رد درخواست عضویت',
                'اعلان رد درخواست عضویت',
                [
                    'brand_name',
                    'scope_title',
                    'action_url',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'scope_title' =>
                        'پشتیبانی سامانه',
                    'action_url' =>
                        '/admin/dashboard',
                ],
                120,
            ],

            [
                'membership.role.changed',
                'membership.role.changed',
                'in_app',
                'تغییر نقش عضویت',
                'اعلان تغییر نقش کاربر در عضویت',
                [
                    'brand_name',
                    'scope_title',
                    'role_title',
                    'action_url',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'scope_title' =>
                        'پشتیبانی سامانه',
                    'role_title' =>
                        'کاربر',
                    'action_url' =>
                        '/admin/dashboard',
                ],
                130,
            ],

            [
                'membership.revoked',
                'membership.revoked',
                'in_app',
                'لغو عضویت',
                'اعلان لغو عضویت',
                [
                    'brand_name',
                    'scope_title',
                    'action_url',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'scope_title' =>
                        'پشتیبانی سامانه',
                    'action_url' =>
                        '/admin/dashboard',
                ],
                140,
            ],

            [
                'membership.restored',
                'membership.restored',
                'in_app',
                'بازگردانی عضویت',
                'اعلان بازگردانی عضویت',
                [
                    'brand_name',
                    'scope_title',
                    'action_url',
                ],
                [
                    'brand_name' =>
                        'سامانه هوشمند تروکا',
                    'scope_title' =>
                        'پشتیبانی سامانه',
                    'action_url' =>
                        '/admin/dashboard',
                ],
                150,
            ],
        ];

        $statement =
            $this->db->prepare("
                INSERT INTO
                    notification_template_definitions (
                        code,
                        event_type,
                        channel_code,
                        locale,
                        display_title,
                        description,
                        allowed_variables_json,
                        sample_variables_json,
                        is_system,
                        sort_order,
                        is_active,
                        created_at,
                        updated_at
                    )
                VALUES (
                    ?,
                    ?,
                    ?,
                    'fa',
                    ?,
                    ?,
                    ?,
                    ?,
                    1,
                    ?,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    event_type =
                        VALUES(event_type),

                    display_title =
                        VALUES(display_title),

                    description =
                        VALUES(description),

                    allowed_variables_json =
                        VALUES(allowed_variables_json),

                    sample_variables_json =
                        VALUES(sample_variables_json),

                    is_system = 1,

                    sort_order =
                        VALUES(sort_order),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        foreach ($rows as $row) {
            $statement->execute([
                $row[0],
                $row[1],
                $row[2],
                $row[3],
                $row[4],
                $this->json($row[5]),
                $this->json($row[6]),
                $row[7],
            ]);
        }
    }

    private function ensureRoutes(): void
    {
        if (!$this->tableExists(
            'admin_route_permissions'
        )) {
            return;
        }

        $routes = [
            [
                '/admin/communications/templates',
                'GET',
                self::MANAGE_PERMISSION,
            ],
            [
                '/admin/communications/templates/save',
                'POST',
                self::MANAGE_PERMISSION,
            ],
            [
                '/admin/communications/templates/preview',
                'POST',
                self::MANAGE_PERMISSION,
            ],
            [
                '/admin/communications/templates/test-send',
                'POST',
                self::SEND_PERMISSION,
            ],
        ];

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
                    ?,
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

        foreach ($routes as $route) {
            $statement->execute([
                $route[0],
                $route[1],
                $this->json([
                    $route[2],
                ]),
            ]);
        }
    }

    private function ensureNavigation(): void
    {
        if (!$this->tableExists(
            'admin_navigation_items'
        )) {
            return;
        }

        $parent =
            $this->db->prepare("
                SELECT id
                FROM admin_navigation_items
                WHERE shell_key = 'core'
                  AND item_key = 'communications'
                LIMIT 1
            ");

        $parent->execute();

        $parentId =
            (int) $parent->fetchColumn();

        if ($parentId < 1) {
            return;
        }

        $statement =
            $this->db->prepare("
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
                    ?,
                    'core',
                    'message-templates',
                    'link',
                    'sidebar',
                    0,
                    'متن‌های پیام',
                    'مدیریت متن‌های پیامک، ایمیل، بله و اعلان‌های سامانه',
                    '/admin/communications/templates',
                    'core',
                    'file-text',
                    NULL,
                    'any',
                    ?,
                    NULL,
                    ?,
                    55,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    parent_id =
                        VALUES(parent_id),

                    title =
                        VALUES(title),

                    description =
                        VALUES(description),

                    route_path =
                        VALUES(route_path),

                    target_application =
                        VALUES(target_application),

                    icon_code =
                        VALUES(icon_code),

                    permission_mode =
                        VALUES(permission_mode),

                    permission_codes_json =
                        VALUES(permission_codes_json),

                    active_paths_json =
                        VALUES(active_paths_json),

                    sort_order =
                        VALUES(sort_order),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            $parentId,

            $this->json([
                self::MANAGE_PERMISSION,
            ]),

            $this->json([
                '/admin/communications/templates',
            ]),
        ]);
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

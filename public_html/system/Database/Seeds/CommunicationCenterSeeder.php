<?php

namespace IPKF\Database\Seeds;

class CommunicationCenterSeeder extends Seeder
{
    private const PERMISSIONS = [
        ['messages.view', 'message', 'view', 'مشاهده کارتابل پیام‌ها'],
        ['messages.send', 'message', 'send', 'ارسال پیام داخلی'],
        ['messages.reply', 'message', 'reply', 'پاسخ به پیام داخلی'],
        ['messages.admin.view', 'message', 'admin_view', 'مشاهده مدیریتی پیام‌ها'],
        ['messages.admin.manage', 'message', 'admin_manage', 'مدیریت پیام‌رسان داخلی'],
        ['notifications.providers.manage', 'provider', 'manage', 'مدیریت سرویس‌دهنده‌های اعلان'],
        ['notifications.routing.manage', 'routing', 'manage', 'مدیریت قواعد ارسال اعلان'],
        ['notifications.preferences.self', 'preference', 'self', 'مدیریت ترجیحات اعلان شخصی'],
        ['notifications.reports.view', 'report', 'view', 'مشاهده گزارش ارسال و تحویل'],
    ];

    public function run(): void
    {
        $this->seedPermissions();
        $this->seedChannels();
        $this->seedProviderTypes();
        $this->seedEvents();
        $this->seedRecipientPolicy();
        $this->seedNavigation();
        $this->seedRoutePermissions();
    }

    private function seedPermissions(): void
    {
        $permission = $this->db->prepare("
            INSERT INTO permissions (
                code, module, resource, action, title,
                is_active, created_at, updated_at
            )
            VALUES (?, 'communications', ?, ?, ?, 1,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach (self::PERMISSIONS as $row) {
            $permission->execute($row);
        }

        $assign = $this->db->prepare("
            INSERT IGNORE INTO role_permissions (
                role_id, permission_id, created_at
            )
            SELECT roles.id, permissions.id, CURRENT_TIMESTAMP
            FROM roles
            INNER JOIN permissions ON permissions.code = ?
            WHERE roles.code = ?
            LIMIT 1
        ");

        foreach ([
            'messages.view',
            'messages.send',
            'messages.reply',
            'notifications.preferences.self',
        ] as $code) {
            $assign->execute([$code, 'user']);
        }

        foreach (self::PERMISSIONS as [$code]) {
            $assign->execute([$code, 'super_admin']);
        }
    }

    private function seedChannels(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO notification_channels (
                code, title, driver_code, is_internal,
                supports_subject, sort_order, is_active,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP)
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
            ['in_app', 'کارتابل داخلی', 'in_app', 1, 1, 10, 1],
            ['email', 'ایمیل', 'email', 0, 1, 20, 1],
            ['sms', 'پیام کوتاه (SMS)', 'sms', 0, 0, 30, 1],
            ['messenger', 'پیام‌رسان', 'messenger', 0, 0, 40, 1],
        ] as $channel) {
            $statement->execute($channel);
        }
    }

    private function seedProviderTypes(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO notification_provider_types (
                code, channel_code, title, driver_code,
                supports_balance, config_schema_json,
                sort_order, is_active, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 1,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                channel_code = VALUES(channel_code),
                title = VALUES(title),
                driver_code = VALUES(driver_code),
                supports_balance = VALUES(supports_balance),
                config_schema_json = VALUES(config_schema_json),
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        $providers = [
            ['gmail_smtp', 'email', 'Gmail', 'smtp', 0, [
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select',
                    'options' => ['none', 'tls', 'ssl']],
                ['key' => 'username', 'type' => 'text', 'required' => true],
                ['key' => 'from_address', 'type' => 'email', 'required' => true],
                ['key' => 'from_name', 'type' => 'text'],
            ], 10],
            ['yahoo_smtp', 'email', 'Yahoo Mail', 'smtp', 0, [
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select',
                    'options' => ['none', 'tls', 'ssl']],
                ['key' => 'username', 'type' => 'text', 'required' => true],
                ['key' => 'from_address', 'type' => 'email', 'required' => true],
                ['key' => 'from_name', 'type' => 'text'],
            ], 20],
            ['microsoft365_smtp', 'email', 'Microsoft 365 / Outlook', 'smtp', 0, [
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select',
                    'options' => ['none', 'tls', 'ssl']],
                ['key' => 'username', 'type' => 'text', 'required' => true],
                ['key' => 'from_address', 'type' => 'email', 'required' => true],
                ['key' => 'from_name', 'type' => 'text'],
            ], 30],
            ['smtp', 'email', 'SMTP سفارشی / سازمانی', 'smtp', 0, [
                ['key' => 'provider_name', 'type' => 'text'],
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select',
                    'options' => ['none', 'tls', 'ssl']],
                ['key' => 'username', 'type' => 'text'],
                ['key' => 'from_address', 'type' => 'email'],
                ['key' => 'from_name', 'type' => 'text'],
            ], 40],
            ['kavenegar', 'sms', 'کاوه‌نگار', 'kavenegar', 1, [
                ['key' => 'sender', 'type' => 'text'],
                ['key' => 'balance_endpoint', 'type' => 'url'],
            ], 100],
            ['melipayamak', 'sms', 'ملی پیامک', 'melipayamak', 1, [
                ['key' => 'username', 'type' => 'text'],
                ['key' => 'sender', 'type' => 'text'],
                ['key' => 'endpoint', 'type' => 'url'],
                ['key' => 'balance_endpoint', 'type' => 'url'],
            ], 110],
            ['ippanel', 'sms', 'IPPanel / فراز اس‌ام‌اس', 'ippanel', 1, [
                ['key' => 'sender', 'type' => 'text'],
                ['key' => 'endpoint', 'type' => 'url'],
                ['key' => 'balance_endpoint', 'type' => 'url'],
            ], 120],
            ['generic_sms', 'sms', 'سرویس پیامک سفارشی', 'generic_sms', 1, [
                ['key' => 'provider_name', 'type' => 'text', 'required' => true],
                ['key' => 'endpoint', 'type' => 'url', 'required' => true],
                ['key' => 'username', 'type' => 'text'],
                ['key' => 'sender', 'type' => 'text'],
                ['key' => 'balance_endpoint', 'type' => 'url'],
            ], 130],
            ['bale_bot', 'messenger', 'پیام‌رسان بله', 'bale_bot', 0, [
                ['key' => 'api_base', 'type' => 'url'],
                ['key' => 'bot_username', 'type' => 'text'],
            ], 200],
            ['telegram_bot', 'messenger', 'تلگرام', 'telegram_bot', 0, [
                ['key' => 'api_base', 'type' => 'url'],
                ['key' => 'parse_mode', 'type' => 'select',
                    'options' => ['plain', 'HTML', 'MarkdownV2']],
            ], 210],
            ['eitaa_bot', 'messenger', 'ایتا', 'eitaa_bot', 0, [
                ['key' => 'api_base', 'type' => 'url'],
                ['key' => 'bot_username', 'type' => 'text'],
            ], 220],
            ['whatsapp_cloud', 'messenger', 'WhatsApp Cloud API', 'whatsapp_cloud', 0, [
                ['key' => 'phone_number_id', 'type' => 'text'],
                ['key' => 'business_account_id', 'type' => 'text'],
                ['key' => 'api_version', 'type' => 'text'],
            ], 230],
        ];

        foreach ($providers as $provider) {
            [$code, $channel, $title, $driver, $balance,
                $schema, $sort] = $provider;

            $statement->execute([
                $code,
                $channel,
                $title,
                $driver,
                $balance,
                json_encode(
                    $schema,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
                $sort,
            ]);
        }
    }

    private function seedEvents(): void
    {
        $event = $this->db->prepare("
            INSERT INTO notification_event_catalog (
                event_type, title, source_module, description,
                default_priority, is_mandatory, is_active,
                sort_order, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, 1, ?,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                source_module = VALUES(source_module),
                description = VALUES(description),
                default_priority = VALUES(default_priority),
                is_mandatory = VALUES(is_mandatory),
                is_active = 1,
                sort_order = VALUES(sort_order),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ([
            ['messages.new', 'پیام داخلی جدید', 'communications',
                'ارسال پیام جدید از یک کاربر به کاربر دیگر',
                'normal', 1, 10],
            ['messages.unread_on_login',
                'یادآوری پیام خوانده‌نشده هنگام ورود',
                'communications',
                'نمایش تعداد پیام‌های خوانده‌نشده پس از ورود',
                'high', 1, 20],
            ['system.test', 'اعلان آزمایشی سامانه', 'core',
                'رویداد آزمایشی زیرساخت اعلان',
                'normal', 0, 100],
        ] as $row) {
            $event->execute($row);
        }

        $route = $this->db->prepare("
            INSERT INTO notification_routing_rules (
                event_type, scope_type, scope_reference,
                channel_code, provider_instance_id,
                is_enabled, is_mandatory, priority,
                conditions_json, sort_order,
                created_at, updated_at
            )
            VALUES (?, 'global', '*', 'in_app', NULL,
                1, ?, 100, NULL, ?,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                is_enabled = 1,
                is_mandatory = VALUES(is_mandatory),
                sort_order = VALUES(sort_order),
                updated_at = CURRENT_TIMESTAMP
        ");
        $route->execute(['messages.new', 1, 10]);
        $route->execute(['messages.unread_on_login', 1, 20]);

        $template = $this->db->prepare("
            INSERT INTO notification_templates (
                code, event_type, channel_code, locale,
                title_template, body_template,
                action_url_template, format_code,
                version, is_active, created_at, updated_at
            )
            VALUES (?, ?, 'in_app', 'fa', ?, ?, ?, 'plain',
                1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title_template = VALUES(title_template),
                body_template = VALUES(body_template),
                action_url_template = VALUES(action_url_template),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        $template->execute([
            'messages.new',
            'messages.new',
            'پیام جدید از {{sender_name}}',
            '{{subject}}',
            '{{action_url}}',
        ]);
        $template->execute([
            'messages.unread_on_login',
            'messages.unread_on_login',
            'پیام خوانده‌نشده دارید',
            'تعداد پیام‌های خوانده‌نشده: {{unread_count}}',
            '/admin/messages/inbox',
        ]);
    }

    private function seedRecipientPolicy(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO message_recipient_policies (
                code, title, description, evaluator_code,
                config_json, is_default, priority,
                status_code, created_at, updated_at
            )
            VALUES (
                'all_active_users',
                'همه کاربران فعال',
                'سیاست پایه مرحله نخست؛ قواعد سازمانی و حوزه‌ای بعداً جایگزین می‌شوند.',
                'all_active_users',
                ?,
                1,
                10,
                'active',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                evaluator_code = VALUES(evaluator_code),
                config_json = VALUES(config_json),
                is_default = 1,
                priority = VALUES(priority),
                status_code = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        $statement->execute([
            json_encode(['exclude_self' => true]),
        ]);
    }

    private function seedNavigation(): void
    {
        $roots = [
            ['core', 'dashboard', 'link', 'داشبورد', 'نمای کلی سامانه',
                '/admin/dashboard', 'core', 'dashboard', null,
                ['admin.dashboard.view'], null, ['/admin/dashboard'], 0],
            ['core', 'users', 'link', 'مدیریت کاربران',
                'کاربران، نقش‌ها و دسترسی‌ها',
                '/admin/modules/users', 'core', 'users', 'blue',
                ['users.view', 'access.manage'], null,
                ['/admin/modules/users', '/admin/users/*', '/admin/access'], 10],
            ['core', 'organization', 'link', 'ساختار سازمانی',
                'واحدها، سمت‌ها و انتصاب‌ها',
                '/admin/modules/organization', 'core', 'organization', 'teal',
                ['organizations.manage', 'org_units.manage',
                    'appointments.manage', 'org_units.view', 'positions.view'],
                null, ['/admin/modules/organization',
                    '/admin/organization-setup',
                    '/admin/organization-chart',
                    '/admin/appointments', '/admin/org-units',
                    '/admin/positions'], 20],
            ['core', 'system', 'link', 'مدیریت سامانه',
                'تنظیمات، ظاهر و صفحات',
                '/admin/modules/system', 'core', 'system', 'purple',
                ['admin.theme.manage', 'admin.settings.manage',
                    'admin.pages.manage'], null,
                ['/admin/modules/system', '/admin/theme',
                    '/admin/settings', '/admin/pages'], 30],
            ['core', 'work', 'link', 'مدیریت کار',
                'پروژه‌ها، Workها و تسک‌ها',
                '/admin/work', 'work', 'circle-check', 'green',
                ['work.project.view'], null,
                ['/admin/work', '/admin/work/*'], 34],
            ['core', 'automation', 'link', 'اتوماسیون اداری',
                'مکاتبات و دبیرخانه',
                '/admin/automation', 'automation', 'file-lines', 'indigo',
                ['automation.correspondence.view'], null,
                ['/admin/automation', '/admin/automation/*'], 35],
            ['core', 'communications', 'group', 'پیام‌ها و اعلان‌ها',
                'کارتابل داخلی و تنظیمات پیام و اعلان',
                '/admin/communications', 'core', 'envelope', 'cyan',
                ['messages.view', 'notifications.view',
                    'notifications.providers.manage',
                    'notifications.routing.manage',
                    'notifications.preferences.self',
                    'notifications.reports.view'],
                'communications_unread_total',
                ['/admin/communications', '/admin/messages/*',
                    '/admin/notifications', '/admin/notifications/*',
                    '/admin/communications/settings'], 36],
            ['core', 'reports', 'link', 'گزارش‌ها',
                'گزارش‌های مدیریتی و عملیاتی',
                '/admin/reports', 'core', 'reports', 'amber',
                ['admin.reports.view'], null, ['/admin/reports'], 40],
            ['core', 'support', 'link', 'راهنما و پشتیبانی سامانه',
                'راهنما و پیگیری درخواست‌ها',
                '/admin/support', 'core', 'support', 'rose',
                ['support.view'], null, ['/admin/support'], 50],

            ['work', 'work-dashboard', 'link',
                'داشبورد مدیریت کار', null,
                '/admin/work', 'work', 'dashboard', 'green',
                ['work.project.view'], null, ['/admin/work'], 10],
            ['work', 'work-projects', 'link',
                'پروژه‌ها', null,
                '/admin/work/projects', 'work', 'organization', 'green',
                ['work.project.view'], null,
                ['/admin/work/projects', '/admin/work/projects/*'], 20],
            ['work', 'work-settings', 'link',
                'تنظیمات', null,
                '/admin/work/settings', 'work', 'sliders', 'green',
                ['work.settings.view'], null,
                ['/admin/work/settings', '/admin/work/settings/*'], 30],

            ['automation', 'automation-dashboard', 'link',
                'داشبورد اتوماسیون', null,
                '/admin/automation', 'automation', 'dashboard', 'indigo',
                ['automation.correspondence.view'], null,
                ['/admin/automation'], 10],
            ['automation', 'automation-correspondences', 'link',
                'مکاتبات', null,
                '/admin/automation/correspondences', 'automation',
                'file-lines', 'indigo',
                ['automation.correspondence.view'], null,
                ['/admin/automation/correspondences'], 20],
            ['automation', 'automation-create', 'link',
                'ایجاد پیش‌نویس', null,
                '/admin/automation/correspondences/create',
                'automation', 'circle-check', 'indigo',
                ['automation.correspondence.create'], null,
                ['/admin/automation/correspondences/create'], 30],
            ['automation', 'automation-templates', 'link',
                'قالب‌های مکاتبه', null,
                '/admin/automation/templates', 'automation',
                'palette', 'indigo',
                ['automation.correspondence.view'], null,
                ['/admin/automation/templates'], 40],
            ['automation', 'automation-secretariat', 'link',
                'دبیرخانه و دفاتر ثبت',
                'مدیریت دبیرخانه، دوره ثبت، منابع شماره و دفاتر ثبت',
                '/admin/automation/secretariat', 'automation',
                'organization', 'indigo',
                ['automation.registry.manage'], null,
                ['/admin/automation/secretariat',
                    '/admin/automation/secretariat/*'], 50],
        ];

        foreach ($roots as $item) {
            $this->upsertNavigation(null, $item);
        }

        $this->upsertNavigation(null, [
            'core',
            'messages-unread-alert',
            'link',
            'کارتابل من',
            'پیام‌ها و اعلان‌های خوانده‌نشده',
            '/admin/messages/inbox',
            'core',
            'bell',
            'cyan',
            ['messages.view'],
            'communications_unread_total',
            ['/admin/messages/inbox', '/admin/messages/thread/*',
                '/admin/notifications', '/admin/notifications/*'],
            10,
            'topbar',
            0,
        ]);

        $this->upsertNavigation(null, [
            'core',
            'account-home',
            'link',
            'پیشخوان اصلی',
            'بازگشت به داشبورد مرکزی سامانه',
            '/admin/dashboard',
            'core',
            'dashboard',
            'green',
            [],
            null,
            ['/admin/dashboard'],
            5,
            'account',
            0,
        ]);

        $this->upsertNavigation(null, [
            'core',
            'account-profile',
            'link',
            'پروفایل کاربری',
            'هویت، اطلاعات حساب، امنیت و دسترسی‌های من',
            '/admin/profile',
            'core',
            'user',
            'green',
            [],
            null,
            ['/admin/profile', '/admin/profile/*',
                '/admin/account', '/admin/security',
                '/admin/my-theme'],
            10,
            'account',
            0,
        ]);

        $this->upsertNavigation(null, [
            'core',
            'account-cartable',
            'link',
            'کارتابل من',
            'پیام‌ها و اعلان‌های شخصی',
            '/admin/messages/inbox',
            'core',
            'envelope',
            'cyan',
            ['messages.view'],
            'communications_unread_total',
            ['/admin/messages/inbox', '/admin/messages/thread/*',
                '/admin/notifications', '/admin/notifications/*'],
            20,
            'account',
            0,
        ]);

        $this->upsertNavigation(null, [
            'core',
            'account-logout',
            'link',
            'خروج',
            'پایان نشست کاربری',
            '/admin/logout',
            'core',
            'circle-xmark',
            'rose',
            [],
            null,
            ['/admin/logout'],
            90,
            'account',
            0,
        ]);

        $accountKeys = [
            'account-home',
            'account-profile',
            'account-cartable',
            'account-logout',
        ];
        $accountPlaceholders = implode(
            ',',
            array_fill(0, count($accountKeys), '?')
        );
        $deactivateAccountItems = $this->db->prepare("
            UPDATE admin_navigation_items
            SET is_active = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE shell_key = 'core'
              AND placement_code = 'account'
              AND item_key NOT IN ({$accountPlaceholders})
        ");
        $deactivateAccountItems->execute($accountKeys);

        $parentId = $this->navigationId('core', 'communications');

        $children = [
            ['core', 'communications-inbox', 'link',
                'کارتابل داخلی', 'پیام‌های دریافتی و گفتگوها',
                '/admin/messages/inbox', 'core', 'envelope', 'cyan',
                ['messages.view'], 'messages_unread_count',
                ['/admin/messages/inbox', '/admin/messages/thread/*'], 10],
            ['core', 'communications-compose', 'link',
                'ارسال پیام', 'ارسال پیام داخلی به کاربران مجاز',
                '/admin/messages/compose', 'core', 'circle-check', 'green',
                ['messages.send'], null, ['/admin/messages/compose'], 20],
            ['core', 'communications-sent', 'link',
                'پیام‌های ارسالی', 'سابقه پیام‌های ارسال‌شده',
                '/admin/messages/sent', 'core', 'file-lines', 'blue',
                ['messages.view'], null, ['/admin/messages/sent'], 30],
            ['core', 'communications-notifications', 'link',
                'اعلان‌های من', 'اعلان‌های داخل سامانه',
                '/admin/notifications', 'core', 'status', 'violet',
                ['notifications.view'], 'notifications_unread_count',
                ['/admin/notifications', '/admin/notifications/*'], 40],
            ['core', 'communications-settings', 'link',
                'تنظیمات پیام و اعلان',
                'سرویس‌دهنده‌ها، قواعد، روش‌های دریافت و گزارش تحویل',
                '/admin/communications/settings',
                'core', 'sliders', 'purple',
                ['notifications.providers.manage',
                    'notifications.routing.manage',
                    'notifications.preferences.self',
                    'notifications.reports.view'], null,
                ['/admin/communications/settings'], 50],
        ];

        foreach ($children as $item) {
            $this->upsertNavigation($parentId, $item);
        }

        $obsolete = [
            'communications-providers',
            'communications-provider-defaults',
            'communications-routing',
            'communications-preferences',
            'communications-reports',
        ];
        $placeholders = implode(
            ',',
            array_fill(0, count($obsolete), '?')
        );
        $deactivate = $this->db->prepare("
            UPDATE admin_navigation_items
            SET is_active = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE shell_key = 'core'
              AND item_key IN ({$placeholders})
        ");
        $deactivate->execute($obsolete);
    }

    private function seedRoutePermissions(): void
    {
        $routes = [
            ['GET', '/admin/communications',
                ['messages.view', 'notifications.view'], 'any', 10],
            ['GET', '/admin/messages/inbox',
                ['messages.view'], 'any', 20],
            ['GET', '/admin/messages/compose',
                ['messages.send'], 'any', 20],
            ['POST', '/admin/messages/compose',
                ['messages.send'], 'any', 20],
            ['GET', '/admin/messages/sent',
                ['messages.view'], 'any', 20],
            ['GET', '/admin/messages/thread/{reference}',
                ['messages.view'], 'any', 30],
            ['POST', '/admin/messages/thread/{reference}/reply',
                ['messages.reply'], 'any', 30],
            ['GET', '/admin/messages/monitor',
                ['messages.admin.view'], 'any', 35],
            ['POST', '/admin/messages/monitor/{reference}',
                ['messages.admin.view'], 'any', 35],
            ['GET', '/admin/messages/monitor/view/{reference}',
                ['messages.admin.view'], 'any', 35],
            ['GET', '/admin/communications/settings',
                ['notifications.providers.manage',
                    'notifications.routing.manage',
                    'notifications.preferences.self',
                    'notifications.reports.view'], 'any', 40],
            ['POST',
                '/admin/communications/settings/providers/save',
                ['notifications.providers.manage'], 'any', 50],
            ['POST',
                '/admin/communications/settings/providers/{reference}/status',
                ['notifications.providers.manage'], 'any', 50],
            ['POST', '/admin/communications/settings/preferences',
                ['notifications.preferences.self'], 'any', 50],
        ];

        $statement = $this->db->prepare("
            INSERT INTO admin_route_permissions (
                route_pattern, http_method, permission_mode,
                permission_codes_json, priority, is_active,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, 1,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                permission_mode = VALUES(permission_mode),
                permission_codes_json = VALUES(permission_codes_json),
                priority = VALUES(priority),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($routes as [$method, $path, $permissions, $mode, $priority]) {
            $statement->execute([
                $path,
                $method,
                $mode,
                json_encode($permissions),
                $priority,
            ]);
        }
    }

    private function upsertNavigation(?int $parentId, array $item): void
    {
        [
            $shell, $key, $type, $title, $description,
            $route, $target, $icon, $color, $permissions,
            $badge, $activePaths, $sort,
        ] = array_slice($item, 0, 13);
        $placement = (string) ($item[13] ?? 'sidebar');
        $hideWhenBadgeEmpty = !empty($item[14]) ? 1 : 0;

        $statement = $this->db->prepare("
            INSERT INTO admin_navigation_items (
                parent_id, shell_key, item_key, item_type,
                placement_code, hide_when_badge_empty,
                title, description, route_path,
                target_application, icon_code, color_code,
                permission_mode, permission_codes_json,
                badge_source, active_paths_json,
                sort_order, is_active, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'any',
                ?, ?, ?, ?, 1, CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                item_type = VALUES(item_type),
                placement_code = VALUES(placement_code),
                hide_when_badge_empty = VALUES(hide_when_badge_empty),
                title = VALUES(title),
                description = VALUES(description),
                route_path = VALUES(route_path),
                target_application = VALUES(target_application),
                icon_code = VALUES(icon_code),
                color_code = VALUES(color_code),
                permission_mode = VALUES(permission_mode),
                permission_codes_json = VALUES(permission_codes_json),
                badge_source = VALUES(badge_source),
                active_paths_json = VALUES(active_paths_json),
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        $statement->execute([
            $parentId,
            $shell,
            $key,
            $type,
            $placement,
            $hideWhenBadgeEmpty,
            $title,
            $description,
            $route,
            $target,
            $icon,
            $color,
            json_encode($permissions, JSON_UNESCAPED_UNICODE),
            $badge,
            json_encode(
                $activePaths,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            $sort,
        ]);
    }

    private function navigationId(string $shell, string $key): ?int
    {
        $statement = $this->db->prepare("
            SELECT id
            FROM admin_navigation_items
            WHERE shell_key = ?
              AND item_key = ?
            LIMIT 1
        ");
        $statement->execute([$shell, $key]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}

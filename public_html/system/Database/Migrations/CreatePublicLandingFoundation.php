<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

class CreatePublicLandingFoundation extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS public_page_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                setting_value TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_by_user_id BIGINT UNSIGNED NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY public_page_settings_key_unique (setting_key),
                INDEX public_page_settings_active_sort
                    (is_active, sort_order, id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS public_page_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                item_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                eyebrow VARCHAR(190) NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL,
                image_url VARCHAR(500) NULL,
                mobile_image_url VARCHAR(500) NULL,
                action_text VARCHAR(190) NULL,
                action_url VARCHAR(500) NULL,
                action_target VARCHAR(20) NOT NULL DEFAULT '_self',
                icon VARCHAR(100) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                metadata_json LONGTEXT NULL,
                created_by_user_id BIGINT UNSIGNED NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY public_page_items_type_code_unique
                    (item_type, code),
                INDEX public_page_items_runtime
                    (item_type, is_active, sort_order, id),
                INDEX public_page_items_schedule
                    (starts_at, ends_at)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedSettings();
        $this->seedItems();
    }

    public function down(): void
    {
        $this->db->exec(
            "DROP TABLE IF EXISTS public_page_items"
        );
        $this->db->exec(
            "DROP TABLE IF EXISTS public_page_settings"
        );
    }

    private function seedSettings(): void
    {
        $rows = [
            ['page_title', 'سامانه هوشمند تروکا', 10],
            ['meta_description', 'درگاه یکپارچه خدمات سازمانی', 20],
            ['status_text', 'سامانه فعال است', 30],
            ['footer_text', 'کلیه حقوق این وب‌سایت محفوظ است.', 40],
            ['show_status', '1', 50],
            ['show_version', '1', 60],
            ['show_deploy_date', '1', 70],
            ['show_register', '0', 80],
            ['login_label', 'ورود به سامانه', 90],
            ['register_label', 'ثبت‌نام', 100],
            ['register_url', '/register', 110],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO public_page_settings
                (setting_key, setting_value, sort_order)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_key = VALUES(setting_key)
        ");

        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }

    private function seedItems(): void
    {
        $rows = [
            ['nav', 'intro', '', 'معرفی', '', '', '', '', '#intro', '_self', '', 10],
            ['nav', 'services', '', 'خدمات', '', '', '', '', '#services', '_self', '', 20],
            ['nav', 'announcements', '', 'اطلاعیه‌ها', '', '', '', '', '#announcements', '_self', '', 30],
            ['slide', 'main', 'درگاه یکپارچه خدمات سازمانی', 'سامانه هوشمند تروکا',
                'دسترسی امن و یکپارچه به خدمات و فرایندهای سازمانی.',
                '', '', 'ورود به سامانه', '/admin/login', '_self', '', 10],
            ['card', 'integrated', '', 'مدیریت یکپارچه',
                'مدیریت متمرکز اطلاعات، کاربران و فرایندهای سازمانی.',
                '', '', '', '', '_self', 'layers', 10],
            ['card', 'security', '', 'امنیت و دسترسی',
                'کنترل نقش، سطح دسترسی و هویت کاربران به‌صورت ساختاریافته.',
                '', '', '', '', '_self', 'shield', 20],
            ['card', 'extensible', '', 'زیرساخت قابل توسعه',
                'قابلیت افزودن خدمات و قابلیت‌های جدید بدون وابستگی به صفحه عمومی.',
                '', '', '', '', '_self', 'grid', 30],
            ['footer_link', 'login', '', 'ورود به سامانه', '',
                '', '', '', '/admin/login', '_self', '', 10],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO public_page_items (
                item_type, code, eyebrow, title, body,
                image_url, mobile_image_url, action_text,
                action_url, action_target, icon, sort_order
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                item_type = VALUES(item_type)
        ");

        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }
}

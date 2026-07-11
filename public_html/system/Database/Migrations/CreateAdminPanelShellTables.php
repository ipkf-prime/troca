<?php

namespace IPKF\Database\Migrations;

class CreateAdminPanelShellTables extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS app_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                namespace VARCHAR(80) NOT NULL,
                setting_key VARCHAR(120) NOT NULL,
                setting_value TEXT NULL,
                value_type VARCHAR(30) NOT NULL DEFAULT 'string',
                is_public TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY app_settings_user_namespace_key_unique (user_id, namespace, setting_key),
                INDEX app_settings_user_namespace_index (user_id, namespace),
                INDEX app_settings_namespace_index (namespace),
                INDEX app_settings_is_public_index (is_public)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec('ALTER TABLE app_settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(): void
    {
    }
}

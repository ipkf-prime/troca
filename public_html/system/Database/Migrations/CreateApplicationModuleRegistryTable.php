<?php

namespace IPKF\Database\Migrations;

class CreateApplicationModuleRegistryTable extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS application_modules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                display_name VARCHAR(190) NOT NULL,
                base_url VARCHAR(500) NOT NULL,
                sso_callback_url VARCHAR(500) NULL,
                database_connection_name VARCHAR(150) CHARACTER SET ascii COLLATE ascii_bin NULL,
                database_host VARCHAR(255) NULL,
                database_port SMALLINT UNSIGNED NULL,
                database_name VARCHAR(190) NULL,
                secret_reference VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY application_modules_key_unique (module_key),
                INDEX application_modules_active_sort_index (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
    }
}

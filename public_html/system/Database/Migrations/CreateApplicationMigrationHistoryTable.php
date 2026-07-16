<?php

namespace IPKF\Database\Migrations;

class CreateApplicationMigrationHistoryTable extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS application_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                application_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                connection_name VARCHAR(150) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                migration VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY application_migrations_unique (application_code, connection_name, migration),
                INDEX application_migrations_app_index (application_code),
                INDEX application_migrations_connection_index (connection_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
    }
}

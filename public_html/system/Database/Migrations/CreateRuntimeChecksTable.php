<?php

namespace IPKF\Database\Migrations;

class CreateRuntimeChecksTable extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ipkf_runtime_checks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                check_key VARCHAR(100) NOT NULL,
                check_value VARCHAR(255) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY ipkf_runtime_checks_check_key_unique (check_key)
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS ipkf_runtime_checks");
    }
}

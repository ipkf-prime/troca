<?php

namespace IPKF\Database\Migrations;

class CreateAuthenticationLoginHistoryTable extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS auth_login_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                role_assignment_id BIGINT UNSIGNED NULL,
                role_code_snapshot VARCHAR(100) NULL,
                role_title_snapshot VARCHAR(190) NULL,
                auth_method VARCHAR(40) NOT NULL DEFAULT 'session',
                mfa_verified TINYINT(1) NOT NULL DEFAULT 0,
                session_hash CHAR(64) NULL,
                ip_address VARCHAR(64) NULL,
                user_agent TEXT NULL,
                browser_label VARCHAR(190) NULL,
                logged_in_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX auth_login_history_user_time_index (
                    user_id,
                    logged_in_at,
                    id
                ),
                INDEX auth_login_history_role_assignment_index (
                    role_assignment_id
                ),
                CONSTRAINT auth_login_history_user_fk
                    FOREIGN KEY (user_id)
                    REFERENCES users (id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT,
                CONSTRAINT auth_login_history_role_assignment_fk
                    FOREIGN KEY (role_assignment_id)
                    REFERENCES user_role_assignments (id)
                    ON DELETE SET NULL
                    ON UPDATE RESTRICT
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
    }
}

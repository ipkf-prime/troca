<?php

namespace IPKF\Database\Migrations;

class CreateIdentityAccessFoundationTables extends Migration
{
    public function up(): void
    {
        $this->ensureIdentityColumns();
        $this->ensureRolesPriority();
        $this->createLoginTokensTable();
        $this->createIdentityChangeRequestsTable();
        $this->createMfaDeliveryChallengesTable();
    }

    public function down(): void
    {
    }

    private function ensureIdentityColumns(): void
    {
        foreach ([
            'users' => [
                'username_norm' => 'VARCHAR(100) NULL',
                'email_norm' => 'VARCHAR(150) NULL',
                'mobile_norm' => 'VARCHAR(20) NULL',
            ],
            'persons' => [
                'email_norm' => 'VARCHAR(150) NULL',
                'mobile_norm' => 'VARCHAR(20) NULL',
            ],
        ] as $table => $columns) {
            foreach ($columns as $column => $definition) {
                if (!$this->columnExists($table, $column)) {
                    $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                }

                $this->addIndexIfMissing($table, "{$table}_{$column}_index", $column);
            }
        }

        $this->db->exec("UPDATE users SET username_norm = LOWER(username) WHERE username IS NOT NULL AND username_norm IS NULL");
        $this->db->exec("UPDATE users SET email_norm = LOWER(email) WHERE email IS NOT NULL AND email_norm IS NULL");
        $this->db->exec("UPDATE users SET mobile_norm = mobile WHERE mobile REGEXP '^09[0-9]{9}$' AND mobile_norm IS NULL");
        $this->db->exec("UPDATE persons SET email_norm = LOWER(email) WHERE email IS NOT NULL AND email_norm IS NULL");
        $this->db->exec("UPDATE persons SET mobile_norm = mobile WHERE mobile REGEXP '^09[0-9]{9}$' AND mobile_norm IS NULL");
    }

    private function ensureRolesPriority(): void
    {
        if (!$this->columnExists('roles', 'priority')) {
            $this->db->exec('ALTER TABLE roles ADD COLUMN priority INT DEFAULT 100');
            $this->addIndexIfMissing('roles', 'roles_priority_index', 'priority');
        }
    }

    private function createLoginTokensTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS auth_login_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                purpose VARCHAR(80) NOT NULL,
                source VARCHAR(80) NULL,
                redirect_path VARCHAR(255) NULL,
                expires_at TIMESTAMP NULL,
                used_at TIMESTAMP NULL,
                revoked_at TIMESTAMP NULL,
                created_by_user_id BIGINT UNSIGNED NULL,
                created_ip VARCHAR(64) NULL,
                consumed_ip VARCHAR(64) NULL,
                created_user_agent TEXT NULL,
                consumed_user_agent TEXT NULL,
                metadata_json TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY auth_login_tokens_token_hash_unique (token_hash),
                INDEX auth_login_tokens_user_id_index (user_id),
                INDEX auth_login_tokens_purpose_index (purpose),
                INDEX auth_login_tokens_expires_at_index (expires_at),
                INDEX auth_login_tokens_used_at_index (used_at),
                INDEX auth_login_tokens_revoked_at_index (revoked_at)
            )
        ");
    }

    private function createIdentityChangeRequestsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS identity_change_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                field_name VARCHAR(30) NOT NULL,
                old_value VARCHAR(255) NULL,
                new_value VARCHAR(255) NOT NULL,
                normalized_new_value VARCHAR(255) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                channel VARCHAR(30) NOT NULL,
                expires_at TIMESTAMP NULL,
                verified_at TIMESTAMP NULL,
                applied_at TIMESTAMP NULL,
                cancelled_at TIMESTAMP NULL,
                attempts INT DEFAULT 0,
                created_ip VARCHAR(64) NULL,
                verified_ip VARCHAR(64) NULL,
                created_user_agent TEXT NULL,
                verified_user_agent TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX identity_change_requests_user_id_index (user_id),
                INDEX identity_change_requests_field_name_index (field_name),
                INDEX identity_change_requests_normalized_new_value_index (normalized_new_value),
                INDEX identity_change_requests_expires_at_index (expires_at),
                INDEX identity_change_requests_applied_at_index (applied_at)
            )
        ");
    }

    private function createMfaDeliveryChallengesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mfa_delivery_challenges (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                method VARCHAR(30) NOT NULL,
                purpose VARCHAR(80) NOT NULL DEFAULT 'mfa_login',
                code_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NULL,
                consumed_at TIMESTAMP NULL,
                attempts INT DEFAULT 0,
                created_ip VARCHAR(64) NULL,
                created_user_agent TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX mfa_delivery_challenges_user_id_index (user_id),
                INDEX mfa_delivery_challenges_method_index (method),
                INDEX mfa_delivery_challenges_expires_at_index (expires_at),
                INDEX mfa_delivery_challenges_consumed_at_index (consumed_at)
            )
        ");
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function addIndexIfMissing(string $table, string $indexName, string $column): void
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
        ");
        $statement->execute([$table, $indexName]);

        if ((int) $statement->fetchColumn() === 0) {
            $this->db->exec("ALTER TABLE {$table} ADD INDEX {$indexName} ({$column})");
        }
    }
}

<?php

namespace IPKF\Database\Migrations;

class CreateAuthRbacSchemaTables extends Migration
{
    public function up(): void
    {
        $this->createPersonsTable();
        $this->createUsersTable();
        $this->createRoleAreasTable();
        $this->createRoleKindsTable();
        $this->createRolesTable();
        $this->createPermissionsTable();
        $this->createRolePermissionsTable();
        $this->ensureOrganizationsHierarchy();
        $this->createUserRoleAssignmentsTable();
        $this->createMfaTables();
    }

    public function down(): void
    {
        foreach ([
            'recovery_codes',
            'trusted_devices',
            'mfa_challenges',
            'user_mfa_methods',
            'user_role_assignments',
            'role_permissions',
            'permissions',
            'roles',
            'role_kinds',
            'role_areas',
            'users',
            'persons',
        ] as $table) {
            $this->db->exec("DROP TABLE IF EXISTS {$table}");
        }
    }

    private function createPersonsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS persons (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                person_type VARCHAR(30) NOT NULL DEFAULT 'individual',
                national_code VARCHAR(20) NULL,
                first_name VARCHAR(100) NULL,
                last_name VARCHAR(100) NULL,
                full_name VARCHAR(255) NOT NULL,
                father_name VARCHAR(100) NULL,
                birth_date DATE NULL,
                registration_date DATE NULL,
                registration_place VARCHAR(150) NULL,
                registration_number VARCHAR(50) NULL,
                economic_code VARCHAR(50) NULL,
                gender VARCHAR(30) NULL,
                mobile VARCHAR(20) NULL,
                phone VARCHAR(20) NULL,
                email VARCHAR(150) NULL,
                postal_code VARCHAR(20) NULL,
                address TEXT NULL,
                province_id BIGINT NULL,
                city_id BIGINT NULL,
                avatar VARCHAR(255) NULL,
                is_deceased TINYINT(1) DEFAULT 0,
                deceased_at DATE NULL,
                status VARCHAR(30) DEFAULT 'active',
                created_by BIGINT NULL,
                updated_by BIGINT NULL,
                deleted_by BIGINT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL,
                INDEX persons_national_code_index (national_code),
                INDEX persons_mobile_index (mobile),
                INDEX persons_email_index (email),
                INDEX persons_province_id_index (province_id),
                INDEX persons_city_id_index (city_id),
                INDEX persons_status_index (status)
            )
        ");
    }

    private function createUsersTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                person_id BIGINT UNSIGNED NULL,
                username VARCHAR(100) NULL,
                email VARCHAR(150) NULL,
                mobile VARCHAR(20) NULL,
                password_hash VARCHAR(255) NOT NULL,
                status VARCHAR(30) DEFAULT 'active',
                email_verified_at TIMESTAMP NULL,
                mobile_verified_at TIMESTAMP NULL,
                last_login_at TIMESTAMP NULL,
                last_password_change_at TIMESTAMP NULL,
                force_password_change TINYINT(1) DEFAULT 0,
                failed_login_attempts INT DEFAULT 0,
                locked_until TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL,
                INDEX users_person_id_index (person_id),
                INDEX users_username_index (username),
                INDEX users_email_index (email),
                INDEX users_mobile_index (mobile),
                INDEX users_status_index (status)
            )
        ");
    }

    private function createRoleAreasTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS role_areas (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(80) NOT NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT NULL,
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY role_areas_code_unique (code)
            )
        ");
    }

    private function createRoleKindsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS role_kinds (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(80) NOT NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY role_kinds_code_unique (code)
            )
        ");
    }

    private function createRolesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                legacy_code INT NULL,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT NULL,
                role_area_id BIGINT UNSIGNED NULL,
                role_kind_id BIGINT UNSIGNED NULL,
                is_system TINYINT(1) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                is_editable TINYINT(1) DEFAULT 1,
                is_deletable TINYINT(1) DEFAULT 1,
                can_send_sms TINYINT(1) DEFAULT 0,
                requires_center TINYINT(1) DEFAULT 0,
                can_manage_other_users TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY roles_code_unique (code),
                INDEX roles_legacy_code_index (legacy_code),
                INDEX roles_role_area_id_index (role_area_id),
                INDEX roles_role_kind_id_index (role_kind_id),
                INDEX roles_is_active_index (is_active)
            )
        ");
    }

    private function createPermissionsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(150) NOT NULL,
                module VARCHAR(80) NOT NULL,
                resource VARCHAR(80) NOT NULL,
                action VARCHAR(80) NOT NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY permissions_code_unique (code),
                INDEX permissions_module_index (module),
                INDEX permissions_resource_index (resource),
                INDEX permissions_action_index (action),
                INDEX permissions_is_active_index (is_active)
            )
        ");
    }

    private function createRolePermissionsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_id BIGINT UNSIGNED NOT NULL,
                permission_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL,
                INDEX role_permissions_role_id_index (role_id),
                INDEX role_permissions_permission_id_index (permission_id),
                UNIQUE KEY role_permissions_role_permission_unique (role_id, permission_id)
            )
        ");
    }

    private function ensureOrganizationsHierarchy(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS organizations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id BIGINT NULL,
                legacy_code BIGINT NULL,
                province_id BIGINT NULL,
                city_id BIGINT NULL,
                geo_level_id BIGINT NULL,
                org_level_id BIGINT NULL,
                org_type_id BIGINT NULL,
                org_reg_id BIGINT NULL,
                title VARCHAR(255) NOT NULL,
                short_title VARCHAR(150) NULL,
                requires_org_type TINYINT(1) DEFAULT 0,
                depth TINYINT UNSIGNED DEFAULT 0,
                path VARCHAR(500) NULL,
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            )
        ");

        $columns = [
            'parent_id' => 'BIGINT NULL',
            'legacy_code' => 'BIGINT NULL',
            'province_id' => 'BIGINT NULL',
            'city_id' => 'BIGINT NULL',
            'geo_level_id' => 'BIGINT NULL',
            'org_level_id' => 'BIGINT NULL',
            'org_type_id' => 'BIGINT NULL',
            'org_reg_id' => 'BIGINT NULL',
            'title' => 'VARCHAR(255) NULL',
            'short_title' => 'VARCHAR(150) NULL',
            'requires_org_type' => 'TINYINT(1) DEFAULT 0',
            'depth' => 'TINYINT UNSIGNED DEFAULT 0',
            'path' => 'VARCHAR(500) NULL',
            'sort_order' => 'INT DEFAULT 0',
            'is_active' => 'TINYINT(1) DEFAULT 1',
            'created_at' => 'TIMESTAMP NULL',
            'updated_at' => 'TIMESTAMP NULL',
            'deleted_at' => 'TIMESTAMP NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('organizations', $column)) {
                $this->db->exec("ALTER TABLE organizations ADD COLUMN {$column} {$definition}");
            }
        }

        foreach ([
            'parent_id',
            'legacy_code',
            'province_id',
            'city_id',
            'org_level_id',
            'org_type_id',
            'depth',
            'path',
            'is_active',
        ] as $column) {
            $this->addIndexIfMissing('organizations', "organizations_{$column}_index", $column);
        }
    }

    private function createUserRoleAssignmentsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_role_assignments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                scope_type VARCHAR(50) NOT NULL DEFAULT 'global',
                scope_id BIGINT NULL,
                organization_id BIGINT NULL,
                include_children TINYINT(1) DEFAULT 0,
                province_id BIGINT NULL,
                city_id BIGINT NULL,
                county_id BIGINT NULL,
                district_id BIGINT NULL,
                village_id BIGINT NULL,
                company_id BIGINT NULL,
                center_id BIGINT NULL,
                warehouse_id BIGINT NULL,
                fiscal_year_id BIGINT NULL,
                starts_at TIMESTAMP NULL,
                ends_at TIMESTAMP NULL,
                is_active TINYINT(1) DEFAULT 1,
                assigned_by BIGINT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX user_role_assignments_user_id_index (user_id),
                INDEX user_role_assignments_role_id_index (role_id),
                INDEX user_role_assignments_scope_type_index (scope_type),
                INDEX user_role_assignments_scope_id_index (scope_id),
                INDEX user_role_assignments_organization_id_index (organization_id),
                INDEX user_role_assignments_fiscal_year_id_index (fiscal_year_id),
                INDEX user_role_assignments_is_active_index (is_active),
                INDEX user_role_assignments_include_children_index (include_children)
            )
        ");
    }

    private function createMfaTables(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_mfa_methods (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                method VARCHAR(30) NOT NULL,
                label VARCHAR(100) NULL,
                secret_encrypted TEXT NULL,
                destination_masked VARCHAR(150) NULL,
                is_primary TINYINT(1) DEFAULT 0,
                is_enabled TINYINT(1) DEFAULT 1,
                verified_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX user_mfa_methods_user_id_index (user_id),
                INDEX user_mfa_methods_method_index (method),
                INDEX user_mfa_methods_is_enabled_index (is_enabled)
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mfa_challenges (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                method VARCHAR(30) NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NULL,
                consumed_at TIMESTAMP NULL,
                attempts INT DEFAULT 0,
                ip_address VARCHAR(64) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP NULL,
                INDEX mfa_challenges_user_id_index (user_id),
                INDEX mfa_challenges_method_index (method),
                INDEX mfa_challenges_expires_at_index (expires_at),
                INDEX mfa_challenges_consumed_at_index (consumed_at)
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS trusted_devices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                device_token_hash VARCHAR(255) NOT NULL,
                device_name VARCHAR(150) NULL,
                ip_address VARCHAR(64) NULL,
                user_agent TEXT NULL,
                expires_at TIMESTAMP NULL,
                revoked_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                INDEX trusted_devices_user_id_index (user_id),
                INDEX trusted_devices_expires_at_index (expires_at),
                INDEX trusted_devices_revoked_at_index (revoked_at)
            )
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS recovery_codes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                used_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                INDEX recovery_codes_user_id_index (user_id)
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

    private function addIndexIfMissing(string $table, string $index, string $column): void
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
        ");

        $statement->execute([$table, $index]);

        if ((int) $statement->fetchColumn() === 0) {
            $this->db->exec("ALTER TABLE {$table} ADD INDEX {$index} ({$column})");
        }
    }
}

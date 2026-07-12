<?php

namespace IPKF\Database\Migrations;

class CreateAdminUsersOrganizationTables extends Migration
{
    public function up(): void
    {
        $this->createOrgUnitsTable();
        $this->createPositionsTable();
        $this->createUserOrgAssignmentsTable();
    }

    public function down(): void
    {
    }

    private function createOrgUnitsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS org_units (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id BIGINT UNSIGNED NULL,
                code VARCHAR(100) NULL,
                title VARCHAR(255) NOT NULL,
                type VARCHAR(80) NULL,
                path VARCHAR(500) NULL,
                depth INT UNSIGNED NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                description TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL,
                UNIQUE KEY org_units_code_unique (code),
                INDEX org_units_parent_id_index (parent_id),
                INDEX org_units_type_index (type),
                INDEX org_units_path_index (path),
                INDEX org_units_depth_index (depth),
                INDEX org_units_sort_order_index (sort_order),
                INDEX org_units_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec('ALTER TABLE org_units CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function createPositionsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS positions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY positions_code_unique (code),
                INDEX positions_status_index (status),
                INDEX positions_sort_order_index (sort_order)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec('ALTER TABLE positions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function createUserOrgAssignmentsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_org_assignments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                org_unit_id BIGINT UNSIGNED NOT NULL,
                position_id BIGINT UNSIGNED NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                started_at TIMESTAMP NULL,
                ended_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX user_org_assignments_user_id_index (user_id),
                INDEX user_org_assignments_org_unit_id_index (org_unit_id),
                INDEX user_org_assignments_position_id_index (position_id),
                INDEX user_org_assignments_status_index (status),
                INDEX user_org_assignments_user_org_unit_index (user_id, org_unit_id),
                INDEX user_org_assignments_user_primary_index (user_id, is_primary)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec('ALTER TABLE user_org_assignments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
}

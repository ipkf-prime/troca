<?php

namespace IPKF\Database\Migrations;

class CreateModuleReferenceDataTables extends Migration
{
    public function up(): void
    {
        foreach ($this->statements() as $statement) {
            $this->db->exec($statement);
        }
    }

    public function down(): void
    {
    }

    private function statements(): array
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return [
            "CREATE TABLE IF NOT EXISTS module_reference_groups (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(190) NOT NULL,
                description VARCHAR(1000) NULL,
                management_mode VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'dynamic',
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY module_reference_groups_unique (module_code, code),
                INDEX module_reference_groups_module_sort_index (module_code, is_active, sort_order)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS module_reference_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                group_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title_fa VARCHAR(190) NOT NULL,
                title_en VARCHAR(190) NULL,
                color VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                is_locked TINYINT(1) NOT NULL DEFAULT 0,
                metadata_json LONGTEXT NULL,
                created_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                updated_by_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY module_reference_items_unique (group_id, code),
                INDEX module_reference_items_group_sort_index (group_id, is_active, sort_order),
                CONSTRAINT module_reference_items_group_fk FOREIGN KEY (group_id)
                    REFERENCES module_reference_groups (id) ON DELETE CASCADE ON UPDATE RESTRICT
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS module_reference_audit_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                group_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                item_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
                action_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                actor_user_reference VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                actor_display_name_snapshot VARCHAR(255) NOT NULL,
                before_json LONGTEXT NULL,
                after_json LONGTEXT NULL,
                occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX module_reference_audit_scope_time_index
                    (module_code, group_code, occurred_at),
                INDEX module_reference_audit_actor_time_index
                    (actor_user_reference, occurred_at)
            ) {$options}",
        ];
    }
}

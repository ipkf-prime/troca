<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

class CreateWorkExternalSourceBridgeFoundation extends Migration
{
    public function up(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS work_project_source_bindings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_reference VARCHAR(64) NOT NULL,
                work_project_id BIGINT UNSIGNED NOT NULL,
                source_module_code VARCHAR(64) NOT NULL,
                source_resource_type VARCHAR(64) NOT NULL,
                source_reference VARCHAR(191) NOT NULL,
                binding_role_code VARCHAR(64) NOT NULL DEFAULT 'default',
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                metadata_json JSON NULL,
                created_by_user_reference VARCHAR(128) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY work_project_source_bindings_reference_unique (public_reference),
                UNIQUE KEY work_project_source_bindings_identity_unique (
                    work_project_id,
                    source_module_code,
                    source_resource_type,
                    source_reference,
                    binding_role_code
                ),
                INDEX work_project_source_bindings_source_lookup_index (
                    source_module_code,
                    source_resource_type,
                    source_reference,
                    binding_role_code,
                    status,
                    is_primary
                ),
                INDEX work_project_source_bindings_project_status_index (
                    work_project_id,
                    status
                ),
                CONSTRAINT work_project_source_bindings_project_fk
                    FOREIGN KEY (work_project_id)
                    REFERENCES work_projects (id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS work_item_source_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_reference VARCHAR(64) NOT NULL,
                work_item_id BIGINT UNSIGNED NOT NULL,
                source_module_code VARCHAR(64) NOT NULL,
                source_resource_type VARCHAR(64) NOT NULL,
                source_reference VARCHAR(191) NOT NULL,
                relation_type_code VARCHAR(64) NOT NULL DEFAULT 'related_to',
                metadata_json JSON NULL,
                created_by_user_reference VARCHAR(128) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY work_item_source_links_reference_unique (public_reference),
                UNIQUE KEY work_item_source_links_identity_unique (
                    work_item_id,
                    source_module_code,
                    source_resource_type,
                    source_reference,
                    relation_type_code
                ),
                INDEX work_item_source_links_source_lookup_index (
                    source_module_code,
                    source_resource_type,
                    source_reference,
                    relation_type_code
                ),
                INDEX work_item_source_links_item_index (work_item_id),
                CONSTRAINT work_item_source_links_item_fk
                    FOREIGN KEY (work_item_id)
                    REFERENCES work_items (id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS work_item_source_links');
        $this->db->exec('DROP TABLE IF EXISTS work_project_source_bindings');
    }
}

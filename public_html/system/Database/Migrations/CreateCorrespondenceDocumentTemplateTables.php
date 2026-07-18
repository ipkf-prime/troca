<?php

namespace IPKF\Database\Migrations;

use PDO;

class CreateCorrespondenceDocumentTemplateTables extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_document_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title_fa VARCHAR(255) NOT NULL,
                title_en VARCHAR(255) NULL,
                language_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                page_size_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                orientation_code VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'portrait',
                signature_slots TINYINT UNSIGNED NOT NULL DEFAULT 1,
                current_version_id BIGINT UNSIGNED NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY corr_doc_templates_reference_unique (public_reference),
                UNIQUE KEY corr_doc_templates_code_unique (code),
                INDEX corr_doc_templates_status_sort_index (status, sort_order),
                INDEX corr_doc_templates_current_version_index (current_version_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS correspondence_document_template_versions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NOT NULL,
                version_number INT UNSIGNED NOT NULL,
                header_schema_json LONGTEXT NOT NULL,
                footer_schema_json LONGTEXT NOT NULL,
                page_schema_json LONGTEXT NOT NULL,
                signature_schema_json LONGTEXT NOT NULL,
                content_checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                UNIQUE KEY corr_doc_template_versions_unique (template_id, version_number),
                INDEX corr_doc_template_versions_status_index (status),
                CONSTRAINT corr_doc_template_versions_template_fk FOREIGN KEY (template_id)
                    REFERENCES correspondence_document_templates (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumnIfMissing('correspondences', 'document_template_version_id', 'BIGINT UNSIGNED NULL AFTER summary');
        $this->addColumnIfMissing('correspondence_versions', 'document_template_snapshot_json', 'LONGTEXT NULL AFTER summary_snapshot');
    }

    public function down(): void
    {
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        if ((int) $statement->fetchColumn() === 0) {
            $this->db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

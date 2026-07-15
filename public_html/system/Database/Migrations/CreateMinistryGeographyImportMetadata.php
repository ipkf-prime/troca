<?php

namespace IPKF\Database\Migrations;

class CreateMinistryGeographyImportMetadata extends Migration
{
    public function up(): void
    {
        $this->createSourceLevelMappingsTable();
        $this->createSourceImportSettingsTable();
    }

    public function down(): void
    {
    }

    private function createSourceLevelMappingsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_source_level_mappings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                source_type_value VARCHAR(100) NOT NULL,
                geographic_level_code VARCHAR(100) NOT NULL,
                parent_geographic_level_code VARCHAR(100) NULL,
                expected_code_length INT UNSIGNED NULL,
                parent_prefix_length INT UNSIGNED NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geo_source_level_source_type_unique (source_id, source_type_value),
                INDEX geo_source_level_source_index (source_id),
                INDEX geo_source_level_code_index (geographic_level_code),
                INDEX geo_source_level_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'geographic_source_level_mappings',
            'geo_source_level_source_foreign',
            'source_id',
            'data_sources',
            'id',
            'RESTRICT'
        );
    }

    private function createSourceImportSettingsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_source_import_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                setting_key VARCHAR(120) NOT NULL,
                setting_value LONGTEXT NULL,
                value_type VARCHAR(30) NOT NULL DEFAULT 'string',
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY data_source_import_setting_unique (source_id, setting_key),
                INDEX data_source_import_setting_source_index (source_id),
                INDEX data_source_import_setting_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'data_source_import_settings',
            'data_source_import_setting_source_foreign',
            'source_id',
            'data_sources',
            'id',
            'RESTRICT'
        );
    }

    private function addForeignKeyIfPossible(
        string $table,
        string $constraint,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete
    ): void {
        if (!$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || $this->foreignKeyExists($table, $constraint)
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys($referenceTable)
            || $this->columnType($table, $column) !== $this->columnType($referenceTable, $referenceColumn)
        ) {
            return;
        }

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column}) REFERENCES {$referenceTable} ({$referenceColumn})
            ON UPDATE CASCADE ON DELETE {$onDelete}
        ");
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnType(string $table, string $column): string
    {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);

        return strtolower((string) $statement->fetchColumn());
    }

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower((string) $statement->fetchColumn()) === 'innodb';
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type = 'FOREIGN KEY'
        ");
        $statement->execute([$table, $constraint]);

        return (int) $statement->fetchColumn() > 0;
    }
}

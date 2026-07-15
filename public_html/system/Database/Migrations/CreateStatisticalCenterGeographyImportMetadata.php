<?php

namespace IPKF\Database\Migrations;

class CreateStatisticalCenterGeographyImportMetadata extends Migration
{
    public function up(): void
    {
        $this->createSourceRecordTypeMappingsTable();
        $this->extendImportRowsTable();
    }

    public function down(): void
    {
    }

    private function createSourceRecordTypeMappingsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_source_record_type_mappings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                source_record_type VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                source_title VARCHAR(255) NOT NULL,
                derived_level_code VARCHAR(100) NULL,
                source_entity_kind VARCHAR(100) NOT NULL,
                parent_record_type VARCHAR(100) NULL,
                code_field VARCHAR(100) NOT NULL,
                parent_code_fields_json LONGTEXT NULL,
                canonical_auto_match_allowed TINYINT(1) NOT NULL DEFAULT 0,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geo_source_record_type_unique (source_id, source_record_type),
                INDEX geo_source_record_source_index (source_id),
                INDEX geo_source_record_kind_index (source_entity_kind),
                INDEX geo_source_record_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'geographic_source_record_type_mappings',
            'geo_source_record_source_foreign',
            'source_id',
            'data_sources',
            'id',
            'RESTRICT'
        );
    }

    private function extendImportRowsTable(): void
    {
        if (!$this->tableExists('geographic_import_rows')) {
            return;
        }

        $this->addColumnIfMissing('geographic_import_rows', 'source_entity_kind', 'VARCHAR(100) NULL');
        $this->addColumnIfMissing('geographic_import_rows', 'source_local_code', 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');
        $this->addColumnIfMissing('geographic_import_rows', 'source_composite_key', 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');
        $this->addColumnIfMissing('geographic_import_rows', 'source_parent_composite_key', 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');
        $this->addColumnIfMissing('geographic_import_rows', 'source_parent_record_type', 'VARCHAR(100) NULL');
        $this->addColumnIfMissing('geographic_import_rows', 'source_classifier_code', 'VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');
        $this->addColumnIfMissing('geographic_import_rows', 'normalized_source_code', 'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');

        $this->addIndexIfMissing('geographic_import_rows', 'geo_import_rows_batch_type_index', 'batch_id, source_record_type');
        $this->addIndexIfMissing('geographic_import_rows', 'geo_import_rows_batch_composite_index', 'batch_id, source_composite_key(180)');
        $this->addIndexIfMissing('geographic_import_rows', 'geo_import_rows_batch_local_code_index', 'batch_id, source_local_code(180)');
        $this->addIndexIfMissing('geographic_import_rows', 'geo_import_rows_batch_parent_key_index', 'batch_id, source_parent_composite_key(180)');
        $this->addIndexIfMissing('geographic_import_rows', 'geo_import_rows_batch_validation_index', 'batch_id, validation_status');
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private function addIndexIfMissing(string $table, string $index, string $columns): void
    {
        if (!$this->indexExists($table, $index)) {
            $this->db->exec("ALTER TABLE {$table} ADD INDEX {$index} ({$columns})");
        }
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
        return $this->schemaCount('information_schema.tables', 'table_name', $table) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
        ");
        $statement->execute([$table, $index]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnType(string $table, string $column): string
    {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1
        ");
        $statement->execute([$table, $column]);

        return strtolower((string) $statement->fetchColumn());
    }

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower((string) $statement->fetchColumn()) === 'innodb';
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE() AND table_name = ?
              AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'
        ");
        $statement->execute([$table, $constraint]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function schemaCount(string $schemaTable, string $column, string $value): int
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM {$schemaTable}
            WHERE table_schema = DATABASE() AND {$column} = ?
        ");
        $statement->execute([$value]);

        return (int) $statement->fetchColumn();
    }
}

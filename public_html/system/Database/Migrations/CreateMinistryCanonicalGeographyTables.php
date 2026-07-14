<?php

namespace IPKF\Database\Migrations;

class CreateMinistryCanonicalGeographyTables extends Migration
{
    public function up(): void
    {
        $this->createRunsTable();
        $this->createItemsTable();
    }

    public function down(): void
    {
    }

    private function createRunsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_canonicalization_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                source_snapshot_id BIGINT UNSIGNED NOT NULL,
                source_batch_id BIGINT UNSIGNED NOT NULL,
                canonicalization_type VARCHAR(100) NOT NULL,
                algorithm_version VARCHAR(60) NOT NULL,
                plan_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                plan_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                source_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'planning',
                planned_at TIMESTAMP NULL,
                applied_at TIMESTAMP NULL,
                total_source_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                eligible_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                excluded_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                create_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                reuse_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                conflict_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                relation_create_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                identifier_create_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                mapping_create_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                summary_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geo_canonical_runs_reference_unique (plan_reference),
                UNIQUE KEY geo_canonical_runs_boundary_unique (
                    source_snapshot_id, canonicalization_type, algorithm_version
                ),
                INDEX geo_canonical_runs_source_index (source_id),
                INDEX geo_canonical_runs_snapshot_index (source_snapshot_id),
                INDEX geo_canonical_runs_batch_index (source_batch_id),
                INDEX geo_canonical_runs_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_canonicalization_runs', 'geo_canonical_runs_source_foreign', 'source_id', 'data_sources', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_canonicalization_runs', 'geo_canonical_runs_snapshot_foreign', 'source_snapshot_id', 'data_source_snapshots', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_canonicalization_runs', 'geo_canonical_runs_batch_foreign', 'source_batch_id', 'geographic_import_batches', 'id', 'RESTRICT');
    }

    private function createItemsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_canonicalization_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                canonicalization_run_id BIGINT UNSIGNED NOT NULL,
                import_row_id BIGINT UNSIGNED NOT NULL,
                action_type VARCHAR(30) NOT NULL,
                item_status VARCHAR(30) NOT NULL DEFAULT 'planned',
                existing_geographic_location_id BIGINT UNSIGNED NULL,
                resulting_geographic_location_id BIGINT UNSIGNED NULL,
                parent_import_row_id BIGINT UNSIGNED NULL,
                resulting_parent_location_id BIGINT UNSIGNED NULL,
                reason_code VARCHAR(100) NULL,
                review_status VARCHAR(30) NOT NULL DEFAULT 'pending',
                source_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geo_canonical_items_run_row_unique (canonicalization_run_id, import_row_id),
                INDEX geo_canonical_items_run_action_index (canonicalization_run_id, action_type),
                INDEX geo_canonical_items_run_status_index (canonicalization_run_id, item_status),
                INDEX geo_canonical_items_run_review_index (canonicalization_run_id, review_status),
                INDEX geo_canonical_items_import_row_index (import_row_id),
                INDEX geo_canonical_items_existing_location_index (existing_geographic_location_id),
                INDEX geo_canonical_items_result_location_index (resulting_geographic_location_id),
                INDEX geo_canonical_items_parent_row_index (parent_import_row_id)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_canonicalization_items', 'geo_canonical_items_run_foreign', 'canonicalization_run_id', 'geographic_canonicalization_runs', 'id', 'CASCADE');
        $this->addForeignKeyIfPossible('geographic_canonicalization_items', 'geo_canonical_items_import_row_foreign', 'import_row_id', 'geographic_import_rows', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_canonicalization_items', 'geo_canonical_items_existing_location_foreign', 'existing_geographic_location_id', 'geographic_locations', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_canonicalization_items', 'geo_canonical_items_result_location_foreign', 'resulting_geographic_location_id', 'geographic_locations', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_canonicalization_items', 'geo_canonical_items_parent_row_foreign', 'parent_import_row_id', 'geographic_import_rows', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_canonicalization_items', 'geo_canonical_items_parent_location_foreign', 'resulting_parent_location_id', 'geographic_locations', 'id', 'RESTRICT');
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

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower((string) $statement->fetchColumn()) === 'innodb';
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
}

<?php

namespace IPKF\Database\Migrations;

class CreateMultiSourceCodingGeographyTables extends Migration
{
    public function up(): void
    {
        $this->createDataSourcesTable();
        $this->createAuthorityScopesTable();
        $this->createSourceSnapshotsTable();
        $this->createCodingSystemsTable();
        $this->createCodeSetsTable();
        $this->createCodeSegmentsTable();
        $this->createCodeValuesTable();
        $this->createGeographicHierarchyTypesTable();
        $this->extendGeographicLocationRelationsTable();
        $this->createGeographicExternalIdentifiersTable();
        $this->createGeographicExternalCodeMappingsTable();
        $this->createGeographicImportBatchesTable();
        $this->createGeographicImportRowsTable();
        $this->createGeographicImportIssuesTable();
        $this->createGeographicImportMatchCandidatesTable();
    }

    public function down(): void
    {
    }

    private function createDataSourcesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_sources (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                authority_name VARCHAR(255) NULL,
                source_kind VARCHAR(60) NOT NULL,
                country_iso_code VARCHAR(3) NULL,
                description TEXT NULL,
                default_priority INT NULL,
                is_authoritative TINYINT(1) NOT NULL DEFAULT 0,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY data_sources_code_unique (code),
                INDEX data_sources_kind_index (source_kind),
                INDEX data_sources_country_index (country_iso_code),
                INDEX data_sources_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function createAuthorityScopesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_source_authority_scopes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                domain_code VARCHAR(120) NOT NULL,
                title VARCHAR(255) NOT NULL,
                priority INT NOT NULL DEFAULT 100,
                is_authoritative TINYINT(1) NOT NULL DEFAULT 0,
                conflict_policy VARCHAR(100) NULL,
                description TEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY data_source_scopes_source_domain_unique (source_id, domain_code),
                INDEX data_source_scopes_source_index (source_id),
                INDEX data_source_scopes_domain_index (domain_code),
                INDEX data_source_scopes_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'data_source_authority_scopes',
            'data_source_scopes_source_foreign',
            'source_id',
            'data_sources',
            'id',
            'RESTRICT'
        );
    }

    private function createSourceSnapshotsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS data_source_snapshots (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                version_label VARCHAR(150) NULL,
                publication_date DATE NULL,
                observed_at TIMESTAMP NULL,
                source_filename VARCHAR(255) NULL,
                file_sha256 CHAR(64) NULL,
                file_size BIGINT UNSIGNED NULL,
                row_count BIGINT UNSIGNED NULL,
                schema_signature VARCHAR(191) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'registered',
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY data_source_snapshots_source_hash_unique (source_id, file_sha256),
                INDEX data_source_snapshots_source_index (source_id),
                INDEX data_source_snapshots_publication_index (publication_date),
                INDEX data_source_snapshots_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'data_source_snapshots',
            'data_source_snapshots_source_foreign',
            'source_id',
            'data_sources',
            'id',
            'RESTRICT'
        );
    }

    private function createCodingSystemsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS external_coding_systems (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                is_versioned TINYINT(1) NOT NULL DEFAULT 1,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY external_coding_systems_code_unique (code),
                INDEX external_coding_systems_source_index (source_id),
                INDEX external_coding_systems_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'external_coding_systems',
            'external_coding_systems_source_foreign',
            'source_id',
            'data_sources',
            'id',
            'RESTRICT'
        );
    }

    private function createCodeSetsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS external_code_sets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                coding_system_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                entity_domain VARCHAR(60) NOT NULL,
                expected_length INT UNSIGNED NULL,
                parent_code_set_id BIGINT UNSIGNED NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY external_code_sets_system_code_unique (coding_system_id, code),
                INDEX external_code_sets_system_index (coding_system_id),
                INDEX external_code_sets_domain_index (entity_domain),
                INDEX external_code_sets_parent_index (parent_code_set_id),
                INDEX external_code_sets_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'external_code_sets',
            'external_code_sets_system_foreign',
            'coding_system_id',
            'external_coding_systems',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'external_code_sets',
            'external_code_sets_parent_foreign',
            'parent_code_set_id',
            'external_code_sets',
            'id',
            'SET NULL'
        );
    }

    private function createCodeSegmentsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS external_code_segments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code_set_id BIGINT UNSIGNED NOT NULL,
                segment_code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                start_position INT UNSIGNED NOT NULL,
                segment_length INT UNSIGNED NOT NULL,
                referenced_code_set_id BIGINT UNSIGNED NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY external_code_segments_set_code_unique (code_set_id, segment_code),
                INDEX external_code_segments_set_index (code_set_id),
                INDEX external_code_segments_reference_index (referenced_code_set_id),
                INDEX external_code_segments_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'external_code_segments',
            'external_code_segments_set_foreign',
            'code_set_id',
            'external_code_sets',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'external_code_segments',
            'external_code_segments_reference_foreign',
            'referenced_code_set_id',
            'external_code_sets',
            'id',
            'SET NULL'
        );
    }

    private function createCodeValuesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS external_code_values (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code_set_id BIGINT UNSIGNED NOT NULL,
                source_snapshot_id BIGINT UNSIGNED NULL,
                code VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                normalized_title VARCHAR(255) NULL,
                parent_code_value_id BIGINT UNSIGNED NULL,
                external_status VARCHAR(60) NULL,
                valid_from DATE NULL,
                valid_to DATE NULL,
                raw_metadata_json LONGTEXT NULL,
                row_checksum CHAR(64) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY external_code_values_version_code_unique (code_set_id, source_snapshot_id, code),
                INDEX external_code_values_set_index (code_set_id),
                INDEX external_code_values_snapshot_index (source_snapshot_id),
                INDEX external_code_values_code_index (code),
                INDEX external_code_values_parent_index (parent_code_value_id),
                INDEX external_code_values_normalized_title_index (normalized_title(191))
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'external_code_values',
            'external_code_values_set_foreign',
            'code_set_id',
            'external_code_sets',
            'id',
            'RESTRICT'
        );
        $this->addForeignKeyIfPossible(
            'external_code_values',
            'external_code_values_snapshot_foreign',
            'source_snapshot_id',
            'data_source_snapshots',
            'id',
            'SET NULL'
        );
        $this->addForeignKeyIfPossible(
            'external_code_values',
            'external_code_values_parent_foreign',
            'parent_code_value_id',
            'external_code_values',
            'id',
            'SET NULL'
        );
    }

    private function createGeographicHierarchyTypesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_hierarchy_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                is_authoritative TINYINT(1) NOT NULL DEFAULT 0,
                supports_history TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geographic_hierarchy_types_code_unique (code),
                INDEX geographic_hierarchy_types_status_index (status),
                INDEX geographic_hierarchy_types_sort_index (sort_order)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

    }

    private function extendGeographicLocationRelationsTable(): void
    {
        if (!$this->tableExists('geographic_location_relations')) {
            return;
        }

        $this->addColumnIfMissing('geographic_location_relations', 'hierarchy_type_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfMissing('geographic_location_relations', 'source_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfMissing('geographic_location_relations', 'source_snapshot_id', 'BIGINT UNSIGNED NULL');
        $this->addColumnIfMissing('geographic_location_relations', 'review_status', 'VARCHAR(30) NULL');

        $this->addIndexIfMissing('geographic_location_relations', 'geo_relations_hierarchy_index', 'hierarchy_type_id');
        $this->addIndexIfMissing('geographic_location_relations', 'geo_relations_source_index', 'source_id');
        $this->addIndexIfMissing('geographic_location_relations', 'geo_relations_snapshot_index', 'source_snapshot_id');
        $this->addIndexIfMissing('geographic_location_relations', 'geo_relations_hierarchy_parent_index', 'hierarchy_type_id, parent_location_id');
        $this->addIndexIfMissing('geographic_location_relations', 'geo_relations_hierarchy_child_index', 'hierarchy_type_id, child_location_id');
        $this->addIndexIfMissing('geographic_location_relations', 'geo_relations_hierarchy_status_index', 'hierarchy_type_id, status');

        $this->addForeignKeyIfPossible(
            'geographic_location_relations',
            'geo_relations_hierarchy_foreign',
            'hierarchy_type_id',
            'geographic_hierarchy_types',
            'id',
            'SET NULL'
        );
        $this->addForeignKeyIfPossible(
            'geographic_location_relations',
            'geo_relations_source_foreign',
            'source_id',
            'data_sources',
            'id',
            'SET NULL'
        );
        $this->addForeignKeyIfPossible(
            'geographic_location_relations',
            'geo_relations_snapshot_foreign',
            'source_snapshot_id',
            'data_source_snapshots',
            'id',
            'SET NULL'
        );
    }

    private function createGeographicExternalIdentifiersTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_external_identifiers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                geographic_location_id BIGINT UNSIGNED NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                source_snapshot_id BIGINT UNSIGNED NULL,
                coding_system_id BIGINT UNSIGNED NULL,
                code_set_id BIGINT UNSIGNED NULL,
                identifier_type VARCHAR(60) NOT NULL,
                identifier_value VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                valid_from DATE NULL,
                valid_to DATE NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX geo_external_ids_location_index (geographic_location_id),
                INDEX geo_external_ids_source_index (source_id),
                INDEX geo_external_ids_value_index (identifier_value(150)),
                INDEX geo_external_ids_source_type_value_index (source_id, identifier_type, identifier_value(100)),
                INDEX geo_external_ids_set_value_index (code_set_id, identifier_value(150)),
                INDEX geo_external_ids_snapshot_index (source_snapshot_id)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_external_identifiers', 'geo_external_ids_location_foreign', 'geographic_location_id', 'geographic_locations', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_external_identifiers', 'geo_external_ids_source_foreign', 'source_id', 'data_sources', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_external_identifiers', 'geo_external_ids_snapshot_foreign', 'source_snapshot_id', 'data_source_snapshots', 'id', 'SET NULL');
        $this->addForeignKeyIfPossible('geographic_external_identifiers', 'geo_external_ids_system_foreign', 'coding_system_id', 'external_coding_systems', 'id', 'SET NULL');
        $this->addForeignKeyIfPossible('geographic_external_identifiers', 'geo_external_ids_set_foreign', 'code_set_id', 'external_code_sets', 'id', 'SET NULL');
    }

    private function createGeographicExternalCodeMappingsTable(): void
    {
        $userIdType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_external_code_mappings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                external_code_value_id BIGINT UNSIGNED NOT NULL,
                geographic_location_id BIGINT UNSIGNED NOT NULL,
                mapping_status VARCHAR(30) NOT NULL DEFAULT 'proposed',
                match_method VARCHAR(60) NULL,
                confidence_score DECIMAL(5,2) NULL,
                reviewed_by_user_id {$userIdType} NULL,
                reviewed_at TIMESTAMP NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX geo_external_mappings_value_index (external_code_value_id),
                INDEX geo_external_mappings_location_index (geographic_location_id),
                INDEX geo_external_mappings_status_index (mapping_status),
                INDEX geo_external_mappings_reviewer_index (reviewed_by_user_id)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_external_code_mappings', 'geo_external_mappings_value_foreign', 'external_code_value_id', 'external_code_values', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_external_code_mappings', 'geo_external_mappings_location_foreign', 'geographic_location_id', 'geographic_locations', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_external_code_mappings', 'geo_external_mappings_reviewer_foreign', 'reviewed_by_user_id', 'users', 'id', 'SET NULL');
    }

    private function createGeographicImportBatchesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_import_batches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                source_snapshot_id BIGINT UNSIGNED NULL,
                import_mode VARCHAR(60) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                total_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                valid_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                warning_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                invalid_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                summary_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX geo_import_batches_source_index (source_id),
                INDEX geo_import_batches_snapshot_index (source_snapshot_id),
                INDEX geo_import_batches_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_import_batches', 'geo_import_batches_source_foreign', 'source_id', 'data_sources', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_import_batches', 'geo_import_batches_snapshot_foreign', 'source_snapshot_id', 'data_source_snapshots', 'id', 'SET NULL');
    }

    private function createGeographicImportRowsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_import_rows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id BIGINT UNSIGNED NOT NULL,
                source_row_number BIGINT UNSIGNED NULL,
                source_record_type VARCHAR(100) NULL,
                source_code VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
                source_title VARCHAR(255) NULL,
                source_parent_code VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
                normalized_title VARCHAR(255) NULL,
                derived_level_code VARCHAR(100) NULL,
                derived_parent_code VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
                row_checksum CHAR(64) NULL,
                validation_status VARCHAR(30) NOT NULL DEFAULT 'pending',
                raw_payload_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX geo_import_rows_batch_index (batch_id),
                INDEX geo_import_rows_source_code_index (source_code(150)),
                INDEX geo_import_rows_parent_code_index (source_parent_code(150)),
                INDEX geo_import_rows_status_index (validation_status),
                INDEX geo_import_rows_checksum_index (row_checksum)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_import_rows', 'geo_import_rows_batch_foreign', 'batch_id', 'geographic_import_batches', 'id', 'CASCADE');
    }

    private function createGeographicImportIssuesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_import_issues (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id BIGINT UNSIGNED NOT NULL,
                import_row_id BIGINT UNSIGNED NULL,
                issue_code VARCHAR(100) NOT NULL,
                severity VARCHAR(30) NOT NULL,
                field_name VARCHAR(100) NULL,
                message TEXT NOT NULL,
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                INDEX geo_import_issues_batch_index (batch_id),
                INDEX geo_import_issues_row_index (import_row_id),
                INDEX geo_import_issues_code_index (issue_code),
                INDEX geo_import_issues_severity_index (severity)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_import_issues', 'geo_import_issues_batch_foreign', 'batch_id', 'geographic_import_batches', 'id', 'CASCADE');
        $this->addForeignKeyIfPossible('geographic_import_issues', 'geo_import_issues_row_foreign', 'import_row_id', 'geographic_import_rows', 'id', 'SET NULL');
    }

    private function createGeographicImportMatchCandidatesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_import_match_candidates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id BIGINT UNSIGNED NOT NULL,
                import_row_id BIGINT UNSIGNED NOT NULL,
                geographic_location_id BIGINT UNSIGNED NOT NULL,
                match_method VARCHAR(60) NOT NULL,
                confidence_score DECIMAL(5,2) NULL,
                candidate_rank INT UNSIGNED NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'proposed',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX geo_import_candidates_batch_index (batch_id),
                INDEX geo_import_candidates_row_index (import_row_id),
                INDEX geo_import_candidates_location_index (geographic_location_id),
                INDEX geo_import_candidates_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_import_match_candidates', 'geo_import_candidates_batch_foreign', 'batch_id', 'geographic_import_batches', 'id', 'CASCADE');
        $this->addForeignKeyIfPossible('geographic_import_match_candidates', 'geo_import_candidates_row_foreign', 'import_row_id', 'geographic_import_rows', 'id', 'CASCADE');
        $this->addForeignKeyIfPossible('geographic_import_match_candidates', 'geo_import_candidates_location_foreign', 'geographic_location_id', 'geographic_locations', 'id', 'RESTRICT');
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
            || !$this->columnExists($table, $column)
            || !$this->columnExists($referenceTable, $referenceColumn)
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

    private function referenceColumnType(string $table, string $column, string $fallback): string
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return $fallback;
        }

        $type = $this->columnType($table, $column);

        return $type === '' ? $fallback : strtoupper($type);
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
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

    private function columnType(string $table, string $column): string
    {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);

        return strtolower((string) $statement->fetchColumn());
    }

    private function indexExists(string $table, string $index): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
        ");
        $statement->execute([$table, $index]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower((string) $statement->fetchColumn()) === 'innodb';
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type = 'FOREIGN KEY'
        ");
        $statement->execute([$table, $constraint]);

        return (int) $statement->fetchColumn() > 0;
    }
}

<?php

namespace IPKF\Database\Migrations;

class CreateMinistrySciGeographyCrosswalkTables extends Migration
{
    public function up(): void
    {
        $this->createRunsTable();
        $this->createCandidatesTable();
        $this->createIssuesTable();
        $this->addImportRowIndexes();
    }

    public function down(): void
    {
    }

    private function createRunsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_crosswalk_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                crosswalk_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                source_snapshot_id BIGINT UNSIGNED NOT NULL,
                target_snapshot_id BIGINT UNSIGNED NOT NULL,
                source_batch_id BIGINT UNSIGNED NOT NULL,
                target_batch_id BIGINT UNSIGNED NOT NULL,
                crosswalk_type VARCHAR(100) NOT NULL,
                algorithm_version VARCHAR(60) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                total_source_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                exact_candidates BIGINT UNSIGNED NOT NULL DEFAULT 0,
                probable_candidates BIGINT UNSIGNED NOT NULL DEFAULT 0,
                ambiguous_candidates BIGINT UNSIGNED NOT NULL DEFAULT 0,
                unmatched_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                excluded_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
                summary_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geo_crosswalk_reference_unique (crosswalk_reference),
                UNIQUE KEY geo_crosswalk_version_unique (
                    source_snapshot_id, target_snapshot_id,
                    crosswalk_type, algorithm_version
                ),
                INDEX geo_crosswalk_source_snapshot_index (source_snapshot_id),
                INDEX geo_crosswalk_target_snapshot_index (target_snapshot_id),
                INDEX geo_crosswalk_source_batch_index (source_batch_id),
                INDEX geo_crosswalk_target_batch_index (target_batch_id),
                INDEX geo_crosswalk_status_index (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_crosswalk_runs', 'geo_crosswalk_source_snapshot_foreign', 'source_snapshot_id', 'data_source_snapshots', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_crosswalk_runs', 'geo_crosswalk_target_snapshot_foreign', 'target_snapshot_id', 'data_source_snapshots', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_crosswalk_runs', 'geo_crosswalk_source_batch_foreign', 'source_batch_id', 'geographic_import_batches', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_crosswalk_runs', 'geo_crosswalk_target_batch_foreign', 'target_batch_id', 'geographic_import_batches', 'id', 'RESTRICT');
    }

    private function createCandidatesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_crosswalk_candidates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                crosswalk_run_id BIGINT UNSIGNED NOT NULL,
                ministry_import_row_id BIGINT UNSIGNED NULL,
                statistical_import_row_id BIGINT UNSIGNED NOT NULL,
                candidate_pair_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                candidate_kind VARCHAR(60) NOT NULL,
                match_status VARCHAR(30) NOT NULL,
                match_method VARCHAR(100) NOT NULL,
                confidence_score DECIMAL(5,2) NULL,
                hierarchy_match_score DECIMAL(5,2) NULL,
                title_match_score DECIMAL(5,2) NULL,
                code_match_score DECIMAL(5,2) NULL,
                parent_match_score DECIMAL(5,2) NULL,
                candidate_rank INT UNSIGNED NULL,
                primary_reason_code VARCHAR(100) NOT NULL,
                reason_codes_json LONGTEXT NULL,
                review_status VARCHAR(30) NOT NULL DEFAULT 'pending',
                reviewed_by_user_id BIGINT UNSIGNED NULL,
                reviewed_at TIMESTAMP NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY geo_crosswalk_candidate_pair_unique (candidate_pair_key),
                INDEX geo_crosswalk_candidates_run_index (crosswalk_run_id),
                INDEX geo_crosswalk_candidates_ministry_row_index (ministry_import_row_id),
                INDEX geo_crosswalk_candidates_statistical_row_index (statistical_import_row_id),
                INDEX geo_crosswalk_candidates_run_source_status_index (
                    crosswalk_run_id, statistical_import_row_id, match_status
                ),
                INDEX geo_crosswalk_candidates_status_index (crosswalk_run_id, match_status),
                INDEX geo_crosswalk_candidates_kind_index (crosswalk_run_id, candidate_kind),
                INDEX geo_crosswalk_candidates_reason_index (crosswalk_run_id, primary_reason_code),
                INDEX geo_crosswalk_candidates_run_review_index (crosswalk_run_id, review_status),
                INDEX geo_crosswalk_candidates_review_index (review_status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_crosswalk_candidates', 'geo_crosswalk_candidates_run_foreign', 'crosswalk_run_id', 'geographic_crosswalk_runs', 'id', 'CASCADE');
        $this->addForeignKeyIfPossible('geographic_crosswalk_candidates', 'geo_crosswalk_candidates_ministry_row_foreign', 'ministry_import_row_id', 'geographic_import_rows', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_crosswalk_candidates', 'geo_crosswalk_candidates_statistical_row_foreign', 'statistical_import_row_id', 'geographic_import_rows', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('geographic_crosswalk_candidates', 'geo_crosswalk_candidates_reviewer_foreign', 'reviewed_by_user_id', 'users', 'id', 'SET NULL');
    }

    private function createIssuesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS geographic_crosswalk_issues (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                crosswalk_run_id BIGINT UNSIGNED NOT NULL,
                candidate_id BIGINT UNSIGNED NULL,
                statistical_import_row_id BIGINT UNSIGNED NULL,
                issue_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                issue_code VARCHAR(100) NOT NULL,
                severity VARCHAR(30) NOT NULL,
                message TEXT NOT NULL,
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                UNIQUE KEY geo_crosswalk_issue_key_unique (issue_key),
                INDEX geo_crosswalk_issues_run_index (crosswalk_run_id),
                INDEX geo_crosswalk_issues_candidate_index (candidate_id),
                INDEX geo_crosswalk_issues_statistical_row_index (statistical_import_row_id),
                INDEX geo_crosswalk_issues_code_index (crosswalk_run_id, issue_code),
                INDEX geo_crosswalk_issues_severity_index (severity)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible('geographic_crosswalk_issues', 'geo_crosswalk_issues_run_foreign', 'crosswalk_run_id', 'geographic_crosswalk_runs', 'id', 'CASCADE');
        $this->addForeignKeyIfPossible('geographic_crosswalk_issues', 'geo_crosswalk_issues_candidate_foreign', 'candidate_id', 'geographic_crosswalk_candidates', 'id', 'CASCADE');
        $this->addForeignKeyIfPossible('geographic_crosswalk_issues', 'geo_crosswalk_issues_statistical_row_foreign', 'statistical_import_row_id', 'geographic_import_rows', 'id', 'RESTRICT');
    }

    private function addImportRowIndexes(): void
    {
        if (!$this->tableExists('geographic_import_rows')) {
            return;
        }

        $this->addIndexIfMissing(
            'geographic_import_rows',
            'geo_import_rows_batch_level_title_index',
            'batch_id, derived_level_code, normalized_title(80)'
        );
        $this->addIndexIfMissing(
            'geographic_import_rows',
            'geo_import_rows_batch_kind_title_index',
            'batch_id, source_entity_kind, normalized_title(80)'
        );
        $this->addIndexIfMissing(
            'geographic_import_rows',
            'geo_import_rows_batch_type_status_index',
            'batch_id, source_record_type, validation_status'
        );
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
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $statement->execute([$table]);

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

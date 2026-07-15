<?php

namespace App\Repositories;

use IPKF\Database\Database;
use IPKF\Support\Clock;
use PDO;
use PDOStatement;
use RuntimeException;

class GeographyImportRepository
{
    private PDO $db;
    private ?PDOStatement $stageRowStatement = null;
    private ?PDOStatement $stageIssueStatement = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function sourceId(string $code): int
    {
        $statement = $this->db->prepare("SELECT id FROM data_sources WHERE code = ? AND status = 'active' LIMIT 1");
        $statement->execute([$code]);
        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException('Configured data source is unavailable.');
        }

        return (int) $id;
    }

    public function levelMappings(int $sourceId): array
    {
        $statement = $this->db->prepare("
            SELECT source_type_value, geographic_level_code, parent_geographic_level_code,
                   expected_code_length, parent_prefix_length
            FROM geographic_source_level_mappings
            WHERE source_id = ? AND status = 'active'
            ORDER BY sort_order ASC, id ASC
        ");
        $statement->execute([$sourceId]);

        return $statement->fetchAll();
    }

    public function settings(int $sourceId): array
    {
        $statement = $this->db->prepare("
            SELECT setting_key, setting_value, value_type
            FROM data_source_import_settings
            WHERE source_id = ? AND status = 'active'
        ");
        $statement->execute([$sourceId]);
        $settings = [];

        foreach ($statement->fetchAll() as $row) {
            $value = $row['setting_value'];

            if ($row['value_type'] === 'json') {
                $decoded = json_decode((string) $value, true);
                $value = is_array($decoded) ? $decoded : [];
            } elseif ($row['value_type'] === 'integer') {
                $value = (int) $value;
            } elseif ($row['value_type'] === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $settings[$row['setting_key']] = $value;
        }

        return $settings;
    }

    public function recordTypeMappings(int $sourceId): array
    {
        $statement = $this->db->prepare("
            SELECT source_record_type, source_title, derived_level_code,
                   source_entity_kind, parent_record_type, code_field,
                   parent_code_fields_json, canonical_auto_match_allowed
            FROM geographic_source_record_type_mappings
            WHERE source_id = ? AND status = 'active'
            ORDER BY sort_order ASC, id ASC
        ");
        $statement->execute([$sourceId]);

        return $statement->fetchAll();
    }

    public function createOrReuseSnapshot(
        int $sourceId,
        string $filename,
        string $sha256,
        int $fileSize
    ): int {
        $now = Clock::databaseTimestamp();
        $statement = $this->db->prepare("
            INSERT INTO data_source_snapshots (
                source_id, observed_at, source_filename, file_sha256, file_size,
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'registered', ?, ?)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $statement->execute([$sourceId, $now, $filename, $sha256, $fileSize, $now, $now]);
        $id = (int) $this->db->lastInsertId();

        if ($id > 0) {
            return $id;
        }

        $lookup = $this->db->prepare("
            SELECT id FROM data_source_snapshots
            WHERE source_id = ? AND file_sha256 = ?
            LIMIT 1
        ");
        $lookup->execute([$sourceId, $sha256]);
        $id = $lookup->fetchColumn();

        if ($id === false) {
            throw new RuntimeException('Source snapshot could not be registered.');
        }

        return (int) $id;
    }

    public function reusableSummary(int $snapshotId): ?array
    {
        $statement = $this->db->prepare("
            SELECT summary_json
            FROM geographic_import_batches
            WHERE source_snapshot_id = ?
              AND import_mode = 'validate'
              AND status IN ('validated', 'ready_for_review')
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute([$snapshotId]);
        $summary = $statement->fetchColumn();

        if ($summary === false) {
            return null;
        }

        $decoded = json_decode((string) $summary, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function prepareBatch(int $sourceId, int $snapshotId): int
    {
        $statement = $this->db->prepare("
            SELECT id FROM geographic_import_batches
            WHERE source_id = ? AND source_snapshot_id = ? AND import_mode = 'validate'
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute([$sourceId, $snapshotId]);
        $id = $statement->fetchColumn();
        $now = Clock::databaseTimestamp();

        if ($id !== false) {
            $batchId = (int) $id;
            $this->db->prepare("DELETE FROM geographic_import_issues WHERE batch_id = ?")->execute([$batchId]);
            $this->db->prepare("DELETE FROM geographic_import_rows WHERE batch_id = ?")->execute([$batchId]);
            $this->db->prepare("
                UPDATE geographic_import_batches
                SET status = 'parsing', started_at = ?, completed_at = NULL,
                    total_rows = 0, valid_rows = 0, warning_rows = 0,
                    invalid_rows = 0, summary_json = NULL, updated_at = ?
                WHERE id = ?
            ")->execute([$now, $now, $batchId]);

            return $batchId;
        }

        $insert = $this->db->prepare("
            INSERT INTO geographic_import_batches (
                source_id, source_snapshot_id, import_mode, status, started_at,
                created_at, updated_at
            ) VALUES (?, ?, 'validate', 'parsing', ?, ?, ?)
        ");
        $insert->execute([$sourceId, $snapshotId, $now, $now, $now]);

        return (int) $this->db->lastInsertId();
    }

    public function stageRow(int $batchId, array $row): int
    {
        $now = Clock::databaseTimestamp();
        $this->stageRowStatement ??= $this->db->prepare("
            INSERT INTO geographic_import_rows (
                batch_id, source_row_number, source_record_type, source_code,
                source_title, source_parent_code, normalized_title,
                derived_level_code, derived_parent_code, row_checksum,
                validation_status, raw_payload_json, source_entity_kind,
                source_local_code, source_composite_key,
                source_parent_composite_key, source_parent_record_type,
                source_classifier_code, normalized_source_code,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $sourceRowNumber = $row['source_row_number'] ?? $row['row']->sourceRowNumber;
        $sourceRecordType = $row['source_record_type'] ?? $row['row']->sourceType;
        $sourceTitle = $row['source_title'] ?? $row['row']->approvedTitle;
        $this->stageRowStatement->execute([
            $batchId,
            $sourceRowNumber,
            $sourceRecordType,
            $row['source_code'] !== '' ? $row['source_code'] : null,
            $sourceTitle,
            null,
            $row['normalized_title'] !== '' ? $row['normalized_title'] : null,
            $row['derived_level_code'],
            $row['derived_parent_code'],
            $row['row_checksum'],
            $row['validation_status'],
            json_encode(
                $row['raw_payload'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            ),
            $row['source_entity_kind'] ?? null,
            $row['source_local_code'] ?? null,
            $row['source_composite_key'] ?? null,
            $row['source_parent_composite_key'] ?? null,
            $row['source_parent_record_type'] ?? null,
            $row['source_classifier_code'] ?? null,
            $row['normalized_source_code'] ?? null,
            $now,
            $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function stageIssue(int $batchId, ?int $rowId, array $issue): void
    {
        $this->stageIssueStatement ??= $this->db->prepare("
            INSERT INTO geographic_import_issues (
                batch_id, import_row_id, issue_code, severity, field_name,
                message, metadata_json, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $this->stageIssueStatement->execute([
            $batchId,
            $rowId,
            $issue['code'],
            $issue['severity'],
            $issue['field'],
            $issue['message'],
            $issue['metadata'] === []
                ? null
                : json_encode($issue['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            Clock::databaseTimestamp(),
        ]);
    }

    public function applyCompositeValidation(int $batchId): void
    {
        $this->insertMissingParentIssues($batchId);
        $this->insertParentMismatchIssues($batchId);
        $this->insertDuplicateObservationIssues($batchId);
        $this->insertDuplicateVariationIssues($batchId, 'normalized_title', 'SOURCE_CODE_TITLE_VARIATION', 'source_title');
        $this->insertDuplicateVariationIssues($batchId, 'source_parent_composite_key', 'SOURCE_CODE_PARENT_VARIATION', 'source_parent_composite_key');
        $this->insertDuplicateVariationIssues($batchId, 'source_classifier_code', 'SOURCE_CODE_DIAG_VARIATION', 'source_classifier_code');
        $this->refreshValidationStatuses($batchId);
    }

    public function batchValidationCounts(int $batchId): array
    {
        $statement = $this->db->prepare("
            SELECT validation_status, COUNT(*) AS aggregate_count
            FROM geographic_import_rows
            WHERE batch_id = ?
            GROUP BY validation_status
        ");
        $statement->execute([$batchId]);
        $counts = ['valid' => 0, 'warning' => 0, 'invalid' => 0];

        foreach ($statement->fetchAll() as $row) {
            if (array_key_exists($row['validation_status'], $counts)) {
                $counts[$row['validation_status']] = (int) $row['aggregate_count'];
            }
        }

        return $counts;
    }

    public function sourceKindCounts(int $batchId): array
    {
        $statement = $this->db->prepare("
            SELECT source_entity_kind, COUNT(*) AS aggregate_count
            FROM geographic_import_rows
            WHERE batch_id = ? AND source_entity_kind IS NOT NULL
            GROUP BY source_entity_kind
            ORDER BY source_entity_kind
        ");
        $statement->execute([$batchId]);
        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            $counts[$row['source_entity_kind']] = (int) $row['aggregate_count'];
        }

        return $counts;
    }

    public function issueCounts(int $batchId): array
    {
        $statement = $this->db->prepare("
            SELECT issue_code, COUNT(*) AS aggregate_count
            FROM geographic_import_issues
            WHERE batch_id = ?
            GROUP BY issue_code
            ORDER BY issue_code
        ");
        $statement->execute([$batchId]);
        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            $counts[$row['issue_code']] = (int) $row['aggregate_count'];
        }

        return $counts;
    }

    public function classifierPresenceCount(int $batchId): int
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM geographic_import_rows
            WHERE batch_id = ?
              AND source_classifier_code IS NOT NULL
              AND source_classifier_code <> ''
        ");
        $statement->execute([$batchId]);

        return (int) $statement->fetchColumn();
    }

    public function completeBatch(int $batchId, string $status, array $counts, array $summary): void
    {
        $now = Clock::databaseTimestamp();
        $statement = $this->db->prepare("
            UPDATE geographic_import_batches
            SET status = ?, completed_at = ?, total_rows = ?, valid_rows = ?,
                warning_rows = ?, invalid_rows = ?, summary_json = ?, updated_at = ?
            WHERE id = ?
        ");
        $statement->execute([
            $status,
            $now,
            array_sum($counts),
            $counts['valid'],
            $counts['warning'],
            $counts['invalid'],
            json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $now,
            $batchId,
        ]);
    }

    public function updateSnapshot(
        int $snapshotId,
        string $status,
        ?int $rowCount = null,
        ?string $schemaSignature = null
    ): void {
        $statement = $this->db->prepare("
            UPDATE data_source_snapshots
            SET status = ?, row_count = COALESCE(?, row_count),
                schema_signature = COALESCE(?, schema_signature), updated_at = ?
            WHERE id = ?
        ");
        $statement->execute([
            $status,
            $rowCount,
            $schemaSignature,
            Clock::databaseTimestamp(),
            $snapshotId,
        ]);
    }

    public function failBatch(int $batchId, int $snapshotId, string $status): void
    {
        $now = Clock::databaseTimestamp();
        $this->db->prepare("
            UPDATE geographic_import_batches
            SET status = ?, completed_at = ?, updated_at = ?
            WHERE id = ?
        ")->execute([$status, $now, $now, $batchId]);
        $this->updateSnapshot($snapshotId, $status);
    }

    public function transaction(callable $callback): mixed
    {
        $this->db->beginTransaction();

        try {
            $result = $callback();
            $this->db->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function insertMissingParentIssues(int $batchId): void
    {
        $statement = $this->db->prepare("
            INSERT INTO geographic_import_issues (
                batch_id, import_row_id, issue_code, severity,
                field_name, message, metadata_json, created_at
            )
            SELECT child.batch_id, child.id, 'MISSING_PARENT_OBSERVATION', 'error',
                   'source_parent_composite_key',
                   'The source parent observation is missing from this snapshot.',
                   NULL, ?
            FROM geographic_import_rows child
            WHERE child.batch_id = ?
              AND child.source_parent_composite_key IS NOT NULL
              AND child.source_parent_record_type IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM geographic_import_rows parent
                  WHERE parent.batch_id = child.batch_id
                    AND parent.source_composite_key = child.source_parent_composite_key
              )
        ");
        $statement->execute([Clock::databaseTimestamp(), $batchId]);
    }

    private function insertParentMismatchIssues(int $batchId): void
    {
        $statement = $this->db->prepare("
            INSERT INTO geographic_import_issues (
                batch_id, import_row_id, issue_code, severity,
                field_name, message, metadata_json, created_at
            )
            SELECT child.batch_id, child.id, 'PARENT_CONTEXT_MISMATCH', 'error',
                   'source_parent_composite_key',
                   'The source parent exists only with an incompatible record type.',
                   NULL, ?
            FROM geographic_import_rows child
            WHERE child.batch_id = ?
              AND child.source_parent_composite_key IS NOT NULL
              AND child.source_parent_record_type IS NOT NULL
              AND EXISTS (
                  SELECT 1 FROM geographic_import_rows parent
                  WHERE parent.batch_id = child.batch_id
                    AND parent.source_composite_key = child.source_parent_composite_key
              )
              AND NOT EXISTS (
                  SELECT 1 FROM geographic_import_rows parent
                  WHERE parent.batch_id = child.batch_id
                    AND parent.source_composite_key = child.source_parent_composite_key
                    AND FIND_IN_SET(parent.source_record_type, child.source_parent_record_type) > 0
              )
        ");
        $statement->execute([Clock::databaseTimestamp(), $batchId]);
    }

    private function insertDuplicateObservationIssues(int $batchId): void
    {
        $base = "
            FROM geographic_import_rows rows_to_flag
            INNER JOIN (
                SELECT source_record_type, source_composite_key,
                       COUNT(*) AS duplicate_count,
                       MIN(row_checksum) AS minimum_checksum,
                       MAX(row_checksum) AS maximum_checksum
                FROM geographic_import_rows
                WHERE batch_id = ? AND source_composite_key IS NOT NULL
                GROUP BY source_record_type, source_composite_key
                HAVING COUNT(*) > 1
            ) duplicate_groups
                ON duplicate_groups.source_record_type = rows_to_flag.source_record_type
               AND duplicate_groups.source_composite_key = rows_to_flag.source_composite_key
            WHERE rows_to_flag.batch_id = ?
        ";
        $exact = $this->db->prepare("
            INSERT INTO geographic_import_issues (
                batch_id, import_row_id, issue_code, severity,
                field_name, message, metadata_json, created_at
            )
            SELECT rows_to_flag.batch_id, rows_to_flag.id,
                   'DUPLICATE_SOURCE_OBSERVATION', 'warning',
                   'source_composite_key',
                   'An exact source observation occurs more than once.',
                   NULL, ?
            {$base}
              AND duplicate_groups.minimum_checksum = duplicate_groups.maximum_checksum
        ");
        $exact->execute([Clock::databaseTimestamp(), $batchId, $batchId]);

        $conflicting = $this->db->prepare("
            INSERT INTO geographic_import_issues (
                batch_id, import_row_id, issue_code, severity,
                field_name, message, metadata_json, created_at
            )
            SELECT rows_to_flag.batch_id, rows_to_flag.id,
                   'DUPLICATE_SOURCE_COMPOSITE_KEY', 'error',
                   'source_composite_key',
                   'Conflicting observations share one source composite key.',
                   NULL, ?
            {$base}
              AND duplicate_groups.minimum_checksum <> duplicate_groups.maximum_checksum
        ");
        $conflicting->execute([Clock::databaseTimestamp(), $batchId, $batchId]);
    }

    private function insertDuplicateVariationIssues(
        int $batchId,
        string $column,
        string $issueCode,
        string $fieldName
    ): void {
        $allowedColumns = ['normalized_title', 'source_parent_composite_key', 'source_classifier_code'];

        if (!in_array($column, $allowedColumns, true)) {
            throw new RuntimeException('Unsupported duplicate comparison column.');
        }

        $statement = $this->db->prepare("
            INSERT INTO geographic_import_issues (
                batch_id, import_row_id, issue_code, severity,
                field_name, message, metadata_json, created_at
            )
            SELECT rows_to_flag.batch_id, rows_to_flag.id, ?, 'warning', ?,
                   'Conflicting source observations require review.', NULL, ?
            FROM geographic_import_rows rows_to_flag
            INNER JOIN (
                SELECT source_record_type, source_composite_key
                FROM geographic_import_rows
                WHERE batch_id = ? AND source_composite_key IS NOT NULL
                GROUP BY source_record_type, source_composite_key
                HAVING COUNT(*) > 1
                   AND COUNT(DISTINCT COALESCE({$column}, '')) > 1
            ) duplicate_groups
                ON duplicate_groups.source_record_type = rows_to_flag.source_record_type
               AND duplicate_groups.source_composite_key = rows_to_flag.source_composite_key
            WHERE rows_to_flag.batch_id = ?
        ");
        $statement->execute([
            $issueCode,
            $fieldName,
            Clock::databaseTimestamp(),
            $batchId,
            $batchId,
        ]);
    }

    private function refreshValidationStatuses(int $batchId): void
    {
        $statement = $this->db->prepare("
            UPDATE geographic_import_rows import_rows
            LEFT JOIN (
                SELECT import_row_id,
                       MAX(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) AS has_error,
                       MAX(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) AS has_warning
                FROM geographic_import_issues
                WHERE batch_id = ? AND import_row_id IS NOT NULL
                GROUP BY import_row_id
            ) issue_summary ON issue_summary.import_row_id = import_rows.id
            SET import_rows.validation_status = CASE
                WHEN COALESCE(issue_summary.has_error, 0) = 1 THEN 'invalid'
                WHEN COALESCE(issue_summary.has_warning, 0) = 1 THEN 'warning'
                ELSE 'valid'
            END,
            import_rows.updated_at = ?
            WHERE import_rows.batch_id = ?
        ");
        $statement->execute([$batchId, Clock::databaseTimestamp(), $batchId]);
    }
}

<?php

namespace App\Repositories;

use IPKF\Database\Database;
use IPKF\Support\Clock;
use PDO;
use RuntimeException;

class GeographyImportRepository
{
    private PDO $db;

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
        $statement = $this->db->prepare("
            INSERT INTO geographic_import_rows (
                batch_id, source_row_number, source_record_type, source_code,
                source_title, source_parent_code, normalized_title,
                derived_level_code, derived_parent_code, row_checksum,
                validation_status, raw_payload_json, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $statement->execute([
            $batchId,
            $row['row']->sourceRowNumber,
            $row['row']->sourceType,
            $row['source_code'] !== '' ? $row['source_code'] : null,
            $row['row']->approvedTitle,
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
            $now,
            $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function stageIssue(int $batchId, int $rowId, array $issue): void
    {
        $statement = $this->db->prepare("
            INSERT INTO geographic_import_issues (
                batch_id, import_row_id, issue_code, severity, field_name,
                message, metadata_json, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $statement->execute([
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
}

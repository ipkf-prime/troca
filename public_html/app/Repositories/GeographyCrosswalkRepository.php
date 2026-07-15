<?php

namespace App\Repositories;

use IPKF\Database\Database;
use IPKF\Support\Clock;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class GeographyCrosswalkRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function completedBatch(string $sourceCode, string $batchReference): array
    {
        $statement = $this->db->prepare("
            SELECT batches.id, batches.source_snapshot_id, batches.total_rows,
                   batches.summary_json
            FROM geographic_import_batches batches
            INNER JOIN data_sources sources ON sources.id = batches.source_id
            WHERE sources.code = ?
              AND batches.status IN ('validated', 'ready_for_review')
              AND batches.summary_json IS NOT NULL
            ORDER BY batches.id DESC
            LIMIT 200
        ");
        $statement->execute([$sourceCode]);

        foreach ($statement->fetchAll() as $row) {
            $summary = json_decode((string) $row['summary_json'], true);

            if (is_array($summary)
                && hash_equals((string) ($summary['batch_reference'] ?? ''), $batchReference)
            ) {
                return [
                    'id' => (int) $row['id'],
                    'snapshot_id' => (int) $row['source_snapshot_id'],
                    'total_rows' => (int) $row['total_rows'],
                ];
            }
        }

        throw new InvalidArgumentException('Compatible completed source batch was not found.');
    }

    public function prepareRun(
        array $sourceBatch,
        array $targetBatch,
        string $crosswalkType,
        string $algorithmVersion
    ): array {
        $statement = $this->db->prepare("
            SELECT id, crosswalk_reference, status, summary_json
            FROM geographic_crosswalk_runs
            WHERE source_snapshot_id = ?
              AND target_snapshot_id = ?
              AND crosswalk_type = ?
              AND algorithm_version = ?
            LIMIT 1
        ");
        $statement->execute([
            $sourceBatch['snapshot_id'],
            $targetBatch['snapshot_id'],
            $crosswalkType,
            $algorithmVersion,
        ]);
        $existing = $statement->fetch();

        if (is_array($existing)) {
            $summary = json_decode((string) ($existing['summary_json'] ?? ''), true);

            if ($existing['status'] === 'ready_for_review' && is_array($summary)) {
                return [
                    'id' => (int) $existing['id'],
                    'reference' => (string) $existing['crosswalk_reference'],
                    'reusable_summary' => $summary,
                ];
            }

            $reviewed = $this->db->prepare("
                SELECT COUNT(*) FROM geographic_crosswalk_candidates
                WHERE crosswalk_run_id = ? AND review_status <> 'pending'
            ");
            $reviewed->execute([(int) $existing['id']]);

            if ((int) $reviewed->fetchColumn() > 0) {
                throw new RuntimeException('Reviewed crosswalk results cannot be replaced.');
            }

            $runId = (int) $existing['id'];
            $this->transaction(function () use ($runId, $sourceBatch, $targetBatch): void {
                $this->db->prepare("DELETE FROM geographic_crosswalk_issues WHERE crosswalk_run_id = ?")
                    ->execute([$runId]);
                $this->db->prepare("DELETE FROM geographic_crosswalk_candidates WHERE crosswalk_run_id = ?")
                    ->execute([$runId]);
                $now = Clock::databaseTimestamp();
                $this->db->prepare("
                    UPDATE geographic_crosswalk_runs
                    SET source_batch_id = ?, target_batch_id = ?, status = 'building',
                        started_at = ?, completed_at = NULL, total_source_rows = 0,
                        exact_candidates = 0, probable_candidates = 0,
                        ambiguous_candidates = 0, unmatched_rows = 0,
                        excluded_rows = 0, summary_json = NULL, updated_at = ?
                    WHERE id = ?
                ")->execute([
                    $sourceBatch['id'],
                    $targetBatch['id'],
                    $now,
                    $now,
                    $runId,
                ]);
            });

            return [
                'id' => $runId,
                'reference' => (string) $existing['crosswalk_reference'],
                'reusable_summary' => null,
            ];
        }

        $reference = 'XW-' . strtoupper(bin2hex(random_bytes(6)));
        $now = Clock::databaseTimestamp();
        $insert = $this->db->prepare("
            INSERT INTO geographic_crosswalk_runs (
                crosswalk_reference, source_snapshot_id, target_snapshot_id,
                source_batch_id, target_batch_id, crosswalk_type,
                algorithm_version, status, started_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'building', ?, ?, ?)
        ");
        $insert->execute([
            $reference,
            $sourceBatch['snapshot_id'],
            $targetBatch['snapshot_id'],
            $sourceBatch['id'],
            $targetBatch['id'],
            $crosswalkType,
            $algorithmVersion,
            $now,
            $now,
            $now,
        ]);

        return [
            'id' => (int) $this->db->lastInsertId(),
            'reference' => $reference,
            'reusable_summary' => null,
        ];
    }

    public function classifyExcludedRows(int $runId, int $sourceBatchId): void
    {
        $this->insertExclusion($runId, $sourceBatchId, "source.validation_status = 'invalid'", 'unsupported', 'SOURCE_ROW_INVALID');
        $this->insertExclusion(
            $runId,
            $sourceBatchId,
            "source.source_entity_kind IN ('settlement_observation', 'diag_classified_settlement_observation')",
            'settlement',
            'SETTLEMENT_NOT_IN_MINISTRY_SCOPE'
        );
        $this->insertExclusion(
            $runId,
            $sourceBatchId,
            "source.source_entity_kind = 'statistical_urban_unit'
             AND source.normalized_title REGEXP '[0-9]+[[:space:]]*$'",
            'statistical_urban_unit',
            'NUMBERED_URBAN_UNIT_EXCLUDED'
        );
        $this->insertExclusion(
            $runId,
            $sourceBatchId,
            "(
                source.source_entity_kind IS NULL
                OR source.source_entity_kind NOT IN (
                    'province_observation', 'county_observation',
                    'district_observation', 'rural_district_observation',
                    'statistical_urban_unit', 'settlement_observation',
                    'diag_classified_settlement_observation'
                )
            )",
            'unsupported',
            'LEVEL_MISMATCH'
        );
    }

    public function buildLevel(
        int $runId,
        int $sourceBatchId,
        int $targetBatchId,
        string $sourceKind,
        string $targetLevel,
        ?string $parentSourceKind,
        string $candidateKind,
        bool $cityCandidate
    ): void {
        // Materialize one hierarchy level so pair ranking stays set-based and bounded.
        $this->preparePairTable();
        $this->db->exec('TRUNCATE TABLE ipkf_crosswalk_candidate_pairs');

        if ($parentSourceKind === null) {
            $pairs = $this->db->prepare("
                INSERT IGNORE INTO ipkf_crosswalk_candidate_pairs (
                    statistical_row_id, ministry_row_id,
                    raw_title_exact, parent_probable
                )
                SELECT source.id, target.id,
                       BINARY source.source_title = BINARY target.source_title, 0
                FROM geographic_import_rows source
                INNER JOIN geographic_import_rows target
                    ON target.batch_id = ?
                   AND target.derived_level_code = ?
                   AND target.validation_status <> 'invalid'
                   AND BINARY target.normalized_title = BINARY source.normalized_title
                WHERE source.batch_id = ?
                  AND source.source_entity_kind = ?
                  AND source.validation_status <> 'invalid'
                  AND source.normalized_title IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM geographic_crosswalk_candidates existing
                      WHERE existing.crosswalk_run_id = ?
                        AND existing.statistical_import_row_id = source.id
                  )
            ");
            $pairs->execute([$targetBatchId, $targetLevel, $sourceBatchId, $sourceKind, $runId]);
        } else {
            $pairs = $this->db->prepare("
                INSERT IGNORE INTO ipkf_crosswalk_candidate_pairs (
                    statistical_row_id, ministry_row_id,
                    raw_title_exact, parent_probable
                )
                SELECT source.id, target.id,
                       BINARY source.source_title = BINARY target.source_title,
                       parent_candidate.match_status = 'probable'
                FROM geographic_import_rows source
                INNER JOIN geographic_import_rows source_parent
                    ON source_parent.batch_id = source.batch_id
                   AND source_parent.source_entity_kind = ?
                   AND source_parent.source_composite_key = source.source_parent_composite_key
                INNER JOIN geographic_crosswalk_candidates parent_candidate
                    ON parent_candidate.crosswalk_run_id = ?
                   AND parent_candidate.statistical_import_row_id = source_parent.id
                   AND parent_candidate.match_status IN ('exact', 'probable')
                   AND parent_candidate.ministry_import_row_id IS NOT NULL
                INNER JOIN geographic_import_rows ministry_parent
                    ON ministry_parent.id = parent_candidate.ministry_import_row_id
                   AND ministry_parent.batch_id = ?
                INNER JOIN geographic_import_rows target
                    ON target.batch_id = ?
                   AND target.derived_level_code = ?
                   AND target.validation_status <> 'invalid'
                   AND target.derived_parent_code = ministry_parent.source_code
                   AND BINARY target.normalized_title = BINARY source.normalized_title
                WHERE source.batch_id = ?
                  AND source.source_entity_kind = ?
                  AND source.validation_status <> 'invalid'
                  AND source.normalized_title IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM geographic_crosswalk_candidates existing
                      WHERE existing.crosswalk_run_id = ?
                        AND existing.statistical_import_row_id = source.id
                  )
            ");
            $pairs->execute([
                $parentSourceKind, $runId, $targetBatchId, $targetBatchId,
                $targetLevel, $sourceBatchId, $sourceKind, $runId,
            ]);
        }

        $this->insertPairCandidates($runId, $candidateKind, $cityCandidate);
        $this->insertUnresolvedRows($runId, $sourceBatchId, $sourceKind, $parentSourceKind, $candidateKind);
    }

    public function createIssues(int $runId): void
    {
        $statement = $this->db->prepare("
            INSERT IGNORE INTO geographic_crosswalk_issues (
                crosswalk_run_id, candidate_id, statistical_import_row_id,
                issue_key, issue_code, severity, message, metadata_json, created_at
            )
            SELECT candidate.crosswalk_run_id, candidate.id,
                   candidate.statistical_import_row_id,
                   SHA2(CONCAT(candidate.crosswalk_run_id, ':', candidate.id, ':',
                       candidate.primary_reason_code), 256),
                   candidate.primary_reason_code,
                   CASE
                       WHEN candidate.primary_reason_code = 'SOURCE_ROW_INVALID' THEN 'error'
                       WHEN candidate.match_status IN ('exact', 'excluded') THEN 'info'
                       ELSE 'warning'
                   END,
                   'Crosswalk classification was generated for review.',
                   NULL, ?
            FROM geographic_crosswalk_candidates candidate
            WHERE candidate.crosswalk_run_id = ?
        ");
        $statement->execute([Clock::databaseTimestamp(), $runId]);
    }

    public function statusCounts(int $runId): array
    {
        $statement = $this->db->prepare("
            SELECT match_status, COUNT(DISTINCT statistical_import_row_id) AS aggregate_count
            FROM geographic_crosswalk_candidates
            WHERE crosswalk_run_id = ?
            GROUP BY match_status
        ");
        $statement->execute([$runId]);
        $counts = ['exact' => 0, 'probable' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'excluded' => 0];

        foreach ($statement->fetchAll() as $row) {
            if (array_key_exists($row['match_status'], $counts)) {
                $counts[$row['match_status']] = (int) $row['aggregate_count'];
            }
        }

        return $counts;
    }

    public function sourceKindCounts(int $runId): array
    {
        $statement = $this->db->prepare("
            SELECT source.source_entity_kind,
                   COUNT(DISTINCT candidate.statistical_import_row_id) AS aggregate_count
            FROM geographic_crosswalk_candidates candidate
            INNER JOIN geographic_import_rows source
                ON source.id = candidate.statistical_import_row_id
            WHERE candidate.crosswalk_run_id = ?
            GROUP BY source.source_entity_kind
            ORDER BY source.source_entity_kind
        ");
        $statement->execute([$runId]);
        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            $counts[(string) ($row['source_entity_kind'] ?? 'unsupported')] = (int) $row['aggregate_count'];
        }

        return $counts;
    }

    public function reasonCounts(int $runId): array
    {
        $statement = $this->db->prepare("
            SELECT primary_reason_code,
                   COUNT(DISTINCT statistical_import_row_id) AS aggregate_count
            FROM geographic_crosswalk_candidates
            WHERE crosswalk_run_id = ?
            GROUP BY primary_reason_code
            ORDER BY primary_reason_code
        ");
        $statement->execute([$runId]);
        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            $counts[$row['primary_reason_code']] = (int) $row['aggregate_count'];
        }

        return $counts;
    }

    public function classifiedSourceCount(int $runId): int
    {
        $statement = $this->db->prepare("
            SELECT COUNT(DISTINCT statistical_import_row_id)
            FROM geographic_crosswalk_candidates
            WHERE crosswalk_run_id = ?
        ");
        $statement->execute([$runId]);

        return (int) $statement->fetchColumn();
    }

    public function completeRun(int $runId, int $totalSourceRows, array $counts, array $summary): void
    {
        $now = Clock::databaseTimestamp();
        $statement = $this->db->prepare("
            UPDATE geographic_crosswalk_runs
            SET status = 'ready_for_review', completed_at = ?,
                total_source_rows = ?, exact_candidates = ?,
                probable_candidates = ?, ambiguous_candidates = ?,
                unmatched_rows = ?, excluded_rows = ?,
                summary_json = ?, updated_at = ?
            WHERE id = ?
        ");
        $statement->execute([
            $now, $totalSourceRows, $counts['exact'], $counts['probable'],
            $counts['ambiguous'], $counts['unmatched'], $counts['excluded'],
            json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $now, $runId,
        ]);
    }

    public function failRun(int $runId): void
    {
        $now = Clock::databaseTimestamp();
        $this->db->prepare("
            UPDATE geographic_crosswalk_runs
            SET status = 'failed', completed_at = ?, updated_at = ?
            WHERE id = ?
        ")->execute([$now, $now, $runId]);
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

    private function preparePairTable(): void
    {
        $this->db->exec("
            CREATE TEMPORARY TABLE IF NOT EXISTS ipkf_crosswalk_candidate_pairs (
                statistical_row_id BIGINT UNSIGNED NOT NULL,
                ministry_row_id BIGINT UNSIGNED NOT NULL,
                raw_title_exact TINYINT(1) NOT NULL DEFAULT 0,
                parent_probable TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (statistical_row_id, ministry_row_id),
                INDEX ipkf_crosswalk_pairs_source_index (statistical_row_id)
            ) ENGINE=InnoDB
        ");
    }

    private function insertExclusion(
        int $runId,
        int $sourceBatchId,
        string $condition,
        string $candidateKind,
        string $reasonCode
    ): void {
        $now = Clock::databaseTimestamp();
        $statement = $this->db->prepare("
            INSERT IGNORE INTO geographic_crosswalk_candidates (
                crosswalk_run_id, ministry_import_row_id,
                statistical_import_row_id, candidate_pair_key,
                candidate_kind, match_status, match_method,
                primary_reason_code, reason_codes_json,
                review_status, created_at, updated_at
            )
            SELECT ?, NULL, source.id,
                   SHA2(CONCAT(?, ':', source.id, ':0'), 256),
                   ?, 'excluded', 'policy_exclusion',
                   ?, CONCAT('[\"', ?, '\"]'),
                   'pending', ?, ?
            FROM geographic_import_rows source
            WHERE source.batch_id = ?
              AND ({$condition})
              AND NOT EXISTS (
                  SELECT 1 FROM geographic_crosswalk_candidates existing
                  WHERE existing.crosswalk_run_id = ?
                    AND existing.statistical_import_row_id = source.id
              )
        ");
        $statement->execute([
            $runId, $runId, $candidateKind, $reasonCode, $reasonCode,
            $now, $now, $sourceBatchId, $runId,
        ]);
    }

    private function insertPairCandidates(int $runId, string $candidateKind, bool $cityCandidate): void
    {
        $now = Clock::databaseTimestamp();
        $statement = $this->db->prepare("
            INSERT IGNORE INTO geographic_crosswalk_candidates (
                crosswalk_run_id, ministry_import_row_id,
                statistical_import_row_id, candidate_pair_key,
                candidate_kind, match_status, match_method,
                confidence_score, hierarchy_match_score,
                title_match_score, code_match_score, parent_match_score,
                candidate_rank, primary_reason_code, reason_codes_json,
                review_status, created_at, updated_at
            )
            SELECT ?, classified.ministry_row_id, classified.statistical_row_id,
                   SHA2(CONCAT(?, ':', classified.statistical_row_id, ':',
                       classified.ministry_row_id), 256),
                   ?,
                   CASE
                       WHEN classified.candidate_count > 1 THEN 'ambiguous'
                       WHEN ? = 1 THEN 'probable'
                       WHEN classified.raw_title_exact = 1
                            AND classified.parent_probable = 0 THEN 'exact'
                       ELSE 'probable'
                   END,
                   CASE WHEN ? = 1
                       THEN 'statistical_urban_parent_title'
                       ELSE 'full_hierarchy_normalized_title'
                   END,
                   CASE
                       WHEN classified.candidate_count > 1 THEN NULL
                       WHEN ? = 1 THEN 85.00
                       WHEN classified.raw_title_exact = 1
                            AND classified.parent_probable = 0 THEN 100.00
                       ELSE 92.00
                   END,
                   CASE WHEN classified.parent_probable = 1 THEN 95.00 ELSE 100.00 END,
                   CASE WHEN classified.raw_title_exact = 1 THEN 100.00 ELSE 95.00 END,
                   NULL,
                   CASE WHEN classified.parent_probable = 1 THEN 95.00 ELSE 100.00 END,
                   CASE WHEN classified.candidate_count > 1 THEN classified.candidate_rank ELSE 1 END,
                   CASE
                       WHEN classified.candidate_count > 1 THEN 'MULTIPLE_TARGET_CANDIDATES'
                       WHEN ? = 1 THEN 'SAFE_NORMALIZATION_CANDIDATE'
                       WHEN classified.raw_title_exact = 1
                            AND classified.parent_probable = 0 THEN 'EXACT_HIERARCHY_CANDIDATE'
                       ELSE 'SAFE_NORMALIZATION_CANDIDATE'
                   END,
                   CONCAT('[\"',
                       CASE
                           WHEN classified.candidate_count > 1 THEN 'MULTIPLE_TARGET_CANDIDATES'
                           WHEN ? = 1 THEN 'SAFE_NORMALIZATION_CANDIDATE'
                           WHEN classified.raw_title_exact = 1
                                AND classified.parent_probable = 0 THEN 'EXACT_HIERARCHY_CANDIDATE'
                           ELSE 'SAFE_NORMALIZATION_CANDIDATE'
                       END,
                   '\"]'),
                   'pending', ?, ?
            FROM (
                SELECT pairs.*,
                       COUNT(*) OVER (PARTITION BY pairs.statistical_row_id) AS candidate_count,
                       ROW_NUMBER() OVER (
                           PARTITION BY pairs.statistical_row_id
                           ORDER BY pairs.ministry_row_id
                       ) AS candidate_rank
                FROM ipkf_crosswalk_candidate_pairs pairs
            ) classified
        ");
        $city = $cityCandidate ? 1 : 0;
        $statement->execute([
            $runId, $runId, $candidateKind, $city, $city,
            $city, $city, $city, $now, $now,
        ]);
    }

    private function insertUnresolvedRows(
        int $runId,
        int $sourceBatchId,
        string $sourceKind,
        ?string $parentSourceKind,
        string $candidateKind
    ): void {
        $now = Clock::databaseTimestamp();

        if ($parentSourceKind === null) {
            $statement = $this->db->prepare("
                INSERT IGNORE INTO geographic_crosswalk_candidates (
                    crosswalk_run_id, ministry_import_row_id,
                    statistical_import_row_id, candidate_pair_key,
                    candidate_kind, match_status, match_method,
                    primary_reason_code, reason_codes_json,
                    review_status, created_at, updated_at
                )
                SELECT ?, NULL, source.id,
                       SHA2(CONCAT(?, ':', source.id, ':0'), 256),
                       ?, 'unmatched', 'no_compatible_target',
                       'NO_TARGET_CANDIDATE', '[\"NO_TARGET_CANDIDATE\"]',
                       'pending', ?, ?
                FROM geographic_import_rows source
                WHERE source.batch_id = ?
                  AND source.source_entity_kind = ?
                  AND source.validation_status <> 'invalid'
                  AND NOT EXISTS (
                      SELECT 1 FROM geographic_crosswalk_candidates existing
                      WHERE existing.crosswalk_run_id = ?
                        AND existing.statistical_import_row_id = source.id
                  )
            ");
            $statement->execute([
                $runId, $runId, $candidateKind, $now, $now,
                $sourceBatchId, $sourceKind, $runId,
            ]);

            return;
        }

        $statement = $this->db->prepare("
            INSERT IGNORE INTO geographic_crosswalk_candidates (
                crosswalk_run_id, ministry_import_row_id,
                statistical_import_row_id, candidate_pair_key,
                candidate_kind, match_status, match_method,
                primary_reason_code, reason_codes_json,
                review_status, created_at, updated_at
            )
            SELECT ?, NULL, source.id,
                   SHA2(CONCAT(?, ':', source.id, ':0'), 256),
                   ?,
                   CASE
                       WHEN EXISTS (
                           SELECT 1
                           FROM geographic_import_rows source_parent
                           INNER JOIN geographic_crosswalk_candidates parent_candidate
                               ON parent_candidate.crosswalk_run_id = ?
                              AND parent_candidate.statistical_import_row_id = source_parent.id
                              AND parent_candidate.match_status NOT IN ('exact', 'probable')
                           WHERE source_parent.batch_id = source.batch_id
                             AND source_parent.source_entity_kind = ?
                             AND source_parent.source_composite_key = source.source_parent_composite_key
                       ) THEN 'ambiguous'
                       ELSE 'unmatched'
                   END,
                   'parent_first_no_compatible_target',
                   CASE
                       WHEN EXISTS (
                           SELECT 1
                           FROM geographic_import_rows source_parent
                           INNER JOIN geographic_crosswalk_candidates parent_candidate
                               ON parent_candidate.crosswalk_run_id = ?
                              AND parent_candidate.statistical_import_row_id = source_parent.id
                              AND parent_candidate.match_status NOT IN ('exact', 'probable')
                           WHERE source_parent.batch_id = source.batch_id
                             AND source_parent.source_entity_kind = ?
                             AND source_parent.source_composite_key = source.source_parent_composite_key
                       ) THEN 'PARENT_CANDIDATE_UNRESOLVED'
                       ELSE 'NO_TARGET_CANDIDATE'
                   END,
                   CASE
                       WHEN EXISTS (
                           SELECT 1
                           FROM geographic_import_rows source_parent
                           INNER JOIN geographic_crosswalk_candidates parent_candidate
                               ON parent_candidate.crosswalk_run_id = ?
                              AND parent_candidate.statistical_import_row_id = source_parent.id
                              AND parent_candidate.match_status NOT IN ('exact', 'probable')
                           WHERE source_parent.batch_id = source.batch_id
                             AND source_parent.source_entity_kind = ?
                             AND source_parent.source_composite_key = source.source_parent_composite_key
                       ) THEN '[\"PARENT_CANDIDATE_UNRESOLVED\"]'
                       ELSE '[\"NO_TARGET_CANDIDATE\"]'
                   END,
                   'pending', ?, ?
            FROM geographic_import_rows source
            WHERE source.batch_id = ?
              AND source.source_entity_kind = ?
              AND source.validation_status <> 'invalid'
              AND NOT EXISTS (
                  SELECT 1 FROM geographic_crosswalk_candidates existing
                  WHERE existing.crosswalk_run_id = ?
                    AND existing.statistical_import_row_id = source.id
              )
        ");
        $statement->execute([
            $runId, $runId, $candidateKind,
            $runId, $parentSourceKind,
            $runId, $parentSourceKind,
            $runId, $parentSourceKind,
            $now, $now, $sourceBatchId, $sourceKind, $runId,
        ]);
    }
}

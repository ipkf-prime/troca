<?php

namespace App\Repositories;

use App\Services\GeographyCanonicalization\MinistryCanonicalizationPolicy as Policy;
use IPKF\Database\Database;
use IPKF\Support\Clock;
use PDO;
use RuntimeException;

class MinistryCanonicalGeographyRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function completedBatch(string $batchReference): array
    {
        $statement = $this->db->prepare("
            SELECT batches.id, batches.source_id, batches.source_snapshot_id,
                   batches.status, batches.total_rows, batches.summary_json
            FROM geographic_import_batches batches
            INNER JOIN data_sources sources ON sources.id = batches.source_id
            WHERE sources.code = ?
              AND batches.status IN ('validated', 'ready_for_review')
              AND batches.summary_json IS NOT NULL
            ORDER BY batches.id DESC
            LIMIT 200
        ");
        $statement->execute([Policy::SOURCE_CODE]);

        foreach ($statement->fetchAll() as $row) {
            $summary = json_decode((string) $row['summary_json'], true);

            if (is_array($summary)
                && hash_equals((string) ($summary['batch_reference'] ?? ''), $batchReference)
            ) {
                return [
                    'id' => (int) $row['id'],
                    'source_id' => (int) $row['source_id'],
                    'snapshot_id' => (int) $row['source_snapshot_id'],
                    'status' => (string) $row['status'],
                    'total_rows' => (int) $row['total_rows'],
                    'reference' => $batchReference,
                ];
            }
        }

        throw new RuntimeException('Compatible Ministry source batch was not found.');
    }

    public function metadata(): array
    {
        $metadata = [
            'hierarchy_type_id' => $this->idForCode('geographic_hierarchy_types', Policy::HIERARCHY_TYPE),
            'relation_type_id' => $this->idForCode('geographic_relation_types', Policy::RELATION_TYPE),
            'coding_system_id' => $this->idForCode('external_coding_systems', Policy::CODING_SYSTEM),
            'code_set_id' => $this->codeSetId(Policy::CODING_SYSTEM, Policy::HIERARCHY_CODE_SET),
            'national_code_set_id' => $this->codeSetId(Policy::CODING_SYSTEM, 'national_location_identifier'),
            'level_ids' => [],
        ];

        foreach (array_merge(['country'], array_keys(Policy::LEVELS)) as $level) {
            $metadata['level_ids'][$level] = $this->idForCode('geographic_level_types', $level);
        }

        if ($metadata['hierarchy_type_id'] === null
            || $metadata['relation_type_id'] === null
            || $metadata['coding_system_id'] === null
            || $metadata['code_set_id'] === null
            || $metadata['national_code_set_id'] === null
            || in_array(null, $metadata['level_ids'], true)
        ) {
            throw new RuntimeException('Canonical geography metadata is incomplete.');
        }

        return $metadata;
    }

    public function sourceRows(int $batchId): array
    {
        $statement = $this->db->prepare("
            SELECT id, source_code, source_title, normalized_title,
                   derived_level_code, derived_parent_code, row_checksum,
                   validation_status, raw_payload_json
            FROM geographic_import_rows
            WHERE batch_id = ?
            ORDER BY CASE derived_level_code
                WHEN 'province' THEN 20
                WHEN 'county' THEN 30
                WHEN 'district' THEN 40
                WHEN 'rural_district' THEN 50
                WHEN 'city' THEN 60
                ELSE 999
            END, source_code, id
        ");
        $statement->execute([$batchId]);

        return $statement->fetchAll();
    }

    public function canonicalState(array $batch, array $metadata): array
    {
        $codeMappings = [];
        $statement = $this->db->prepare("
            SELECT values_table.code, mappings.geographic_location_id,
                   levels.code AS level_code, locations.status AS location_status
            FROM external_code_values values_table
            INNER JOIN geographic_external_code_mappings mappings
                ON mappings.external_code_value_id = values_table.id
               AND mappings.mapping_status = 'confirmed'
            INNER JOIN geographic_locations locations
                ON locations.id = mappings.geographic_location_id
            INNER JOIN geographic_level_types levels
                ON levels.id = locations.level_type_id
            WHERE values_table.code_set_id = ?
              AND values_table.source_snapshot_id = ?
        ");
        $statement->execute([$metadata['code_set_id'], $batch['snapshot_id']]);

        foreach ($statement->fetchAll() as $row) {
            $codeMappings[(string) $row['code']][] = [
                'location_id' => (int) $row['geographic_location_id'],
                'level' => (string) $row['level_code'],
                'status' => (string) $row['location_status'],
            ];
        }

        $identifierMappings = [];
        $statement = $this->db->prepare("
            SELECT identifiers.identifier_value, identifiers.geographic_location_id,
                   levels.code AS level_code, locations.status AS location_status
            FROM geographic_external_identifiers identifiers
            INNER JOIN geographic_locations locations
                ON locations.id = identifiers.geographic_location_id
            INNER JOIN geographic_level_types levels
                ON levels.id = locations.level_type_id
            WHERE identifiers.source_id = ?
              AND identifiers.source_snapshot_id = ?
              AND identifiers.identifier_type = ?
              AND identifiers.status = 'active'
        ");
        $statement->execute([
            $batch['source_id'],
            $batch['snapshot_id'],
            Policy::HIERARCHY_IDENTIFIER,
        ]);

        foreach ($statement->fetchAll() as $row) {
            $identifierMappings[(string) $row['identifier_value']][] = [
                'location_id' => (int) $row['geographic_location_id'],
                'level' => (string) $row['level_code'],
                'status' => (string) $row['location_status'],
            ];
        }

        $titles = [];
        $levelIds = array_values($metadata['level_ids']);
        $placeholders = implode(',', array_fill(0, count($levelIds), '?'));
        $statement = $this->db->prepare("
            SELECT locations.id, locations.title, levels.code AS level_code
            FROM geographic_locations locations
            INNER JOIN geographic_level_types levels ON levels.id = locations.level_type_id
            WHERE locations.level_type_id IN ({$placeholders})
              AND locations.status = 'active'
        ");
        $statement->execute($levelIds);

        foreach ($statement->fetchAll() as $row) {
            $titles[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'level' => (string) $row['level_code'],
            ];
        }

        $officialParents = [];
        $statement = $this->db->prepare("
            SELECT child_location_id, parent_location_id
            FROM geographic_location_relations
            WHERE relation_type_id = ? AND hierarchy_type_id = ?
              AND status = 'active'
        ");
        $statement->execute([
            $metadata['relation_type_id'],
            $metadata['hierarchy_type_id'],
        ]);

        foreach ($statement->fetchAll() as $row) {
            $officialParents[(int) $row['child_location_id']][] = (int) $row['parent_location_id'];
        }

        return compact('codeMappings', 'identifierMappings', 'titles', 'officialParents');
    }

    public function existingRun(array $batch): ?array
    {
        $statement = $this->db->prepare("
            SELECT * FROM geographic_canonicalization_runs
            WHERE source_snapshot_id = ?
              AND canonicalization_type = ?
              AND algorithm_version = ?
            LIMIT 1
        ");
        $statement->execute([
            $batch['snapshot_id'],
            Policy::CANONICALIZATION_TYPE,
            Policy::ALGORITHM_VERSION,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function storePlan(
        array $batch,
        string $reference,
        string $planFingerprint,
        string $sourceFingerprint,
        array $items,
        array $summary
    ): array {
        return $this->transaction(function () use (
            $batch,
            $reference,
            $planFingerprint,
            $sourceFingerprint,
            $items,
            $summary
        ): array {
            $now = Clock::databaseTimestamp();
            $existing = $this->existingRun($batch);

            if (is_array($existing)) {
                if (in_array($existing['status'], ['planned', 'applying', 'applied', 'ready_for_review', 'failed'], true)) {
                    return $existing;
                }

                $runId = (int) $existing['id'];
                $this->db->prepare("DELETE FROM geographic_canonicalization_items WHERE canonicalization_run_id = ?")
                    ->execute([$runId]);
                $this->db->prepare("
                    UPDATE geographic_canonicalization_runs
                    SET source_batch_id = ?, plan_reference = ?, plan_fingerprint = ?,
                        source_fingerprint = ?, status = 'planning', planned_at = NULL,
                        applied_at = NULL, summary_json = NULL, updated_at = ?
                    WHERE id = ?
                ")->execute([
                    $batch['id'], $reference, $planFingerprint,
                    $sourceFingerprint, $now, $runId,
                ]);
            } else {
                $statement = $this->db->prepare("
                    INSERT INTO geographic_canonicalization_runs (
                        source_id, source_snapshot_id, source_batch_id,
                        canonicalization_type, algorithm_version, plan_reference,
                        plan_fingerprint, source_fingerprint, status,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'planning', ?, ?)
                ");
                $statement->execute([
                    $batch['source_id'], $batch['snapshot_id'], $batch['id'],
                    Policy::CANONICALIZATION_TYPE, Policy::ALGORITHM_VERSION,
                    $reference, $planFingerprint, $sourceFingerprint, $now, $now,
                ]);
                $runId = (int) $this->db->lastInsertId();
            }

            $insert = $this->db->prepare("
                INSERT INTO geographic_canonicalization_items (
                    canonicalization_run_id, import_row_id, action_type,
                    item_status, existing_geographic_location_id,
                    parent_import_row_id, reason_code, review_status,
                    source_fingerprint, created_at, updated_at
                ) VALUES (?, ?, ?, 'planned', ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $insert->execute([
                    $runId,
                    $item['import_row_id'],
                    $item['action_type'],
                    $item['existing_location_id'],
                    $item['parent_import_row_id'],
                    $item['reason_code'],
                    $item['review_status'],
                    $item['source_fingerprint'],
                    $now,
                    $now,
                ]);
            }

            $counts = $this->planCounts($items);
            $statement = $this->db->prepare("
                UPDATE geographic_canonicalization_runs
                SET status = 'planned', planned_at = ?, total_source_rows = ?,
                    eligible_rows = ?, excluded_rows = ?, create_count = ?,
                    reuse_count = ?, conflict_count = ?, summary_json = ?, updated_at = ?
                WHERE id = ?
            ");
            $statement->execute([
                $now,
                count($items),
                $counts['eligible'],
                $counts['exclude'],
                $counts['create'],
                $counts['reuse'],
                $counts['conflict'],
                json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $now,
                $runId,
            ]);

            return $this->runById($runId);
        });
    }

    public function runByReference(string $reference): ?array
    {
        $statement = $this->db->prepare("
            SELECT * FROM geographic_canonicalization_runs
            WHERE plan_reference = ? LIMIT 1
        ");
        $statement->execute([$reference]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function runById(int $runId): array
    {
        $statement = $this->db->prepare("SELECT * FROM geographic_canonicalization_runs WHERE id = ? LIMIT 1");
        $statement->execute([$runId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new RuntimeException('Canonicalization run is unavailable.');
        }

        return $row;
    }

    public function items(int $runId): array
    {
        $statement = $this->db->prepare("
            SELECT items.*, rows.source_code, rows.source_title,
                   rows.normalized_title, rows.derived_level_code,
                   rows.derived_parent_code, rows.row_checksum,
                   rows.validation_status, rows.raw_payload_json
            FROM geographic_canonicalization_items items
            INNER JOIN geographic_import_rows rows ON rows.id = items.import_row_id
            WHERE items.canonicalization_run_id = ?
            ORDER BY CASE rows.derived_level_code
                WHEN 'province' THEN 20
                WHEN 'county' THEN 30
                WHEN 'district' THEN 40
                WHEN 'rural_district' THEN 50
                WHEN 'city' THEN 60
                ELSE 999
            END, rows.source_code, rows.id
        ");
        $statement->execute([$runId]);

        return $statement->fetchAll();
    }

    public function beginApply(int $runId): void
    {
        $this->db->prepare("
            UPDATE geographic_canonicalization_runs
            SET status = 'applying', updated_at = ?
            WHERE id = ? AND status IN ('planned', 'failed', 'ready_for_review')
        ")->execute([Clock::databaseTimestamp(), $runId]);
    }

    public function resolveCountry(int $countryLevelId): array
    {
        $statement = $this->db->prepare("
            SELECT id FROM geographic_locations
            WHERE level_type_id = ? AND status = 'active'
              AND (
                  country_iso_code IN ('IR', 'IRN')
                  OR code = ? OR official_code = ?
                  OR BINARY title = BINARY ?
              )
            ORDER BY id
        ");
        $statement->execute([
            $countryLevelId,
            Policy::COUNTRY_CODE,
            Policy::COUNTRY_CODE,
            Policy::COUNTRY_TITLE,
        ]);
        $ids = array_map('intval', array_column($statement->fetchAll(), 'id'));

        if (count($ids) > 1) {
            return ['status' => 'conflict', 'location_id' => null];
        }

        return [
            'status' => $ids === [] ? 'create' : 'reuse',
            'location_id' => $ids[0] ?? null,
        ];
    }

    public function createCountry(int $countryLevelId): int
    {
        $now = Clock::databaseTimestamp();
        $statement = $this->db->prepare("
            INSERT INTO geographic_locations (
                level_type_id, code, official_code, title, country_iso_code,
                status, is_system, metadata_json, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'active', 1, ?, ?, ?)
        ");
        $statement->execute([
            $countryLevelId,
            Policy::COUNTRY_CODE,
            Policy::COUNTRY_CODE,
            Policy::COUNTRY_TITLE,
            Policy::COUNTRY_ISO,
            json_encode(['managed_by' => 'ministry_canonicalization'], JSON_UNESCAPED_UNICODE),
            $now,
            $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function resolveOrCreateCountry(int $countryLevelId): int
    {
        $country = $this->resolveCountry($countryLevelId);

        if ($country['status'] === 'conflict') {
            throw new RuntimeException('Iran country root requires review.');
        }

        return $country['location_id'] ?? $this->createCountry($countryLevelId);
    }

    public function acquireApplyLock(int $runId): bool
    {
        $statement = $this->db->prepare("SELECT GET_LOCK(?, 0)");
        $statement->execute(['ipkf_geo_apply_' . $runId]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function releaseApplyLock(int $runId): void
    {
        $statement = $this->db->prepare("SELECT RELEASE_LOCK(?)");
        $statement->execute(['ipkf_geo_apply_' . $runId]);
    }

    public function locationLevel(int $locationId): ?string
    {
        $statement = $this->db->prepare("
            SELECT levels.code
            FROM geographic_locations locations
            INNER JOIN geographic_level_types levels ON levels.id = locations.level_type_id
            WHERE locations.id = ? AND locations.status = 'active' LIMIT 1
        ");
        $statement->execute([$locationId]);
        $level = $statement->fetchColumn();

        return $level === false ? null : (string) $level;
    }

    public function createLocation(array $item, int $levelTypeId, int $sourceId, int $snapshotId): int
    {
        $now = Clock::databaseTimestamp();
        $statement = $this->db->prepare("
            INSERT INTO geographic_locations (
                level_type_id, title, country_iso_code, status, is_system,
                metadata_json, created_at, updated_at
            ) VALUES (?, ?, ?, 'active', 0, ?, ?, ?)
        ");
        $statement->execute([
            $levelTypeId,
            $item['source_title'],
            Policy::COUNTRY_ISO,
            json_encode([
                'managed_by' => 'ministry_canonicalization',
                'source_id' => $sourceId,
                'source_snapshot_id' => $snapshotId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $now,
            $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function codeValue(
        int $codeSetId,
        int $snapshotId,
        array $item,
        ?int $parentCodeValueId
    ): int {
        $statement = $this->db->prepare("
            SELECT id, parent_code_value_id FROM external_code_values
            WHERE code_set_id = ? AND source_snapshot_id = ? AND code = ?
            LIMIT 1
        ");
        $statement->execute([$codeSetId, $snapshotId, $item['source_code']]);
        $existing = $statement->fetch();

        if (is_array($existing)) {
            if ((int) ($existing['parent_code_value_id'] ?? 0) !== (int) ($parentCodeValueId ?? 0)) {
                throw new RuntimeException('External code parent conflict.');
            }

            return (int) $existing['id'];
        }

        $now = Clock::databaseTimestamp();
        $statement = $this->db->prepare("
            INSERT INTO external_code_values (
                code_set_id, source_snapshot_id, code, title, normalized_title,
                parent_code_value_id, external_status, raw_metadata_json,
                row_checksum, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?)
        ");
        $statement->execute([
            $codeSetId,
            $snapshotId,
            $item['source_code'],
            $item['source_title'],
            $item['normalized_title'],
            $parentCodeValueId,
            json_encode([
                'source_parent_code' => $item['derived_parent_code'],
                'source_level' => $item['derived_level_code'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $item['row_checksum'],
            $now,
            $now,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function codeValueId(int $codeSetId, int $snapshotId, ?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        $statement = $this->db->prepare("
            SELECT id FROM external_code_values
            WHERE code_set_id = ? AND source_snapshot_id = ? AND code = ? LIMIT 1
        ");
        $statement->execute([$codeSetId, $snapshotId, $code]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function codeValueParentConflict(
        int $codeSetId,
        int $snapshotId,
        string $code,
        ?int $expectedParentId
    ): bool {
        $statement = $this->db->prepare("
            SELECT parent_code_value_id FROM external_code_values
            WHERE code_set_id = ? AND source_snapshot_id = ? AND code = ? LIMIT 1
        ");
        $statement->execute([$codeSetId, $snapshotId, $code]);
        $parentId = $statement->fetchColumn();

        return $parentId !== false && (int) ($parentId ?? 0) !== (int) ($expectedParentId ?? 0);
    }

    public function ensureIdentifier(
        int $locationId,
        int $sourceId,
        int $snapshotId,
        int $codingSystemId,
        int $codeSetId,
        string $type,
        string $value,
        bool $primary
    ): bool {
        if ($value === '') {
            return false;
        }

        if ($type === Policy::HIERARCHY_IDENTIFIER) {
            $statement = $this->db->prepare("
                SELECT DISTINCT geographic_location_id
                FROM geographic_external_identifiers
                WHERE source_id = ? AND source_snapshot_id = ?
                  AND identifier_type = ? AND identifier_value = ?
                  AND status = 'active'
            ");
            $statement->execute([$sourceId, $snapshotId, $type, $value]);
            $locations = array_map('intval', array_column($statement->fetchAll(), 'geographic_location_id'));

            if ($locations !== [] && !in_array($locationId, $locations, true)) {
                throw new RuntimeException('Hierarchy identifier conflict.');
            }
        }

        $statement = $this->db->prepare("
            SELECT id FROM geographic_external_identifiers
            WHERE geographic_location_id = ? AND source_id = ?
              AND source_snapshot_id = ? AND identifier_type = ?
              AND identifier_value = ? LIMIT 1
        ");
        $statement->execute([$locationId, $sourceId, $snapshotId, $type, $value]);

        if ($statement->fetchColumn() !== false) {
            return false;
        }

        $now = Clock::databaseTimestamp();
        $this->db->prepare("
            INSERT INTO geographic_external_identifiers (
                geographic_location_id, source_id, source_snapshot_id,
                coding_system_id, code_set_id, identifier_type,
                identifier_value, is_primary, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
        ")->execute([
            $locationId, $sourceId, $snapshotId, $codingSystemId,
            $codeSetId, $type, $value, $primary ? 1 : 0, $now, $now,
        ]);

        return true;
    }

    public function ensureConfirmedMapping(int $codeValueId, int $locationId): bool
    {
        $statement = $this->db->prepare("
            SELECT id, geographic_location_id
            FROM geographic_external_code_mappings
            WHERE external_code_value_id = ? AND mapping_status = 'confirmed'
            ORDER BY id
        ");
        $statement->execute([$codeValueId]);
        $rows = $statement->fetchAll();

        foreach ($rows as $row) {
            if ((int) $row['geographic_location_id'] !== $locationId) {
                throw new RuntimeException('Confirmed source mapping conflict.');
            }
        }

        if ($rows !== []) {
            return false;
        }

        $now = Clock::databaseTimestamp();
        $this->db->prepare("
            INSERT INTO geographic_external_code_mappings (
                external_code_value_id, geographic_location_id, mapping_status,
                match_method, confidence_score, reviewed_at, notes,
                created_at, updated_at
            ) VALUES (?, ?, 'confirmed', 'authoritative_source_apply', 100.00, ?, ?, ?, ?)
        ")->execute([
            $codeValueId,
            $locationId,
            $now,
            'Applied by protected Ministry canonicalization policy.',
            $now,
            $now,
        ]);

        return true;
    }

    public function ensureOfficialRelation(
        int $parentId,
        int $childId,
        int $relationTypeId,
        int $hierarchyTypeId,
        int $sourceId,
        int $snapshotId
    ): bool {
        if ($this->officialParentConflict($parentId, $childId, $relationTypeId, $hierarchyTypeId)) {
            throw new RuntimeException('Official parent conflict.');
        }

        $statement = $this->db->prepare("
            SELECT parent_location_id
            FROM geographic_location_relations
            WHERE child_location_id = ? AND relation_type_id = ?
              AND hierarchy_type_id = ? AND status = 'active'
        ");
        $statement->execute([$childId, $relationTypeId, $hierarchyTypeId]);
        $parents = array_values(array_unique(array_map('intval', array_column($statement->fetchAll(), 'parent_location_id'))));

        if ($parents === [$parentId]) {
            return false;
        }

        $now = Clock::databaseTimestamp();
        $this->db->prepare("
            INSERT INTO geographic_location_relations (
                parent_location_id, child_location_id, relation_type_id,
                hierarchy_type_id, source_id, source_snapshot_id,
                is_primary, status, review_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, 'active', 'approved_by_policy', ?, ?)
        ")->execute([
            $parentId, $childId, $relationTypeId, $hierarchyTypeId,
            $sourceId, $snapshotId, $now, $now,
        ]);

        return true;
    }

    public function officialParentConflict(
        int $parentId,
        int $childId,
        int $relationTypeId,
        int $hierarchyTypeId
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM geographic_location_relations
            WHERE child_location_id = ? AND relation_type_id = ?
              AND hierarchy_type_id = ? AND status = 'active'
              AND parent_location_id <> ?
        ");
        $statement->execute([$childId, $relationTypeId, $hierarchyTypeId, $parentId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function markItemApplied(
        int $itemId,
        int $locationId,
        int $parentLocationId
    ): void {
        $this->db->prepare("
            UPDATE geographic_canonicalization_items
            SET resulting_geographic_location_id = ?,
                resulting_parent_location_id = ?, item_status = 'applied',
                review_status = 'applied', updated_at = ?
            WHERE id = ?
        ")->execute([$locationId, $parentLocationId, Clock::databaseTimestamp(), $itemId]);
    }

    public function markItemConflict(int $itemId, string $reasonCode): void
    {
        $this->db->prepare("
            UPDATE geographic_canonicalization_items
            SET action_type = 'conflict', item_status = 'blocked',
                reason_code = ?, review_status = 'review_required', updated_at = ?
            WHERE id = ?
        ")->execute([$reasonCode, Clock::databaseTimestamp(), $itemId]);
    }

    public function parentLocation(int $runId, ?int $parentImportRowId): ?int
    {
        if ($parentImportRowId === null) {
            return null;
        }

        $statement = $this->db->prepare("
            SELECT resulting_geographic_location_id
            FROM geographic_canonicalization_items
            WHERE canonicalization_run_id = ? AND import_row_id = ?
              AND item_status = 'applied'
            LIMIT 1
        ");
        $statement->execute([$runId, $parentImportRowId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function addApplyCounts(int $runId, int $relations, int $identifiers, int $mappings): void
    {
        $this->db->prepare("
            UPDATE geographic_canonicalization_runs
            SET relation_create_count = relation_create_count + ?,
                identifier_create_count = identifier_create_count + ?,
                mapping_create_count = mapping_create_count + ?,
                updated_at = ?
            WHERE id = ?
        ")->execute([$relations, $identifiers, $mappings, Clock::databaseTimestamp(), $runId]);
    }

    public function finishApply(int $runId, array $summary, string $status): void
    {
        $now = Clock::databaseTimestamp();
        $this->db->prepare("
            UPDATE geographic_canonicalization_runs
            SET status = ?, applied_at = CASE WHEN ? = 'applied' THEN ? ELSE applied_at END,
                conflict_count = (
                    SELECT COUNT(*) FROM geographic_canonicalization_items
                    WHERE canonicalization_run_id = ? AND action_type = 'conflict'
                ),
                summary_json = ?, updated_at = ?
            WHERE id = ?
        ")->execute([
            $status,
            $status,
            $now,
            $runId,
            json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $now,
            $runId,
        ]);
    }

    public function failApply(int $runId): void
    {
        $this->db->prepare("
            UPDATE geographic_canonicalization_runs
            SET status = 'failed', updated_at = ?
            WHERE id = ? AND status <> 'applied'
        ")->execute([Clock::databaseTimestamp(), $runId]);
    }

    public function markPlanStale(int $runId): void
    {
        $this->db->prepare("
            UPDATE geographic_canonicalization_runs
            SET status = 'stale', updated_at = ?
            WHERE id = ? AND status <> 'applied'
        ")->execute([Clock::databaseTimestamp(), $runId]);
    }

    public function aggregateApplyState(int $runId): array
    {
        $statement = $this->db->prepare("
            SELECT
                SUM(action_type = 'create' AND item_status = 'applied') AS created_locations,
                SUM(action_type = 'reuse' AND item_status = 'applied') AS reused_locations,
                SUM(action_type = 'exclude') AS excluded_rows,
                SUM(action_type = 'conflict') AS conflict_count,
                SUM(action_type IN ('create', 'reuse') AND item_status <> 'applied') AS pending_safe_items
            FROM geographic_canonicalization_items
            WHERE canonicalization_run_id = ?
        ");
        $statement->execute([$runId]);
        $row = $statement->fetch() ?: [];

        return array_map('intval', $row);
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

    private function planCounts(array $items): array
    {
        $counts = ['eligible' => 0, 'create' => 0, 'reuse' => 0, 'conflict' => 0, 'exclude' => 0];

        foreach ($items as $item) {
            $action = $item['action_type'];
            $counts[$action]++;

            if ($action !== 'exclude') {
                $counts['eligible']++;
            }
        }

        return $counts;
    }

    private function idForCode(string $table, string $code): ?int
    {
        $statement = $this->db->prepare("SELECT id FROM {$table} WHERE code = ? AND status = 'active' LIMIT 1");
        $statement->execute([$code]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function codeSetId(string $systemCode, string $setCode): ?int
    {
        $statement = $this->db->prepare("
            SELECT sets.id FROM external_code_sets sets
            INNER JOIN external_coding_systems systems ON systems.id = sets.coding_system_id
            WHERE systems.code = ? AND sets.code = ? AND sets.status = 'active' LIMIT 1
        ");
        $statement->execute([$systemCode, $setCode]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}

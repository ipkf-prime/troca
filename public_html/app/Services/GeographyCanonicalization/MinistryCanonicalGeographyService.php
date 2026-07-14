<?php

namespace App\Services\GeographyCanonicalization;

use App\Repositories\MinistryCanonicalGeographyRepository;
use App\Services\GeographyImport\PersianTextNormalizer;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MinistryCanonicalGeographyService
{
    public const PLAN_MODE = 'plan';
    public const APPLY_MODE = 'apply';

    public function __construct(
        private readonly MinistryCanonicalGeographyRepository $repository = new MinistryCanonicalGeographyRepository(),
        private readonly PersianTextNormalizer $normalizer = new PersianTextNormalizer()
    ) {
    }

    public function run(
        string $sourceBatchReference,
        string $mode,
        ?string $planReference = null,
        ?string $fingerprintPrefix = null
    ): array {
        $this->validateRequest($sourceBatchReference, $mode, $planReference, $fingerprintPrefix);

        return $mode === self::PLAN_MODE
            ? $this->plan($sourceBatchReference)
            : $this->apply($sourceBatchReference, (string) $planReference, (string) $fingerprintPrefix);
    }

    public function plan(string $sourceBatchReference): array
    {
        $batch = $this->repository->completedBatch($sourceBatchReference);
        $metadata = $this->repository->metadata();
        $existing = $this->repository->existingRun($batch);

        if (is_array($existing)
            && in_array($existing['status'], ['planned', 'applying', 'applied', 'ready_for_review', 'failed'], true)
        ) {
            return $this->planResponse($existing, $sourceBatchReference);
        }

        $classification = $this->classify($batch, $metadata);
        $reference = 'CAN-' . strtoupper(bin2hex(random_bytes(6)));
        $privateSummary = [
            'plan' => $classification['summary'],
            'country_resolution' => $classification['country'],
        ];
        $run = $this->repository->storePlan(
            $batch,
            $reference,
            $classification['plan_fingerprint'],
            $classification['source_fingerprint'],
            $classification['items'],
            $privateSummary
        );

        return $this->planResponse($run, $sourceBatchReference);
    }

    public function apply(
        string $sourceBatchReference,
        string $planReference,
        string $fingerprintPrefix
    ): array {
        $batch = $this->repository->completedBatch($sourceBatchReference);
        $metadata = $this->repository->metadata();
        $run = $this->repository->runByReference($planReference);

        if (!is_array($run)
            || (int) $run['source_batch_id'] !== $batch['id']
            || (int) $run['source_snapshot_id'] !== $batch['snapshot_id']
            || !hash_equals(
                strtoupper(substr((string) $run['plan_fingerprint'], 0, MinistryCanonicalizationPolicy::FINGERPRINT_PREFIX_LENGTH)),
                strtoupper($fingerprintPrefix)
            )
        ) {
            throw new InvalidArgumentException('Canonicalization confirmation is invalid.');
        }

        if ($run['status'] === 'applied') {
            return $this->storedApplyResponse($run, $sourceBatchReference);
        }

        if (!in_array($run['status'], ['planned', 'applying', 'failed', 'ready_for_review'], true)) {
            throw new InvalidArgumentException('Canonicalization plan is not applicable.');
        }

        $sourceRows = $this->repository->sourceRows($batch['id']);
        $sourceFingerprint = $this->sourceFingerprint($sourceRows);

        if (!hash_equals((string) $run['source_fingerprint'], $sourceFingerprint)) {
            $this->repository->markPlanStale((int) $run['id']);
            throw new RuntimeException('Canonicalization plan is stale.');
        }

        if ($run['status'] === 'planned') {
            $current = $this->classify($batch, $metadata, $sourceRows);

            if (!hash_equals((string) $run['plan_fingerprint'], $current['plan_fingerprint'])) {
                $this->repository->markPlanStale((int) $run['id']);
                throw new RuntimeException('Canonicalization plan conflicts with current canonical state.');
            }
        }

        $country = $this->repository->resolveCountry($metadata['level_ids']['country']);

        if ($country['status'] === 'conflict') {
            throw new RuntimeException('Iran country root requires review.');
        }

        $runId = (int) $run['id'];

        if (!$this->repository->acquireApplyLock($runId)) {
            throw new RuntimeException('Canonical geography apply is already running.');
        }

        try {
            $this->repository->beginApply($runId);
            $countryId = $this->repository->transaction(
                fn (): int => $this->repository->resolveOrCreateCountry($metadata['level_ids']['country'])
            );

            $items = $this->repository->items($runId);

            foreach (array_chunk($items, MinistryCanonicalizationPolicy::APPLY_CHUNK_SIZE) as $chunk) {
                $this->repository->transaction(function () use (
                    $chunk,
                    $runId,
                    $countryId,
                    $batch,
                    $metadata
                ): void {
                    $state = $this->repository->canonicalState($batch, $metadata);
                    $counts = ['relations' => 0, 'identifiers' => 0, 'mappings' => 0];

                    foreach ($chunk as $item) {
                        $itemCounts = $this->applyItem($item, $runId, $countryId, $batch, $metadata, $state);

                        foreach ($counts as $key => $value) {
                            $counts[$key] += $itemCounts[$key];
                        }
                    }

                    if (array_sum($counts) > 0) {
                        $this->repository->addApplyCounts(
                            $runId,
                            $counts['relations'],
                            $counts['identifiers'],
                            $counts['mappings']
                        );
                    }
                });
            }
            $state = $this->repository->aggregateApplyState($runId);
            $finalStatus = $state['conflict_count'] === 0 && $state['pending_safe_items'] === 0
                ? 'applied'
                : 'ready_for_review';
            $currentRun = $this->repository->runById($runId);
            $summary = $this->privateSummary($currentRun);
            $summary['apply'] = [
                'success' => true,
                'plan_reference' => $planReference,
                'final_status' => $finalStatus,
                'created_locations' => $state['created_locations'],
                'reused_locations' => $state['reused_locations'],
                'created_relations' => (int) $currentRun['relation_create_count'],
                'created_identifiers' => (int) $currentRun['identifier_create_count'],
                'created_confirmed_mappings' => (int) $currentRun['mapping_create_count'],
                'excluded_rows' => $state['excluded_rows'],
                'conflict_count' => $state['conflict_count'],
                'canonical_write_performed' => true,
                'sci_write_performed' => false,
                'bot_write_performed' => false,
            ];
            $this->repository->finishApply($runId, $summary, $finalStatus);

            return $summary['apply'];
        } catch (Throwable $exception) {
            $this->repository->failApply($runId);
            throw new RuntimeException('Canonical geography apply failed safely and may be resumed.', 0, $exception);
        } finally {
            $this->repository->releaseApplyLock($runId);
        }
    }

    private function classify(array $batch, array $metadata, ?array $sourceRows = null): array
    {
        $rows = $sourceRows ?? $this->repository->sourceRows($batch['id']);

        if (count($rows) !== $batch['total_rows']) {
            throw new RuntimeException('Ministry source row count changed after validation.');
        }

        $canonical = $this->repository->canonicalState($batch, $metadata);
        $country = $this->repository->resolveCountry($metadata['level_ids']['country']);
        $titles = [];

        foreach ($canonical['titles'] as $location) {
            $key = $location['level'] . "\0" . $this->normalizer->text($location['title']);
            $titles[$key][] = $location['id'];
        }

        $codeCounts = [];
        $rowIdByCode = [];
        $rowByCode = [];

        foreach ($rows as $row) {
            $code = (string) ($row['source_code'] ?? '');

            if ($code !== '') {
                $codeCounts[$code] = ($codeCounts[$code] ?? 0) + 1;
                $rowIdByCode[$code] = (int) $row['id'];
                $rowByCode[$code] = $row;
            }
        }

        $items = [];
        $countsByLevel = [];
        $countsByReason = [];
        $plannedLocationByCode = [];

        foreach ($rows as $row) {
            $level = (string) ($row['derived_level_code'] ?? '');
            $code = (string) ($row['source_code'] ?? '');
            $parentCode = (string) ($row['derived_parent_code'] ?? '');
            $reason = null;
            $action = null;
            $existingLocationId = null;
            $parentRowId = null;

            if ($row['validation_status'] === 'invalid') {
                $action = 'exclude';
                $reason = $code === '' ? 'MISSING_HIERARCHY_CODE' : 'SOURCE_ROW_INVALID';
            } elseif (!MinistryCanonicalizationPolicy::levelSupported($level)) {
                $action = 'exclude';
                $reason = 'UNSUPPORTED_LEVEL';
            } elseif ($code === '' || preg_match('/\A\d+\z/D', $code) !== 1) {
                $action = 'exclude';
                $reason = 'STRUCTURALLY_INVALID_CODE';
            } elseif (($codeCounts[$code] ?? 0) !== 1) {
                $action = 'exclude';
                $reason = 'DUPLICATE_HIERARCHY_CODE';
            } elseif ($level !== 'province' && !isset($rowIdByCode[$parentCode])) {
                $action = 'exclude';
                $reason = 'MISSING_SOURCE_PARENT';
            } elseif ($level !== 'province'
                && (($rowByCode[$parentCode]['validation_status'] ?? 'invalid') === 'invalid'
                    || ($rowByCode[$parentCode]['derived_level_code'] ?? null)
                        !== MinistryCanonicalizationPolicy::LEVELS[$level]['parent'])
            ) {
                $action = 'exclude';
                $reason = 'INCOMPATIBLE_SOURCE_PARENT';
            } else {
                $parentRowId = $level === 'province' ? null : $rowIdByCode[$parentCode];
                $trusted = $this->trustedMatch($code, $level, $canonical);

                if ($trusted['status'] === 'conflict') {
                    $action = 'conflict';
                    $reason = $trusted['reason'];
                } elseif ($trusted['status'] === 'reuse') {
                    $action = 'reuse';
                    $reason = 'TRUSTED_MINISTRY_MAPPING';
                    $existingLocationId = $trusted['location_id'];
                    $intendedParentId = $level === 'province'
                        ? $country['location_id']
                        : ($plannedLocationByCode[$parentCode] ?? null);
                    $currentParents = array_values(array_unique(
                        $canonical['officialParents'][$existingLocationId] ?? []
                    ));

                    if ($currentParents !== []
                        && ($intendedParentId === null
                            || count($currentParents) !== 1
                            || $currentParents[0] !== $intendedParentId)
                    ) {
                        $action = 'conflict';
                        $reason = 'OFFICIAL_PARENT_CONFLICT';
                        $existingLocationId = null;
                    }
                } else {
                    $titleKey = $level . "\0" . $this->normalizer->text($row['normalized_title'] ?? $row['source_title']);

                    if (($titles[$titleKey] ?? []) !== []) {
                        $action = 'conflict';
                        $reason = 'TITLE_ONLY_MATCH_REVIEW';
                    } else {
                        $action = 'create';
                        $reason = 'AUTHORITATIVE_SOURCE_CREATE';
                    }
                }
            }

            $sourceFingerprint = $this->rowFingerprint($row);
            $items[] = [
                'import_row_id' => (int) $row['id'],
                'action_type' => $action,
                'existing_location_id' => $existingLocationId,
                'parent_import_row_id' => $parentRowId,
                'reason_code' => $reason,
                'review_status' => match ($action) {
                    'create', 'reuse' => 'approved_by_policy',
                    'conflict' => 'review_required',
                    default => 'rejected',
                },
                'source_fingerprint' => $sourceFingerprint,
                'level' => $level !== '' ? $level : 'unsupported',
            ];
            $plannedLocationByCode[$code] = $action === 'reuse' ? $existingLocationId : null;
            $countsByLevel[$level !== '' ? $level : 'unsupported'] =
                ($countsByLevel[$level !== '' ? $level : 'unsupported'] ?? 0) + 1;
            $countsByReason[$reason] = ($countsByReason[$reason] ?? 0) + 1;
        }

        ksort($countsByLevel);
        ksort($countsByReason);
        $counts = $this->counts($items);
        $sourceFingerprint = $this->sourceFingerprint($rows);
        $planFingerprint = $this->planFingerprint($sourceFingerprint, $country, $items);

        return [
            'items' => $items,
            'country' => $country,
            'source_fingerprint' => $sourceFingerprint,
            'plan_fingerprint' => $planFingerprint,
            'summary' => [
                'algorithm_version' => MinistryCanonicalizationPolicy::ALGORITHM_VERSION,
                'total_source_rows' => count($items),
                'eligible_rows' => $counts['eligible'],
                'excluded_rows' => $counts['exclude'],
                'create_count' => $counts['create'],
                'reuse_count' => $counts['reuse'],
                'conflict_count' => $counts['conflict'],
                'counts_by_level' => $countsByLevel,
                'counts_by_reason_code' => $countsByReason,
            ],
        ];
    }

    private function applyItem(
        array $item,
        int $runId,
        int $countryId,
        array $batch,
        array $metadata,
        array $canonical
    ): array {
        if ($item['item_status'] === 'applied'
            || in_array($item['action_type'], ['exclude', 'conflict', 'no_change'], true)
        ) {
            return ['relations' => 0, 'identifiers' => 0, 'mappings' => 0];
        }

        if (!hash_equals((string) $item['source_fingerprint'], $this->rowFingerprint($item))) {
            throw new RuntimeException('Canonicalization source item changed after planning.');
        }

        $level = (string) $item['derived_level_code'];
        $parentId = $level === 'province'
            ? $countryId
            : $this->repository->parentLocation($runId, (int) $item['parent_import_row_id']);

        if ($parentId === null) {
            $this->repository->markItemConflict((int) $item['id'], 'PARENT_NOT_APPLIED');

            return ['relations' => 0, 'identifiers' => 0, 'mappings' => 0];
        }

        $locationId = $item['existing_geographic_location_id'] !== null
            ? (int) $item['existing_geographic_location_id']
            : null;

        if ($locationId === null) {
            $trusted = $this->trustedMatch((string) $item['source_code'], $level, $canonical);

            if ($trusted['status'] === 'conflict') {
                $this->repository->markItemConflict((int) $item['id'], $trusted['reason']);

                return ['relations' => 0, 'identifiers' => 0, 'mappings' => 0];
            }

            $locationId = $trusted['status'] === 'reuse' ? $trusted['location_id'] : null;
        }

        if ($locationId !== null
            && $this->repository->officialParentConflict(
                $parentId,
                $locationId,
                $metadata['relation_type_id'],
                $metadata['hierarchy_type_id']
            )
        ) {
            $this->repository->markItemConflict((int) $item['id'], 'OFFICIAL_PARENT_CONFLICT');

            return ['relations' => 0, 'identifiers' => 0, 'mappings' => 0];
        }

        if ($locationId === null) {
            $locationId = $this->repository->createLocation(
                $item,
                $metadata['level_ids'][$level],
                $batch['source_id'],
                $batch['snapshot_id']
            );
        } elseif ($this->repository->locationLevel($locationId) !== $level) {
            $this->repository->markItemConflict((int) $item['id'], 'INCOMPATIBLE_MAPPED_LEVEL');

            return ['relations' => 0, 'identifiers' => 0, 'mappings' => 0];
        }

        $parentCodeValueId = $level === 'province'
            ? null
            : $this->repository->codeValueId(
                $metadata['code_set_id'],
                $batch['snapshot_id'],
                (string) $item['derived_parent_code']
            );

        if ($level !== 'province' && $parentCodeValueId === null) {
            throw new RuntimeException('Parent external code value is unavailable.');
        }

        if ($this->repository->codeValueParentConflict(
            $metadata['code_set_id'],
            $batch['snapshot_id'],
            (string) $item['source_code'],
            $parentCodeValueId
        )) {
            $this->repository->markItemConflict((int) $item['id'], 'EXTERNAL_CODE_PARENT_CONFLICT');

            return ['relations' => 0, 'identifiers' => 0, 'mappings' => 0];
        }

        $codeValueId = $this->repository->codeValue(
            $metadata['code_set_id'],
            $batch['snapshot_id'],
            $item,
            $parentCodeValueId
        );
        $relations = $this->repository->ensureOfficialRelation(
            $parentId,
            $locationId,
            $metadata['relation_type_id'],
            $metadata['hierarchy_type_id'],
            $batch['source_id'],
            $batch['snapshot_id']
        ) ? 1 : 0;
        $identifiers = $this->repository->ensureIdentifier(
            $locationId,
            $batch['source_id'],
            $batch['snapshot_id'],
            $metadata['coding_system_id'],
            $metadata['code_set_id'],
            MinistryCanonicalizationPolicy::HIERARCHY_IDENTIFIER,
            (string) $item['source_code'],
            true
        ) ? 1 : 0;
        $nationalIdentifier = $this->nationalIdentifier($item['raw_payload_json'] ?? null);

        if ($nationalIdentifier !== '') {
            $identifiers += $this->repository->ensureIdentifier(
                $locationId,
                $batch['source_id'],
                $batch['snapshot_id'],
                $metadata['coding_system_id'],
                $metadata['national_code_set_id'],
                MinistryCanonicalizationPolicy::NATIONAL_IDENTIFIER,
                $nationalIdentifier,
                false
            ) ? 1 : 0;
        }

        $mappings = $this->repository->ensureConfirmedMapping($codeValueId, $locationId) ? 1 : 0;
        $this->repository->markItemApplied((int) $item['id'], $locationId, $parentId);
        return [
            'relations' => $relations,
            'identifiers' => $identifiers,
            'mappings' => $mappings,
        ];
    }

    private function trustedMatch(string $code, string $level, array $canonical): array
    {
        $matches = array_merge(
            $canonical['codeMappings'][$code] ?? [],
            $canonical['identifierMappings'][$code] ?? []
        );
        $byLocation = [];

        foreach ($matches as $match) {
            $byLocation[$match['location_id']] = [
                'level' => $match['level'],
                'status' => $match['status'] ?? 'active',
            ];
        }

        if (count($byLocation) > 1) {
            return ['status' => 'conflict', 'location_id' => null, 'reason' => 'TRUSTED_MAPPING_DISAGREEMENT'];
        }

        if ($byLocation === []) {
            return ['status' => 'none', 'location_id' => null, 'reason' => null];
        }

        $locationId = (int) array_key_first($byLocation);

        if ($byLocation[$locationId]['status'] !== 'active') {
            return ['status' => 'conflict', 'location_id' => null, 'reason' => 'INACTIVE_MAPPED_LOCATION'];
        }

        if ($byLocation[$locationId]['level'] !== $level) {
            return ['status' => 'conflict', 'location_id' => null, 'reason' => 'INCOMPATIBLE_MAPPED_LEVEL'];
        }

        return ['status' => 'reuse', 'location_id' => $locationId, 'reason' => null];
    }

    private function rowFingerprint(array $row): string
    {
        return hash('sha256', implode('|', [
            (string) ($row['import_row_id'] ?? $row['id'] ?? ''),
            (string) ($row['row_checksum'] ?? ''),
            (string) ($row['validation_status'] ?? ''),
            (string) ($row['source_code'] ?? ''),
            (string) ($row['derived_parent_code'] ?? ''),
            (string) ($row['derived_level_code'] ?? ''),
        ]));
    }

    private function sourceFingerprint(array $rows): string
    {
        return hash('sha256', implode('', array_map(fn (array $row): string => $this->rowFingerprint($row), $rows)));
    }

    private function planFingerprint(string $sourceFingerprint, array $country, array $items): string
    {
        $payload = [
            'source' => $sourceFingerprint,
            'country' => [$country['status'], $country['location_id']],
            'items' => array_map(static fn (array $item): array => [
                $item['import_row_id'],
                $item['action_type'],
                $item['existing_location_id'],
                $item['parent_import_row_id'],
                $item['reason_code'],
                $item['source_fingerprint'],
            ], $items),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function counts(array $items): array
    {
        $counts = ['eligible' => 0, 'create' => 0, 'reuse' => 0, 'conflict' => 0, 'exclude' => 0];

        foreach ($items as $item) {
            $counts[$item['action_type']]++;

            if ($item['action_type'] !== 'exclude') {
                $counts['eligible']++;
            }
        }

        return $counts;
    }

    private function planResponse(array $run, string $sourceBatchReference): array
    {
        $summary = $this->privateSummary($run)['plan'] ?? [];

        return [
            'success' => true,
            'plan_reference' => (string) $run['plan_reference'],
            'algorithm_version' => MinistryCanonicalizationPolicy::ALGORITHM_VERSION,
            'source_batch_reference' => $sourceBatchReference,
            'plan_fingerprint_prefix' => strtoupper(substr(
                (string) $run['plan_fingerprint'],
                0,
                MinistryCanonicalizationPolicy::FINGERPRINT_PREFIX_LENGTH
            )),
            'total_source_rows' => (int) ($summary['total_source_rows'] ?? $run['total_source_rows']),
            'eligible_rows' => (int) ($summary['eligible_rows'] ?? $run['eligible_rows']),
            'excluded_rows' => (int) ($summary['excluded_rows'] ?? $run['excluded_rows']),
            'create_count' => (int) ($summary['create_count'] ?? $run['create_count']),
            'reuse_count' => (int) ($summary['reuse_count'] ?? $run['reuse_count']),
            'conflict_count' => (int) ($summary['conflict_count'] ?? $run['conflict_count']),
            'counts_by_level' => $summary['counts_by_level'] ?? [],
            'counts_by_reason_code' => $summary['counts_by_reason_code'] ?? [],
            'canonical_write_performed' => false,
        ];
    }

    private function storedApplyResponse(array $run, string $sourceBatchReference): array
    {
        $summary = $this->privateSummary($run);
        $response = $summary['apply'] ?? null;

        if (!is_array($response)) {
            throw new RuntimeException('Applied canonicalization summary is unavailable.');
        }

        return $response;
    }

    private function privateSummary(array $run): array
    {
        $summary = json_decode((string) ($run['summary_json'] ?? ''), true);

        return is_array($summary) ? $summary : [];
    }

    private function nationalIdentifier(mixed $rawPayload): string
    {
        $payload = json_decode((string) $rawPayload, true);

        return is_array($payload)
            ? $this->normalizer->code($payload['national_identifier'] ?? '')
            : '';
    }

    private function validateRequest(
        string $sourceBatchReference,
        string $mode,
        ?string $planReference,
        ?string $fingerprintPrefix
    ): void {
        if (preg_match('/\AMOI-[A-F0-9]{12}\z/D', $sourceBatchReference) !== 1
            || !in_array($mode, [self::PLAN_MODE, self::APPLY_MODE], true)
        ) {
            throw new InvalidArgumentException('Unsupported canonicalization request.');
        }

        if ($mode === self::APPLY_MODE
            && (preg_match('/\ACAN-[A-F0-9]{12}\z/D', (string) $planReference) !== 1
                || preg_match('/\A[A-F0-9]{16}\z/D', strtoupper((string) $fingerprintPrefix)) !== 1)
        ) {
            throw new InvalidArgumentException('Canonicalization confirmation is incomplete.');
        }
    }
}

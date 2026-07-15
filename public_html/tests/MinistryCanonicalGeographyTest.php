<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Repositories\MinistryCanonicalGeographyRepository;
use App\Services\GeographyCanonicalization\MinistryCanonicalizationApplyException;
use App\Services\GeographyCanonicalization\MinistryCanonicalizationFailureLogger;
use App\Services\GeographyCanonicalization\MinistryCanonicalGeographyService;

class FakeMinistryCanonicalRepository extends MinistryCanonicalGeographyRepository
{
    public ?array $storedRun = null;
    public array $storedItems = [];

    public function __construct(
        public array $rows,
        public array $canonical,
        public array $country = ['status' => 'create', 'location_id' => null]
    )
    {
    }

    public function completedBatch(string $batchReference): array
    {
        return [
            'id' => 10,
            'source_id' => 20,
            'snapshot_id' => 30,
            'status' => 'ready_for_review',
            'total_rows' => count($this->rows),
            'reference' => $batchReference,
        ];
    }

    public function metadata(): array
    {
        return [
            'hierarchy_type_id' => 1,
            'relation_type_id' => 2,
            'coding_system_id' => 3,
            'code_set_id' => 4,
            'national_code_set_id' => 5,
            'level_ids' => [
                'country' => 1,
                'province' => 2,
                'county' => 3,
                'district' => 4,
                'rural_district' => 5,
                'city' => 6,
            ],
        ];
    }

    public function sourceRows(int $batchId): array
    {
        return $this->rows;
    }

    public function canonicalState(array $batch, array $metadata): array
    {
        return $this->canonical;
    }

    public function resolveCountry(int $countryLevelId): array
    {
        return $this->country;
    }

    public function existingRun(array $batch): ?array
    {
        return $this->storedRun;
    }

    public function storePlan(
        array $batch,
        string $reference,
        string $planFingerprint,
        string $sourceFingerprint,
        array $items,
        array $summary
    ): array {
        $this->storedItems = $items;
        $counts = array_count_values(array_column($items, 'action_type'));
        $this->storedRun = [
            'id' => 1,
            'source_batch_id' => $batch['id'],
            'source_snapshot_id' => $batch['snapshot_id'],
            'plan_reference' => $reference,
            'plan_fingerprint' => $planFingerprint,
            'source_fingerprint' => $sourceFingerprint,
            'status' => 'planned',
            'total_source_rows' => count($items),
            'eligible_rows' => count($items) - ($counts['exclude'] ?? 0),
            'excluded_rows' => $counts['exclude'] ?? 0,
            'create_count' => $counts['create'] ?? 0,
            'reuse_count' => $counts['reuse'] ?? 0,
            'conflict_count' => $counts['conflict'] ?? 0,
            'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE),
        ];

        return $this->storedRun;
    }
}

final class FakeMinistryFailureLogger extends MinistryCanonicalizationFailureLogger
{
    public array $entries = [];

    public function log(
        string $failureReference,
        string $stage,
        ?string $level,
        ?int $chunkNumber,
        Throwable $exception
    ): void {
        $this->entries[] = compact('failureReference', 'stage', 'level', 'chunkNumber', 'exception');
    }
}

final class FakeApplyMinistryCanonicalRepository extends FakeMinistryCanonicalRepository
{
    public bool $failFirstLocation = true;
    public int $countryCreateCount = 0;
    public int $nextLocationId = 100;
    public array $locationLevels = [];
    public array $transactionLevels = [];
    public ?array $failure = null;
    private array $activeTransactionLevels = [];

    public function storePlan(
        array $batch,
        string $reference,
        string $planFingerprint,
        string $sourceFingerprint,
        array $items,
        array $summary
    ): array {
        $run = parent::storePlan(
            $batch,
            $reference,
            $planFingerprint,
            $sourceFingerprint,
            $items,
            $summary
        );

        foreach ($this->storedItems as $index => &$item) {
            $row = $this->rows[$index];
            $item = array_merge($item, $row, [
                'id' => $index + 1,
                'item_status' => 'planned',
                'existing_geographic_location_id' => $item['existing_location_id'],
                'resulting_geographic_location_id' => null,
                'resulting_parent_location_id' => null,
            ]);
        }
        unset($item);
        $this->storedRun['relation_create_count'] = 0;
        $this->storedRun['identifier_create_count'] = 0;
        $this->storedRun['mapping_create_count'] = 0;

        return $this->storedRun;
    }

    public function runByReference(string $reference): ?array
    {
        return $this->storedRun !== null && $this->storedRun['plan_reference'] === $reference
            ? $this->storedRun
            : null;
    }

    public function runById(int $runId): array
    {
        return $this->storedRun;
    }

    public function acquireApplyLock(int $runId): bool
    {
        return true;
    }

    public function releaseApplyLock(int $runId): void
    {
    }

    public function beginApply(int $runId): void
    {
        $this->storedRun['status'] = 'applying';
    }

    public function resolveOrCreateCountry(int $countryLevelId): int
    {
        if ($this->country['location_id'] === null) {
            $this->country['location_id'] = 1;
            $this->country['status'] = 'reuse';
            $this->countryCreateCount++;
        }

        return 1;
    }

    public function pendingItemsForLevel(int $runId, string $level): array
    {
        return array_values(array_filter(
            $this->storedItems,
            static fn (array $item): bool => $item['derived_level_code'] === $level
                && in_array($item['action_type'], ['create', 'reuse'], true)
                && $item['item_status'] !== 'applied'
        ));
    }

    public function transaction(callable $callback): mixed
    {
        $items = $this->storedItems;
        $run = $this->storedRun;
        $locations = $this->locationLevels;
        $nextLocationId = $this->nextLocationId;
        $this->activeTransactionLevels = [];

        try {
            $result = $callback();

            if ($this->activeTransactionLevels !== []) {
                $this->transactionLevels[] = array_values(array_unique($this->activeTransactionLevels));
            }

            return $result;
        } catch (Throwable $exception) {
            $this->storedItems = $items;
            $this->storedRun = $run;
            $this->locationLevels = $locations;
            $this->nextLocationId = $nextLocationId;
            throw $exception;
        } finally {
            $this->activeTransactionLevels = [];
        }
    }

    public function createLocation(array $item, int $levelTypeId, int $sourceId, int $snapshotId): int
    {
        if ($this->failFirstLocation) {
            $this->failFirstLocation = false;
            throw new PDOException('Synthetic private SQL detail from geographic_locations.');
        }

        $locationId = $this->nextLocationId++;
        $this->locationLevels[$locationId] = $item['derived_level_code'];
        $this->activeTransactionLevels[] = $item['derived_level_code'];

        return $locationId;
    }

    public function locationLevel(int $locationId): ?string
    {
        return $this->locationLevels[$locationId] ?? null;
    }

    public function officialParentConflict(
        int $parentId,
        int $childId,
        int $relationTypeId,
        int $hierarchyTypeId
    ): bool {
        return false;
    }

    public function parentLocation(int $runId, ?int $parentImportRowId): ?int
    {
        foreach ($this->storedItems as $item) {
            if ($item['import_row_id'] === $parentImportRowId && $item['item_status'] === 'applied') {
                return $item['resulting_geographic_location_id'];
            }
        }

        return null;
    }

    public function codeValueId(int $codeSetId, int $snapshotId, ?string $code): ?int
    {
        return $code === null || $code === '' ? null : 500;
    }

    public function codeValueParentConflict(
        int $codeSetId,
        int $snapshotId,
        string $code,
        ?int $expectedParentId
    ): bool {
        return false;
    }

    public function codeValue(
        int $codeSetId,
        int $snapshotId,
        array $item,
        ?int $parentCodeValueId
    ): int {
        return 1000 + (int) $item['id'];
    }

    public function ensureOfficialRelation(
        int $parentId,
        int $childId,
        int $relationTypeId,
        int $hierarchyTypeId,
        int $sourceId,
        int $snapshotId
    ): bool {
        return true;
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
        return true;
    }

    public function ensureConfirmedMapping(int $codeValueId, int $locationId): bool
    {
        return true;
    }

    public function markItemApplied(int $itemId, int $locationId, int $parentLocationId): void
    {
        foreach ($this->storedItems as &$item) {
            if ($item['id'] === $itemId) {
                $item['item_status'] = 'applied';
                $item['review_status'] = 'applied';
                $item['resulting_geographic_location_id'] = $locationId;
                $item['resulting_parent_location_id'] = $parentLocationId;
                return;
            }
        }
        unset($item);
    }

    public function reconcileApplyCounts(int $runId, array $batch, array $metadata): array
    {
        $applied = count(array_filter(
            $this->storedItems,
            static fn (array $item): bool => $item['item_status'] === 'applied'
        ));
        $counts = ['relations' => $applied, 'identifiers' => $applied, 'mappings' => $applied];
        $this->storedRun['relation_create_count'] = $counts['relations'];
        $this->storedRun['identifier_create_count'] = $counts['identifiers'];
        $this->storedRun['mapping_create_count'] = $counts['mappings'];

        return $counts;
    }

    public function levelApplyState(int $runId, string $level): array
    {
        $items = array_filter(
            $this->storedItems,
            static fn (array $item): bool => $item['derived_level_code'] === $level
        );

        return [
            'pending_safe_items' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array($item['action_type'], ['create', 'reuse'], true)
                    && $item['item_status'] !== 'applied'
            )),
            'unresolved_parent_items' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['item_status'] === 'applied'
                    && $item['resulting_parent_location_id'] === null
            )),
        ];
    }

    public function aggregateApplyState(int $runId): array
    {
        return [
            'created_locations' => count(array_filter(
                $this->storedItems,
                static fn (array $item): bool => $item['action_type'] === 'create'
                    && $item['item_status'] === 'applied'
            )),
            'reused_locations' => 0,
            'excluded_rows' => count(array_filter(
                $this->storedItems,
                static fn (array $item): bool => $item['action_type'] === 'exclude'
            )),
            'conflict_count' => 0,
            'pending_safe_items' => count(array_filter(
                $this->storedItems,
                static fn (array $item): bool => in_array($item['action_type'], ['create', 'reuse'], true)
                    && $item['item_status'] !== 'applied'
            )),
        ];
    }

    public function finishApply(int $runId, array $summary, string $status): void
    {
        $this->storedRun['status'] = $status;
        $this->storedRun['summary_json'] = json_encode($summary, JSON_UNESCAPED_UNICODE);
    }

    public function failApply(int $runId, array $failure): void
    {
        $this->failure = $failure;
        $this->storedRun['status'] = 'failed';
        $this->storedRun['failure_reference'] = $failure['reference'];
        $this->storedRun['failure_stage'] = $failure['stage'];
    }

    public function statusSummary(int $runId, array $batch, array $metadata): array
    {
        $aggregate = $this->aggregateApplyState($runId);
        $applied = $aggregate['created_locations'] + $aggregate['reused_locations'];

        return [
            'total_items' => count($this->storedItems),
            'applied_items' => $applied,
            'planned_items' => count(array_filter(
                $this->storedItems,
                static fn (array $item): bool => $item['item_status'] === 'planned'
            )),
            'blocked_items' => 0,
            'excluded_items' => $aggregate['excluded_rows'],
            'conflict_items' => 0,
            'created_locations' => $aggregate['created_locations'],
            'created_relations' => $applied,
            'created_identifiers' => $applied,
            'created_confirmed_mappings' => $applied,
        ];
    }
}

function syntheticRow(
    int $id,
    string $code,
    string $title,
    string $level,
    string $parent,
    string $status = 'valid',
    string $nationalIdentifier = ''
): array {
    $payload = ['national_identifier' => $nationalIdentifier];

    return [
        'id' => $id,
        'source_code' => $code,
        'source_title' => $title,
        'normalized_title' => $title,
        'derived_level_code' => $level,
        'derived_parent_code' => $parent,
        'row_checksum' => hash('sha256', json_encode([$id, $code, $title, $parent, $payload], JSON_UNESCAPED_UNICODE)),
        'validation_status' => $status,
        'raw_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ];
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$rows = [
    syntheticRow(1, '01', 'استان آزمون', 'province', 'IR'),
    syntheticRow(2, '0101', 'شهرستان آزمون', 'county', '01'),
    syntheticRow(3, '010101', 'بخش آزمون', 'district', '0101'),
    syntheticRow(4, '01010101', 'دهستان آزمون', 'rural_district', '010101'),
    syntheticRow(5, '010101001', 'شهر نخست', 'city', '010101', 'warning', '900001'),
    syntheticRow(6, '010101002', 'شهر دوم', 'city', '010101', 'warning', '900001'),
    syntheticRow(7, '', 'بدون کد', 'city', '010101', 'invalid'),
    syntheticRow(8, '020101001', 'بدون والد', 'city', '020101', 'warning'),
];
$canonical = [
    'codeMappings' => [
        '0101' => [['location_id' => 99, 'level' => 'county']],
    ],
    'identifierMappings' => [],
    'titles' => [
        ['id' => 77, 'title' => 'بخش آزمون', 'level' => 'district'],
    ],
    'officialParents' => [],
];
$repository = new FakeMinistryCanonicalRepository($rows, $canonical);
$service = new MinistryCanonicalGeographyService($repository);
$first = $service->plan('MOI-AAAAAAAAAAAA');
$actions = array_column($repository->storedItems, 'action_type', 'import_row_id');
$reasons = array_column($repository->storedItems, 'reason_code', 'import_row_id');

expect($first['canonical_write_performed'] === false, 'Plan must never perform canonical writes.');
expect($first['total_source_rows'] === 8, 'Every source row must receive one plan item.');
expect($first['eligible_rows'] === 6 && $first['excluded_rows'] === 2, 'Eligibility classification is incorrect.');
expect($actions[2] === 'reuse', 'Trusted Ministry mapping must be reused.');
expect($actions[3] === 'conflict' && $reasons[3] === 'TITLE_ONLY_MATCH_REVIEW', 'Title-only reuse must be blocked.');
expect($actions[5] === 'create' && $actions[6] === 'create', 'Repeated national identifiers must not merge locations.');
expect($reasons[7] === 'MISSING_HIERARCHY_CODE', 'Missing-code row must be excluded.');
expect($reasons[8] === 'MISSING_SOURCE_PARENT', 'Missing-parent row must be excluded.');

$second = $service->plan('MOI-AAAAAAAAAAAA');
expect($second['plan_reference'] === $first['plan_reference'], 'Repeated planning must reuse the immutable plan.');
expect($second['plan_fingerprint_prefix'] === $first['plan_fingerprint_prefix'], 'Repeated planning must be deterministic.');

$countryReuseRepository = new FakeMinistryCanonicalRepository(
    $rows,
    $canonical,
    ['status' => 'reuse', 'location_id' => 1]
);
$countryReuse = (new MinistryCanonicalGeographyService($countryReuseRepository))->plan('MOI-CCCCCCCCCCCC');
$countrySummary = json_decode((string) $countryReuseRepository->storedRun['summary_json'], true);
expect(($countrySummary['country_resolution']['status'] ?? null) === 'reuse', 'An unambiguous Iran root must be reusable.');
expect($countryReuse['canonical_write_performed'] === false, 'Country-root planning must remain read-only.');

$parentConflictCanonical = $canonical;
$parentConflictCanonical['officialParents'][99] = [555];
$parentConflictRepository = new FakeMinistryCanonicalRepository($rows, $parentConflictCanonical);
(new MinistryCanonicalGeographyService($parentConflictRepository))->plan('MOI-DDDDDDDDDDDD');
$parentConflictActions = array_column($parentConflictRepository->storedItems, 'action_type', 'import_row_id');
$parentConflictReasons = array_column($parentConflictRepository->storedItems, 'reason_code', 'import_row_id');
expect(
    $parentConflictActions[2] === 'conflict' && $parentConflictReasons[2] === 'OFFICIAL_PARENT_CONFLICT',
    'A conflicting official parent must never be overwritten by policy.'
);

$changedRows = $rows;
$changedRows[0]['row_checksum'] = str_repeat('a', 64);
$changedRepository = new FakeMinistryCanonicalRepository($changedRows, $canonical);
$changed = (new MinistryCanonicalGeographyService($changedRepository))->plan('MOI-BBBBBBBBBBBB');
expect($changed['plan_fingerprint_prefix'] !== $first['plan_fingerprint_prefix'], 'Source checksum changes must alter the plan fingerprint.');

expect(array_keys(\App\Services\GeographyCanonicalization\MinistryCanonicalizationPolicy::LEVELS) === [
    'province', 'county', 'district', 'rural_district', 'city',
], 'Canonical apply levels must remain parent-first.');

$applyRows = [
    syntheticRow(11, '11', 'Synthetic province', 'province', 'IR'),
    syntheticRow(12, '1101', 'Synthetic county', 'county', '11'),
    syntheticRow(13, '110101', 'Synthetic district', 'district', '1101'),
    syntheticRow(14, '11010101', 'Synthetic rural district', 'rural_district', '110101'),
    syntheticRow(15, '110101001', 'Synthetic city', 'city', '110101'),
];
$applyCanonical = [
    'codeMappings' => [],
    'identifierMappings' => [],
    'titles' => [],
    'officialParents' => [],
];
$applyRepository = new FakeApplyMinistryCanonicalRepository($applyRows, $applyCanonical);
$failureLogger = new FakeMinistryFailureLogger();
$applyService = new MinistryCanonicalGeographyService(
    $applyRepository,
    new \App\Services\GeographyImport\PersianTextNormalizer(),
    $failureLogger
);
$applyPlan = $applyService->plan('MOI-EEEEEEEEEEEE');
$applyRepository->storedRun['status'] = 'failed';
$failureResponse = null;

try {
    $applyService->apply(
        'MOI-EEEEEEEEEEEE',
        $applyPlan['plan_reference'],
        $applyPlan['plan_fingerprint_prefix']
    );
} catch (MinistryCanonicalizationApplyException $exception) {
    $failureResponse = $exception->safeResponse();
}

expect(is_array($failureResponse), 'A forced apply failure must return a safe recoverable exception.');
expect($failureResponse['failure_stage'] === 'create_location', 'Failure stage must identify the safe operation boundary.');
expect($failureResponse['applied_item_count'] === 0, 'First-chunk rollback must leave no applied item.');
expect($failureResponse['canonical_write_performed'] === false, 'Rolled-back writes must not be reported as canonical writes.');
expect(!str_contains(json_encode($failureResponse), 'Synthetic private SQL detail'), 'Public failure data must not expose exception details.');
expect($applyRepository->failure['reference'] === $failureResponse['failure_reference'], 'Private failure reference must be persisted.');
expect($applyRepository->storedItems[0]['item_status'] === 'planned', 'Failed province must remain planned for retry.');
expect(count($failureLogger->entries) === 1, 'Original failure must be sent to private logging.');

$failureStatus = $applyService->status('MOI-EEEEEEEEEEEE', $applyPlan['plan_reference']);
expect($failureStatus['run_status'] === 'failed' && $failureStatus['resume_safe'] === true, 'Failed status must remain resumable.');
expect($failureStatus['last_failure_reference'] === $failureResponse['failure_reference'], 'Status must expose only the opaque failure reference.');

$retry = $applyService->apply(
    'MOI-EEEEEEEEEEEE',
    $applyPlan['plan_reference'],
    $applyPlan['plan_fingerprint_prefix']
);
expect($retry['success'] === true, 'The same failed plan must succeed on retry.');
expect($applyRepository->countryCreateCount === 1, 'A country root committed before failure must be reused.');
expect(count($applyRepository->transactionLevels) === 5, 'Apply must commit one synthetic chunk per hierarchy level.');

foreach ($applyRepository->transactionLevels as $levels) {
    expect(count($levels) === 1, 'A canonical apply chunk must never mix hierarchy levels.');
}

$countsAfterRetry = [
    $applyRepository->storedRun['relation_create_count'],
    $applyRepository->storedRun['identifier_create_count'],
    $applyRepository->storedRun['mapping_create_count'],
];
$repeat = $applyService->apply(
    'MOI-EEEEEEEEEEEE',
    $applyPlan['plan_reference'],
    $applyPlan['plan_fingerprint_prefix']
);
expect($repeat['success'] === true, 'Repeated successful apply must be idempotent.');
expect($countsAfterRetry === [5, 5, 5], 'Reconciled counters must reflect committed artifacts.');
expect($countsAfterRetry === [
    $applyRepository->storedRun['relation_create_count'],
    $applyRepository->storedRun['identifier_create_count'],
    $applyRepository->storedRun['mapping_create_count'],
], 'Repeated apply must not double counters.');
expect($repeat['sci_write_performed'] === false && $repeat['bot_write_performed'] === false, 'SCI and bot writes must remain blocked.');

$repositorySql = file_get_contents(BASE_PATH . '/app/Repositories/MinistryCanonicalGeographyRepository.php');
expect($repositorySql !== false, 'Repository SQL source must be readable.');
expect(
    preg_match('/\bgeographic_import_rows\s+rows\b/i', $repositorySql) === 0,
    'Canonicalization SQL must not use MariaDB reserved ROWS as a table alias.'
);
expect(
    preg_match('/\brows\.(source_code|source_title|normalized_title|derived_level_code|derived_parent_code|row_checksum|validation_status|raw_payload_json|id)\b/i', $repositorySql) === 0,
    'Canonicalization SQL must not reference a reserved ROWS alias.'
);

echo "Ministry canonical geography synthetic tests passed.\n";

<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Repositories\MinistryCanonicalGeographyRepository;
use App\Services\GeographyCanonicalization\MinistryCanonicalGeographyService;

final class FakeMinistryCanonicalRepository extends MinistryCanonicalGeographyRepository
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

echo "Ministry canonical geography synthetic tests passed.\n";

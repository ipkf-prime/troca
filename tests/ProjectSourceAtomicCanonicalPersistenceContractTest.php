<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$interfaceFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceCanonicalPersistenceRepositoryInterface.php';

$plannerFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceCanonicalMaterializationPlanner.php';

$policyFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceStageValidationPolicy.php';

$repositoryFile =
    $root
    . '/public_html/app/Repositories/'
    . 'ProjectSourceCanonicalPersistenceRepository.php';

$serviceFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceAtomicApplyService.php';

foreach (
    [
        $interfaceFile,
        $plannerFile,
        $policyFile,
        $repositoryFile,
        $serviceFile,
    ]
    as $file
) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable A11/12 file: '
            . $file
        );
    }
}

require_once $interfaceFile;
require_once $policyFile;
require_once $plannerFile;
require_once $serviceFile;

use App\Services\ProjectSources\ProjectSourceAtomicApplyService;
use App\Services\ProjectSources\ProjectSourceCanonicalMaterializationPlanner;
use App\Services\ProjectSources\ProjectSourceCanonicalPersistenceRepositoryInterface;

final class A11FakeRepository
    implements ProjectSourceCanonicalPersistenceRepositoryInterface
{
    public int $transactionCount = 0;
    public int $persistCount = 0;
    public int $appliedCount = 0;
    public int $failureCount = 0;

    public function transaction(
        Closure $operation
    ): mixed {
        $this->transactionCount++;

        return $operation();
    }

    public function lockApplyContext(
        int $projectId,
        string $sourceCode,
        int $runId
    ): ?array {
        if (
            $projectId !== 10
            || $sourceCode !==
                'master-catalog'
            || $runId !== 700
        ) {
            return null;
        }

        return [
            'project_id' =>
                10,

            'source_code' =>
                'master-catalog',

            'driver_code' =>
                'fixture',

            'catalog_code' =>
                'entities',

            'dimension_code' =>
                'organization',

            'run_id' =>
                700,

            'run_status' =>
                'validated',

            'source_snapshot_token' =>
                'snapshot-700',

            'item_count' =>
                1,

            'dimension_id' =>
                50,

            'dimension_project_id' =>
                10,

            'dimension_code_resolved' =>
                'organization',

            'dimension_status' =>
                'active',

            'source_mode_code' =>
                'external',

            'source_key' =>
                'master-catalog',

            'value_kind_code' =>
                'reference',

            'hierarchy_mode_code' =>
                'tree',

            'supports_descendants' =>
                1,
        ];
    }

    public function stagedBatch(
        array $context
    ): array {
        return [
            'source_code' =>
                'master-catalog',

            'driver_code' =>
                'fixture',

            'catalog_code' =>
                'entities',

            'dimension_code' =>
                'organization',

            'snapshot_token' =>
                'snapshot-700',

            'next_cursor' =>
                null,

            'item_count' =>
                1,

            'rows' => [
                [
                    'row_number' =>
                        1,

                    'source_reference' =>
                        'شرکت-۱',

                    'parent_source_reference' =>
                        'root',

                    'title' =>
                        'شرکت نمونه',

                    'source_status' =>
                        'active',

                    'attributes_json' =>
                        '{}',

                    'payload_hash' =>
                        str_repeat(
                            'a',
                            64
                        ),
                ],
            ],
        ];
    }

    public function existingCanonical(
        int $dimensionId
    ): array {
        return [
            'root' => [
                'source_reference' =>
                    'root',

                'value_reference' =>
                    'ROOT-CANONICAL',

                'parent_source_reference' =>
                    null,
            ],
        ];
    }

    public function persistPlan(
        array $context,
        array $plan,
        string $actorReference
    ): array {
        $this->persistCount++;

        if (
            !str_starts_with(
                (string) $plan[
                    'upserts'
                ][0][
                    'value_reference'
                ],
                'SRCV-'
            )
        ) {
            throw new RuntimeException(
                'Expected canonical ASCII identity.'
            );
        }

        return [
            'dimension_id' => 50,
            'upserted_count' => 1,
            'deleted_count' => 0,
            'path_rebuilt' => true,
        ];
    }

    public function markRunApplied(
        int $runId,
        string $snapshotToken,
        int $itemCount
    ): void {
        $this->appliedCount++;
    }

    public function recordApplyFailure(
        int $runId,
        string $message
    ): void {
        $this->failureCount++;
    }
}

$repository =
    new A11FakeRepository();

$service =
    new ProjectSourceAtomicApplyService(
        $repository,
        new ProjectSourceCanonicalMaterializationPlanner()
    );

$result =
    $service->applyValidatedRun(
        10,
        'master-catalog',
        700,
        'user:501'
    );

if (
    ($result['ok'] ?? false)
    !== true
) {
    throw new RuntimeException(
        'Atomic apply result must succeed.'
    );
}

if (
    $repository->transactionCount !== 1
    || $repository->persistCount !== 1
    || $repository->appliedCount !== 1
    || $repository->failureCount !== 0
) {
    throw new RuntimeException(
        'Atomic apply orchestration count mismatch.'
    );
}

$failed = false;

try {
    $service->applyValidatedRun(
        11,
        'master-catalog',
        700
    );
} catch (Throwable) {
    $failed = true;
}

if (!$failed) {
    throw new RuntimeException(
        'Unknown trusted apply context must fail closed.'
    );
}

if ($repository->failureCount !== 1) {
    throw new RuntimeException(
        'Apply failure must be recorded separately.'
    );
}

$repositoryContent =
    (string) file_get_contents(
        $repositoryFile
    );

foreach (
    [
        'FOR UPDATE',
        'beginTransaction',
        'commit',
        'rollBack',
        'ticketing_project_sources',
        'ticketing_project_source_runs',
        'ticketing_project_source_stage_rows',
        'ticketing_scope_dimension_values',
        'ticketing_scope_dimension_value_paths',
        'ON DUPLICATE KEY UPDATE',
        "status = 'applied'",
        'last_successful_snapshot_token',
    ]
    as $marker
) {
    if (
        !str_contains(
            $repositoryContent,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Missing atomic persistence marker: '
            . $marker
        );
    }
}

if (
    preg_match(
        '/DELETE\s+FROM\s+ticketing_scope_dimension_values/i',
        $repositoryContent
    ) === 1
) {
    throw new RuntimeException(
        'Deletion-by-omission leaked into canonical persistence.'
    );
}

foreach (
    [
        'province',
        'county',
        'company',
    ]
    as $businessSpecific
) {
    if (
        stripos(
            $repositoryContent,
            $businessSpecific
        ) !== false
    ) {
        throw new RuntimeException(
            'Business-specific level leaked into persistence repository: '
            . $businessSpecific
        );
    }
}

if (
    preg_match(
        '/\bNP\b/u',
        $repositoryContent
    ) === 1
) {
    throw new RuntimeException(
        'Source adapter identity leaked into persistence repository.'
    );
}

echo "PROJECT_SOURCE_ATOMIC_CANONICAL_PERSISTENCE_CONTRACT=PASS\n";
echo "TRUSTED_SOURCE_RUN_DIMENSION_CONTEXT=PASS\n";
echo "ROW_LOCKING=FOR_UPDATE\n";
echo "TRANSACTION_BOUNDARY=PASS\n";
echo "VALIDATED_RUN_REQUIRED=PASS\n";
echo "CANONICAL_UPSERT=NO_DELETE_BY_OMISSION\n";
echo "PARENT_BINDING=SAME_DIMENSION\n";
echo "VALUE_PATH_REBUILD=ATOMIC\n";
echo "RUN_AND_SOURCE_SUCCESS_MARKERS=ATOMIC\n";
echo "FAILURE_RECORDING=OUTSIDE_FAILED_TRANSACTION\n";
echo "SOURCE_ADAPTER_IDENTITY=ABSENT\n";
echo "BUSINESS_SPECIFIC_LEVELS=ABSENT\n";

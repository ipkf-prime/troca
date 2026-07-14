<?php

namespace App\Services\GeographyCrosswalk;

use App\Repositories\GeographyCrosswalkRepository;
use App\Services\GeographyImport\MinistryGeographyImporter;
use App\Services\GeographyImport\StatisticalCenterGeographyImporter;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MinistrySciGeographyCrosswalkService
{
    public const MODE = 'build-candidates';

    public function __construct(
        private readonly GeographyCrosswalkRepository $repository = new GeographyCrosswalkRepository()
    ) {
    }

    public function build(string $sourceBatchReference, string $targetBatchReference, string $mode): array
    {
        $this->validateRequest($sourceBatchReference, $targetBatchReference, $mode);
        $sourceBatch = $this->repository->completedBatch(
            StatisticalCenterGeographyImporter::SOURCE_CODE,
            $sourceBatchReference
        );
        $targetBatch = $this->repository->completedBatch(
            MinistryGeographyImporter::SOURCE_CODE,
            $targetBatchReference
        );
        $run = $this->repository->prepareRun(
            $sourceBatch,
            $targetBatch,
            GeographyCrosswalkPolicy::CROSSWALK_TYPE,
            GeographyCrosswalkPolicy::ALGORITHM_VERSION
        );

        if ($run['reusable_summary'] !== null) {
            return $run['reusable_summary'];
        }

        $runId = (int) $run['id'];

        try {
            $this->repository->classifyExcludedRows($runId, $sourceBatch['id']);

            foreach (GeographyCrosswalkPolicy::LEVELS as $level) {
                $this->repository->buildLevel(
                    $runId,
                    $sourceBatch['id'],
                    $targetBatch['id'],
                    $level['source_kind'],
                    $level['target_level'],
                    $level['parent_source_kind'],
                    $level['candidate_kind'],
                    $level['city_candidate']
                );
            }

            $classifiedRows = $this->repository->classifiedSourceCount($runId);

            if ($classifiedRows !== $sourceBatch['total_rows']) {
                throw new RuntimeException('Crosswalk source classification is incomplete.');
            }

            $this->repository->createIssues($runId);
            $counts = $this->repository->statusCounts($runId);
            $summary = [
                'success' => true,
                'crosswalk_reference' => $run['reference'],
                'algorithm_version' => GeographyCrosswalkPolicy::ALGORITHM_VERSION,
                'source_batch_reference' => $sourceBatchReference,
                'target_batch_reference' => $targetBatchReference,
                'total_source_observations' => $sourceBatch['total_rows'],
                'exact_candidate_count' => $counts['exact'],
                'probable_candidate_count' => $counts['probable'],
                'ambiguous_count' => $counts['ambiguous'],
                'unmatched_count' => $counts['unmatched'],
                'excluded_count' => $counts['excluded'],
                'counts_by_source_kind' => $this->repository->sourceKindCounts($runId),
                'counts_by_reason_code' => $this->repository->reasonCounts($runId),
                'final_status' => 'ready_for_review',
                'canonical_write_performed' => false,
                'confirmed_mapping_write_performed' => false,
            ];
            $this->repository->completeRun($runId, $sourceBatch['total_rows'], $counts, $summary);

            return $summary;
        } catch (Throwable $exception) {
            $this->repository->failRun($runId);
            throw new RuntimeException('Geography crosswalk candidate generation failed.', 0, $exception);
        }
    }

    private function validateRequest(
        string $sourceBatchReference,
        string $targetBatchReference,
        string $mode
    ): void {
        if ($mode !== self::MODE
            || preg_match('/\ASCI-[A-F0-9]{12}\z/D', $sourceBatchReference) !== 1
            || preg_match('/\AMOI-[A-F0-9]{12}\z/D', $targetBatchReference) !== 1
        ) {
            throw new InvalidArgumentException('Unsupported crosswalk request.');
        }
    }
}

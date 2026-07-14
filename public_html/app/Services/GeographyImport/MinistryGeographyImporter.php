<?php

namespace App\Services\GeographyImport;

use App\Repositories\GeographyImportRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MinistryGeographyImporter implements GeographyImportAdapterInterface
{
    public const SOURCE_CODE = 'iran_ministry_of_interior';
    public const MODE = 'validate';

    private const COMPLETED_STATUSES = ['validated', 'ready_for_review'];

    public function __construct(
        private readonly GeographyImportRepository $repository = new GeographyImportRepository(),
        private readonly ?MinistryGeographyCsvParser $csvParser = null,
        private readonly ?MinistryGeographyValidator $validator = null,
        private readonly ?GeographyImportRunService $runService = null
    ) {
    }

    public static function xlsxAvailable(): bool
    {
        return false;
    }

    public function sourceCode(): string
    {
        return self::SOURCE_CODE;
    }

    public function validateFile(string $filename): array
    {
        $run = $this->runs()->prepare(self::SOURCE_CODE, $filename, self::xlsxAvailable());
        $sourceId = $run['source_id'];
        $settings = $run['settings'];
        $path = $run['path'];
        $sha256 = $run['sha256'];
        $snapshotId = $run['snapshot_id'];
        $reusable = $run['reusable_summary'];

        if ($reusable !== null
            && in_array($reusable['final_batch_status'] ?? null, self::COMPLETED_STATUSES, true)
        ) {
            return $reusable;
        }

        $batchId = (int) $run['batch_id'];

        try {
            $parsed = $this->parser()->parse($path);
        } catch (InvalidArgumentException $exception) {
            $this->repository->failBatch($batchId, $snapshotId, 'validation_failed');
            throw $exception;
        } catch (Throwable $exception) {
            $this->repository->failBatch($batchId, $snapshotId, 'failed');
            throw new RuntimeException('Source parsing failed.', 0, $exception);
        }

        if ($parsed['rows'] === []) {
            $this->repository->failBatch($batchId, $snapshotId, 'validation_failed');
            throw new InvalidArgumentException('Source file contains no data rows.');
        }

        $mappings = $this->repository->levelMappings($sourceId);

        if ($mappings === []) {
            $this->repository->failBatch($batchId, $snapshotId, 'validation_failed');
            throw new RuntimeException('Source level mappings are unavailable.');
        }

        $placeholderValues = $settings['geography.placeholder_values'] ?? [];
        $placeholderValues = is_array($placeholderValues) ? $placeholderValues : [];
        $countryRoot = trim((string) ($settings['geography.country_root_code'] ?? ''));

        if ($countryRoot === '') {
            $this->repository->failBatch($batchId, $snapshotId, 'validation_failed');
            throw new RuntimeException('Source country root configuration is unavailable.');
        }
        $validated = $this->validator()->validate(
            $parsed['rows'],
            $mappings,
            $placeholderValues,
            $countryRoot
        );
        $finalStatus = ($validated['counts']['warning'] > 0 || $validated['counts']['invalid'] > 0)
            ? 'ready_for_review'
            : 'validated';
        $summary = [
            'success' => true,
            'source' => self::SOURCE_CODE,
            'batch_reference' => 'MOI-' . strtoupper(substr($sha256, 0, 12)),
            'snapshot_status' => $finalStatus,
            'file_hash_prefix' => substr($sha256, 0, 12),
            'total_parsed_rows' => count($parsed['rows']),
            'blank_skipped_rows' => (int) $parsed['blank_rows'],
            'counts_by_source_type' => $validated['source_type_counts'],
            'valid_rows' => $validated['counts']['valid'],
            'warning_rows' => $validated['counts']['warning'],
            'invalid_rows' => $validated['counts']['invalid'],
            'issue_counts' => $validated['issue_counts'],
            'final_batch_status' => $finalStatus,
            'canonical_write_performed' => false,
        ];

        try {
            $this->repository->transaction(function () use (
                $batchId,
                $snapshotId,
                $parsed,
                $validated,
                $finalStatus,
                $summary
            ): void {
                foreach ($validated['rows'] as $row) {
                    $rowId = $this->repository->stageRow($batchId, $row);

                    foreach ($row['issues'] as $issue) {
                        $this->repository->stageIssue($batchId, $rowId, $issue);
                    }
                }

                $this->repository->completeBatch($batchId, $finalStatus, $validated['counts'], $summary);
                $this->repository->updateSnapshot(
                    $snapshotId,
                    $finalStatus,
                    count($parsed['rows']),
                    $parsed['schema_signature']
                );
            });
        } catch (Throwable $exception) {
            $this->repository->failBatch($batchId, $snapshotId, 'failed');
            throw new RuntimeException('Staging validation results failed.', 0, $exception);
        }

        return $summary;
    }

    private function parser(): MinistryGeographyCsvParser
    {
        return $this->csvParser
            ?? new MinistryGeographyCsvParser(new PersianTextNormalizer());
    }

    private function validator(): MinistryGeographyValidator
    {
        return $this->validator
            ?? new MinistryGeographyValidator(new PersianTextNormalizer());
    }

    private function runs(): GeographyImportRunService
    {
        return $this->runService ?? new GeographyImportRunService($this->repository);
    }
}

<?php

namespace App\Services\GeographyImport;

use App\Repositories\GeographyImportRepository;
use Generator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class StatisticalCenterGeographyImporter implements GeographyImportAdapterInterface
{
    public const SOURCE_CODE = 'iran_statistical_center';
    public const MODE = 'validate';

    private const COMPLETED_STATUSES = ['validated', 'ready_for_review'];

    public function __construct(
        private readonly GeographyImportRepository $repository = new GeographyImportRepository(),
        private readonly ?StatisticalCenterGeographyCsvParser $csvParser = null,
        private readonly ?StatisticalCenterGeographyValidator $validator = null,
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
        $sourceId = (int) $run['source_id'];
        $settings = $run['settings'];
        $sha256 = $run['sha256'];
        $snapshotId = (int) $run['snapshot_id'];
        $reusable = $run['reusable_summary'];

        if ($reusable !== null
            && in_array($reusable['final_batch_status'] ?? null, self::COMPLETED_STATUSES, true)
        ) {
            return $reusable;
        }

        $batchId = (int) $run['batch_id'];

        try {
            $configuration = $this->configuration($sourceId, $settings);
            $stream = $this->parser()->stream(
                $run['path'],
                $configuration['header_aliases'],
                $configuration['delimiter']
            );
            $totalRows = $this->stageStream(
                $stream,
                $batchId,
                $configuration['mappings'],
                $configuration['country_root'],
                $configuration['chunk_size']
            );
            $parserSummary = $stream->getReturn();

            if ($totalRows < 1) {
                throw new InvalidArgumentException('Source file contains no data rows.');
            }

            $this->repository->transaction(function () use ($batchId): void {
                $this->repository->applyCompositeValidation($batchId);
            });

            $counts = $this->repository->batchValidationCounts($batchId);
            $issueCounts = $this->repository->issueCounts($batchId);
            $finalStatus = ($counts['warning'] > 0 || $counts['invalid'] > 0)
                ? 'ready_for_review'
                : 'validated';
            $summary = [
                'success' => true,
                'source' => self::SOURCE_CODE,
                'batch_reference' => 'SCI-' . strtoupper(substr($sha256, 0, 12)),
                'snapshot_status' => $finalStatus,
                'file_hash_prefix' => substr($sha256, 0, 12),
                'total_parsed_rows' => $totalRows,
                'blank_skipped_rows' => (int) ($parserSummary['blank_rows'] ?? 0),
                'counts_by_source_type' => $this->repository->sourceKindCounts($batchId),
                'valid_rows' => $counts['valid'],
                'warning_rows' => $counts['warning'],
                'invalid_rows' => $counts['invalid'],
                'issue_counts' => $issueCounts,
                'diag_present_rows' => $this->repository->classifierPresenceCount($batchId),
                'final_batch_status' => $finalStatus,
                'canonical_write_performed' => false,
            ];

            $this->repository->transaction(function () use (
                $batchId,
                $snapshotId,
                $totalRows,
                $parserSummary,
                $counts,
                $finalStatus,
                $summary
            ): void {
                $this->repository->completeBatch($batchId, $finalStatus, $counts, $summary);
                $this->repository->updateSnapshot(
                    $snapshotId,
                    $finalStatus,
                    $totalRows,
                    $parserSummary['schema_signature'] ?? null
                );
            });

            return $summary;
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'MISSING_REQUIRED_HEADER') {
                $this->repository->stageIssue($batchId, null, [
                    'code' => 'MISSING_REQUIRED_HEADER',
                    'severity' => 'error',
                    'field' => null,
                    'message' => 'One or more required source headers are missing.',
                    'metadata' => [],
                ]);
            }

            $this->repository->failBatch($batchId, $snapshotId, 'validation_failed');
            throw $exception;
        } catch (Throwable $exception) {
            $this->repository->failBatch($batchId, $snapshotId, 'failed');
            throw new RuntimeException('Statistical geography source validation failed.', 0, $exception);
        }
    }

    private function configuration(int $sourceId, array $settings): array
    {
        if (($settings['geography.validate_only'] ?? false) !== true) {
            throw new RuntimeException('Statistical geography import must remain validate-only.');
        }

        $encoding = strtoupper(trim((string) ($settings['geography.encoding'] ?? '')));
        $headerAliases = $settings['geography.expected_headers'] ?? [];
        $delimiter = (string) ($settings['geography.delimiter'] ?? ',');
        $countryRoot = trim((string) ($settings['geography.country_root_code'] ?? ''));
        $chunkSize = (int) ($settings['geography.staging_chunk_size'] ?? 500);
        $mappings = [];

        foreach ($this->repository->recordTypeMappings($sourceId) as $mapping) {
            $mappings[(string) $mapping['source_record_type']] = $mapping;
        }

        if ($encoding !== 'UTF-8'
            || !is_array($headerAliases)
            || count($headerAliases) < 12
            || strlen($delimiter) !== 1
            || $countryRoot === ''
            || $mappings === []
        ) {
            throw new RuntimeException('Statistical geography source configuration is incomplete.');
        }

        return [
            'header_aliases' => $headerAliases,
            'delimiter' => $delimiter,
            'country_root' => $countryRoot,
            'chunk_size' => max(100, min(2000, $chunkSize)),
            'mappings' => $mappings,
        ];
    }

    private function stageStream(
        Generator $stream,
        int $batchId,
        array $mappings,
        string $countryRoot,
        int $chunkSize
    ): int {
        $chunk = [];
        $totalRows = 0;
        $validator = $this->validator();

        foreach ($stream as $row) {
            $chunk[] = $validator->validate($row, $mappings, $countryRoot);
            $totalRows++;

            if (count($chunk) >= $chunkSize) {
                $this->stageChunk($batchId, $chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->stageChunk($batchId, $chunk);
        }

        return $totalRows;
    }

    private function stageChunk(int $batchId, array $rows): void
    {
        $this->repository->transaction(function () use ($batchId, $rows): void {
            foreach ($rows as $row) {
                $rowId = $this->repository->stageRow($batchId, $row);

                foreach ($row['issues'] as $issue) {
                    $this->repository->stageIssue($batchId, $rowId, $issue);
                }
            }
        });
    }

    private function parser(): StatisticalCenterGeographyCsvParser
    {
        return $this->csvParser
            ?? new StatisticalCenterGeographyCsvParser(new PersianTextNormalizer());
    }

    private function validator(): StatisticalCenterGeographyValidator
    {
        return $this->validator
            ?? new StatisticalCenterGeographyValidator(new PersianTextNormalizer());
    }

    private function runs(): GeographyImportRunService
    {
        return $this->runService ?? new GeographyImportRunService($this->repository);
    }
}

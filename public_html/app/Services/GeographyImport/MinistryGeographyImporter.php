<?php

namespace App\Services\GeographyImport;

use App\Repositories\GeographyImportRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MinistryGeographyImporter
{
    public const SOURCE_CODE = 'iran_ministry_of_interior';
    public const MODE = 'validate';

    private const DEFAULT_MAX_FILE_SIZE = 26214400;
    private const COMPLETED_STATUSES = ['validated', 'ready_for_review'];

    public function __construct(
        private readonly GeographyImportRepository $repository = new GeographyImportRepository(),
        private readonly ?MinistryGeographyCsvParser $csvParser = null,
        private readonly ?MinistryGeographyValidator $validator = null
    ) {
    }

    public static function xlsxAvailable(): bool
    {
        return false;
    }

    public function validateFile(string $filename): array
    {
        $sourceId = $this->repository->sourceId(self::SOURCE_CODE);
        $settings = $this->repository->settings($sourceId);
        $path = $this->validatedPath($filename, $settings);
        $fileSize = filesize($path);

        if ($fileSize === false) {
            throw new RuntimeException('Source file size could not be read.');
        }

        $sha256 = hash_file('sha256', $path);

        if (!is_string($sha256) || strlen($sha256) !== 64) {
            throw new RuntimeException('Source file hash could not be calculated.');
        }

        $snapshotId = $this->repository->createOrReuseSnapshot(
            $sourceId,
            $filename,
            $sha256,
            $fileSize
        );
        $reusable = $this->repository->reusableSummary($snapshotId);

        if ($reusable !== null
            && in_array($reusable['final_batch_status'] ?? null, self::COMPLETED_STATUSES, true)
        ) {
            return $reusable;
        }

        $batchId = $this->repository->prepareBatch($sourceId, $snapshotId);

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

    private function validatedPath(string $filename, array $settings): string
    {
        $filename = trim($filename);

        if ($filename === ''
            || basename($filename) !== $filename
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || preg_match('/\A[\pL\pN][\pL\pN._ -]*\.(csv|xlsx)\z/ui', $filename) !== 1
        ) {
            throw new InvalidArgumentException('Invalid source filename.');
        }

        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = $settings['geography.allowed_extensions'] ?? ['csv'];
        $allowedExtensions = is_array($allowedExtensions)
            ? array_map(static fn ($value): string => strtolower(trim((string) $value)), $allowedExtensions)
            : ['csv'];

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException('Unsupported source file type.');
        }

        if ($extension === 'xlsx') {
            throw new InvalidArgumentException('XLSX parsing is not available in this deployment.');
        }

        if ($extension !== 'csv') {
            throw new InvalidArgumentException('Unsupported source file type.');
        }

        $directory = BASE_PATH . '/storage/imports/geography/ministry';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Source file is unavailable.');
        }

        $directoryReal = realpath($directory);
        $pathReal = realpath($path);

        if ($directoryReal === false
            || $pathReal === false
            || dirname($pathReal) !== $directoryReal
        ) {
            throw new InvalidArgumentException('Source file location is invalid.');
        }

        $maxFileSize = (int) ($settings['geography.max_file_size_bytes'] ?? self::DEFAULT_MAX_FILE_SIZE);
        $maxFileSize = $maxFileSize > 0 ? $maxFileSize : self::DEFAULT_MAX_FILE_SIZE;
        $fileSize = filesize($pathReal);

        if ($fileSize === false || $fileSize < 1 || $fileSize > $maxFileSize) {
            throw new InvalidArgumentException('Source file size is invalid.');
        }

        if (class_exists(\finfo::class)) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($pathReal);
            $allowedMimes = [
                'text/plain', 'text/csv', 'application/csv',
                'application/vnd.ms-excel', 'application/octet-stream',
            ];

            if (is_string($mime) && !in_array($mime, $allowedMimes, true)) {
                throw new InvalidArgumentException('Source file MIME type is invalid.');
            }
        }

        return $pathReal;
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
}

<?php

namespace App\Services\GeographyImport;

use App\Repositories\GeographyImportRepository;
use RuntimeException;

class GeographyImportRunService
{
    public function __construct(
        private readonly GeographyImportRepository $repository,
        private readonly GeographyImportFileGuard $fileGuard = new GeographyImportFileGuard()
    ) {
    }

    public function prepare(string $sourceCode, string $filename, bool $xlsxAvailable = false): array
    {
        $sourceId = $this->repository->sourceId($sourceCode);
        $settings = $this->repository->settings($sourceId);
        $path = $this->fileGuard->validatedPath($filename, $settings, $xlsxAvailable);
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
        $reusableSummary = $this->repository->reusableSummary($snapshotId);

        return [
            'source_id' => $sourceId,
            'settings' => $settings,
            'path' => $path,
            'file_size' => $fileSize,
            'sha256' => $sha256,
            'snapshot_id' => $snapshotId,
            'reusable_summary' => $reusableSummary,
            'batch_id' => $reusableSummary === null
                ? $this->repository->prepareBatch($sourceId, $snapshotId)
                : null,
        ];
    }
}

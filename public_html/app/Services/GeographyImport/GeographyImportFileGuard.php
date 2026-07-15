<?php

namespace App\Services\GeographyImport;

use InvalidArgumentException;

class GeographyImportFileGuard
{
    private const DEFAULT_MAX_FILE_SIZE = 26214400;

    public function validatedPath(string $filename, array $settings, bool $xlsxAvailable = false): string
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

        if ($extension === 'xlsx' && !$xlsxAvailable) {
            throw new InvalidArgumentException('XLSX parsing is not available in this deployment.');
        }

        if ($extension !== 'csv' && $extension !== 'xlsx') {
            throw new InvalidArgumentException('Unsupported source file type.');
        }

        $sourceFolder = trim((string) ($settings['geography.source_folder'] ?? ''));

        if (preg_match('/\A[a-z0-9][a-z0-9-]*\z/D', $sourceFolder) !== 1) {
            throw new InvalidArgumentException('Source folder configuration is invalid.');
        }

        $root = BASE_PATH . '/storage/imports/geography';
        $directory = $root . DIRECTORY_SEPARATOR . $sourceFolder;
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Source file is unavailable.');
        }

        $rootReal = realpath($root);
        $directoryReal = realpath($directory);
        $pathReal = realpath($path);

        if ($rootReal === false
            || $directoryReal === false
            || $pathReal === false
            || dirname($directoryReal) !== $rootReal
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
            $allowedMimes = $extension === 'csv'
                ? ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream']
                : ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'];

            if (is_string($mime) && !in_array($mime, $allowedMimes, true)) {
                throw new InvalidArgumentException('Source file MIME type is invalid.');
            }
        }

        return $pathReal;
    }
}

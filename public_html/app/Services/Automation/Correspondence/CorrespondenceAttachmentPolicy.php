<?php

namespace App\Services\Automation\Correspondence;

use IPKF\Support\Env;

/**
 * attachment-dynamic-policy-v1
 *
 * Central, environment-driven and fail-safe attachment policy.
 * Environment values can only select from the supported safe catalog.
 */
final class CorrespondenceAttachmentPolicy
{
    private const DEFAULT_MAX_FILES = 3;
    private const DEFAULT_MAX_FILE_MB = 10;
    private const DEFAULT_MAX_TOTAL_MB = 20;

    private const MIME_BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function maxFiles(): int
    {
        return $this->boundedInteger(
            'AUTOMATION_ATTACHMENT_MAX_FILES',
            self::DEFAULT_MAX_FILES,
            1,
            20
        );
    }

    public function maxFileMegabytes(): int
    {
        return $this->boundedInteger(
            'AUTOMATION_ATTACHMENT_MAX_FILE_MB',
            self::DEFAULT_MAX_FILE_MB,
            1,
            100
        );
    }

    public function maxTotalMegabytes(): int
    {
        $configured = $this->boundedInteger(
            'AUTOMATION_ATTACHMENT_MAX_TOTAL_MB',
            self::DEFAULT_MAX_TOTAL_MB,
            1,
            500
        );

        return max(
            $configured,
            $this->maxFileMegabytes()
        );
    }

    public function maxFileBytes(): int
    {
        return
            $this->maxFileMegabytes()
            * 1024
            * 1024;
    }

    public function maxTotalBytes(): int
    {
        return
            $this->maxTotalMegabytes()
            * 1024
            * 1024;
    }

    public function allowedExtensions(): array
    {
        $configured = $this->csv(
            'AUTOMATION_ATTACHMENT_ALLOWED_EXTENSIONS',
            array_keys(self::MIME_BY_EXTENSION)
        );

        $allowed = [];

        foreach ($configured as $extension) {
            $extension = strtolower(
                ltrim(trim($extension), '.')
            );

            if (
                isset(self::MIME_BY_EXTENSION[$extension])
                && !in_array($extension, $allowed, true)
            ) {
                $allowed[] = $extension;
            }
        }

        return $allowed !== []
            ? $allowed
            : array_keys(self::MIME_BY_EXTENSION);
    }

    public function allowedMimeTypes(): array
    {
        $extensionMimes = array_values(
            array_unique(
                array_map(
                    fn (string $extension): string =>
                        self::MIME_BY_EXTENSION[$extension],
                    $this->allowedExtensions()
                )
            )
        );

        $configured = $this->csv(
            'AUTOMATION_ATTACHMENT_ALLOWED_MIME_TYPES',
            $extensionMimes
        );

        $allowed = [];

        foreach ($configured as $mime) {
            $mime = strtolower(trim($mime));

            if (
                in_array($mime, $extensionMimes, true)
                && !in_array($mime, $allowed, true)
            ) {
                $allowed[] = $mime;
            }
        }

        return $allowed !== []
            ? $allowed
            : $extensionMimes;
    }

    public function accepts(
        string $extension,
        string $mime
    ): bool {
        $extension = strtolower(
            ltrim(trim($extension), '.')
        );

        $mime = strtolower(trim($mime));

        return
            in_array(
                $extension,
                $this->allowedExtensions(),
                true
            )
            && isset(self::MIME_BY_EXTENSION[$extension])
            && self::MIME_BY_EXTENSION[$extension] === $mime
            && in_array(
                $mime,
                $this->allowedMimeTypes(),
                true
            );
    }

    public function storageExtensionForMime(
        string $mime
    ): ?string {
        $mime = strtolower(trim($mime));

        foreach (
            $this->allowedExtensions()
            as $extension
        ) {
            if (
                self::MIME_BY_EXTENSION[$extension]
                === $mime
            ) {
                return $extension === 'jpeg'
                    ? 'jpg'
                    : $extension;
            }
        }

        return null;
    }

    public function acceptAttribute(): string
    {
        return implode(
            ',',
            array_map(
                fn (string $extension): string =>
                    '.' . $extension,
                $this->allowedExtensions()
            )
        );
    }

    public function allowedTypeLabel(): string
    {
        $labels = [];

        foreach ($this->allowedExtensions() as $extension) {
            $label = match ($extension) {
                'docx' => 'Word',
                'jpg', 'jpeg' => 'JPG',
                default => strtoupper($extension),
            };

            if (!in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }

        return implode('، ', $labels);
    }

    public function persianNumber(
        int|string $value
    ): string {
        return strtr(
            (string) $value,
            [
                '0' => '۰',
                '1' => '۱',
                '2' => '۲',
                '3' => '۳',
                '4' => '۴',
                '5' => '۵',
                '6' => '۶',
                '7' => '۷',
                '8' => '۸',
                '9' => '۹',
            ]
        );
    }
    public function clientRules(): array
    {
        return [
            'maxFiles' => $this->maxFiles(),
            'maxEach' => $this->maxFileBytes(),
            'maxTotal' => $this->maxTotalBytes(),
            'maxFileMb' => $this->maxFileMegabytes(),
            'maxTotalMb' => $this->maxTotalMegabytes(),
            'extensions' => $this->allowedExtensions(),
        ];
    }

    private function boundedInteger(
        string $key,
        int $default,
        int $minimum,
        int $maximum
    ): int {
        $raw = trim(
            (string) Env::get($key, '')
        );

        if (
            $raw === ''
            || preg_match('/^\d+$/', $raw) !== 1
        ) {
            return $default;
        }

        $value = (int) $raw;

        if (
            $value < $minimum
            || $value > $maximum
        ) {
            return $default;
        }

        return $value;
    }

    private function csv(
        string $key,
        array $default
    ): array {
        $raw = trim(
            (string) Env::get($key, '')
        );

        if ($raw === '') {
            return $default;
        }

        return array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(',', $raw)
                ),
                fn (string $value): bool =>
                    $value !== ''
            )
        );
    }
}
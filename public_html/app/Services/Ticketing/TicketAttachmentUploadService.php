<?php

namespace App\Services\Ticketing;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TicketAttachmentUploadService
{
    private const MAX_FILES = 5;

    private const MAX_FILE_BYTES =
        10 * 1024 * 1024;

    private const MAX_TOTAL_BYTES =
        25 * 1024 * 1024;

    private const STORAGE_DISK =
        'ticketing_private';

    /**
     * Allowed MIME => allowed extensions.
     *
     * SVG / HTML / executable or script formats are
     * intentionally not accepted.
     */
    private const ALLOWED_TYPES = [
        'application/pdf' => [
            'pdf',
        ],

        'image/jpeg' => [
            'jpg',
            'jpeg',
        ],

        'image/png' => [
            'png',
        ],

        'image/webp' => [
            'webp',
        ],

        'text/plain' => [
            'txt',
            'log',
            'csv',
        ],

        'text/csv' => [
            'csv',
        ],

        'application/msword' => [
            'doc',
        ],

        'application/vnd.ms-excel' => [
            'xls',
        ],

        'application/vnd.ms-office' => [
            'doc',
            'xls',
        ],

        'application/x-ole-storage' => [
            'doc',
            'xls',
        ],

        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            => [
                'docx',
            ],

        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            => [
                'xlsx',
            ],

        /*
         * Some libmagic versions detect OOXML files
         * as ZIP containers.
         */
        'application/zip' => [
            'zip',
            'docx',
            'xlsx',
        ],

        'application/x-zip-compressed' => [
            'zip',
            'docx',
            'xlsx',
        ],
    ];


    public function prepare(
        array $input,
        string $uploadedByUserReference
    ): array {
        $files =
            $this->normalizeFiles(
                $input
            );

        if ($files === []) {
            return [];
        }

        if (count($files) > self::MAX_FILES) {
            throw new \InvalidArgumentException(
                'ticket_attachment_too_many'
            );
        }

        $privateRoot =
            $this->privateStorageRoot();

        /*
         * TICKETING_ATTACHMENT_QUARANTINE_SCAN_PROMOTE
         *
         * Final Ticketing uploads live beneath storage/uploads.
         * Quarantine is a sibling beneath the same storage root.
         */
        $storageRoot =
            dirname(
                $privateRoot
            );

        $quarantineRoot =
            $storageRoot
            . '/quarantine/ticketing';

        if (
            !is_dir($quarantineRoot)
            && !mkdir($quarantineRoot, 0700, true)
            && !is_dir($quarantineRoot)
        ) {
            throw new \RuntimeException(
                'ticket_attachment_storage_unavailable'
            );
        }

        @chmod(
            $quarantineRoot,
            0700
        );

        /* Reuse the platform-wide scanner. */
        $scanner =
            new \App\Services\Infrastructure\ClamAvProcessScanner();

        $prepared = [];
        $totalBytes = 0;
        $quarantinePath = null;

        try {
            foreach ($files as $file) {
                $quarantinePath = null;

                $error =
                    (int) (
                        $file['error']
                        ?? UPLOAD_ERR_NO_FILE
                    );

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($error !== UPLOAD_ERR_OK) {
                    throw new \InvalidArgumentException(
                        'ticket_attachment_upload_failed'
                    );
                }

                $temporaryPath =
                    trim(
                        (string) (
                            $file['tmp_name']
                            ?? ''
                        )
                    );

                if (
                    $temporaryPath === ''
                    || !is_uploaded_file($temporaryPath)
                ) {
                    throw new \InvalidArgumentException(
                        'ticket_attachment_upload_invalid'
                    );
                }

                $temporarySize =
                    filesize(
                        $temporaryPath
                    );

                if (
                    $temporarySize === false
                    || (int) $temporarySize < 1
                ) {
                    throw new \InvalidArgumentException(
                        'ticket_attachment_empty'
                    );
                }

                $temporarySize =
                    (int) $temporarySize;

                if ($temporarySize > self::MAX_FILE_BYTES) {
                    throw new \InvalidArgumentException(
                        'ticket_attachment_too_large'
                    );
                }

                $totalBytes += $temporarySize;

                if ($totalBytes > self::MAX_TOTAL_BYTES) {
                    throw new \InvalidArgumentException(
                        'ticket_attachment_total_too_large'
                    );
                }

                $originalName =
                    $this->originalName(
                        (string) (
                            $file['name']
                            ?? ''
                        )
                    );

                $extension =
                    strtolower(
                        pathinfo(
                            $originalName,
                            PATHINFO_EXTENSION
                        )
                    );

                if (
                    $extension === ''
                    || strlen($extension) > 10
                ) {
                    throw new \InvalidArgumentException(
                        'ticket_attachment_type_invalid'
                    );
                }

                /* Browser upload -> account-local quarantine. */
                $quarantinePath =
                    $quarantineRoot
                    . '/'
                    . strtolower(
                        bin2hex(
                            random_bytes(24)
                        )
                    )
                    . '.upload';

                if (
                    !move_uploaded_file(
                        $temporaryPath,
                        $quarantinePath
                    )
                ) {
                    $quarantinePath = null;

                    throw new \RuntimeException(
                        'ticket_attachment_store_failed'
                    );
                }

                @chmod(
                    $quarantinePath,
                    0600
                );

                /* Security metadata comes from quarantine. */
                $size =
                    filesize(
                        $quarantinePath
                    );

                if (
                    $size === false
                    || (int) $size < 1
                    || (int) $size !== $temporarySize
                ) {
                    throw new \InvalidArgumentException(
                        'ticket_attachment_upload_invalid'
                    );
                }

                $size = (int) $size;

                $mimeType =
                    $this->detectMime(
                        $quarantinePath
                    );

                $allowedExtensions =
                    self::ALLOWED_TYPES[
                        $mimeType
                    ] ?? null;

                if (
                    !is_array($allowedExtensions)
                    || !in_array(
                        $extension,
                        $allowedExtensions,
                        true
                    )
                ) {
                    throw new \InvalidArgumentException(
                        'ticket_attachment_type_invalid'
                    );
                }

                $checksum =
                    hash_file(
                        'sha256',
                        $quarantinePath
                    );

                if (
                    !is_string($checksum)
                    || preg_match(
                        '/^[a-f0-9]{64}$/',
                        strtolower($checksum)
                    ) !== 1
                ) {
                    throw new \RuntimeException(
                        'ticket_attachment_checksum_failed'
                    );
                }

                $checksum =
                    strtolower($checksum);

                /*
                 * Shared malware scanner result:
                 * clean / infected / error
                 */
                try {
                    $scanResult =
                        $scanner->scan(
                            $quarantinePath
                        );
                } catch (\Throwable $scanException) {
                    @unlink($quarantinePath);
                    $quarantinePath = null;

                    throw new \InvalidArgumentException(
                        'ticket_attachment_scan_failed',
                        0,
                        $scanException
                    );
                }

                if ($scanResult === 'infected') {
                    @unlink($quarantinePath);
                    $quarantinePath = null;

                    throw new \InvalidArgumentException(
                        'ticket_attachment_infected'
                    );
                }

                if ($scanResult !== 'clean') {
                    @unlink($quarantinePath);
                    $quarantinePath = null;

                    throw new \InvalidArgumentException(
                        'ticket_attachment_scan_failed'
                    );
                }

                /* Allocate final identity only after CLEAN. */
                $publicReference =
                    'TKA-'
                    . strtoupper(
                        bin2hex(
                            random_bytes(12)
                        )
                    );

                $relativeDirectory =
                    'ticketing/attachments/'
                    . gmdate('Y/m');

                $storageKey =
                    $relativeDirectory
                    . '/'
                    . strtolower(
                        bin2hex(
                            random_bytes(24)
                        )
                    )
                    . '.'
                    . $extension;

                $directory =
                    $privateRoot
                    . '/'
                    . $relativeDirectory;

                if (
                    !is_dir($directory)
                    && !mkdir($directory, 0750, true)
                    && !is_dir($directory)
                ) {
                    throw new \RuntimeException(
                        'ticket_attachment_storage_unavailable'
                    );
                }

                $finalPath =
                    $privateRoot
                    . '/'
                    . $storageKey;

                if (is_file($finalPath)) {
                    throw new \RuntimeException(
                        'ticket_attachment_storage_collision'
                    );
                }

                /* Atomic promotion on the deployment filesystem. */
                if (!rename($quarantinePath, $finalPath)) {
                    throw new \RuntimeException(
                        'ticket_attachment_store_failed'
                    );
                }

                $quarantinePath = null;

                @chmod(
                    $finalPath,
                    0640
                );

                /* Post-promote integrity proof. */
                $finalSize =
                    filesize(
                        $finalPath
                    );

                $finalChecksum =
                    hash_file(
                        'sha256',
                        $finalPath
                    );

                if (
                    $finalSize === false
                    || (int) $finalSize !== $size
                    || !is_string($finalChecksum)
                    || !hash_equals(
                        $checksum,
                        strtolower($finalChecksum)
                    )
                ) {
                    @unlink($finalPath);

                    throw new \RuntimeException(
                        'ticket_attachment_checksum_failed'
                    );
                }

                $prepared[] = [
                    'public_reference' =>
                        $publicReference,

                    'storage_disk' =>
                        self::STORAGE_DISK,

                    'storage_key' =>
                        $storageKey,

                    'original_name' =>
                        $originalName,

                    'mime_type' =>
                        $mimeType,

                    'size_bytes' =>
                        $size,

                    'checksum_sha256' =>
                        $checksum,

                    'scan_status_code' =>
                        'clean',

                    'uploaded_by_user_reference' =>
                        $uploadedByUserReference,

                    /* Runtime-only rollback handle. */
                    'absolute_path' =>
                        $finalPath,
                ];
            }
        } catch (\Throwable $exception) {
            if (
                is_string($quarantinePath)
                && $quarantinePath !== ''
                && is_file($quarantinePath)
            ) {
                @unlink($quarantinePath);
            }

            /* Remove already-promoted siblings on multipart failure. */
            $this->cleanup(
                $prepared
            );

            throw $exception;
        }

        return $prepared;
    }

    public function cleanup(
        array $prepared
    ): void {
        foreach ($prepared as $attachment) {
            $path =
                (string) (
                    $attachment[
                        'absolute_path'
                    ]
                    ?? ''
                );

            if (
                $path !== ''
                && is_file($path)
            ) {
                @unlink($path);
            }
        }
    }


    public function errorMessage(
        string $code
    ): string {
        return match ($code) {
            'ticket_attachment_too_many' =>
                'حداکثر ۵ فایل قابل پیوست است.',

            'ticket_attachment_too_large' =>
                'حجم هر فایل حداکثر ۱۰ مگابایت است.',

            'ticket_attachment_total_too_large' =>
                'مجموع حجم پیوست‌ها حداکثر ۲۵ مگابایت است.',

            'ticket_attachment_type_invalid' =>
                'نوع یکی از فایل‌های پیوست مجاز نیست.',

            'ticket_attachment_empty' =>
                'فایل خالی قابل پیوست نیست.',

            'ticket_attachment_upload_failed',
            'ticket_attachment_upload_invalid' =>
                'بارگذاری یکی از فایل‌ها کامل یا معتبر نیست.',

            'ticket_attachment_infected' =>
                'فایل انتخاب‌شده آلوده تشخیص داده شد و بارگذاری آن انجام نشد.',

            'ticket_attachment_scan_failed' =>
                'بررسی امنیتی فایل در حال حاضر انجام نشد. فایل بارگذاری نشد.',

            default =>
                'ذخیره فایل پیوست انجام نشد.',
        };
    }


    private function normalizeFiles(
        array $input
    ): array {
        if (
            !array_key_exists(
                'name',
                $input
            )
        ) {
            return [];
        }

        $names =
            is_array($input['name'])
                ? $input['name']
                : [$input['name']];

        $types =
            is_array(
                $input['type'] ?? null
            )
                ? $input['type']
                : [
                    $input['type']
                    ?? '',
                ];

        $temporaryPaths =
            is_array(
                $input['tmp_name']
                ?? null
            )
                ? $input['tmp_name']
                : [
                    $input['tmp_name']
                    ?? '',
                ];

        $errors =
            is_array(
                $input['error']
                ?? null
            )
                ? $input['error']
                : [
                    $input['error']
                    ?? UPLOAD_ERR_NO_FILE,
                ];

        $sizes =
            is_array(
                $input['size']
                ?? null
            )
                ? $input['size']
                : [
                    $input['size']
                    ?? 0,
                ];

        $files = [];

        foreach (
            array_keys($names)
            as $index
        ) {
            $files[] = [
                'name' =>
                    $names[$index]
                    ?? '',

                'type' =>
                    $types[$index]
                    ?? '',

                'tmp_name' =>
                    $temporaryPaths[$index]
                    ?? '',

                'error' =>
                    $errors[$index]
                    ?? UPLOAD_ERR_NO_FILE,

                'size' =>
                    $sizes[$index]
                    ?? 0,
            ];
        }

        return $files;
    }


    private function originalName(
        string $name
    ): string {
        $name =
            str_replace(
                '\\',
                '/',
                trim($name)
            );

        $name =
            basename($name);

        $name =
            preg_replace(
                '/[\x00-\x1F\x7F]+/u',
                '',
                $name
            ) ?? '';

        $name =
            trim($name);

        if ($name === '') {
            $name = 'attachment';
        }

        return mb_substr(
            $name,
            0,
            500,
            'UTF-8'
        );
    }


    private function detectMime(
        string $path
    ): string {
        $mime = '';

        if (
            class_exists(
                \finfo::class
            )
        ) {
            $info =
                new \finfo(
                    FILEINFO_MIME_TYPE
                );

            $detected =
                $info->file($path);

            if (is_string($detected)) {
                $mime =
                    strtolower(
                        trim($detected)
                    );
            }
        }

        if (
            $mime === ''
            && function_exists(
                'mime_content_type'
            )
        ) {
            $detected =
                mime_content_type($path);

            if (is_string($detected)) {
                $mime =
                    strtolower(
                        trim($detected)
                    );
            }
        }

        if ($mime === '') {
            throw new InvalidArgumentException(
                'ticket_attachment_type_invalid'
            );
        }

        return $mime;
    }


    private function privateStorageRoot(): string
    {
        if (
            !defined('BASE_PATH')
            || trim(
                (string) BASE_PATH
            ) === ''
        ) {
            throw new RuntimeException(
                'ticket_attachment_storage_unavailable'
            );
        }

        return
            rtrim(
                (string) BASE_PATH,
                '/'
            )
            . '/storage/uploads';
    }
}

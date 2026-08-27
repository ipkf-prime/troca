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
            $this->normalizeFiles($input);

        if ($files === []) {
            return [];
        }

        if (count($files) > self::MAX_FILES) {
            throw new InvalidArgumentException(
                'ticket_attachment_too_many'
            );
        }

        $prepared = [];
        $totalBytes = 0;

        try {
            foreach ($files as $file) {
                $error =
                    (int) (
                        $file['error']
                        ?? UPLOAD_ERR_NO_FILE
                    );

                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($error !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException(
                        'ticket_attachment_upload_failed'
                    );
                }

                $temporaryPath =
                    (string) (
                        $file['tmp_name']
                        ?? ''
                    );

                if (
                    $temporaryPath === ''
                    || !is_uploaded_file(
                        $temporaryPath
                    )
                ) {
                    throw new InvalidArgumentException(
                        'ticket_attachment_upload_invalid'
                    );
                }

                $size =
                    filesize($temporaryPath);

                if (
                    $size === false
                    || $size < 1
                ) {
                    throw new InvalidArgumentException(
                        'ticket_attachment_empty'
                    );
                }

                $size = (int) $size;

                if (
                    $size
                    > self::MAX_FILE_BYTES
                ) {
                    throw new InvalidArgumentException(
                        'ticket_attachment_too_large'
                    );
                }

                $totalBytes += $size;

                if (
                    $totalBytes
                    > self::MAX_TOTAL_BYTES
                ) {
                    throw new InvalidArgumentException(
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
                    throw new InvalidArgumentException(
                        'ticket_attachment_type_invalid'
                    );
                }

                $mimeType =
                    $this->detectMime(
                        $temporaryPath
                    );

                $allowedExtensions =
                    self::ALLOWED_TYPES[
                        $mimeType
                    ] ?? null;

                if (
                    !is_array(
                        $allowedExtensions
                    )
                    || !in_array(
                        $extension,
                        $allowedExtensions,
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'ticket_attachment_type_invalid'
                    );
                }

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

                $root =
                    $this->privateStorageRoot();

                $directory =
                    $root
                    . '/'
                    . $relativeDirectory;

                if (
                    !is_dir($directory)
                    && !mkdir(
                        $directory,
                        0750,
                        true
                    )
                    && !is_dir($directory)
                ) {
                    throw new RuntimeException(
                        'ticket_attachment_storage_unavailable'
                    );
                }

                $path =
                    $root
                    . '/'
                    . $storageKey;

                if (is_file($path)) {
                    throw new RuntimeException(
                        'ticket_attachment_storage_collision'
                    );
                }

                if (
                    !move_uploaded_file(
                        $temporaryPath,
                        $path
                    )
                ) {
                    throw new RuntimeException(
                        'ticket_attachment_store_failed'
                    );
                }

                @chmod(
                    $path,
                    0640
                );

                $checksum =
                    hash_file(
                        'sha256',
                        $path
                    );

                if (
                    !is_string($checksum)
                    || preg_match(
                        '/^[a-f0-9]{64}$/',
                        $checksum
                    ) !== 1
                ) {
                    @unlink($path);

                    throw new RuntimeException(
                        'ticket_attachment_checksum_failed'
                    );
                }

                $prepared[] = [
                    'public_reference' =>
                        $publicReference,

                    'storage_disk' =>
                        self::STORAGE_DISK,

                    /*
                     * Store only the relative key.
                     * Absolute server paths never enter DB.
                     */
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
                        'pending',

                    'uploaded_by_user_reference' =>
                        $uploadedByUserReference,

                    /*
                     * Runtime-only cleanup handle.
                     * Repository never persists this value.
                     */
                    'absolute_path' =>
                        $path,
                ];
            }
        } catch (Throwable $exception) {
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

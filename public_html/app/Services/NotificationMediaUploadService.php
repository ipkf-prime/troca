<?php

namespace App\Services;

use App\Repositories\NotificationMediaRepository;
use finfo;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

class NotificationMediaUploadService extends BaseService
{
    public const MAX_FILES = 5;
    public const MAX_FILE_BYTES = 10485760;
    public const MAX_TOTAL_BYTES = 31457280;

    private const TYPES = [
        'jpg' => ['image', ['image/jpeg']],
        'jpeg' => ['image', ['image/jpeg']],
        'png' => ['image', ['image/png']],
        'webp' => ['image', ['image/webp']],
        'mp4' => ['video', ['video/mp4']],
        'mp3' => ['audio', ['audio/mpeg', 'audio/mp3']],
        'm4a' => [
            'audio',
            ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        ],
        'ogg' => [
            'audio',
            ['audio/ogg', 'application/ogg'],
        ],
        'pdf' => ['document', ['application/pdf']],
        'docx' => [
            'document',
            [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/octet-stream',
            ],
        ],
        'xlsx' => [
            'document',
            [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
        ],
        'txt' => ['document', ['text/plain']],
    ];

    public function __construct(
        private ?NotificationMediaRepository $repository = null
    ) {
        $this->repository ??=
            new NotificationMediaRepository();
    }

    public function store(
        int $actorUserId,
        array $uploadSet
    ): array {
        $uploads = $this->normalize($uploadSet);

        if ($uploads === []) {
            throw new InvalidArgumentException(
                'notification_send_media_required'
            );
        }

        if (count($uploads) > self::MAX_FILES) {
            throw new InvalidArgumentException(
                'notification_send_media_count_exceeded'
            );
        }

        $total = 0;

        foreach ($uploads as $upload) {
            $error = (int) $upload['error'];

            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(
                    in_array(
                        $error,
                        [
                            UPLOAD_ERR_INI_SIZE,
                            UPLOAD_ERR_FORM_SIZE,
                        ],
                        true
                    )
                        ? 'notification_send_media_file_size_exceeded'
                        : 'notification_send_media_upload_failed'
                );
            }

            $size = (int) $upload['size'];

            if (
                $size < 1
                || $size > self::MAX_FILE_BYTES
            ) {
                throw new InvalidArgumentException(
                    'notification_send_media_file_size_exceeded'
                );
            }

            $total += $size;
        }

        if ($total > self::MAX_TOTAL_BYTES) {
            throw new InvalidArgumentException(
                'notification_send_media_total_size_exceeded'
            );
        }

        $stored = [];

        try {
            foreach ($uploads as $upload) {
                $stored[] = $this->storeOne(
                    $actorUserId,
                    $upload
                );
            }

            return $stored;
        } catch (Throwable $exception) {
            $this->cleanup($stored);
            throw $exception;
        }
    }

    public function cleanup(array $assets): void
    {
        foreach ($assets as $asset) {
            $path = (string) (
                $asset['storage_path'] ?? ''
            );

            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }

        $this->repository->remove(array_map(
            static fn (array $asset): int =>
                (int) ($asset['id'] ?? 0),
            $assets
        ));
    }

    private function storeOne(
        int $actorUserId,
        array $upload
    ): array {
        $temporaryPath = (string) $upload['tmp_name'];

        if (
            $temporaryPath === ''
            || !is_uploaded_file($temporaryPath)
        ) {
            throw new InvalidArgumentException(
                'notification_send_media_upload_invalid'
            );
        }

        $originalName = $this->safeName(
            (string) $upload['name']
        );
        $extension = strtolower(pathinfo(
            $originalName,
            PATHINFO_EXTENSION
        ));
        $definition = self::TYPES[$extension] ?? null;

        if (!is_array($definition)) {
            throw new InvalidArgumentException(
                'notification_send_media_type_invalid'
            );
        }

        $mime = $this->mime($temporaryPath);

        if (!in_array(
            $mime,
            $definition[1],
            true
        )) {
            throw new InvalidArgumentException(
                'notification_send_media_type_invalid'
            );
        }

        if (
            in_array($extension, ['docx', 'xlsx'], true)
            && !$this->validOfficeArchive(
                $temporaryPath,
                $extension,
                $mime
            )
        ) {
            throw new InvalidArgumentException(
                'notification_send_media_type_invalid'
            );
        }

        $directory = $this->directory();
        $storedName = bin2hex(random_bytes(20))
            . '.' . $extension;
        $path = $directory
            . DIRECTORY_SEPARATOR
            . $storedName;

        if (!move_uploaded_file(
            $temporaryPath,
            $path
        )) {
            throw new RuntimeException(
                'notification_send_media_storage_failed'
            );
        }

        @chmod($path, 0600);

        try {
            $checksum = hash_file('sha256', $path);

            if (!is_string($checksum) || $checksum === '') {
                throw new RuntimeException(
                    'notification_send_media_storage_failed'
                );
            }

            return $this->repository->create(
                $actorUserId,
                [
                    'original_name' => $originalName,
                    'stored_name' => $storedName,
                    'storage_path' => $path,
                    'mime_type' => $mime,
                    'extension' => $extension,
                    'media_kind' =>
                        (string) $definition[0],
                    'size_bytes' => (int) filesize($path),
                    'checksum_sha256' => $checksum,
                ]
            );
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
    }

    private function normalize(array $files): array
    {
        $names = $files['name'] ?? [];

        if (!is_array($names)) {
            $names = [$names];
        }

        $result = [];

        foreach ($names as $index => $name) {
            $error = $this->at(
                $files['error'] ?? [],
                $index,
                UPLOAD_ERR_NO_FILE
            );

            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result[] = [
                'name' => (string) $name,
                'tmp_name' => (string) $this->at(
                    $files['tmp_name'] ?? [],
                    $index,
                    ''
                ),
                'error' => (int) $error,
                'size' => (int) $this->at(
                    $files['size'] ?? [],
                    $index,
                    0
                ),
            ];
        }

        return $result;
    }

    private function at(
        mixed $value,
        int|string $index,
        mixed $default
    ): mixed {
        if (is_array($value)) {
            return $value[$index] ?? $default;
        }

        return (int) $index === 0
            ? $value
            : $default;
    }

    private function mime(string $path): string
    {
        $mime = '';

        if (class_exists(finfo::class)) {
            $detector = new finfo(FILEINFO_MIME_TYPE);
            $value = $detector->file($path);
            $mime = is_string($value)
                ? strtolower(trim($value))
                : '';
        }

        if (
            $mime === ''
            && function_exists('mime_content_type')
        ) {
            $value = mime_content_type($path);
            $mime = is_string($value)
                ? strtolower(trim($value))
                : '';
        }

        if ($mime === '') {
            throw new RuntimeException(
                'notification_send_media_type_detection_failed'
            );
        }

        return $mime;
    }

    private function validOfficeArchive(
        string $path,
        string $extension,
        string $mime
    ): bool {
        $official = [
            'docx' =>
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (
            $mime === ($official[$extension] ?? '')
            && !class_exists(ZipArchive::class)
        ) {
            return true;
        }

        if (!class_exists(ZipArchive::class)) {
            return false;
        }

        $archive = new ZipArchive();

        if ($archive->open($path) !== true) {
            return false;
        }

        try {
            $required = $extension === 'docx'
                ? 'word/document.xml'
                : 'xl/workbook.xml';

            return $archive->locateName(
                '[Content_Types].xml'
            ) !== false
                && $archive->locateName($required)
                    !== false;
        } finally {
            $archive->close();
        }
    }

    private function directory(): string
    {
        $root = trim((string) getenv(
            'NOTIFICATION_MEDIA_STORAGE_PATH'
        ));

        if ($root === '') {
            $applicationRoot = dirname(__DIR__, 2);
            $root = dirname($applicationRoot)
                . DIRECTORY_SEPARATOR . 'storage'
                . DIRECTORY_SEPARATOR . 'ipkf'
                . DIRECTORY_SEPARATOR
                . 'notification-media';
        }

        $directory = rtrim(
            $root,
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR . date('Y')
            . DIRECTORY_SEPARATOR . date('m');

        if (
            !is_dir($directory)
            && !mkdir($directory, 0700, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'notification_send_media_storage_failed'
            );
        }

        @chmod($directory, 0700);

        if (!is_writable($directory)) {
            throw new RuntimeException(
                'notification_send_media_storage_failed'
            );
        }

        return $directory;
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim(preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            '',
            $name
        ) ?? '');

        return mb_substr(
            $name !== '' ? $name : 'file',
            0,
            255,
            'UTF-8'
        );
    }
}

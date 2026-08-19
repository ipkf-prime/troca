<?php

namespace App\Services\Automation\Correspondence;

use IPKF\Support\Clock;
use IPKF\Support\Env;
use RuntimeException;

class CorrespondenceAttachmentService
{
    private const MAX_BYTES = 10485760;
    private const MAX_FILES = 3;
    private const MAX_TOTAL_BYTES = 20971520;
    private const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    public function __construct(
        private ?CorrespondenceRepository $correspondences = null,
        private ?CorrespondenceAttachmentRepository $attachments = null,
        private ?EnterpriseAutomationContextService $enterpriseContext = null
    ) {
        $this->correspondences ??= new CorrespondenceRepository();
        $this->attachments ??= new CorrespondenceAttachmentRepository();
        $this->enterpriseContext ??= new EnterpriseAutomationContextService();
    }

    public function upload(string $correspondenceReference, array $upload, string $role, ?string $title, int $userId): array
    {
        $actor = $this->enterpriseContext->forUser($userId);

        $correspondence = $this->correspondences->findByPublicReferenceScoped(
            $correspondenceReference,
            $actor['repository_scope']
        );
        if ($correspondence === null || ($correspondence['status_code'] ?? '') !== 'draft' || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'invalid_upload'];
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if (!is_uploaded_file($tmp) || $size < 1 || $size > self::MAX_BYTES) return ['ok' => false, 'error' => 'invalid_size'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
        $originalExtension = strtolower((string) pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
        if ($mime === 'application/zip' && $originalExtension === 'docx' && $this->validDocx($tmp)) {
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }
        if (!in_array($mime, self::MIME_TYPES, true)) return ['ok' => false, 'error' => 'invalid_type'];
        if (!in_array($role, ['main', 'enclosure', 'supporting', 'scan'], true)) $role = 'enclosure';

        $configuredRoot = trim((string) Env::get('PRIVATE_FILE_STORAGE_PATH', ''));
        $root = rtrim($configuredRoot !== '' ? $configuredRoot : dirname(BASE_PATH) . '/storage/private/automation', '/\\');
        $directory = $root . '/' . gmdate('Y/m');
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Private storage is unavailable.');
        $reference = 'PF-' . strtoupper(bin2hex(random_bytes(10)));
        $extension = match ($mime) { 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'docx' };
        $path = $directory . '/' . $reference . '.' . $extension;
        if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('Private file could not be stored.');

        try {
            $this->attachments->add((int) $correspondence['id'], [
                'public_reference' => $reference,
                'storage_key' => $path,
                'original_filename' => $this->filename((string) ($upload['name'] ?? 'attachment')),
                'mime_type' => $mime,
                'size_bytes' => $size,
                'sha256_checksum' => hash_file('sha256', $path),
            ],
            $role,
            $this->text($title, 255),
            $userId,
            Clock::databaseTimestamp(),
            $actor
        );
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
        return ['ok' => true];
    }

    /**
     * correspondence-attachment-wizard-v1
     */
    public function uploadMany(
        string $correspondenceReference,
        array $uploads,
        string $role,
        int $userId
    ): array {
        $names =
            $uploads['name']
            ?? [];

        if (!is_array($names)) {
            $names =
                $names === ''
                    ? []
                    : [$names];
        }

        $files = [];

        foreach (
            array_keys($names)
            as $index
        ) {
            $file = [
                'name' =>
                    (string) (
                        $uploads['name'][$index]
                        ?? ''
                    ),

                'type' =>
                    (string) (
                        $uploads['type'][$index]
                        ?? ''
                    ),

                'tmp_name' =>
                    (string) (
                        $uploads['tmp_name'][$index]
                        ?? ''
                    ),

                'error' =>
                    (int) (
                        $uploads['error'][$index]
                        ?? UPLOAD_ERR_NO_FILE
                    ),

                'size' =>
                    (int) (
                        $uploads['size'][$index]
                        ?? 0
                    ),
            ];

            if (
                $file['error']
                === UPLOAD_ERR_NO_FILE
            ) {
                continue;
            }

            $files[] = $file;
        }

        if ($files === []) {
            return [
                'ok' => true,
                'count' => 0,
            ];
        }

        if (
            count($files)
            > self::MAX_FILES
        ) {
            return [
                'ok' => false,
                'error' =>
                    'too_many_files',
                'count' => 0,
            ];
        }

        $total = 0;

        foreach ($files as $file) {
            if (
                $file['error']
                !== UPLOAD_ERR_OK
            ) {
                return [
                    'ok' => false,
                    'error' =>
                        'invalid_upload',
                    'count' => 0,
                ];
            }

            $total +=
                (int) $file['size'];
        }

        if (
            $total
            > self::MAX_TOTAL_BYTES
        ) {
            return [
                'ok' => false,
                'error' =>
                    'invalid_total_size',
                'count' => 0,
            ];
        }

        $stored = 0;

        foreach ($files as $file) {
            $result =
                $this->upload(
                    $correspondenceReference,
                    $file,
                    $role,
                    null,
                    $userId
                );

            if (
                ($result['ok'] ?? false)
                !== true
            ) {
                return [
                    'ok' => false,

                    'error' =>
                        $result['error']
                        ?? 'invalid_upload',

                    'count' => $stored,
                ];
            }

            $stored++;
        }

        return [
            'ok' => true,
            'count' => $stored,
        ];
    }
    /**
     * attachment-soft-delete-v1
     */
    public function remove(
        string $correspondenceReference,
        string $fileReference,
        int $userId
    ): array {
        $correspondenceReference =
            trim($correspondenceReference);

        $fileReference =
            trim($fileReference);

        if (
            $correspondenceReference === ''
            || $fileReference === ''
        ) {
            return [
                'ok' => false,
                'error' =>
                    'attachment_not_removable',
            ];
        }

        $actor =
            $this->enterpriseContext
                ->forUser($userId);

        $removed =
            $this->attachments
                ->remove(
                    $correspondenceReference,
                    $fileReference,
                    $userId,
                    Clock::databaseTimestamp(),
                    $actor
                );

        return $removed
            ? [
                'ok' => true,
                'status' => 'removed',
            ]
            : [
                'ok' => false,
                'error' =>
                    'attachment_not_removable',
            ];
    }
    private function text(?string $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    private function filename(string $value): string
    {
        $value = basename(str_replace('\\', '/', $value));
        $value = $value !== '' ? $value : 'attachment';
        return function_exists('mb_substr') ? mb_substr($value, 0, 500, 'UTF-8') : substr($value, 0, 500);
    }

    private function validDocx(string $path): bool
    {
        if (!class_exists(\ZipArchive::class)) return false;
        $archive = new \ZipArchive();
        if ($archive->open($path) !== true) return false;
        $valid = $archive->locateName('[Content_Types].xml') !== false && $archive->locateName('word/document.xml') !== false;
        $archive->close();
        return $valid;
    }
}

<?php

namespace App\Services\Automation\Correspondence;

use IPKF\Support\Clock;
use IPKF\Support\Env;
use RuntimeException;

class CorrespondenceAttachmentService
{
    private const MAX_BYTES = 10485760;
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

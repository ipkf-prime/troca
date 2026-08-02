<?php

namespace App\Services;

use App\Repositories\InternalMessageRepository;
use RuntimeException;

class InternalMessageAttachmentService extends BaseService
{
    private const MIME = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'txt' => ['text/plain'],
    ];

    public function __construct(private ?InternalMessageRepository $messages = null)
    {
        $this->messages ??= new InternalMessageRepository();
    }

    public function store(int $userId, string $messageReference, array $input): array
    {
        $files = $this->normalize($input);
        if ($files === []) {
            return [];
        }
        $settings = $this->messages->settings();
        if (($settings['attachments_enabled'] ?? '1') !== '1') {
            throw new RuntimeException('message_attachments_disabled');
        }
        $maxFiles = max(1, min(10, (int) ($settings['attachment_max_files'] ?? 3)));
        $maxEach = max(1, (int) ($settings['attachment_max_each_mb'] ?? 10)) * 1048576;
        $maxTotal = max(1, (int) ($settings['attachment_max_total_mb'] ?? 20)) * 1048576;
        $allowed = array_filter(array_map('trim', explode(',', strtolower((string) ($settings['attachment_extensions'] ?? implode(',', array_keys(self::MIME)))))));
        if (count($files) > $maxFiles) {
            throw new RuntimeException('message_attachment_count_exceeded');
        }
        $total = 0;
        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new RuntimeException('message_attachment_upload_invalid');
            }
            $size = (int) $file['size'];
            $total += $size;
            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
            if ($size < 1 || $size > $maxEach || $total > $maxTotal || !in_array($extension, $allowed, true)
                || !in_array($mime, self::MIME[$extension] ?? [], true)) {
                throw new RuntimeException('message_attachment_rejected');
            }
        }
        $directory = dirname(BASE_PATH) . '/storage/private/messages/' . gmdate('Y/m');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('message_attachment_storage_failed');
        }
        $stored = [];
        foreach ($files as $file) {
            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $name = bin2hex(random_bytes(24)) . '.' . $extension;
            $path = $directory . '/' . $name;
            if (!move_uploaded_file((string) $file['tmp_name'], $path)) {
                throw new RuntimeException('message_attachment_storage_failed');
            }
            chmod($path, 0600);
            $record = [
                'public_reference' => 'msga_' . bin2hex(random_bytes(12)),
                'original_name' => $this->filename((string) $file['name']),
                'stored_name' => $name, 'storage_path' => $path,
                'mime_type' => (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path),
                'extension' => $extension, 'size_bytes' => filesize($path) ?: 0,
                'checksum_sha256' => hash_file('sha256', $path),
            ];
            $this->messages->addAttachment($messageReference, $userId, $record);
            $stored[] = $record;
        }
        return $stored;
    }

    public function download(int $userId, string $reference): ?array
    {
        $row = $this->messages->attachmentForUser($userId, $reference);
        return $row && is_file((string) $row['storage_path']) ? $row : null;
    }

    private function normalize(array $input): array
    {
        if (!isset($input['name'])) return [];
        if (!is_array($input['name'])) return [array_map(static fn ($v) => $v, $input)];
        $files = [];
        foreach ($input['name'] as $i => $name) {
            if ((int) ($input['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $files[] = ['name' => $name, 'type' => $input['type'][$i] ?? '', 'tmp_name' => $input['tmp_name'][$i] ?? '',
                'error' => $input['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $input['size'][$i] ?? 0];
        }
        return $files;
    }

    private function filename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'attachment', 0, 255);
    }
}

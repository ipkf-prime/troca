<?php

namespace App\Services\Work;

use App\Repositories\WorkItemDetailRepository;
use App\Services\BaseService;
use IPKF\Support\Env;
use RuntimeException;

class WorkItemDetailService extends BaseService
{
    private const MAX_ATTACHMENT_BYTES = 15728640;

    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'text/plain' => 'txt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    public function __construct(
        private ?WorkItemDetailRepository $details = null,
        private ?WorkReferenceDataService $references = null
    ) {
        $this->details ??= new WorkItemDetailRepository();
        $this->references ??= new WorkReferenceDataService();
    }

    public function view(string $projectReference, string $itemReference): array
    {
        $item = $this->details->detail(trim($projectReference), trim($itemReference));
        if ($item === null) {
            return ['ok' => false];
        }

        $children = $this->details->children((int) $item['id']);
        foreach ($children as &$child) {
            $child['type_title'] = $this->typeTitle((string) $child['item_type']);
            $child['priority_title'] = $this->priorityTitle((string) $child['priority_code']);
        }
        unset($child);

        $activities = $this->details->activities((int) $item['id']);
        foreach ($activities as &$activity) {
            $payload = json_decode((string) ($activity['payload_json'] ?? ''), true);
            $activity['payload'] = is_array($payload) ? $payload : [];
            $activity['event_title'] = $this->eventTitle((string) $activity['event_type']);
        }
        unset($activity);

        $item['type_title'] = $this->typeTitle((string) $item['item_type']);
        $item['priority_title'] = $this->priorityTitle((string) $item['priority_code']);
        $item['is_locked'] = !empty($item['archived_at']) || !empty($item['project_archived_at']);

        return [
            'ok' => true,
            'item' => $item,
            'children' => $children,
            'checklist' => $this->details->checklist((int) $item['id']),
            'comments' => $this->details->comments((int) $item['id']),
            'attachments' => $this->details->attachments((int) $item['id']),
            'activities' => $activities,
        ];
    }

    public function addComment(
        string $projectReference,
        string $itemReference,
        string $body,
        int $userId,
        array $context = []
    ): array {
        $item = $this->editableItem($projectReference, $itemReference);
        if ($item === null) {
            return ['ok' => false, 'error' => 'not_editable'];
        }

        $body = trim($body);
        $length = $this->length($body);
        if ($length < 1 || $length > 10000) {
            return ['ok' => false, 'error' => 'invalid_comment'];
        }

        return [
            'ok' => $this->details->addComment(
                $item,
                $body,
                'user:' . $userId,
                $this->actorDisplayName($context, $userId)
            ),
        ];
    }

    public function addChecklist(
        string $projectReference,
        string $itemReference,
        string $title,
        int $userId,
        array $context = []
    ): array {
        $item = $this->editableItem($projectReference, $itemReference);
        if ($item === null) {
            return ['ok' => false, 'error' => 'not_editable'];
        }

        $title = trim($title);
        $length = $this->length($title);
        if ($length < 1 || $length > 500) {
            return ['ok' => false, 'error' => 'invalid_checklist'];
        }

        return [
            'ok' => $this->details->addChecklistItem(
                $item,
                $title,
                'user:' . $userId,
                $this->actorDisplayName($context, $userId)
            ),
        ];
    }

    public function toggleChecklist(
        string $projectReference,
        string $itemReference,
        int $checklistId,
        bool $completed,
        int $userId,
        array $context = []
    ): bool {
        $item = $this->editableItem($projectReference, $itemReference);
        if ($item === null || $checklistId < 1) {
            return false;
        }

        return $this->details->toggleChecklistItem(
            $item,
            $checklistId,
            $completed,
            'user:' . $userId,
            $this->actorDisplayName($context, $userId)
        );
    }

    public function uploadAttachment(
        string $projectReference,
        string $itemReference,
        array $upload,
        int $userId,
        array $context = []
    ): array {
        $item = $this->editableItem($projectReference, $itemReference);
        if ($item === null) {
            return ['ok' => false, 'error' => 'not_editable'];
        }

        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'upload_failed'];
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);

        if (
            !is_uploaded_file($temporaryPath)
            || $size < 1
            || $size > self::MAX_ATTACHMENT_BYTES
        ) {
            return ['ok' => false, 'error' => 'invalid_size'];
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath)
            ?: 'application/octet-stream';
        $originalName = $this->filename((string) ($upload['name'] ?? 'attachment'));
        $originalExtension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if (
            $mime === 'application/zip'
            && in_array($originalExtension, ['docx', 'xlsx'], true)
            && $this->validOfficeArchive($temporaryPath, $originalExtension)
        ) {
            $mime = $originalExtension === 'docx'
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            return ['ok' => false, 'error' => 'invalid_type'];
        }

        $root = $this->privateStorageRoot();
        $directory = $root . '/' . gmdate('Y/m');

        if (
            !is_dir($directory)
            && !mkdir($directory, 0750, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException('Work private storage is unavailable.');
        }

        $reference = 'WRK-FILE-' . strtoupper(bin2hex(random_bytes(9)));
        $path = $directory . '/' . $reference . '.' . self::MIME_EXTENSIONS[$mime];

        if (!move_uploaded_file($temporaryPath, $path)) {
            throw new RuntimeException('Work attachment could not be stored.');
        }

        try {
            $stored = $this->details->addAttachment(
                $item,
                [
                    'public_reference' => $reference,
                    'storage_key' => $path,
                    'original_name' => $originalName,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'checksum_sha256' => hash_file('sha256', $path),
                ],
                'user:' . $userId,
                $this->actorDisplayName($context, $userId)
            );
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return ['ok' => $stored];
    }

    public function download(
        string $projectReference,
        string $itemReference,
        string $attachmentReference
    ): ?array {
        $attachment = $this->details->attachment(
            trim($projectReference),
            trim($itemReference),
            trim($attachmentReference)
        );

        if ($attachment === null || ($attachment['scan_status'] ?? '') === 'rejected') {
            return null;
        }

        $path = (string) ($attachment['storage_key'] ?? '');
        if (
            $path === ''
            || !is_file($path)
            || !is_readable($path)
            || !hash_equals(
                (string) ($attachment['checksum_sha256'] ?? ''),
                (string) hash_file('sha256', $path)
            )
        ) {
            return null;
        }

        return $attachment + ['path' => $path];
    }

    public function errorMessage(string $code): string
    {
        return match ($code) {
            'invalid_comment' => 'متن دیدگاه معتبر نیست.',
            'invalid_checklist' => 'عنوان چک‌لیست معتبر نیست.',
            'invalid_size' => 'حجم فایل باید بیشتر از صفر و حداکثر ۱۵ مگابایت باشد.',
            'invalid_type' => 'فقط PDF، تصویر JPG/PNG، فایل متنی، Word و Excel مجاز است.',
            'not_editable' => 'این آیتم یا پروژه قابل تغییر نیست.',
            default => 'عملیات انجام نشد.',
        };
    }

    private function editableItem(string $projectReference, string $itemReference): ?array
    {
        $item = $this->details->detail(trim($projectReference), trim($itemReference));

        if (
            $item === null
            || !empty($item['archived_at'])
            || !empty($item['project_archived_at'])
        ) {
            return null;
        }

        return $item;
    }

    private function privateStorageRoot(): string
    {
        $workRoot = trim((string) Env::get('WORK_PRIVATE_FILE_STORAGE_PATH', ''));
        if ($workRoot !== '') {
            return rtrim($workRoot, '/\\');
        }

        $sharedRoot = trim((string) Env::get('PRIVATE_FILE_STORAGE_PATH', ''));
        if ($sharedRoot !== '') {
            return rtrim(dirname(rtrim($sharedRoot, '/\\')), '/\\') . '/work';
        }

        return dirname(BASE_PATH) . '/storage/private/work';
    }

    private function validOfficeArchive(string $path, string $extension): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            return false;
        }

        $archive = new \ZipArchive();
        if ($archive->open($path) !== true) {
            return false;
        }

        $valid = $archive->locateName('[Content_Types].xml') !== false
            && (
                ($extension === 'docx' && $archive->locateName('word/document.xml') !== false)
                || ($extension === 'xlsx' && $archive->locateName('xl/workbook.xml') !== false)
            );

        $archive->close();
        return $valid;
    }

    private function filename(string $value): string
    {
        $value = basename(str_replace('\\', '/', $value));
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?: 'attachment';

        return function_exists('mb_substr')
            ? mb_substr($value, 0, 500, 'UTF-8')
            : substr($value, 0, 500);
    }

    private function typeTitle(string $type): string
    {
        return $this->references->itemTypes()[$type] ?? $type;
    }

    private function priorityTitle(string $priority): string
    {
        return $this->references->itemPriorities()[$priority] ?? $priority;
    }

    private function eventTitle(string $eventType): string
    {
        return [
            'work_item_created' => 'ایجاد آیتم',
            'work_item_updated' => 'ویرایش آیتم',
            'work_item_archived' => 'بایگانی آیتم',
            'work_comment_added' => 'ثبت دیدگاه',
            'work_checklist_added' => 'افزودن مورد چک‌لیست',
            'work_checklist_toggled' => 'تغییر وضعیت چک‌لیست',
            'work_attachment_uploaded' => 'بارگذاری پیوست',
        ][$eventType] ?? $eventType;
    }

    private function actorDisplayName(array $context, int $userId): string
    {
        foreach (['display_name', 'full_name', 'username'] as $field) {
            $value = trim((string) ($context[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'کاربر #' . $userId;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}

<?php

namespace App\Services;

use App\Repositories\InternalMessageRepository;
use RuntimeException;

class InternalMessageAdministrationService extends BaseService
{
    public function __construct(
        private ?InternalMessageRepository $messages = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->messages ??= new InternalMessageRepository();
        $this->authorization ??= new AuthorizationService();
    }

    public function allowed(int $userId): bool
    {
        return $this->authorization->hasPermission($userId, 'messages.admin.view');
    }

    public function index(int $userId): array
    {
        $this->guard($userId);
        return ['items' => $this->messages->monitor(), 'audit' => $this->messages->auditLog()];
    }

    public function thread(int $userId, string $reference, string $reason): ?array
    {
        $this->guard($userId);
        $settings = $this->messages->settings();
        if (($settings['monitor_reason_required'] ?? '1') === '1' && mb_strlen(trim($reason)) < 5) {
            throw new RuntimeException('message_monitor_reason_required');
        }
        $thread = $this->messages->monitoredThread($reference);
        if ($thread !== null) {
            $this->messages->audit($userId, 'monitor_view', (int) $thread['conversation']['id'], null, null, mb_substr(trim($reason), 0, 1000));
        }
        return $thread;
    }

    public function settings(int $userId): array
    {
        if (!$this->authorization->hasPermission($userId, 'messages.admin.manage')) {
            throw new RuntimeException('forbidden');
        }
        return $this->messages->settings();
    }

    public function saveSettings(int $userId, array $input): void
    {
        $this->settings($userId);
        $allowedExtensions = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','txt'];
        $requested = array_filter(array_map('trim', explode(',', strtolower((string) ($input['attachment_extensions'] ?? '')))));
        $extensions = array_values(array_intersect($allowedExtensions, $requested));
        $this->messages->saveSettings($userId, [
            'enabled' => !empty($input['enabled']) ? '1' : '0',
            'attachments_enabled' => !empty($input['attachments_enabled']) ? '1' : '0',
            'attachment_max_files' => (string) max(1, min(10, (int) ($input['attachment_max_files'] ?? 3))),
            'attachment_max_each_mb' => (string) max(1, min(50, (int) ($input['attachment_max_each_mb'] ?? 10))),
            'attachment_max_total_mb' => (string) max(1, min(100, (int) ($input['attachment_max_total_mb'] ?? 20))),
            'attachment_extensions' => implode(',', $extensions ?: $allowedExtensions),
            'monitor_reason_required' => !empty($input['monitor_reason_required']) ? '1' : '0',
            'audit_retention_days' => (string) max(365, min(3650, (int) ($input['audit_retention_days'] ?? 3650))),
            'login_summary_notification' => '0',
        ]);
    }

    private function guard(int $userId): void
    {
        if (!$this->allowed($userId)) throw new RuntimeException('forbidden');
    }
}

<?php

namespace App\Services\Work;

use App\Repositories\WorkProjectRepository;
use App\Services\BaseService;

class WorkProjectService extends BaseService
{
    public function __construct(private ?WorkProjectRepository $projects = null)
    {
        $this->projects ??= new WorkProjectRepository();
    }

    public function index(array $filters = []): array
    {
        $statusOptions = $this->statusOptions();
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if (function_exists('mb_substr')) {
            $q = mb_substr($q, 0, 120, 'UTF-8');
        } else {
            $q = substr($q, 0, 120);
        }

        if (!array_key_exists($status, $statusOptions)) {
            $status = '';
        }

        $items = $this->projects->index([
            'q' => $q,
            'status' => $status,
        ]);

        foreach ($items as &$item) {
            $item['status_title'] = !empty($item['archived_at']) ? $statusOptions['archived'] : ($statusOptions[$item['status_code']] ?? $item['status_code']);
            $item['visibility_title'] = $this->visibilityTitle((string) ($item['visibility_code'] ?? ''));
        }
        unset($item);

        return [
            'items' => $items,
            'total' => count($items),
            'q' => $q,
            'status' => $status,
            'status_options' => $statusOptions,
        ];
    }

    private function statusOptions(): array
    {
        return [
            '' => 'همه وضعیت‌ها',
            'active' => 'فعال',
            'paused' => 'متوقف',
            'completed' => 'تکمیل‌شده',
            'cancelled' => 'لغوشده',
            'archived' => 'بایگانی‌شده',
        ];
    }

    private function visibilityTitle(string $code): string
    {
        return match ($code) {
            'public' => 'عمومی',
            'organization' => 'سازمانی',
            'members' => 'اعضای پروژه',
            default => 'خصوصی',
        };
    }
}

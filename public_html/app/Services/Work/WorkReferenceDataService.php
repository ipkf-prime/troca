<?php

namespace App\Services\Work;

use App\Repositories\ModuleReferenceRepository;
use App\Services\BaseService;

class WorkReferenceDataService extends BaseService
{
    private array $cache = [];

    public function __construct(private ?ModuleReferenceRepository $references = null)
    {
        $this->references ??= new ModuleReferenceRepository('work.primary');
    }

    public function projectStatuses(): array
    {
        return $this->options('project_status', [
            'active' => 'فعال',
            'paused' => 'متوقف',
            'completed' => 'تکمیل‌شده',
            'cancelled' => 'لغوشده',
        ]);
    }

    public function projectVisibilities(): array
    {
        return $this->options('project_visibility', [
            'private' => 'خصوصی',
            'members' => 'اعضای پروژه',
            'organization' => 'سازمانی',
            'public' => 'عمومی',
        ]);
    }

    public function itemPriorities(): array
    {
        return $this->options('item_priority', [
            'low' => 'کم',
            'normal' => 'عادی',
            'high' => 'زیاد',
            'urgent' => 'فوری',
        ]);
    }

    public function itemTypes(): array
    {
        $options = $this->options('item_type', [
            'work' => 'کار',
            'milestone' => 'نقطه عطف',
            'task' => 'تسک',
            'subtask' => 'زیرتسک',
        ]);

        // Structural Work item codes are part of validation and hierarchy rules.
        // Never let an incomplete settings row remove one of these codes at runtime.
        foreach ([
            'work' => 'کار',
            'milestone' => 'نقطه عطف',
            'task' => 'تسک',
            'subtask' => 'زیرتسک',
        ] as $code => $fallbackTitle) {
            $options[$code] ??= $fallbackTitle;
        }

        return $options;
    }

    private function options(string $groupCode, array $fallback): array
    {
        if (isset($this->cache[$groupCode])) {
            return $this->cache[$groupCode];
        }

        try {
            $resolved = $this->references->options('work', $groupCode);
        } catch (\Throwable) {
            $resolved = [];
        }

        return $this->cache[$groupCode] = $resolved !== [] ? $resolved : $fallback;
    }
}

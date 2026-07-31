<?php

namespace App\Services\Work;

use App\Repositories\WorkMyItemsRepository;
use App\Services\AuthService;
use App\Services\BaseService;

class WorkMyItemsService extends BaseService
{
    public function __construct(
        private ?WorkMyItemsRepository $items = null,
        private ?AuthService $auth = null
    ) {
        $this->items ??= new WorkMyItemsRepository();
        $this->auth ??= new AuthService();
    }

    public function view(array $filters = []): array
    {
        $scopeOptions = $this->scopeOptions();
        $scope = trim((string) ($filters['scope'] ?? 'open'));

        if (!array_key_exists($scope, $scopeOptions)) {
            $scope = 'open';
        }

        $query = $this->limit(trim((string) ($filters['q'] ?? '')), 120);
        $userId = $this->auth->currentUserId();

        if ($userId === null) {
            return [
                'scope' => $scope,
                'q' => $query,
                'scope_options' => $scopeOptions,
                'counts' => array_fill_keys(array_keys($scopeOptions), 0),
                'items' => [],
                'total' => 0,
            ];
        }

        $userReference = 'user:' . $userId;
        $rows = $this->items->items($userReference, $scope, $query);

        foreach ($rows as &$row) {
            $row['type_title'] = $this->typeOptions()[(string) $row['item_type']]
                ?? (string) $row['item_type'];
            $row['priority_title'] = $this->priorityOptions()[(string) $row['priority_code']]
                ?? (string) $row['priority_code'];
            $row['is_overdue'] = (int) $row['is_closed'] === 0
                && !empty($row['due_at'])
                && strtotime((string) $row['due_at']) < time();
        }
        unset($row);

        return [
            'scope' => $scope,
            'q' => $query,
            'scope_options' => $scopeOptions,
            'counts' => $this->items->counts($userReference),
            'items' => $rows,
            'total' => count($rows),
        ];
    }

    private function scopeOptions(): array
    {
        return [
            'open' => 'کارهای باز من',
            'today' => 'سررسید امروز',
            'overdue' => 'عقب‌افتاده',
            'unassigned' => 'بدون مسئول',
            'completed' => 'تکمیل‌شده',
        ];
    }

    private function typeOptions(): array
    {
        return [
            'work' => 'کار',
            'milestone' => 'نقطه عطف',
            'task' => 'تسک',
            'subtask' => 'زیرتسک',
        ];
    }

    private function priorityOptions(): array
    {
        return [
            'low' => 'کم',
            'normal' => 'عادی',
            'high' => 'زیاد',
            'urgent' => 'فوری',
        ];
    }

    private function limit(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}

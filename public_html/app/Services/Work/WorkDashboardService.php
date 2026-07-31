<?php

namespace App\Services\Work;

use App\Repositories\WorkDashboardRepository;
use App\Services\BaseService;

class WorkDashboardService extends BaseService
{
    public function __construct(
        private ?WorkDashboardRepository $dashboard = null,
        private ?WorkMyItemsService $myItems = null,
        private ?WorkReferenceDataService $references = null
    ) {
        $this->dashboard ??= new WorkDashboardRepository();
        $this->myItems ??= new WorkMyItemsService();
        $this->references ??= new WorkReferenceDataService();
    }

    public function view(?array $filters = null): array
    {
        $filters ??= is_array($_GET ?? null) ? $_GET : [];
        $recentTasks = $this->dashboard->recentTasks();
        $priorities = $this->references->itemPriorities();

        foreach ($recentTasks as &$task) {
            $code = (string) ($task['priority'] ?? '');
            $task['priority'] = $priorities[$code] ?? $code;
        }
        unset($task);

        return [
            'summary' => $this->dashboard->summary(),
            'recent_tasks' => $recentTasks,
            'my_work' => $this->myItems->view($filters),
        ];
    }
}

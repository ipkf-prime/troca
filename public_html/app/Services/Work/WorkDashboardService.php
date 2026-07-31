<?php

namespace App\Services\Work;

use App\Repositories\WorkDashboardRepository;
use App\Services\BaseService;

class WorkDashboardService extends BaseService
{
    public function __construct(
        private ?WorkDashboardRepository $dashboard = null,
        private ?WorkMyItemsService $myItems = null
    ) {
        $this->dashboard ??= new WorkDashboardRepository();
        $this->myItems ??= new WorkMyItemsService();
    }

    public function view(?array $filters = null): array
    {
        $filters ??= is_array($_GET ?? null) ? $_GET : [];

        return [
            'summary' => $this->dashboard->summary(),
            'recent_tasks' => $this->dashboard->recentTasks(),
            'my_work' => $this->myItems->view($filters),
        ];
    }
}

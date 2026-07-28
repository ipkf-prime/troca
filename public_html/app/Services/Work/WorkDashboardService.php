<?php

namespace App\Services\Work;

use App\Repositories\WorkDashboardRepository;
use App\Services\BaseService;

class WorkDashboardService extends BaseService
{
    public function __construct(private ?WorkDashboardRepository $dashboard = null)
    {
        $this->dashboard ??= new WorkDashboardRepository();
    }

    public function view(): array
    {
        return ['summary' => $this->dashboard->summary(), 'recent_tasks' => $this->dashboard->recentTasks()];
    }
}

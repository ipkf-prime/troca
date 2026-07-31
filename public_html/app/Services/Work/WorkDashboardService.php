<?php

namespace App\Services\Work;

use App\Repositories\WorkDashboardRepository;
use App\Services\BaseService;

class WorkDashboardService extends BaseService
{
    public function __construct(
        private ?WorkDashboardRepository $dashboard = null,
        private ?WorkMyItemsService $myItems = null,
        private ?WorkReferenceDataService $references = null,
        private ?WorkProjectAccessService $access = null
    ) {
        $this->dashboard ??= new WorkDashboardRepository();
        $this->myItems ??= new WorkMyItemsService();
        $this->references ??= new WorkReferenceDataService();
        $this->access ??= new WorkProjectAccessService();
    }

    public function view(?array $filters = null, ?int $userId = null): array
    {
        $filters ??= is_array($_GET ?? null) ? $_GET : [];
        $allProjects = $userId === null || $this->access->isPlatformAdmin($userId);
        $userReference = $userId === null ? null : 'user:' . $userId;

        $recentTasks = $this->dashboard->recentTasks(
            8,
            $userReference,
            $allProjects
        );
        $priorities = $this->references->itemPriorities();

        foreach ($recentTasks as &$task) {
            $code = (string) ($task['priority'] ?? '');
            $task['priority'] = $priorities[$code] ?? $code;
        }
        unset($task);

        return [
            'summary' => $this->dashboard->summary($userReference, $allProjects),
            'recent_tasks' => $recentTasks,
            'my_work' => $this->myItems->view($filters),
            'access_scope' => $allProjects ? 'all' : 'project_membership',
        ];
    }
}

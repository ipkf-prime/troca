<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkDashboardRepository
{
    private PDO $db;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->db = ($connections ?? new ConnectionResolver())->resolve('work.primary');
    }

    public function summary(): array
    {
        $scalar = fn (string $sql): int => (int) $this->db->query($sql)->fetchColumn();

        return [
            'projects' => $scalar("SELECT COUNT(*) FROM work_projects WHERE status = 'active'"),
            'works' => $scalar("SELECT COUNT(*) FROM work_items WHERE status NOT IN ('closed', 'cancelled')"),
            'open_tasks' => $scalar("SELECT COUNT(*) FROM work_tasks WHERE status NOT IN ('done', 'cancelled')"),
            'overdue_tasks' => $scalar("SELECT COUNT(*) FROM work_tasks WHERE due_at < UTC_TIMESTAMP() AND status NOT IN ('done', 'cancelled')"),
        ];
    }

    public function recentTasks(int $limit = 8): array
    {
        $statement = $this->db->prepare("
            SELECT t.title, t.status, t.priority, t.progress_percent,
                   w.title AS work_title, p.title AS project_title
            FROM work_tasks t
            INNER JOIN work_items w ON w.id = t.work_id
            INNER JOIN work_projects p ON p.id = w.project_id
            ORDER BY t.updated_at DESC, t.id DESC
            LIMIT ?
        ");
        $statement->bindValue(1, max(1, min(20, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}

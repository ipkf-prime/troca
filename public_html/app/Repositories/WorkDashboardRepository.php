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
            'projects' => $scalar("SELECT COUNT(*) FROM work_projects WHERE status_code = 'active' AND archived_at IS NULL"),
            'works' => $scalar("SELECT COUNT(*) FROM work_items wi INNER JOIN work_statuses ws ON ws.id = wi.status_id WHERE wi.item_type = 'work' AND wi.archived_at IS NULL AND ws.is_closed = 0"),
            'open_tasks' => $scalar("SELECT COUNT(*) FROM work_items wi INNER JOIN work_statuses ws ON ws.id = wi.status_id WHERE wi.item_type IN ('task', 'subtask') AND wi.archived_at IS NULL AND ws.is_closed = 0"),
            'overdue_tasks' => $scalar("SELECT COUNT(*) FROM work_items wi INNER JOIN work_statuses ws ON ws.id = wi.status_id WHERE wi.item_type IN ('task', 'subtask') AND wi.due_at < UTC_TIMESTAMP() AND wi.archived_at IS NULL AND ws.is_closed = 0"),
        ];
    }

    public function recentTasks(int $limit = 8): array
    {
        $statement = $this->db->prepare("
            SELECT t.title, t.priority_code AS priority, t.progress_percent,
                   ws.code AS status, ws.title AS status_title,
                   COALESCE(parent.title, '-') AS work_title,
                   p.title AS project_title
            FROM work_items t
            INNER JOIN work_statuses ws ON ws.id = t.status_id
            INNER JOIN work_projects p ON p.id = t.project_id
            LEFT JOIN work_items parent ON parent.id = t.parent_id
            WHERE t.item_type IN ('task', 'subtask')
              AND t.archived_at IS NULL
            ORDER BY t.updated_at DESC, t.id DESC
            LIMIT ?
        ");
        $statement->bindValue(1, max(1, min(20, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

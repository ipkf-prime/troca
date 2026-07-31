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

    public function summary(?string $userReference = null, bool $allProjects = true): array
    {
        [$projectScope, $scopeParameters] = $this->projectScope('p', $userReference, $allProjects);

        return [
            'projects' => $this->scalar(
                "SELECT COUNT(*)
                 FROM work_projects p
                 WHERE p.status_code = 'active'
                   AND p.archived_at IS NULL
                   AND {$projectScope}",
                $scopeParameters
            ),
            'works' => $this->scalar(
                "SELECT COUNT(*)
                 FROM work_items wi
                 INNER JOIN work_statuses ws ON ws.id = wi.status_id
                 INNER JOIN work_projects p ON p.id = wi.project_id
                 WHERE wi.item_type = 'work'
                   AND wi.archived_at IS NULL
                   AND p.archived_at IS NULL
                   AND ws.is_closed = 0
                   AND {$projectScope}",
                $scopeParameters
            ),
            'open_tasks' => $this->scalar(
                "SELECT COUNT(*)
                 FROM work_items wi
                 INNER JOIN work_statuses ws ON ws.id = wi.status_id
                 INNER JOIN work_projects p ON p.id = wi.project_id
                 WHERE wi.item_type IN ('task', 'subtask')
                   AND wi.archived_at IS NULL
                   AND p.archived_at IS NULL
                   AND ws.is_closed = 0
                   AND {$projectScope}",
                $scopeParameters
            ),
            'overdue_tasks' => $this->scalar(
                "SELECT COUNT(*)
                 FROM work_items wi
                 INNER JOIN work_statuses ws ON ws.id = wi.status_id
                 INNER JOIN work_projects p ON p.id = wi.project_id
                 WHERE wi.item_type IN ('task', 'subtask')
                   AND wi.due_at IS NOT NULL
                   AND wi.due_at < UTC_TIMESTAMP()
                   AND wi.archived_at IS NULL
                   AND p.archived_at IS NULL
                   AND ws.is_closed = 0
                   AND {$projectScope}",
                $scopeParameters
            ),
            'statuses' => $this->scalar(
                "SELECT COUNT(*) FROM work_statuses WHERE is_active = 1",
                []
            ),
        ];
    }

    public function recentTasks(
        int $limit = 8,
        ?string $userReference = null,
        bool $allProjects = true
    ): array {
        [$projectScope, $scopeParameters] = $this->projectScope('p', $userReference, $allProjects);

        $statement = $this->db->prepare("
            SELECT
                t.public_reference,
                t.title,
                t.priority_code AS priority,
                t.progress_percent,
                ws.code AS status,
                ws.title AS status_title,
                COALESCE(parent.title, '-') AS work_title,
                p.public_reference AS project_reference,
                p.title AS project_title
            FROM work_items t
            INNER JOIN work_statuses ws ON ws.id = t.status_id
            INNER JOIN work_projects p ON p.id = t.project_id
            LEFT JOIN work_items parent ON parent.id = t.parent_id
            WHERE t.item_type IN ('task', 'subtask')
              AND t.archived_at IS NULL
              AND p.archived_at IS NULL
              AND {$projectScope}
            ORDER BY t.updated_at DESC, t.id DESC
            LIMIT ?
        ");

        $position = 1;
        foreach ($scopeParameters as $parameter) {
            $statement->bindValue($position++, $parameter, PDO::PARAM_STR);
        }
        $statement->bindValue($position, max(1, min(20, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function projectScope(
        string $alias,
        ?string $userReference,
        bool $allProjects
    ): array {
        if ($allProjects || $userReference === null || trim($userReference) === '') {
            return ['1 = 1', []];
        }

        return [
            "(
                {$alias}.visibility_code = 'public'
                OR {$alias}.owner_user_reference = ?
                OR EXISTS (
                    SELECT 1
                    FROM work_project_members dashboard_pm
                    WHERE dashboard_pm.project_id = {$alias}.id
                      AND dashboard_pm.user_reference = ?
                      AND dashboard_pm.left_at IS NULL
                )
            )",
            [$userReference, $userReference],
        ];
    }

    private function scalar(string $sql, array $parameters): int
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }
}

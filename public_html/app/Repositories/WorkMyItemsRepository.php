<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkMyItemsRepository
{
    private PDO $db;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->db = ($connections ?? new ConnectionResolver())->resolve('work.primary');
    }

    public function counts(string $userReference): array
    {
        $counts = [];

        foreach (['open', 'today', 'overdue', 'unassigned', 'completed'] as $scope) {
            $counts[$scope] = $this->countForScope($userReference, $scope);
        }

        return $counts;
    }

    public function items(
        string $userReference,
        string $scope,
        string $query = '',
        int $limit = 150
    ): array {
        [$where, $parameters] = $this->conditions($userReference, $scope, $query);
        $limit = max(1, min(300, $limit));

        $orderBy = $scope === 'completed'
            ? 'wi.completed_at DESC, wi.updated_at DESC, wi.id DESC'
            : "CASE WHEN wi.due_at IS NULL THEN 1 ELSE 0 END,
               wi.due_at ASC,
               FIELD(wi.priority_code, 'urgent', 'high', 'normal', 'low'),
               wi.updated_at DESC,
               wi.id DESC";

        $statement = $this->db->prepare("
            SELECT
                wi.id,
                wi.public_reference,
                wi.item_type,
                wi.sequence_number,
                wi.title,
                wi.priority_code,
                wi.progress_percent,
                wi.due_at,
                wi.completed_at,
                wi.updated_at,
                ws.code AS status_code,
                ws.title AS status_title,
                ws.is_closed,
                p.public_reference AS project_reference,
                p.title AS project_title,
                parent.title AS parent_title,
                assignee.user_reference AS assignee_reference,
                assignee.display_name_snapshot AS assignee_name
            FROM work_items wi
            INNER JOIN work_statuses ws ON ws.id = wi.status_id
            INNER JOIN work_projects p ON p.id = wi.project_id
            LEFT JOIN work_items parent ON parent.id = wi.parent_id
            LEFT JOIN work_item_assignees assignee
              ON assignee.id = (
                    SELECT a.id
                    FROM work_item_assignees a
                    WHERE a.work_item_id = wi.id
                      AND a.assignment_role = 'responsible'
                      AND a.unassigned_at IS NULL
                    ORDER BY a.id DESC
                    LIMIT 1
              )
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderBy}
            LIMIT {$limit}
        ");
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countForScope(string $userReference, string $scope): int
    {
        [$where, $parameters] = $this->conditions($userReference, $scope, '');

        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM work_items wi
            INNER JOIN work_statuses ws ON ws.id = wi.status_id
            INNER JOIN work_projects p ON p.id = wi.project_id
            LEFT JOIN work_item_assignees assignee
              ON assignee.id = (
                    SELECT a.id
                    FROM work_item_assignees a
                    WHERE a.work_item_id = wi.id
                      AND a.assignment_role = 'responsible'
                      AND a.unassigned_at IS NULL
                    ORDER BY a.id DESC
                    LIMIT 1
              )
            WHERE " . implode(' AND ', $where) . "
        ");
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    private function conditions(string $userReference, string $scope, string $query): array
    {
        $where = [
            'wi.archived_at IS NULL',
            'p.archived_at IS NULL',
        ];
        $parameters = [];

        if ($scope === 'unassigned') {
            $where[] = 'assignee.id IS NULL';
            $where[] = 'ws.is_closed = 0';
            $where[] = "EXISTS (
                SELECT 1
                FROM work_project_members pm
                WHERE pm.project_id = wi.project_id
                  AND pm.user_reference = ?
                  AND pm.left_at IS NULL
            )";
            $parameters[] = $userReference;
        } else {
            $where[] = 'assignee.user_reference = ?';
            $parameters[] = $userReference;

            if ($scope === 'completed') {
                $where[] = 'ws.is_closed = 1';
            } else {
                $where[] = 'ws.is_closed = 0';

                if ($scope === 'today') {
                    $where[] = 'wi.due_at >= UTC_DATE()';
                    $where[] = 'wi.due_at < UTC_DATE() + INTERVAL 1 DAY';
                } elseif ($scope === 'overdue') {
                    $where[] = 'wi.due_at IS NOT NULL';
                    $where[] = 'wi.due_at < UTC_TIMESTAMP()';
                }
            }
        }

        $query = trim($query);
        if ($query !== '') {
            $where[] = '(wi.title LIKE ? OR p.title LIKE ? OR wi.public_reference LIKE ?)';
            $needle = '%' . $query . '%';
            $parameters[] = $needle;
            $parameters[] = $needle;
            $parameters[] = $needle;
        }

        return [$where, $parameters];
    }
}

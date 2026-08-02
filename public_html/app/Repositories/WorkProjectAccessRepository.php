<?php

namespace App\Repositories;

use App\Support\AdminTableSort;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkProjectAccessRepository
{
    private PDO $db;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->db = ($connections ?? new ConnectionResolver())->resolve('work.primary');
    }

    public function project(string $publicReference, string $userReference): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                p.id,
                p.public_reference,
                p.code,
                p.title,
                p.owner_user_reference,
                p.organization_reference,
                p.organization_snapshot,
                p.status_code,
                p.visibility_code,
                p.archived_at,
                COALESCE(
                    (
                        SELECT pm.role_code
                        FROM work_project_members pm
                        WHERE pm.project_id = p.id
                          AND pm.user_reference = ?
                          AND pm.left_at IS NULL
                        ORDER BY pm.id DESC
                        LIMIT 1
                    ),
                    CASE WHEN p.owner_user_reference = ? THEN 'owner' ELSE NULL END
                ) AS my_role_code
            FROM work_projects p
            WHERE p.public_reference = ?
            LIMIT 1
        ");
        $statement->execute([$userReference, $userReference, $publicReference]);
        $project = $statement->fetch(PDO::FETCH_ASSOC);

        return $project ?: null;
    }

    public function item(string $projectReference, string $itemReference, string $userReference): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                wi.id,
                wi.public_reference,
                wi.created_by_user_reference,
                wi.archived_at AS item_archived_at,
                p.id AS project_id,
                p.public_reference AS project_reference,
                p.owner_user_reference,
                p.visibility_code,
                p.archived_at AS project_archived_at,
                COALESCE(
                    (
                        SELECT pm.role_code
                        FROM work_project_members pm
                        WHERE pm.project_id = p.id
                          AND pm.user_reference = ?
                          AND pm.left_at IS NULL
                        ORDER BY pm.id DESC
                        LIMIT 1
                    ),
                    CASE WHEN p.owner_user_reference = ? THEN 'owner' ELSE NULL END
                ) AS my_role_code,
                (
                    SELECT a.user_reference
                    FROM work_item_assignees a
                    WHERE a.work_item_id = wi.id
                      AND a.assignment_role = 'responsible'
                      AND a.unassigned_at IS NULL
                    ORDER BY a.id DESC
                    LIMIT 1
                ) AS assignee_reference
            FROM work_items wi
            INNER JOIN work_projects p ON p.id = wi.project_id
            WHERE p.public_reference = ?
              AND wi.public_reference = ?
            LIMIT 1
        ");
        $statement->execute([
            $userReference,
            $userReference,
            $projectReference,
            $itemReference,
        ]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function index(
        array $filters,
        string $userReference,
        bool $allProjects
    ): array {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $sort = AdminTableSort::resolve(
            $filters,
            [
                'title' => 'p.title',
                'status' => 'p.status_code',
                'visibility' => 'p.visibility_code',
                'owner' => 'owner_display_name',
                'members' => 'member_count',
                'items' => 'item_count',
                'open_items' => 'open_item_count',
                'created_at' => 'p.created_at',
                'target_date' => 'p.target_date',
                'updated_at' => 'p.updated_at',
            ],
            'updated_at',
            'desc'
        );
        $where = [];
        $parameters = [];

        if (!$allProjects) {
            $where[] = "(
                p.visibility_code = 'public'
                OR p.owner_user_reference = ?
                OR EXISTS (
                    SELECT 1
                    FROM work_project_members scope_pm
                    WHERE scope_pm.project_id = p.id
                      AND scope_pm.user_reference = ?
                      AND scope_pm.left_at IS NULL
                )
            )";
            $parameters[] = $userReference;
            $parameters[] = $userReference;
        }

        if ($status === 'archived') {
            $where[] = 'p.archived_at IS NOT NULL';
        } elseif ($status === 'current') {
            $where[] = 'p.archived_at IS NULL';
        } elseif ($status !== '') {
            $where[] = 'p.archived_at IS NULL';
            $where[] = 'p.status_code = ?';
            $parameters[] = $status;
        }

        if ($q !== '') {
            $where[] = '(
                p.title LIKE ?
                OR p.code LIKE ?
                OR p.public_reference LIKE ?
                OR p.organization_snapshot LIKE ?
            )';
            $needle = '%' . $q . '%';
            array_push($parameters, $needle, $needle, $needle, $needle);
        }

        $whereSql = $where === [] ? '1 = 1' : implode(' AND ', $where);

        $statement = $this->db->prepare("
            SELECT
                p.id,
                p.public_reference,
                p.code,
                p.title,
                p.description,
                p.owner_user_reference,
                MAX(CASE
                    WHEN pm.user_reference = p.owner_user_reference
                     AND pm.left_at IS NULL
                    THEN pm.display_name_snapshot
                END) AS owner_display_name,
                p.organization_reference,
                p.organization_snapshot,
                p.start_date,
                p.target_date,
                p.status_code,
                p.visibility_code,
                p.archived_at,
                p.created_at,
                p.updated_at,
                COUNT(DISTINCT CASE WHEN pm.left_at IS NULL THEN pm.id END) AS member_count,
                COUNT(DISTINCT CASE WHEN wi.archived_at IS NULL THEN wi.id END) AS item_count,
                COUNT(DISTINCT CASE
                    WHEN wi.archived_at IS NULL AND ws.is_closed = 0 THEN wi.id
                END) AS open_item_count,
                COALESCE(
                    (
                        SELECT scope_role.role_code
                        FROM work_project_members scope_role
                        WHERE scope_role.project_id = p.id
                          AND scope_role.user_reference = ?
                          AND scope_role.left_at IS NULL
                        ORDER BY scope_role.id DESC
                        LIMIT 1
                    ),
                    CASE WHEN p.owner_user_reference = ? THEN 'owner' ELSE NULL END
                ) AS my_role_code
            FROM work_projects p
            LEFT JOIN work_project_members pm ON pm.project_id = p.id
            LEFT JOIN work_items wi ON wi.project_id = p.id
            LEFT JOIN work_statuses ws ON ws.id = wi.status_id
            WHERE {$whereSql}
            GROUP BY
                p.id, p.public_reference, p.code, p.title, p.description,
                p.owner_user_reference, p.organization_reference, p.organization_snapshot,
                p.start_date, p.target_date, p.status_code, p.visibility_code,
                p.archived_at, p.created_at, p.updated_at
            ORDER BY {$sort['sql']} {$sort['dir']}, p.id DESC
            LIMIT 100
        ");

        $statement->execute(array_merge([$userReference, $userReference], $parameters));

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

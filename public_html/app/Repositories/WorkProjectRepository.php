<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkProjectRepository
{
    private PDO $db;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->db = ($connections ?? new ConnectionResolver())->resolve('work.primary');
    }

    public function index(array $filters = []): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $where = [];
        $parameters = [];

        if ($status === 'archived') {
            $where[] = 'p.archived_at IS NOT NULL';
        } else {
            $where[] = 'p.archived_at IS NULL';

            if ($status !== '') {
                $where[] = 'p.status_code = ?';
                $parameters[] = $status;
            }
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

        $statement = $this->db->prepare("
            SELECT
                p.id,
                p.public_reference,
                p.code,
                p.title,
                p.description,
                p.owner_user_reference,
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
                END) AS open_item_count
            FROM work_projects p
            LEFT JOIN work_project_members pm
              ON pm.project_id = p.id
            LEFT JOIN work_items wi
              ON wi.project_id = p.id
            LEFT JOIN work_statuses ws
              ON ws.id = wi.status_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY
                p.id, p.public_reference, p.code, p.title, p.description,
                p.owner_user_reference, p.organization_reference, p.organization_snapshot,
                p.start_date, p.target_date, p.status_code, p.visibility_code,
                p.archived_at, p.created_at, p.updated_at
            ORDER BY p.updated_at DESC, p.id DESC
            LIMIT 100
        ");
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

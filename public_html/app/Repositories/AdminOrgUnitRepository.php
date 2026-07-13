<?php

namespace App\Repositories;

use PDO;

class AdminOrgUnitRepository extends BaseRepository
{
    public function paginate(string $query, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $where = $this->searchWhere($query);
        $params = $this->searchParams($query);

        $totalStatement = $this->connection()->prepare("
            SELECT COUNT(DISTINCT org_units.id)
            FROM org_units
            LEFT JOIN org_units AS parent_units
              ON parent_units.id = org_units.parent_id
             AND parent_units.deleted_at IS NULL
            {$where}
        ");

        foreach ($params as $key => $value) {
            $totalStatement->bindValue($key, $value);
        }

        $totalStatement->execute();
        $total = (int) $totalStatement->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $statement = $this->connection()->prepare("
            SELECT
                org_units.id,
                org_units.code,
                org_units.title,
                org_units.type,
                org_units.depth,
                org_units.status,
                org_units.sort_order,
                org_units.created_at,
                parent_units.title AS parent_title
            FROM org_units
            LEFT JOIN org_units AS parent_units
              ON parent_units.id = org_units.parent_id
             AND parent_units.deleted_at IS NULL
            {$where}
            ORDER BY org_units.sort_order ASC, org_units.id ASC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
        ];
    }

    private function searchWhere(string $query): string
    {
        if ($query === '') {
            return 'WHERE org_units.deleted_at IS NULL';
        }

        return "
            WHERE org_units.deleted_at IS NULL
              AND (
                   org_units.title LIKE :search_title
                OR org_units.code LIKE :search_code
                OR org_units.type LIKE :search_type
                OR parent_units.title LIKE :search_parent_title
              )
        ";
    }

    private function searchParams(string $query): array
    {
        if ($query === '') {
            return [];
        }

        $value = '%' . $query . '%';

        return [
            ':search_title' => $value,
            ':search_code' => $value,
            ':search_type' => $value,
            ':search_parent_title' => $value,
        ];
    }
}

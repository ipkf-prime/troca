<?php

namespace App\Repositories;

use PDO;

class AdminPositionRepository extends BaseRepository
{
    public function paginate(string $query, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $where = $this->searchWhere($query);
        $params = $this->searchParams($query);

        $totalStatement = $this->connection()->prepare("
            SELECT COUNT(DISTINCT positions.id)
            FROM positions
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
                positions.id,
                positions.code,
                positions.title,
                positions.description,
                positions.status,
                positions.sort_order,
                positions.created_at
            FROM positions
            {$where}
            ORDER BY positions.sort_order ASC, positions.id ASC
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
            return 'WHERE 1 = 1';
        }

        return "
            WHERE (
                   positions.title LIKE :search_title
                OR positions.code LIKE :search_code
                OR positions.description LIKE :search_description
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
            ':search_description' => $value,
        ];
    }
}

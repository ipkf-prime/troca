<?php

namespace App\Repositories;

use PDO;

class AdminUserRepository extends BaseRepository
{
    public function paginate(string $query, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $where = $this->searchWhere($query);
        $params = $this->searchParams($query);

        $totalStatement = $this->connection()->prepare("
            SELECT COUNT(DISTINCT users.id)
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
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
                users.id,
                users.username,
                COALESCE(users.mobile, persons.mobile) AS mobile,
                COALESCE(users.email, persons.email) AS email,
                users.status,
                users.created_at,
                persons.full_name,
                primary_org.title AS primary_org_unit_title,
                COUNT(DISTINCT roles.id) AS active_role_count,
                GROUP_CONCAT(DISTINCT roles.title ORDER BY roles.id ASC SEPARATOR '، ') AS active_role_titles
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            LEFT JOIN user_role_assignments AS active_assignments
              ON active_assignments.user_id = users.id
             AND active_assignments.is_active = 1
             AND (active_assignments.starts_at IS NULL OR active_assignments.starts_at <= CURRENT_TIMESTAMP)
             AND (active_assignments.ends_at IS NULL OR active_assignments.ends_at >= CURRENT_TIMESTAMP)
            LEFT JOIN roles
              ON roles.id = active_assignments.role_id
             AND roles.is_active = 1
            LEFT JOIN user_org_assignments AS primary_user_org
              ON primary_user_org.user_id = users.id
             AND primary_user_org.is_primary = 1
             AND primary_user_org.status = 'active'
            LEFT JOIN org_units AS primary_org
              ON primary_org.id = primary_user_org.org_unit_id
            {$where}
            GROUP BY
                users.id,
                users.username,
                users.mobile,
                users.email,
                users.status,
                users.created_at,
                persons.mobile,
                persons.email,
                persons.full_name,
                primary_org.title
            ORDER BY users.id ASC
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
            return 'WHERE users.deleted_at IS NULL';
        }

        return "
            WHERE users.deleted_at IS NULL
              AND (
                   users.username LIKE :search_username
                OR users.mobile LIKE :search_user_mobile
                OR users.email LIKE :search_user_email
                OR persons.mobile LIKE :search_person_mobile
                OR persons.email LIKE :search_person_email
                OR persons.full_name LIKE :search_full_name
                OR persons.first_name LIKE :search_first_name
                OR persons.last_name LIKE :search_last_name
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
            ':search_username' => $value,
            ':search_user_mobile' => $value,
            ':search_user_email' => $value,
            ':search_person_mobile' => $value,
            ':search_person_email' => $value,
            ':search_full_name' => $value,
            ':search_first_name' => $value,
            ':search_last_name' => $value,
        ];
    }
}

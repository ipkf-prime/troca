<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;

class AdminUserListRepository extends BaseRepository
{
    private const SORT_COLUMNS = [
        'name' => "COALESCE(NULLIF(persons.full_name, ''), users.username)",
        'username' => 'users.username',
        'mobile' => 'COALESCE(users.mobile, persons.mobile)',
        'email' => 'COALESCE(users.email, persons.email)',
        'status' => 'users.status',
        'role' => 'COALESCE(active_role_summary.highest_role_priority, -1)',
        'org_unit' => "COALESCE(primary_org.title, '')",
        'created_at' => 'users.created_at',
    ];

    public function paginate(
        string $query,
        int $page,
        int $perPage,
        string $sort,
        string $direction
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $sortSql = self::SORT_COLUMNS[$sort]
            ?? self::SORT_COLUMNS['created_at'];
        $directionSql = strtolower($direction) === 'asc'
            ? 'ASC'
            : 'DESC';
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

        $rolePriority = Database::columnExists(
            'roles',
            'priority'
        ) ? 'active_roles.priority' : '100';

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
                COALESCE(
                    active_role_summary.active_role_count,
                    0
                ) AS active_role_count,
                active_role_summary.highest_role_title,
                active_role_summary.highest_role_priority
            FROM users
            LEFT JOIN persons
              ON persons.id = users.person_id
            LEFT JOIN (
                SELECT
                    active_assignments.user_id,
                    COUNT(DISTINCT active_roles.id)
                        AS active_role_count,
                    SUBSTRING_INDEX(
                        GROUP_CONCAT(
                            active_roles.title
                            ORDER BY
                                {$rolePriority} DESC,
                                CASE
                                    WHEN active_roles.code = 'user'
                                    THEN 1
                                    ELSE 0
                                END ASC,
                                active_roles.id ASC
                            SEPARATOR '||'
                        ),
                        '||',
                        1
                    ) AS highest_role_title,
                    MAX({$rolePriority})
                        AS highest_role_priority
                FROM user_role_assignments
                    AS active_assignments
                INNER JOIN roles AS active_roles
                  ON active_roles.id =
                     active_assignments.role_id
                 AND active_roles.is_active = 1
                WHERE active_assignments.is_active = 1
                  AND (
                      active_assignments.starts_at IS NULL
                      OR active_assignments.starts_at
                         <= CURRENT_TIMESTAMP
                  )
                  AND (
                      active_assignments.ends_at IS NULL
                      OR active_assignments.ends_at
                         >= CURRENT_TIMESTAMP
                  )
                GROUP BY active_assignments.user_id
            ) AS active_role_summary
              ON active_role_summary.user_id = users.id
            LEFT JOIN user_org_assignments
                AS primary_user_org
              ON primary_user_org.user_id = users.id
             AND primary_user_org.is_primary = 1
             AND primary_user_org.status = 'active'
            LEFT JOIN org_units AS primary_org
              ON primary_org.id =
                 primary_user_org.org_unit_id
             AND primary_org.deleted_at IS NULL
            {$where}
            ORDER BY
                {$sortSql} {$directionSql},
                users.id {$directionSql}
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );
        $statement->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );
        $statement->execute();

        return [
            'items' => $statement->fetchAll() ?: [],
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

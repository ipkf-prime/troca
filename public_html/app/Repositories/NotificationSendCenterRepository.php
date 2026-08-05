<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;

class NotificationSendCenterRepository extends BaseRepository
{
    public function recipientOptions(): array
    {
        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'username' => (string) (
                    $row['username'] ?? ''
                ),
                'organization_title' => (string) (
                    $row['organization_title'] ?? ''
                ),
                'city_title' => (string) (
                    $row['city_title'] ?? ''
                ),
                'role_titles' => (string) (
                    $row['role_titles'] ?? ''
                ),
                'has_email' => trim((string) (
                    $row['email_destination'] ?? ''
                )) !== '',
                'has_sms' => trim((string) (
                    $row['sms_destination'] ?? ''
                )) !== '',
                'has_messenger' => trim((string) (
                    $row['messenger_destination'] ?? ''
                )) !== '',
            ],
            $this->recipientRows()
        );
    }

    public function destinationsForUsers(
        array $userIds
    ): array {
        $userIds = array_values(array_unique(
            array_filter(
                array_map('intval', $userIds),
                static fn (int $id): bool => $id > 0
            )
        ));

        if ($userIds === []) {
            return [];
        }

        return $this->recipientRows($userIds);
    }

    private function recipientRows(
        array $userIds = []
    ): array {
        $cityJoin = '';
        $cityTitle = "''";

        if (
            Database::tableExists('cities')
            && Database::columnExists(
                'persons',
                'city_id'
            )
        ) {
            foreach (
                ['title', 'title_fa', 'name']
                as $column
            ) {
                if (Database::columnExists(
                    'cities',
                    $column
                )) {
                    $cityJoin =
                        'LEFT JOIN cities '
                        . 'ON cities.id = persons.city_id';
                    $cityTitle = "COALESCE(
                        NULLIF(cities.{$column}, ''),
                        ''
                    )";
                    break;
                }
            }
        }

        $messenger = "''";

        if (Database::tableExists(
            'notification_messenger_bindings'
        )) {
            $messenger = "COALESCE((
                SELECT bindings.chat_id
                FROM notification_messenger_bindings
                    AS bindings
                WHERE bindings.user_id = users.id
                  AND bindings.status_code = 'active'
                ORDER BY
                    bindings.verified_at DESC,
                    bindings.id DESC
                LIMIT 1
            ), '')";
        }

        $roleTitles = "''";

        if (
            Database::tableExists(
                'user_role_assignments'
            )
            && Database::tableExists('roles')
        ) {
            $roleTitles = "COALESCE((
                SELECT GROUP_CONCAT(
                    DISTINCT roles.title
                    ORDER BY roles.priority ASC,
                        roles.id ASC
                    SEPARATOR '، '
                )
                FROM user_role_assignments
                INNER JOIN roles
                  ON roles.id =
                    user_role_assignments.role_id
                WHERE user_role_assignments.user_id =
                    users.id
                  AND user_role_assignments.is_active = 1
                  AND roles.is_active = 1
                  AND (
                    user_role_assignments.starts_at
                        IS NULL
                    OR user_role_assignments.starts_at
                        <= CURRENT_TIMESTAMP
                  )
                  AND (
                    user_role_assignments.ends_at
                        IS NULL
                    OR user_role_assignments.ends_at
                        >= CURRENT_TIMESTAMP
                  )
            ), '')";
        }

        $organizationJoin = '';
        $organizationTitle = "''";

        if (
            Database::tableExists(
                'user_org_assignments'
            )
            && Database::tableExists('org_units')
        ) {
            $organizationJoin = "
                LEFT JOIN user_org_assignments
                    AS primary_user_org
                  ON primary_user_org.id = (
                    SELECT assignments.id
                    FROM user_org_assignments
                        AS assignments
                    WHERE assignments.user_id =
                        users.id
                      AND assignments.status =
                        'active'
                    ORDER BY
                        assignments.is_primary DESC,
                        assignments.id ASC
                    LIMIT 1
                  )
                LEFT JOIN org_units
                    AS primary_org
                  ON primary_org.id =
                    primary_user_org.org_unit_id
                 AND primary_org.deleted_at IS NULL
            ";
            $organizationTitle = "COALESCE(
                NULLIF(primary_org.title, ''),
                ''
            )";
        }

        $where = [
            "users.status = 'active'",
        ];

        if (Database::columnExists(
            'users',
            'deleted_at'
        )) {
            $where[] = 'users.deleted_at IS NULL';
        }

        if ($userIds !== []) {
            $where[] = 'users.id IN ('
                . implode(
                    ',',
                    array_fill(
                        0,
                        count($userIds),
                        '?'
                    )
                )
                . ')';
        }

        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    NULLIF(users.email, ''),
                    CONCAT('کاربر ', users.id)
                ) AS title,
                users.username,
                COALESCE(
                    NULLIF(persons.email_norm, ''),
                    NULLIF(persons.email, ''),
                    NULLIF(users.email_norm, ''),
                    NULLIF(users.email, ''),
                    ''
                ) AS email_destination,
                COALESCE(
                    NULLIF(persons.mobile_norm, ''),
                    NULLIF(persons.mobile, ''),
                    NULLIF(users.mobile_norm, ''),
                    NULLIF(users.mobile, ''),
                    ''
                ) AS sms_destination,
                {$messenger} AS messenger_destination,
                {$organizationTitle}
                    AS organization_title,
                {$cityTitle} AS city_title,
                {$roleTitles} AS role_titles
            FROM users
            LEFT JOIN persons
              ON persons.id = users.person_id
            {$organizationJoin}
            {$cityJoin}
            WHERE " . implode(
                ' AND ',
                $where
            ) . "
            ORDER BY title ASC, users.id ASC
        ");
        $statement->execute($userIds);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }
}

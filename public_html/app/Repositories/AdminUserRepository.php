<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;

class AdminUserRepository extends BaseRepository
{
    public function findDetail(int $userId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                users.person_id,
                users.username,
                COALESCE(users.mobile, persons.mobile) AS mobile,
                COALESCE(users.email, persons.email) AS email,
                users.status,
                users.email_verified_at,
                users.mobile_verified_at,
                users.last_login_at,
                users.created_at,
                users.updated_at,
                persons.first_name,
                persons.last_name,
                persons.full_name,
                persons.avatar,
                persons.status AS person_status,
                primary_org.title AS primary_org_unit_title,
                COUNT(DISTINCT active_roles.id) AS active_role_count,
                GROUP_CONCAT(DISTINCT active_roles.title ORDER BY active_roles.id ASC SEPARATOR '، ') AS active_role_titles
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            LEFT JOIN user_role_assignments AS active_assignments
              ON active_assignments.user_id = users.id
             AND active_assignments.is_active = 1
             AND (active_assignments.starts_at IS NULL OR active_assignments.starts_at <= CURRENT_TIMESTAMP)
             AND (active_assignments.ends_at IS NULL OR active_assignments.ends_at >= CURRENT_TIMESTAMP)
            LEFT JOIN roles AS active_roles
              ON active_roles.id = active_assignments.role_id
             AND active_roles.is_active = 1
            LEFT JOIN user_org_assignments AS primary_user_org
              ON primary_user_org.user_id = users.id
             AND primary_user_org.is_primary = 1
             AND primary_user_org.status = 'active'
            LEFT JOIN org_units AS primary_org
              ON primary_org.id = primary_user_org.org_unit_id
            WHERE users.id = ?
              AND users.deleted_at IS NULL
            GROUP BY
                users.id,
                users.person_id,
                users.username,
                users.mobile,
                users.email,
                users.status,
                users.email_verified_at,
                users.mobile_verified_at,
                users.last_login_at,
                users.created_at,
                users.updated_at,
                persons.mobile,
                persons.email,
                persons.first_name,
                persons.last_name,
                persons.full_name,
                persons.avatar,
                persons.status,
                primary_org.title
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function roleAssignmentsForDetail(int $userId): array
    {
        $priority = Database::columnExists('roles', 'priority') ? 'roles.priority' : '100';
        $statement = $this->connection()->prepare("
            SELECT
                roles.title AS role_title,
                roles.code AS role_code,
                {$priority} AS priority,
                user_role_assignments.scope_type,
                user_role_assignments.include_children,
                user_role_assignments.starts_at,
                user_role_assignments.ends_at,
                user_role_assignments.is_active
            FROM user_role_assignments
            INNER JOIN roles ON roles.id = user_role_assignments.role_id
            WHERE user_role_assignments.user_id = ?
            ORDER BY
                CASE WHEN user_role_assignments.is_active = 1 THEN 0 ELSE 1 END ASC,
                CASE WHEN roles.code = 'user' THEN 0 ELSE 1 END ASC,
                {$priority} ASC,
                user_role_assignments.id ASC
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function organizationAssignmentsForDetail(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT
                org_units.title AS org_unit_title,
                org_units.code AS org_unit_code,
                positions.title AS position_title,
                positions.code AS position_code,
                user_org_assignments.is_primary,
                user_org_assignments.status,
                user_org_assignments.started_at,
                user_org_assignments.ended_at
            FROM user_org_assignments
            INNER JOIN org_units ON org_units.id = user_org_assignments.org_unit_id
            LEFT JOIN positions ON positions.id = user_org_assignments.position_id
            WHERE user_org_assignments.user_id = ?
            ORDER BY
                user_org_assignments.is_primary DESC,
                org_units.sort_order ASC,
                user_org_assignments.id ASC
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function securitySummaryForDetail(int $userId): array
    {
        $mfaStatement = $this->connection()->prepare("
            SELECT
                COUNT(*) AS enabled_methods_count,
                SUM(CASE WHEN method = 'totp' THEN 1 ELSE 0 END) AS totp_methods_count
            FROM user_mfa_methods
            WHERE user_id = ?
              AND is_enabled = 1
              AND verified_at IS NOT NULL
        ");
        $mfaStatement->execute([$userId]);
        $mfa = $mfaStatement->fetch() ?: [];

        $recoveryStatement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM recovery_codes
            WHERE user_id = ?
              AND used_at IS NULL
        ");
        $recoveryStatement->execute([$userId]);

        $trustedStatement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM trusted_devices
            WHERE user_id = ?
              AND revoked_at IS NULL
              AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
        ");
        $trustedStatement->execute([$userId]);

        return [
            'enabled_methods_count' => (int) ($mfa['enabled_methods_count'] ?? 0),
            'totp_methods_count' => (int) ($mfa['totp_methods_count'] ?? 0),
            'recovery_codes_count' => (int) $recoveryStatement->fetchColumn(),
            'trusted_devices_count' => (int) $trustedStatement->fetchColumn(),
        ];
    }

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

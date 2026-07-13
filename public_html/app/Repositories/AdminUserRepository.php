<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;

class AdminUserRepository extends BaseRepository
{
    public function findDetail(int $userId): ?array
    {
        $personTypeJoin = $this->lookupCodeJoin('person_types', 'person_type_lookup', 'persons.person_type');
        $personTypeTitleSelect = $this->lookupCodeTitleSelect('person_types', 'person_type_lookup', 'person_type_title');
        $personProvinceJoin = $this->lookupIdJoin('provinces', 'person_provinces', 'persons.province_id');
        $personProvinceTitleSelect = $this->lookupIdTitleSelect('provinces', 'person_provinces', 'province_title');
        $personCityJoin = $this->lookupIdJoin('cities', 'person_cities', 'persons.city_id');
        $personCityTitleSelect = $this->lookupIdTitleSelect('cities', 'person_cities', 'city_title');

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
                persons.person_type AS person_type_code,
                persons.province_id IS NOT NULL AS province_reference_exists,
                persons.city_id IS NOT NULL AS city_reference_exists,
                persons.status AS person_status,
                {$personTypeTitleSelect},
                {$personProvinceTitleSelect},
                {$personCityTitleSelect},
                primary_org.title AS primary_org_unit_title,
                COALESCE(active_role_summary.active_role_count, 0) AS active_role_count,
                active_role_summary.active_role_titles
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            {$personTypeJoin}
            {$personProvinceJoin}
            {$personCityJoin}
            LEFT JOIN (
                SELECT
                    active_assignments.user_id,
                    COUNT(DISTINCT active_roles.id) AS active_role_count,
                    GROUP_CONCAT(DISTINCT active_roles.title ORDER BY active_roles.id ASC SEPARATOR '، ') AS active_role_titles
                FROM user_role_assignments AS active_assignments
                INNER JOIN roles AS active_roles
                  ON active_roles.id = active_assignments.role_id
                 AND active_roles.is_active = 1
                WHERE active_assignments.is_active = 1
                  AND (active_assignments.starts_at IS NULL OR active_assignments.starts_at <= CURRENT_TIMESTAMP)
                  AND (active_assignments.ends_at IS NULL OR active_assignments.ends_at >= CURRENT_TIMESTAMP)
                GROUP BY active_assignments.user_id
            ) AS active_role_summary
              ON active_role_summary.user_id = users.id
            LEFT JOIN user_org_assignments AS primary_user_org
              ON primary_user_org.user_id = users.id
             AND primary_user_org.is_primary = 1
             AND primary_user_org.status = 'active'
            LEFT JOIN org_units AS primary_org
              ON primary_org.id = primary_user_org.org_unit_id
             AND primary_org.deleted_at IS NULL
            WHERE users.id = ?
              AND users.deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function roleAssignmentsForDetail(int $userId): array
    {
        $priority = Database::columnExists('roles', 'priority') ? 'roles.priority' : '100';
        $assignmentProvinceJoin = $this->lookupIdJoin('provinces', 'assignment_provinces', 'user_role_assignments.province_id');
        $assignmentProvinceTitleSelect = $this->lookupIdTitleSelect('provinces', 'assignment_provinces', 'province_title');
        $assignmentCityJoin = $this->lookupIdJoin('cities', 'assignment_cities', 'user_role_assignments.city_id');
        $assignmentCityTitleSelect = $this->lookupIdTitleSelect('cities', 'assignment_cities', 'city_title');
        $organizationTypeJoin = $this->lookupIdJoin('org_types', 'assignment_org_types', 'assignment_organizations.org_type_id');
        $organizationTypeTitleSelect = $this->lookupIdTitleSelect('org_types', 'assignment_org_types', 'organization_type_title');
        $organizationLevelJoin = $this->lookupIdJoin('org_levels', 'assignment_org_levels', 'assignment_organizations.org_level_id');
        $organizationLevelTitleSelect = $this->lookupIdTitleSelect('org_levels', 'assignment_org_levels', 'organization_level_title');

        $statement = $this->connection()->prepare("
            SELECT
                roles.title AS role_title,
                roles.code AS role_code,
                {$priority} AS priority,
                user_role_assignments.scope_type,
                user_role_assignments.organization_id IS NOT NULL AS organization_reference_exists,
                user_role_assignments.province_id IS NOT NULL AS province_reference_exists,
                user_role_assignments.city_id IS NOT NULL AS city_reference_exists,
                user_role_assignments.include_children,
                user_role_assignments.starts_at,
                user_role_assignments.ends_at,
                user_role_assignments.is_active,
                assignment_organizations.title AS organization_title,
                assignment_organizations.org_type_id IS NOT NULL AS organization_type_reference_exists,
                assignment_organizations.org_level_id IS NOT NULL AS organization_level_reference_exists,
                {$organizationTypeTitleSelect},
                {$organizationLevelTitleSelect},
                {$assignmentProvinceTitleSelect},
                {$assignmentCityTitleSelect}
            FROM user_role_assignments
            INNER JOIN roles ON roles.id = user_role_assignments.role_id
            LEFT JOIN organizations AS assignment_organizations
              ON assignment_organizations.id = user_role_assignments.organization_id
             AND assignment_organizations.deleted_at IS NULL
            {$organizationTypeJoin}
            {$organizationLevelJoin}
            {$assignmentProvinceJoin}
            {$assignmentCityJoin}
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
                user_org_assignments.org_unit_id IS NOT NULL AS org_unit_reference_exists,
                user_org_assignments.position_id IS NOT NULL AS position_reference_exists,
                user_org_assignments.is_primary,
                user_org_assignments.status,
                user_org_assignments.started_at,
                user_org_assignments.ended_at
            FROM user_org_assignments
            LEFT JOIN org_units ON org_units.id = user_org_assignments.org_unit_id
             AND org_units.deleted_at IS NULL
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
             AND primary_org.deleted_at IS NULL
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

    private function lookupIdTitleSelect(string $table, string $alias, string $selectAlias): string
    {
        if (!$this->lookupTableReady($table) || !Database::columnExists($table, 'id')) {
            return "NULL AS {$selectAlias}";
        }

        return $this->lookupTitleSelect($table, $alias, $selectAlias);
    }

    private function lookupCodeTitleSelect(string $table, string $alias, string $selectAlias): string
    {
        if (!$this->lookupTableReady($table) || !Database::columnExists($table, 'code')) {
            return "NULL AS {$selectAlias}";
        }

        return $this->lookupTitleSelect($table, $alias, $selectAlias);
    }

    private function lookupTitleSelect(string $table, string $alias, string $selectAlias): string
    {
        $column = $this->lookupTitleColumn($table);

        if ($column === null) {
            return "NULL AS {$selectAlias}";
        }

        return "{$alias}.`{$column}` AS {$selectAlias}";
    }

    private function lookupIdJoin(string $table, string $alias, string $sourceColumn): string
    {
        if (!$this->lookupTableReady($table) || !Database::columnExists($table, 'id')) {
            return '';
        }

        return "LEFT JOIN {$table} AS {$alias} ON {$alias}.id = {$sourceColumn}";
    }

    private function lookupCodeJoin(string $table, string $alias, string $sourceColumn): string
    {
        if (!$this->lookupTableReady($table) || !Database::columnExists($table, 'code')) {
            return '';
        }

        return "LEFT JOIN {$table} AS {$alias} ON {$alias}.code = {$sourceColumn}";
    }

    private function lookupTableReady(string $table): bool
    {
        return Database::tableExists($table) && $this->lookupTitleColumn($table) !== null;
    }

    private function lookupTitleColumn(string $table): ?string
    {
        foreach (['title', 'name', 'label'] as $column) {
            if (Database::columnExists($table, $column)) {
                return $column;
            }
        }

        return null;
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

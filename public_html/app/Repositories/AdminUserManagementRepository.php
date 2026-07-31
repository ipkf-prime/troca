<?php

namespace App\Repositories;

use PDO;
use RuntimeException;
use Throwable;

class AdminUserManagementRepository extends BaseRepository
{
    public function findForForm(int $userId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                users.person_id,
                users.username,
                users.email,
                users.mobile,
                users.status,
                users.email_verified_at,
                users.mobile_verified_at,
                persons.first_name,
                persons.last_name,
                persons.full_name
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.id = ?
              AND users.deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $user['role_ids'] = $this->globalRoleIdsForUser($userId);

        return $user;
    }

    public function roles(bool $includeProtected): array
    {
        $where = $includeProtected
            ? 'roles.is_active = 1'
            : "roles.is_active = 1 AND roles.code <> 'super_admin'";

        $statement = $this->connection()->query("
            SELECT
                roles.id,
                roles.code,
                roles.title,
                roles.priority,
                roles.is_system,
                roles.role_kind_id,
                roles.role_area_id,
                COALESCE(role_kinds.code, 'uncategorized') AS role_kind_code,
                COALESCE(role_kinds.title, 'سایر') AS role_kind_title,
                COALESCE(role_areas.code, 'global') AS role_area_code,
                COALESCE(role_areas.title, 'سراسری') AS role_area_title
            FROM roles
            LEFT JOIN role_kinds ON role_kinds.id = roles.role_kind_id
            LEFT JOIN role_areas ON role_areas.id = roles.role_area_id
            WHERE {$where}
            ORDER BY
                CASE WHEN roles.code = 'user' THEN 0 ELSE 1 END,
                role_kinds.title ASC,
                role_areas.title ASC,
                roles.priority ASC,
                roles.title ASC,
                roles.id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function roleKinds(bool $includeProtected): array
    {
        $protected = $includeProtected ? '' : " AND roles.code <> 'super_admin'";
        $statement = $this->connection()->query("
            SELECT DISTINCT role_kinds.id, role_kinds.code, role_kinds.title
            FROM role_kinds
            INNER JOIN roles ON roles.role_kind_id = role_kinds.id
            WHERE roles.is_active = 1 {$protected}
            ORDER BY role_kinds.title ASC, role_kinds.id ASC
        ");
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function roleAreas(bool $includeProtected): array
    {
        $protected = $includeProtected ? '' : " AND roles.code <> 'super_admin'";
        $statement = $this->connection()->query("
            SELECT DISTINCT role_areas.id, role_areas.code, role_areas.title
            FROM role_areas
            INNER JOIN roles ON roles.role_area_id = role_areas.id
            WHERE roles.is_active = 1 {$protected}
            ORDER BY role_areas.title ASC, role_areas.id ASC
        ");
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function roleIdsByIds(array $roleIds, bool $includeProtected): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $protectedFilter = $includeProtected
            ? ''
            : " AND code <> 'super_admin'";

        $statement = $this->connection()->prepare("
            SELECT id
            FROM roles
            WHERE id IN ({$placeholders})
              AND is_active = 1
              {$protectedFilter}
        ");
        $statement->execute($ids);

        return array_map(
            'intval',
            $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
    }

    public function roleIdByCode(string $code): ?int
    {
        $statement = $this->connection()->prepare("
            SELECT id
            FROM roles
            WHERE code = ?
              AND is_active = 1
            LIMIT 1
        ");
        $statement->execute([$code]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function userHasGlobalRole(int $userId, string $roleCode): bool
    {
        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM user_role_assignments assignments
            INNER JOIN roles ON roles.id = assignments.role_id
            WHERE assignments.user_id = ?
              AND assignments.scope_type = 'global'
              AND assignments.scope_id IS NULL
              AND assignments.is_active = 1
              AND roles.code = ?
              AND roles.is_active = 1
        ");
        $statement->execute([$userId, $roleCode]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function identityExists(
        string $field,
        string $normalizedValue,
        ?int $exceptUserId = null
    ): bool {
        $column = match ($field) {
            'username' => 'users.username_norm',
            'email' => 'users.email_norm',
            'mobile' => 'users.mobile_norm',
            default => null,
        };

        if ($column === null || $normalizedValue === '') {
            return false;
        }

        $sql = "
            SELECT COUNT(*)
            FROM users
            WHERE {$column} = ?
              AND users.deleted_at IS NULL
        ";
        $parameters = [$normalizedValue];

        if ($exceptUserId !== null) {
            $sql .= ' AND users.id <> ?';
            $parameters[] = $exceptUserId;
        }

        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(array $data, array $roleIds): int
    {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $person = $db->prepare("
                INSERT INTO persons (
                    person_type,
                    first_name,
                    last_name,
                    full_name,
                    email,
                    email_norm,
                    mobile,
                    mobile_norm,
                    status,
                    created_at,
                    updated_at
                )
                VALUES (
                    'individual',
                    ?, ?, ?, ?, ?, ?, ?,
                    'active',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $person->execute([
                $data['first_name'],
                $data['last_name'],
                $data['full_name'],
                $data['email'],
                $data['email_norm'],
                $data['mobile'],
                $data['mobile_norm'],
            ]);
            $personId = (int) $db->lastInsertId();

            $user = $db->prepare("
                INSERT INTO users (
                    person_id,
                    username,
                    username_norm,
                    email,
                    email_norm,
                    mobile,
                    mobile_norm,
                    password_hash,
                    status,
                    email_verified_at,
                    mobile_verified_at,
                    failed_login_attempts,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, 0,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $user->execute([
                $personId,
                $data['username'],
                $data['username_norm'],
                $data['email'],
                $data['email_norm'],
                $data['mobile'],
                $data['mobile_norm'],
                $data['password_hash'],
                $data['status'],
                $data['email_verified']
                    ? gmdate('Y-m-d H:i:s')
                    : null,
                $data['mobile_verified']
                    ? gmdate('Y-m-d H:i:s')
                    : null,
            ]);
            $userId = (int) $db->lastInsertId();

            $this->syncGlobalRoles($userId, $roleIds);

            $db->commit();

            return $userId;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function update(
        int $userId,
        array $data,
        array $roleIds,
        bool $preserveSuperAdmin
    ): bool {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $current = $this->findForForm($userId);
            if ($current === null) {
                throw new RuntimeException('user_not_found');
            }

            $personId = (int) ($current['person_id'] ?? 0);

            if ($personId < 1) {
                $insertPerson = $db->prepare("
                    INSERT INTO persons (
                        person_type,
                        first_name,
                        last_name,
                        full_name,
                        email,
                        email_norm,
                        mobile,
                        mobile_norm,
                        status,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        'individual',
                        ?, ?, ?, ?, ?, ?, ?,
                        'active',
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");
                $insertPerson->execute([
                    $data['first_name'],
                    $data['last_name'],
                    $data['full_name'],
                    $data['email'],
                    $data['email_norm'],
                    $data['mobile'],
                    $data['mobile_norm'],
                ]);
                $personId = (int) $db->lastInsertId();
            } else {
                $updatePerson = $db->prepare("
                    UPDATE persons
                    SET first_name = ?,
                        last_name = ?,
                        full_name = ?,
                        email = ?,
                        email_norm = ?,
                        mobile = ?,
                        mobile_norm = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $updatePerson->execute([
                    $data['first_name'],
                    $data['last_name'],
                    $data['full_name'],
                    $data['email'],
                    $data['email_norm'],
                    $data['mobile'],
                    $data['mobile_norm'],
                    $personId,
                ]);
            }

            $passwordSql = '';
            $parameters = [
                $personId,
                $data['username'],
                $data['username_norm'],
                $data['email'],
                $data['email_norm'],
                $data['mobile'],
                $data['mobile_norm'],
                $data['status'],
                $data['email_verified']
                    ? gmdate('Y-m-d H:i:s')
                    : null,
                $data['mobile_verified']
                    ? gmdate('Y-m-d H:i:s')
                    : null,
            ];

            if ($data['password_hash'] !== null) {
                $passwordSql = ', password_hash = ?';
                $parameters[] = $data['password_hash'];
            }

            $parameters[] = $userId;

            $updateUser = $db->prepare("
                UPDATE users
                SET person_id = ?,
                    username = ?,
                    username_norm = ?,
                    email = ?,
                    email_norm = ?,
                    mobile = ?,
                    mobile_norm = ?,
                    status = ?,
                    email_verified_at = ?,
                    mobile_verified_at = ?
                    {$passwordSql},
                    failed_login_attempts = 0,
                    locked_until = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND deleted_at IS NULL
            ");
            $updateUser->execute($parameters);

            if ($preserveSuperAdmin) {
                $superAdminId = $this->roleIdByCode('super_admin');
                if ($superAdminId !== null) {
                    $roleIds[] = $superAdminId;
                }
            }

            $this->syncGlobalRoles($userId, $roleIds);

            $db->commit();

            return true;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    private function globalRoleIdsForUser(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT assignments.role_id
            FROM user_role_assignments assignments
            INNER JOIN roles ON roles.id = assignments.role_id
            WHERE assignments.user_id = ?
              AND assignments.scope_type = 'global'
              AND assignments.scope_id IS NULL
              AND assignments.is_active = 1
              AND roles.is_active = 1
            ORDER BY roles.priority ASC, roles.id ASC
        ");
        $statement->execute([$userId]);

        return array_map(
            'intval',
            $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
    }

    private function syncGlobalRoles(int $userId, array $roleIds): void
    {
        $baseRoleId = $this->roleIdByCode('user');
        if ($baseRoleId === null) {
            throw new RuntimeException('base_user_role_missing');
        }

        $roleIds[] = $baseRoleId;
        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn (int $id): bool => $id > 0
        )));

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $deactivate = $this->connection()->prepare("
            UPDATE user_role_assignments
            SET is_active = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
              AND scope_type = 'global'
              AND scope_id IS NULL
              AND role_id NOT IN ({$placeholders})
        ");
        $deactivate->execute(array_merge([$userId], $roleIds));

        foreach ($roleIds as $roleId) {
            $existing = $this->connection()->prepare("
                SELECT id
                FROM user_role_assignments
                WHERE user_id = ?
                  AND role_id = ?
                  AND scope_type = 'global'
                  AND scope_id IS NULL
                LIMIT 1
            ");
            $existing->execute([$userId, $roleId]);
            $assignmentId = $existing->fetchColumn();

            if ($assignmentId !== false) {
                $update = $this->connection()->prepare("
                    UPDATE user_role_assignments
                    SET include_children = 0,
                        is_active = 1,
                        starts_at = NULL,
                        ends_at = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $update->execute([(int) $assignmentId]);
                continue;
            }

            $insert = $this->connection()->prepare("
                INSERT INTO user_role_assignments (
                    user_id,
                    role_id,
                    scope_type,
                    scope_id,
                    include_children,
                    starts_at,
                    ends_at,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?,
                    'global',
                    NULL,
                    0,
                    NULL,
                    NULL,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $insert->execute([$userId, $roleId]);
        }
    }
}

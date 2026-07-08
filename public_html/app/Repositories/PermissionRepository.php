<?php

namespace App\Repositories;

class PermissionRepository extends BaseRepository
{
    protected string $table = 'permissions';

    public function getUserPermissions(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT DISTINCT permissions.*
            FROM permissions
            INNER JOIN role_permissions ON role_permissions.permission_id = permissions.id
            INNER JOIN user_role_assignments ON user_role_assignments.role_id = role_permissions.role_id
            INNER JOIN roles ON roles.id = user_role_assignments.role_id
            WHERE user_role_assignments.user_id = ?
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
              AND permissions.is_active = 1
              AND (user_role_assignments.starts_at IS NULL OR user_role_assignments.starts_at <= CURRENT_TIMESTAMP)
              AND (user_role_assignments.ends_at IS NULL OR user_role_assignments.ends_at >= CURRENT_TIMESTAMP)
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function roleHasPermission(int $roleId, string $permissionCode): bool
    {
        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM role_permissions
            INNER JOIN permissions ON permissions.id = role_permissions.permission_id
            WHERE role_permissions.role_id = ?
              AND permissions.code = ?
              AND permissions.is_active = 1
        ");
        $statement->execute([$roleId, $permissionCode]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function userHasPermission(int $userId, string $permissionCode): bool
    {
        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM permissions
            INNER JOIN role_permissions ON role_permissions.permission_id = permissions.id
            INNER JOIN user_role_assignments ON user_role_assignments.role_id = role_permissions.role_id
            INNER JOIN roles ON roles.id = user_role_assignments.role_id
            WHERE user_role_assignments.user_id = ?
              AND permissions.code = ?
              AND permissions.is_active = 1
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
        ");
        $statement->execute([$userId, $permissionCode]);

        return (int) $statement->fetchColumn() > 0;
    }
}

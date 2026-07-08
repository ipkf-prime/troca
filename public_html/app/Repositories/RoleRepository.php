<?php

namespace App\Repositories;

class RoleRepository extends BaseRepository
{
    protected string $table = 'roles';

    public function findByCode(string $code): ?array
    {
        $statement = $this->connection()->prepare("SELECT * FROM roles WHERE code = ? LIMIT 1");
        $statement->execute([$code]);
        $role = $statement->fetch();

        return $role ?: null;
    }

    public function getUserRoles(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT roles.*
            FROM roles
            INNER JOIN user_role_assignments ON user_role_assignments.role_id = roles.id
            WHERE user_role_assignments.user_id = ?
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
              AND (user_role_assignments.starts_at IS NULL OR user_role_assignments.starts_at <= CURRENT_TIMESTAMP)
              AND (user_role_assignments.ends_at IS NULL OR user_role_assignments.ends_at >= CURRENT_TIMESTAMP)
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function assignRoleToUser(int $userId, int $roleId, string $scopeType = 'global', ?int $scopeId = null, bool $includeChildren = false): void
    {
        $statement = $this->connection()->prepare("
            SELECT id
            FROM user_role_assignments
            WHERE user_id = ?
              AND role_id = ?
              AND scope_type = ?
              AND ((scope_id IS NULL AND ? IS NULL) OR scope_id = ?)
            LIMIT 1
        ");
        $statement->execute([$userId, $roleId, $scopeType, $scopeId, $scopeId]);
        $assignmentId = $statement->fetchColumn();

        if ($assignmentId !== false) {
            $update = $this->connection()->prepare("
                UPDATE user_role_assignments
                SET include_children = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update->execute([$includeChildren ? 1 : 0, (int) $assignmentId]);
            return;
        }

        $insert = $this->connection()->prepare("
            INSERT INTO user_role_assignments (
                user_id, role_id, scope_type, scope_id, include_children,
                is_active, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $insert->execute([$userId, $roleId, $scopeType, $scopeId, $includeChildren ? 1 : 0]);
    }
}

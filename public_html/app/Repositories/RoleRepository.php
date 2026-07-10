<?php

namespace App\Repositories;

use IPKF\Database\Database;

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
        $priority = $this->priorityExpression();
        $statement = $this->connection()->prepare("
            SELECT roles.*
            FROM roles
            INNER JOIN user_role_assignments ON user_role_assignments.role_id = roles.id
            WHERE user_role_assignments.user_id = ?
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
              AND (user_role_assignments.starts_at IS NULL OR user_role_assignments.starts_at <= CURRENT_TIMESTAMP)
              AND (user_role_assignments.ends_at IS NULL OR user_role_assignments.ends_at >= CURRENT_TIMESTAMP)
            ORDER BY CASE WHEN roles.code = 'user' THEN 0 ELSE 1 END ASC,
                     {$priority} ASC,
                     roles.id ASC
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function assignmentsForUser(int $userId): array
    {
        $priority = $this->priorityExpression();
        $statement = $this->connection()->prepare("
            SELECT user_role_assignments.*, roles.code AS role_code, roles.title AS role_title,
                   {$priority} AS priority
            FROM user_role_assignments
            INNER JOIN roles ON roles.id = user_role_assignments.role_id
            WHERE user_role_assignments.user_id = ?
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
              AND (user_role_assignments.starts_at IS NULL OR user_role_assignments.starts_at <= CURRENT_TIMESTAMP)
              AND (user_role_assignments.ends_at IS NULL OR user_role_assignments.ends_at >= CURRENT_TIMESTAMP)
            ORDER BY CASE WHEN roles.code = 'user' THEN 0 ELSE 1 END ASC,
                     {$priority} ASC,
                     user_role_assignments.id ASC
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function assignmentForUser(int $userId, int $assignmentId): ?array
    {
        $priority = $this->priorityExpression();
        $statement = $this->connection()->prepare("
            SELECT user_role_assignments.*, roles.code AS role_code, roles.title AS role_title,
                   {$priority} AS priority
            FROM user_role_assignments
            INNER JOIN roles ON roles.id = user_role_assignments.role_id
            WHERE user_role_assignments.id = ?
              AND user_role_assignments.user_id = ?
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
            LIMIT 1
        ");
        $statement->execute([$assignmentId, $userId]);
        $assignment = $statement->fetch();

        return $assignment ?: null;
    }

    public function lowestAssignmentForUser(int $userId): ?array
    {
        $assignments = $this->assignmentsForUser($userId);

        return $assignments[0] ?? null;
    }

    public function ensureBaseUserAssignment(int $userId): void
    {
        $role = $this->findByCode('user');

        if ($role !== null) {
            $this->assignRoleToUser($userId, (int) $role['id']);
        }
    }

    private function priorityExpression(): string
    {
        return Database::columnExists('roles', 'priority') ? 'roles.priority' : '100';
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

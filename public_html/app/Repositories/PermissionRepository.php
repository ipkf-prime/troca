<?php

namespace App\Repositories;

class PermissionRepository extends BaseRepository
{
    protected string $table = 'permissions';

    public function getUserPermissions(int $userId, ?int $assignmentId = null): array
    {
        $assignmentFilter = $assignmentId === null ? '' : ' AND user_role_assignments.id = ?';
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
              {$assignmentFilter}
        ");
        $params = [$userId];

        if ($assignmentId !== null) {
            $params[] = $assignmentId;
        }

        $statement->execute($params);

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

    public function userHasPermission(int $userId, string $permissionCode, ?int $assignmentId = null): bool
    {
        $assignmentFilter = $assignmentId === null ? '' : ' AND user_role_assignments.id = ?';
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
              {$assignmentFilter}
        ");
        $params = [$userId, $permissionCode];

        if ($assignmentId !== null) {
            $params[] = $assignmentId;
        }

        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function userPermissionOverride(
        int $userId,
        string $permissionCode,
        ?int $assignmentId = null
    ): ?string {
        if (!\IPKF\Database\Database::tableExists(
            'user_permission_overrides'
        )) {
            return null;
        }

        $assignmentId = max(0, (int) ($assignmentId ?? 0));

        $statement = $this->connection()->prepare("
            SELECT user_permission_overrides.effect_code
            FROM user_permission_overrides
            INNER JOIN permissions
                ON permissions.id =
                    user_permission_overrides.permission_id
            WHERE user_permission_overrides.user_id = ?
              AND permissions.code = ?
              AND permissions.is_active = 1
              AND user_permission_overrides.role_assignment_id
                    IN (0, ?)
            ORDER BY user_permission_overrides.role_assignment_id DESC
            LIMIT 1
        ");
        $statement->execute([
            $userId,
            $permissionCode,
            $assignmentId,
        ]);

        $effect = $statement->fetchColumn();

        return in_array($effect, ['allow', 'deny'], true)
            ? (string) $effect
            : null;
    }

    public function communicationMatrix(): array
    {
        $roles = $this->connection()->query("SELECT id, code, title FROM roles WHERE is_active = 1 ORDER BY priority, id")
            ->fetchAll() ?: [];
        $permissions = $this->connection()->query("SELECT id, code, title FROM permissions WHERE module = 'communications' AND is_active = 1 ORDER BY resource, action, id")
            ->fetchAll() ?: [];
        $assigned = $this->connection()->query("SELECT role_permissions.role_id, permissions.code FROM role_permissions INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE permissions.module = 'communications'")
            ->fetchAll() ?: [];
        $map = [];
        foreach ($assigned as $row) {
            $map[(int) $row['role_id']][(string) $row['code']] = true;
        }
        return ['roles' => $roles, 'permissions' => $permissions, 'assigned' => $map];
    }

    public function saveCommunicationRolePermissions(int $roleId, array $codes): bool
    {
        $role = $this->connection()->prepare('SELECT code FROM roles WHERE id = ? AND is_active = 1 LIMIT 1');
        $role->execute([$roleId]);
        $roleCode = (string) ($role->fetchColumn() ?: '');
        if ($roleCode === '' || $roleCode === 'super_admin') {
            return false;
        }
        $allowed = $this->connection()->query("SELECT code FROM permissions WHERE module = 'communications' AND is_active = 1")
            ->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $codes = array_values(array_intersect($allowed, array_map('strval', $codes)));
        $db = $this->connection();
        $db->beginTransaction();
        try {
            $delete = $db->prepare("DELETE role_permissions FROM role_permissions INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE role_permissions.role_id = ? AND permissions.module = 'communications'");
            $delete->execute([$roleId]);
            $insert = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) SELECT ?, id, CURRENT_TIMESTAMP FROM permissions WHERE code = ? AND module = 'communications' AND is_active = 1");
            foreach ($codes as $code) { $insert->execute([$roleId, $code]); }
            $db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }
}

<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

class AccessControlRepository extends BaseRepository
{
    public function page(string $query, int $userId): array
    {
        $permissions = $this->permissions();
        $roles = $this->roles();
        $selectedUser = $userId > 0
            ? $this->user($userId)
            : null;

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'groups' => $this->groupPermissions($permissions),
            'role_map' => $this->roleMap(),
            'users' => $this->users($query),
            'selected_user' => $selectedUser,
            'assignments' => $selectedUser !== null
                ? $this->assignments($userId)
                : [],
            'audit' => $this->audit(),
        ];
    }

    public function roles(): array
    {
        $order = Database::columnExists('roles', 'priority')
            ? 'priority ASC, id ASC'
            : 'id ASC';

        return $this->connection()->query("
            SELECT *
            FROM roles
            ORDER BY {$order}
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function permissions(): array
    {
        $group = Database::columnExists('permissions', 'display_group')
            ? "COALESCE(NULLIF(display_group, ''), resource)"
            : 'resource';
        $type = Database::columnExists('permissions', 'display_type')
            ? 'display_type'
            : "'operation'";
        $sort = Database::columnExists('permissions', 'sort_order')
            ? 'sort_order'
            : '0';
        $sensitive = Database::columnExists('permissions', 'is_sensitive')
            ? 'is_sensitive'
            : '0';

        return $this->connection()->query("
            SELECT id, code, module, resource, action,
                title, description,
                {$group} AS display_group,
                {$type} AS display_type,
                {$sort} AS sort_order,
                {$sensitive} AS is_sensitive
            FROM permissions
            WHERE is_active = 1
            ORDER BY module, sort_order, resource, action, id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function roleMap(): array
    {
        $rows = $this->connection()->query("
            SELECT role_permissions.role_id, permissions.code
            FROM role_permissions
            INNER JOIN permissions
                ON permissions.id = role_permissions.permission_id
            WHERE permissions.is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['role_id']][(string) $row['code']] = true;
        }

        return $map;
    }

    public function users(string $query): array
    {
        $query = trim($query);
        $where = ["users.status = 'active'"];
        $params = [];

        if (Database::columnExists('users', 'deleted_at')) {
            $where[] = 'users.deleted_at IS NULL';
        }

        if ($query !== '') {
            $where[] = "CONCAT_WS(
                ' ',
                COALESCE(persons.full_name, ''),
                COALESCE(persons.national_code, ''),
                COALESCE(persons.mobile, ''),
                COALESCE(users.username, ''),
                COALESCE(users.email, ''),
                COALESCE(users.mobile, ''),
                COALESCE((
                    SELECT GROUP_CONCAT(
                        DISTINCT roles.title
                        ORDER BY roles.priority, roles.id
                        SEPARATOR '، '
                    )
                    FROM user_role_assignments
                    INNER JOIN roles
                        ON roles.id = user_role_assignments.role_id
                    WHERE user_role_assignments.user_id = users.id
                      AND user_role_assignments.is_active = 1
                      AND roles.is_active = 1
                ), ''),
                COALESCE((
                    SELECT org_units.title
                    FROM user_org_assignments
                    INNER JOIN org_units
                        ON org_units.id = user_org_assignments.org_unit_id
                    WHERE user_org_assignments.user_id = users.id
                      AND user_org_assignments.status = 'active'
                    ORDER BY user_org_assignments.is_primary DESC,
                        user_org_assignments.id ASC
                    LIMIT 1
                ), '')
            ) LIKE ?";
            $params[] = '%' . $query . '%';
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
                COALESCE(users.username, '') AS username,
                COALESCE(persons.national_code, '') AS national_code,
                COALESCE(
                    NULLIF(persons.mobile, ''),
                    NULLIF(users.mobile, ''),
                    ''
                ) AS mobile,
                COALESCE((
                    SELECT GROUP_CONCAT(
                        DISTINCT roles.title
                        ORDER BY roles.priority, roles.id
                        SEPARATOR '، '
                    )
                    FROM user_role_assignments
                    INNER JOIN roles
                        ON roles.id = user_role_assignments.role_id
                    WHERE user_role_assignments.user_id = users.id
                      AND user_role_assignments.is_active = 1
                      AND roles.is_active = 1
                ), '') AS role_titles,
                COALESCE((
                    SELECT org_units.title
                    FROM user_org_assignments
                    INNER JOIN org_units
                        ON org_units.id = user_org_assignments.org_unit_id
                    WHERE user_org_assignments.user_id = users.id
                      AND user_org_assignments.status = 'active'
                    ORDER BY user_org_assignments.is_primary DESC,
                        user_org_assignments.id ASC
                    LIMIT 1
                ), '') AS organization_title
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY title, users.id
            LIMIT 100
        ");
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function user(int $userId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    NULLIF(users.email, ''),
                    CONCAT('کاربر ', users.id)
                ) AS title,
                COALESCE(users.username, '') AS username,
                COALESCE(persons.national_code, '') AS national_code,
                COALESCE(
                    NULLIF(persons.mobile, ''),
                    NULLIF(users.mobile, ''),
                    ''
                ) AS mobile
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.id = ?
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function assignments(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT
                user_role_assignments.id,
                user_role_assignments.is_default,
                roles.id AS role_id,
                roles.code AS role_code,
                roles.title AS role_title,
                user_role_assignments.scope_type
            FROM user_role_assignments
            INNER JOIN roles
                ON roles.id = user_role_assignments.role_id
            WHERE user_role_assignments.user_id = ?
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
            ORDER BY roles.priority, user_role_assignments.id
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveDefaultRoleAssignment(
        int $userId,
        int $assignmentId,
        int $actorUserId,
        string $ip
    ): void {
        if (
            !Database::columnExists(
                'user_role_assignments',
                'is_default'
            )
        ) {
            throw new RuntimeException(
                'access_default_role_not_supported'
            );
        }

        if ($this->user($userId) === null) {
            throw new RuntimeException(
                'access_user_not_found'
            );
        }

        $db =
            $this->connection();

        $db->beginTransaction();

        try {
            $lock =
                $db->prepare("
                    SELECT
                        user_role_assignments.id,
                        user_role_assignments.is_default

                    FROM user_role_assignments

                    WHERE user_role_assignments.user_id = ?

                    FOR UPDATE
                ");

            $lock->execute([
                $userId,
            ]);

            $currentRows =
                $lock->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];

            $oldAssignmentId = 0;

            foreach ($currentRows as $row) {
                if (
                    (int) (
                        $row['is_default']
                        ?? 0
                    ) === 1
                ) {
                    $oldAssignmentId =
                        (int) $row['id'];

                    break;
                }
            }

            if ($assignmentId > 0) {
                $target =
                    $db->prepare("
                        SELECT
                            user_role_assignments.id

                        FROM user_role_assignments

                        INNER JOIN roles
                            ON roles.id =
                                user_role_assignments.role_id

                        WHERE
                            user_role_assignments.id = ?
                            AND user_role_assignments.user_id = ?
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

                        LIMIT 1
                    ");

                $target->execute([
                    $assignmentId,
                    $userId,
                ]);

                if (
                    $target->fetchColumn()
                    === false
                ) {
                    throw new RuntimeException(
                        'access_default_assignment_invalid'
                    );
                }
            }

            $clear =
                $db->prepare("
                    UPDATE user_role_assignments

                    SET
                        is_default = 0,
                        updated_at =
                            CURRENT_TIMESTAMP

                    WHERE user_id = ?
                      AND is_default <> 0
                ");

            $clear->execute([
                $userId,
            ]);

            if ($assignmentId > 0) {
                $set =
                    $db->prepare("
                        UPDATE user_role_assignments

                        SET
                            is_default = 1,
                            updated_at =
                                CURRENT_TIMESTAMP

                        WHERE id = ?
                          AND user_id = ?
                    ");

                $set->execute([
                    $assignmentId,
                    $userId,
                ]);

                if ($set->rowCount() !== 1) {
                    throw new RuntimeException(
                        'access_default_assignment_invalid'
                    );
                }
            }

            $this->log(
                $actorUserId,
                'user',
                $userId,
                $assignmentId,
                'default_role_assignment_updated',
                [
                    'assignment_id' =>
                        $oldAssignmentId,
                ],
                [
                    'assignment_id' =>
                        $assignmentId,
                ],
                'تغییر نقش پیش‌فرض کاربر',
                $ip
            );

            $db->commit();

        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }


    public function overrideMap(int $userId, int $assignmentId): array
    {
        if (!Database::tableExists('user_permission_overrides')) {
            return [];
        }

        $statement = $this->connection()->prepare("
            SELECT permissions.code,
                user_permission_overrides.effect_code
            FROM user_permission_overrides
            INNER JOIN permissions
                ON permissions.id =
                    user_permission_overrides.permission_id
            WHERE user_permission_overrides.user_id = ?
              AND user_permission_overrides.role_assignment_id = ?
              AND permissions.is_active = 1
        ");
        $statement->execute([$userId, $assignmentId]);

        $map = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(string) $row['code']] =
                (string) $row['effect_code'];
        }

        return $map;
    }

    public function inheritedMap(int $userId, int $assignmentId): array
    {
        $filter = $assignmentId > 0
            ? ' AND user_role_assignments.id = ?'
            : '';
        $statement = $this->connection()->prepare("
            SELECT DISTINCT permissions.code
            FROM permissions
            INNER JOIN role_permissions
                ON role_permissions.permission_id = permissions.id
            INNER JOIN user_role_assignments
                ON user_role_assignments.role_id =
                    role_permissions.role_id
            INNER JOIN roles
                ON roles.id = user_role_assignments.role_id
            WHERE user_role_assignments.user_id = ?
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
              AND permissions.is_active = 1
              {$filter}
        ");
        $params = [$userId];

        if ($assignmentId > 0) {
            $params[] = $assignmentId;
        }

        $statement->execute($params);

        return array_fill_keys(
            array_map(
                'strval',
                $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
            ),
            true
        );
    }

    public function notificationPolicy(int $userId, int $assignmentId): string
    {
        $effective = function (string $code) use ($userId, $assignmentId): bool {
            $overrides = $this->overrideMap($userId, $assignmentId);

            if (isset($overrides[$code])) {
                return $overrides[$code] === 'allow';
            }

            if ($assignmentId > 0) {
                $global = $this->overrideMap($userId, 0);

                if (isset($global[$code])) {
                    return $global[$code] === 'allow';
                }
            }

            return isset($this->inheritedMap($userId, $assignmentId)[$code]);
        };

        if (
            $effective('notifications.send.manage')
            || $effective('notifications.send.direct')
        ) {
            return 'direct';
        }

        if (
            $effective('notifications.send.view')
            && $effective('notifications.send.request')
        ) {
            return 'approval';
        }

        return 'none';
    }

    public function saveRolePermissions(
        int $roleId,
        array $codes,
        int $actorUserId,
        string $reason,
        string $ip
    ): void {
        $statement = $this->connection()->prepare("
            SELECT code, is_editable
            FROM roles
            WHERE id = ?
            LIMIT 1
        ");
        $statement->execute([$roleId]);
        $role = $statement->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($role)
            || ($role['code'] ?? '') === 'super_admin'
            || empty($role['is_editable'])
        ) {
            throw new RuntimeException('access_role_protected');
        }

        $allowed = array_map(
            'strval',
            $this->connection()->query("
                SELECT code FROM permissions WHERE is_active = 1
            ")->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
        $codes = array_values(array_unique(
            array_intersect($allowed, array_map('strval', $codes))
        ));
        $old = array_keys($this->roleMap()[$roleId] ?? []);
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $delete = $db->prepare("
                DELETE FROM role_permissions WHERE role_id = ?
            ");
            $delete->execute([$roleId]);

            $insert = $db->prepare("
                INSERT IGNORE INTO role_permissions
                    (role_id, permission_id, created_at)
                SELECT ?, id, CURRENT_TIMESTAMP
                FROM permissions
                WHERE code = ? AND is_active = 1
            ");

            foreach ($codes as $code) {
                $insert->execute([$roleId, $code]);
            }

            $this->log(
                $actorUserId,
                'role',
                $roleId,
                0,
                'role_permissions_replaced',
                $old,
                $codes,
                $reason,
                $ip
            );
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function saveUserPolicy(
        int $userId,
        int $assignmentId,
        string $policy,
        bool $canSearch,
        bool $canViewDetails,
        bool $canUseManual,
        int $actorUserId,
        string $reason,
        string $ip
    ): void {
        if ($this->user($userId) === null) {
            throw new RuntimeException('access_user_not_found');
        }

        if ($assignmentId > 0) {
            $check = $this->connection()->prepare("
                SELECT COUNT(*)
                FROM user_role_assignments
                WHERE id = ? AND user_id = ? AND is_active = 1
            ");
            $check->execute([$assignmentId, $userId]);

            if ((int) $check->fetchColumn() < 1) {
                throw new RuntimeException('access_assignment_invalid');
            }
        }

        $effects = match ($policy) {
            'none' => [
                'notifications.send.view' => 'deny',
                'notifications.send.request' => 'deny',
                'notifications.send.direct' => 'deny',
                'notifications.send.manage' => 'deny',
            ],
            'approval' => [
                'notifications.send.view' => 'allow',
                'notifications.send.request' => 'allow',
                'notifications.send.direct' => 'deny',
                'notifications.send.manage' => 'deny',
            ],
            'direct' => [
                'notifications.send.view' => 'allow',
                'notifications.send.request' => 'allow',
                'notifications.send.direct' => 'allow',
            ],
            default => [],
        };

        if ($policy !== 'inherit') {
            $effects['notifications.recipients.search'] =
                $canSearch ? 'allow' : 'deny';
            $effects['notifications.recipients.details'] =
                $canViewDetails ? 'allow' : 'deny';
            $effects['notifications.manual_targets.use'] =
                $canUseManual ? 'allow' : 'deny';
        }

        $old = $this->overrideMap($userId, $assignmentId);
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $delete = $db->prepare("
                DELETE FROM user_permission_overrides
                WHERE user_id = ? AND role_assignment_id = ?
            ");
            $delete->execute([$userId, $assignmentId]);

            $insert = $db->prepare("
                INSERT INTO user_permission_overrides (
                    user_id, permission_id, role_assignment_id,
                    effect_code, reason, created_by_user_id,
                    updated_by_user_id, created_at, updated_at
                )
                SELECT ?, id, ?, ?, ?, ?, ?,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                FROM permissions
                WHERE code = ? AND is_active = 1
            ");

            foreach ($effects as $code => $effect) {
                $insert->execute([
                    $userId,
                    $assignmentId,
                    $effect,
                    $reason,
                    $actorUserId,
                    $actorUserId,
                    $code,
                ]);
            }

            $this->log(
                $actorUserId,
                'user',
                $userId,
                $assignmentId,
                'notification_access_policy_replaced',
                $old,
                $effects,
                $reason,
                $ip
            );
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function audit(): array
    {
        if (!Database::tableExists('access_control_change_logs')) {
            return [];
        }

        return $this->connection()->query("
            SELECT access_control_change_logs.*,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    CONCAT('کاربر ',
                        access_control_change_logs.actor_user_id)
                ) AS actor_title
            FROM access_control_change_logs
            LEFT JOIN users
                ON users.id = access_control_change_logs.actor_user_id
            LEFT JOIN persons ON persons.id = users.person_id
            ORDER BY access_control_change_logs.id DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function groupPermissions(array $permissions): array
    {
        $groups = [];

        foreach ($permissions as $permission) {
            $module = (string) ($permission['module'] ?? 'core');
            $group = (string) (
                $permission['display_group']
                ?? $permission['resource']
                ?? 'عمومی'
            );
            $groups[$module][$group][] = $permission;
        }

        return $groups;
    }

    private function log(
        int $actorUserId,
        string $targetType,
        int $targetId,
        int $assignmentId,
        string $changeType,
        mixed $old,
        mixed $new,
        string $reason,
        string $ip
    ): void {
        $statement = $this->connection()->prepare("
            INSERT INTO access_control_change_logs (
                actor_user_id, target_type, target_id,
                role_assignment_id, change_type,
                old_value, new_value, reason, request_ip,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
                CURRENT_TIMESTAMP)
        ");
        $statement->execute([
            $actorUserId,
            $targetType,
            $targetId,
            $assignmentId,
            $changeType,
            json_encode(
                $old,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            json_encode(
                $new,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            $reason !== '' ? $reason : null,
            $ip !== '' ? $ip : null,
        ]);
    }
}

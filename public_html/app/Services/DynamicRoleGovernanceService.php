<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * DYNAMIC_ROLE_GOVERNANCE_V2
 *
 * Governs creation and editing of both legacy and custom roles. A manager may
 * only change roles below their own priority and may only grant permissions
 * already held by that manager. Permissions above that ceiling are preserved.
 */
class DynamicRoleGovernanceService extends BaseService
{
    private PDO $db;
    private AuthorizationService $authorization;

    public function __construct(
        ?PDO $db = null,
        ?AuthorizationService $authorization = null
    ) {
        $this->db = $db
            ?? (new ConnectionResolver())->resolve('core.primary');
        $this->authorization = $authorization
            ?? new AuthorizationService();
    }

    public function createBuilder(int $actorUserId): array
    {
        $actor = $this->actorContext($actorUserId);

        return $this->catalog($actor) + [
            'mode' => 'create',
            'roles' => $this->manageableRoles($actor),
            'selected_role' => null,
            'selected_permissions' => [],
            'selected_scope_types' => [],
            'selected_identity_fields' => [],
        ];
    }

    public function roleEditor(
        int $actorUserId,
        int $roleId = 0
    ): array {
        $actor = $this->actorContext($actorUserId);
        $roles = $this->manageableRoles($actor);

        if ($roles === []) {
            throw new RuntimeException('access_role_management_forbidden');
        }

        $selected = null;

        foreach ($roles as $role) {
            if ($roleId > 0 && (int) $role['id'] === $roleId) {
                $selected = $role;
                break;
            }
        }

        $selected ??= $roles[0];
        $selectedId = (int) $selected['id'];

        return $this->catalog($actor, $selectedId) + [
            'mode' => 'edit',
            'roles' => $roles,
            'selected_role' => $selected,
            'selected_permissions' => $this->rolePermissionCodes($selectedId),
            'selected_scope_types' => $this->roleScopeCodes($selectedId),
            'selected_identity_fields' => $this->roleIdentityFields($selectedId),
            'metadata_locked' =>
                (int) ($selected['is_system'] ?? 0) === 1
                || in_array(
                    (string) ($selected['code'] ?? ''),
                    ['super_admin', 'user'],
                    true
                ),
        ];
    }

    public function createRole(
        int $actorUserId,
        array $input,
        string $ip
    ): int {
        $actor = $this->actorContext($actorUserId);
        $code = strtolower(trim((string) ($input['code'] ?? '')));

        if (preg_match('/^custom_[a-z][a-z0-9_]{2,60}$/', $code) !== 1) {
            throw new RuntimeException('access_role_code_invalid');
        }

        $values = $this->validatedRoleValues($actor, $input, true);
        $reason = $this->reason($input);

        $this->db->beginTransaction();

        try {
            $duplicate = $this->db->prepare(
                'SELECT COUNT(*) FROM roles WHERE code = ?'
            );
            $duplicate->execute([$code]);

            if ((int) $duplicate->fetchColumn() !== 0) {
                throw new RuntimeException('access_role_code_duplicate');
            }

            $insert = $this->db->prepare("
                INSERT INTO roles (
                    code, title, priority, role_area_id, role_kind_id,
                    is_system, is_active, is_editable, is_deletable,
                    can_manage_other_users, requires_center,
                    created_at, updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, 0, 1, 1, 1, ?, ?,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");
            $insert->execute([
                $code,
                $values['title'],
                $values['priority'],
                $values['role_area_id'],
                $values['role_kind_id'],
                $values['can_manage_other_users'],
                $values['requires_center'],
            ]);

            $roleId = (int) $this->db->lastInsertId();

            if ($roleId < 1) {
                throw new RuntimeException('access_role_create_failed');
            }

            $this->replacePermissions($roleId, $values['permissions']);
            $this->replaceScopePolicies($roleId, $values['scope_types']);
            $this->replaceIdentityRequirements(
                $roleId,
                $values['identity_fields']
            );
            $this->audit(
                $actorUserId,
                $roleId,
                'custom_role_created',
                null,
                ['code' => $code] + $values,
                $reason,
                $ip
            );

            $this->db->commit();

            return $roleId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function updateRole(
        int $actorUserId,
        array $input,
        string $ip
    ): void {
        $actor = $this->actorContext($actorUserId);
        $roleId = max(0, (int) ($input['role_id'] ?? 0));
        $role = $this->role($roleId);

        if ($role === null || !$this->canManageRole($actor, $role)) {
            throw new RuntimeException('access_role_edit_forbidden');
        }

        $values = $this->validatedRoleValues($actor, $input, false);
        $reason = $this->reason($input);
        $metadataLocked =
            (int) ($role['is_system'] ?? 0) === 1
            || in_array((string) $role['code'], ['super_admin', 'user'], true);
        $currentPermissions = $this->rolePermissionCodes($roleId);

        if (!$actor['is_super']) {
            $actorPermissionMap = array_fill_keys(
                $actor['permission_codes'],
                true
            );

            foreach ($currentPermissions as $permission) {
                if (!isset($actorPermissionMap[$permission])) {
                    $values['permissions'][] = $permission;
                }
            }

            $values['permissions'] = array_values(array_unique(
                $values['permissions']
            ));
        }

        if ((string) $role['code'] === 'super_admin') {
            foreach ([
                'access.manage',
                'access.roles.manage',
                'access.scopes.manage',
                'access.users.manage',
            ] as $requiredPermission) {
                if (!in_array(
                    $requiredPermission,
                    $values['permissions'],
                    true
                )) {
                    throw new RuntimeException(
                        'access_super_admin_permission_required'
                    );
                }
            }
        }

        $before = [
            'role' => $role,
            'permissions' => $currentPermissions,
            'scope_types' => $this->roleScopeCodes($roleId),
            'identity_fields' => $this->roleIdentityFields($roleId),
        ];

        $this->db->beginTransaction();

        try {
            if (!$metadataLocked) {
                $update = $this->db->prepare("
                    UPDATE roles
                    SET title = ?,
                        priority = ?,
                        role_area_id = ?,
                        role_kind_id = ?,
                        can_manage_other_users = ?,
                        requires_center = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $update->execute([
                    $values['title'],
                    $values['priority'],
                    $values['role_area_id'],
                    $values['role_kind_id'],
                    $values['can_manage_other_users'],
                    $values['requires_center'],
                    $roleId,
                ]);
            }

            $this->replacePermissions($roleId, $values['permissions']);
            $this->replaceScopePolicies($roleId, $values['scope_types']);
            $this->replaceIdentityRequirements(
                $roleId,
                $values['identity_fields']
            );
            $this->audit(
                $actorUserId,
                $roleId,
                'role_governance_updated',
                $before,
                [
                    'metadata_locked' => $metadataLocked,
                    'permissions' => $values['permissions'],
                    'scope_types' => $values['scope_types'],
                    'identity_fields' => $values['identity_fields'],
                ],
                $reason,
                $ip
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function catalog(array $actor, int $selectedRoleId = 0): array
    {
        $selected = array_fill_keys(
            $selectedRoleId > 0
                ? $this->rolePermissionCodes($selectedRoleId)
                : [],
            true
        );
        $actorPermissions = array_fill_keys(
            $actor['permission_codes'],
            true
        );
        $permissions = $this->db->query("
            SELECT id, code, module, title, display_group, sort_order,
                   is_sensitive
            FROM permissions
            WHERE is_active = 1
            ORDER BY
                COALESCE(NULLIF(display_group, ''), 'سایر مجوزها'),
                sort_order,
                title,
                id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($permissions as &$permission) {
            $code = (string) $permission['code'];
            $permission['selected'] = isset($selected[$code]);
            $permission['manageable'] =
                $actor['is_super'] || isset($actorPermissions[$code]);
        }
        unset($permission);

        return [
            'actor' => $actor,
            'role_areas' => $this->activeLookup('role_areas'),
            'role_kinds' => $this->activeLookup('role_kinds'),
            'permissions' => $permissions,
            'scope_types' => $this->scopeTypes(),
            'identity_fields' => $this->identityFields(),
        ];
    }

    private function actorContext(int $actorUserId): array
    {
        $this->authorizeAny(
            $actorUserId,
            ['access.manage', 'access.roles.manage'],
            'access_role_management_forbidden'
        );

        $statement = $this->db->prepare("
            SELECT roles.code, roles.priority
            FROM user_role_assignments AS assignments
            INNER JOIN roles ON roles.id = assignments.role_id
            WHERE assignments.user_id = ?
              AND assignments.is_active = 1
              AND roles.is_active = 1
              AND (assignments.starts_at IS NULL OR assignments.starts_at <= CURRENT_TIMESTAMP)
              AND (assignments.ends_at IS NULL OR assignments.ends_at >= CURRENT_TIMESTAMP)
            ORDER BY roles.priority DESC, assignments.id
        ");
        $statement->execute([$actorUserId]);
        $roles = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($roles === []) {
            throw new RuntimeException('access_role_management_forbidden');
        }

        $permissionStatement = $this->db->prepare("
            SELECT DISTINCT permissions.code
            FROM user_role_assignments AS assignments
            INNER JOIN roles ON roles.id = assignments.role_id
            INNER JOIN role_permissions
                ON role_permissions.role_id = roles.id
            INNER JOIN permissions
                ON permissions.id = role_permissions.permission_id
            WHERE assignments.user_id = ?
              AND assignments.is_active = 1
              AND roles.is_active = 1
              AND permissions.is_active = 1
              AND (assignments.starts_at IS NULL OR assignments.starts_at <= CURRENT_TIMESTAMP)
              AND (assignments.ends_at IS NULL OR assignments.ends_at >= CURRENT_TIMESTAMP)
            ORDER BY permissions.code
        ");
        $permissionStatement->execute([$actorUserId]);

        return [
            'user_id' => $actorUserId,
            'priority' => max(array_map(
                static fn (array $role): int => (int) $role['priority'],
                $roles
            )),
            'is_super' => in_array(
                'super_admin',
                array_column($roles, 'code'),
                true
            ),
            'permission_codes' => array_map(
                'strval',
                $permissionStatement->fetchAll(PDO::FETCH_COLUMN) ?: []
            ),
        ];
    }

    private function manageableRoles(array $actor): array
    {
        $statement = $this->db->prepare("
            SELECT
                roles.*,
                areas.code AS role_area_code,
                areas.title AS role_area_title,
                kinds.code AS role_kind_code,
                kinds.title AS role_kind_title,
                (
                    SELECT COUNT(*)
                    FROM role_permissions
                    WHERE role_permissions.role_id = roles.id
                ) AS permission_count,
                (
                    SELECT COUNT(*)
                    FROM role_scope_policies
                    WHERE role_scope_policies.role_id = roles.id
                ) AS scope_policy_count,
                (
                    SELECT COUNT(*)
                    FROM user_role_assignments
                    WHERE user_role_assignments.role_id = roles.id
                      AND user_role_assignments.is_active = 1
                ) AS active_assignment_count
            FROM roles
            LEFT JOIN role_areas AS areas ON areas.id = roles.role_area_id
            LEFT JOIN role_kinds AS kinds ON kinds.id = roles.role_kind_id
            WHERE roles.is_active = 1
              AND (? = 1 OR roles.priority < ?)
            ORDER BY roles.priority DESC, roles.id
        ");
        $statement->execute([
            $actor['is_super'] ? 1 : 0,
            $actor['priority'],
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function role(int $roleId): ?array
    {
        $statement = $this->db->prepare("
            SELECT roles.*,
                   areas.code AS role_area_code,
                   kinds.code AS role_kind_code
            FROM roles
            LEFT JOIN role_areas AS areas ON areas.id = roles.role_area_id
            LEFT JOIN role_kinds AS kinds ON kinds.id = roles.role_kind_id
            WHERE roles.id = ?
              AND roles.is_active = 1
            LIMIT 1
        ");
        $statement->execute([$roleId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function canManageRole(array $actor, array $role): bool
    {
        return $actor['is_super']
            || (int) $role['priority'] < (int) $actor['priority'];
    }

    private function validatedRoleValues(
        array $actor,
        array $input,
        bool $creating
    ): array {
        $title = trim((string) ($input['title'] ?? ''));

        if ($this->length($title) < 3 || $this->length($title) > 120) {
            throw new RuntimeException('access_role_title_invalid');
        }

        $areaCode = strtolower(trim(
            (string) ($input['role_area_code'] ?? '')
        ));
        $kindCode = strtolower(trim(
            (string) ($input['role_kind_code'] ?? '')
        ));
        $areaId = $this->lookupId('role_areas', $areaCode);
        $kindId = $this->lookupId('role_kinds', $kindCode);

        if ($areaId < 1 || $kindId < 1) {
            throw new RuntimeException('access_role_classification_invalid');
        }

        $maximumPriority = $actor['is_super']
            ? 999
            : max(1, (int) $actor['priority'] - 1);
        $minimumPriority = $creating ? 10 : 1;
        $priority = max(
            $minimumPriority,
            min($maximumPriority, (int) ($input['priority'] ?? 100))
        );
        $permissions = $this->stringArray($input['permissions'] ?? []);

        if ($creating && $permissions === []) {
            throw new RuntimeException('access_role_permission_required');
        }

        $actorPermissionMap = array_fill_keys(
            $actor['permission_codes'],
            true
        );

        foreach ($permissions as $permission) {
            if (!$actor['is_super'] && !isset($actorPermissionMap[$permission])) {
                throw new RuntimeException('access_permission_ceiling_exceeded');
            }
        }

        $scopeTypes = $this->stringArray($input['scope_types'] ?? []);

        if ($scopeTypes === []) {
            throw new RuntimeException('access_role_scope_required');
        }

        return [
            'title' => $title,
            'priority' => $priority,
            'role_area_id' => $areaId,
            'role_area_code' => $areaCode,
            'role_kind_id' => $kindId,
            'role_kind_code' => $kindCode,
            'can_manage_other_users' =>
                !empty($input['can_manage_other_users']) ? 1 : 0,
            'requires_center' =>
                !empty($input['requires_center']) ? 1 : 0,
            'permissions' => $permissions,
            'scope_types' => $scopeTypes,
            'identity_fields' =>
                $this->stringArray($input['identity_fields'] ?? []),
        ];
    }

    private function reason(array $input): string
    {
        $reason = trim((string) ($input['reason'] ?? ''));

        if ($this->length($reason) < 3 || $this->length($reason) > 500) {
            throw new RuntimeException('access_reason_required');
        }

        return $reason;
    }

    private function replacePermissions(int $roleId, array $codes): void
    {
        $this->db->prepare(
            'DELETE FROM role_permissions WHERE role_id = ?'
        )->execute([$roleId]);
        $insert = $this->db->prepare("
            INSERT INTO role_permissions (role_id, permission_id, created_at)
            SELECT ?, id, CURRENT_TIMESTAMP
            FROM permissions
            WHERE code = ? AND is_active = 1
        ");

        foreach ($codes as $code) {
            $check = $this->db->prepare("
                SELECT COUNT(*) FROM permissions
                WHERE code = ? AND is_active = 1
            ");
            $check->execute([$code]);

            if ((int) $check->fetchColumn() !== 1) {
                throw new RuntimeException('access_permission_invalid');
            }

            $insert->execute([$roleId, $code]);
        }
    }

    private function replaceScopePolicies(int $roleId, array $codes): void
    {
        $this->db->prepare(
            'DELETE FROM role_scope_policies WHERE role_id = ?'
        )->execute([$roleId]);
        $insert = $this->db->prepare("
            INSERT INTO role_scope_policies (
                role_id, scope_type_code, is_required, is_default,
                created_at, updated_at
            )
            SELECT ?, code, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM access_scope_types
            WHERE code = ? AND is_active = 1
        ");

        foreach (array_values($codes) as $index => $code) {
            $insert->execute([
                $roleId,
                $index === 0 ? 1 : 0,
                $index === 0 ? 1 : 0,
                $code,
            ]);

            if ($insert->rowCount() !== 1) {
                throw new RuntimeException('access_scope_type_invalid');
            }
        }
    }

    private function replaceIdentityRequirements(
        int $roleId,
        array $fields
    ): void {
        $allowed = array_fill_keys(array_keys($this->identityFields()), true);
        $this->db->prepare(
            'DELETE FROM role_identity_requirements WHERE role_id = ?'
        )->execute([$roleId]);
        $insert = $this->db->prepare("
            INSERT INTO role_identity_requirements (
                role_id, field_code, verification_mode_code,
                is_required, sort_order, created_at, updated_at
            )
            VALUES (?, ?, ?, 1, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $sortOrder = 10;

        foreach ($fields as $field) {
            if (!isset($allowed[$field])) {
                throw new RuntimeException('access_identity_requirement_invalid');
            }

            $insert->execute([
                $roleId,
                $field,
                in_array($field, ['mobile', 'email'], true)
                    ? 'verified'
                    : 'present',
                $sortOrder,
            ]);
            $sortOrder += 10;
        }
    }

    private function rolePermissionCodes(int $roleId): array
    {
        $statement = $this->db->prepare("
            SELECT permissions.code
            FROM role_permissions
            INNER JOIN permissions
                ON permissions.id = role_permissions.permission_id
            WHERE role_permissions.role_id = ?
              AND permissions.is_active = 1
            ORDER BY permissions.code
        ");
        $statement->execute([$roleId]);

        return array_map(
            'strval',
            $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
    }

    private function roleScopeCodes(int $roleId): array
    {
        if (!Database::tableExists('role_scope_policies')) {
            return [];
        }

        $statement = $this->db->prepare("
            SELECT scope_type_code
            FROM role_scope_policies
            WHERE role_id = ?
            ORDER BY is_default DESC, id
        ");
        $statement->execute([$roleId]);

        return array_map(
            'strval',
            $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
    }

    private function roleIdentityFields(int $roleId): array
    {
        if (!Database::tableExists('role_identity_requirements')) {
            return [];
        }

        $statement = $this->db->prepare("
            SELECT field_code
            FROM role_identity_requirements
            WHERE role_id = ?
            ORDER BY sort_order, id
        ");
        $statement->execute([$roleId]);

        return array_map(
            'strval',
            $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
    }

    private function activeLookup(string $table): array
    {
        if (!in_array($table, ['role_areas', 'role_kinds'], true)) {
            throw new RuntimeException('access_lookup_invalid');
        }

        return $this->db->query("
            SELECT id, code, title
            FROM `{$table}`
            WHERE is_active = 1
            ORDER BY sort_order, id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function lookupId(string $table, string $code): int
    {
        if (!in_array($table, ['role_areas', 'role_kinds'], true)) {
            return 0;
        }

        $statement = $this->db->prepare("
            SELECT id FROM `{$table}`
            WHERE code = ? AND is_active = 1
            LIMIT 1
        ");
        $statement->execute([$code]);

        return (int) $statement->fetchColumn();
    }

    private function scopeTypes(): array
    {
        return $this->db->query("
            SELECT * FROM access_scope_types
            WHERE is_active = 1
            ORDER BY sort_order, id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function identityFields(): array
    {
        return [
            'full_name' => 'نام و نام خانوادگی',
            'national_code' => 'کد ملی',
            'mobile' => 'موبایل تأییدشده',
            'email' => 'ایمیل تأییدشده',
            'province' => 'استان',
            'county' => 'شهرستان',
            'organization' => 'سازمان یا شرکت',
            'position' => 'سمت یا پست سازمانی',
        ];
    }

    private function authorizeAny(
        int $userId,
        array $permissions,
        string $error
    ): void {
        if ($userId < 1) {
            throw new RuntimeException($error);
        }

        foreach ($permissions as $permission) {
            try {
                if ($this->authorization->hasPermission($userId, $permission)) {
                    return;
                }
            } catch (Throwable) {
            }
        }

        throw new RuntimeException($error);
    }

    private function audit(
        int $actorUserId,
        int $roleId,
        string $changeType,
        mixed $oldValue,
        mixed $newValue,
        string $reason,
        string $ip
    ): void {
        if (!Database::tableExists('access_control_change_logs')) {
            return;
        }

        $statement = $this->db->prepare("
            INSERT INTO access_control_change_logs (
                actor_user_id, target_type, target_id,
                role_assignment_id, change_type, old_value, new_value,
                reason, request_ip, created_at
            )
            VALUES (?, 'role', ?, 0, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $statement->execute([
            $actorUserId,
            $roleId,
            $changeType,
            $oldValue === null ? null : json_encode(
                $oldValue,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            $newValue === null ? null : json_encode(
                $newValue,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            $reason,
            $ip !== '' ? $ip : null,
        ]);
    }

    private function stringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                static fn ($item): string => trim((string) $item),
                $value
            ),
            static fn (string $item): bool => $item !== ''
        )));
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}

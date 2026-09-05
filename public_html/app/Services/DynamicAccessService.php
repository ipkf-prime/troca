<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Database\Database;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

/**
 * DYNAMIC_SCOPED_ACCESS_FOUNDATION_V1
 *
 * Creates custom roles on top of the existing RBAC model and manages
 * assignment-specific scopes and typed constraints.
 */
class DynamicAccessService extends BaseService
{
    private PDO $db;
    private AuthorizationService $authorization;

    public function __construct(
        ?PDO $db = null,
        ?AuthorizationService $authorization = null
    ) {
        $this->db = $db
            ?? (new ConnectionResolver())->resolve('core.primary');

        $this->authorization =
            $authorization
            ?? new AuthorizationService();
    }

    public function roleBuilder(int $actorUserId): array
    {
        $this->authorizeAny(
            $actorUserId,
            [
                'access.manage',
                'access.roles.manage',
            ],
            'access_role_management_forbidden'
        );

        return [
            'role_areas' => $this->activeLookup('role_areas'),
            'role_kinds' => $this->activeLookup('role_kinds'),
            'permissions' => $this->permissions(),
            'scope_types' => $this->scopeTypes(),
            'identity_fields' => $this->identityRequirementFields(),
        ];
    }

    public function createRole(
        int $actorUserId,
        array $input,
        string $ip
    ): int {
        $this->authorizeAny(
            $actorUserId,
            [
                'access.manage',
                'access.roles.manage',
            ],
            'access_role_management_forbidden'
        );

        $code = strtolower(trim((string) ($input['code'] ?? '')));

        if (
            preg_match(
                '/^custom_[a-z][a-z0-9_]{2,60}$/',
                $code
            ) !== 1
        ) {
            throw new RuntimeException('access_role_code_invalid');
        }

        $title = trim((string) ($input['title'] ?? ''));

        if (
            $this->length($title) < 3
            || $this->length($title) > 120
        ) {
            throw new RuntimeException('access_role_title_invalid');
        }

        $roleAreaCode = strtolower(trim(
            (string) ($input['role_area_code'] ?? '')
        ));
        $roleKindCode = strtolower(trim(
            (string) ($input['role_kind_code'] ?? '')
        ));

        $roleAreaId = $this->lookupId('role_areas', $roleAreaCode);
        $roleKindId = $this->lookupId('role_kinds', $roleKindCode);

        if ($roleAreaId < 1 || $roleKindId < 1) {
            throw new RuntimeException(
                'access_role_classification_invalid'
            );
        }

        $priority = max(
            10,
            min(899, (int) ($input['priority'] ?? 100))
        );

        $permissions = $this->stringArray(
            $input['permissions'] ?? []
        );

        if ($permissions === []) {
            throw new RuntimeException(
                'access_role_permission_required'
            );
        }

        $scopeTypes = $this->stringArray(
            $input['scope_types'] ?? []
        );

        if ($scopeTypes === []) {
            throw new RuntimeException(
                'access_role_scope_required'
            );
        }

        $identityFields = $this->stringArray(
            $input['identity_fields'] ?? []
        );

        $reason = trim((string) ($input['reason'] ?? ''));

        if (
            $this->length($reason) < 3
            || $this->length($reason) > 500
        ) {
            throw new RuntimeException('access_reason_required');
        }

        $this->db->beginTransaction();

        try {
            $duplicate = $this->db->prepare("
                SELECT COUNT(*)
                FROM roles
                WHERE code = ?
            ");
            $duplicate->execute([$code]);

            if ((int) $duplicate->fetchColumn() !== 0) {
                throw new RuntimeException(
                    'access_role_code_duplicate'
                );
            }

            $insert = $this->db->prepare("
                INSERT INTO roles (
                    code,
                    title,
                    priority,
                    role_area_id,
                    role_kind_id,
                    is_system,
                    is_active,
                    is_editable,
                    is_deletable,
                    can_manage_other_users,
                    requires_center,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, 0, 1, 1, 1, ?, ?,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");

            $insert->execute([
                $code,
                $title,
                $priority,
                $roleAreaId,
                $roleKindId,
                !empty($input['can_manage_other_users']) ? 1 : 0,
                !empty($input['requires_center']) ? 1 : 0,
            ]);

            $roleId = (int) $this->db->lastInsertId();

            if ($roleId < 1) {
                throw new RuntimeException(
                    'access_role_create_failed'
                );
            }

            $this->saveRolePermissions(
                $roleId,
                $permissions
            );

            $this->saveRoleScopePolicies(
                $roleId,
                $scopeTypes
            );

            $this->saveIdentityRequirements(
                $roleId,
                $identityFields
            );

            $this->audit(
                $actorUserId,
                'role',
                $roleId,
                0,
                'custom_role_created',
                null,
                [
                    'code' => $code,
                    'title' => $title,
                    'role_area_code' => $roleAreaCode,
                    'role_kind_code' => $roleKindCode,
                    'priority' => $priority,
                    'permissions' => $permissions,
                    'scope_types' => $scopeTypes,
                    'identity_fields' => $identityFields,
                ],
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

    public function scopeEditor(
        int $actorUserId,
        int $assignmentId = 0
    ): array {
        $this->authorizeAny(
            $actorUserId,
            [
                'access.manage',
                'access.scopes.manage',
                'access.users.manage',
            ],
            'access_scope_management_forbidden'
        );

        $assignments = $this->assignments();

        $selected = null;

        if ($assignmentId > 0) {
            foreach ($assignments as $assignment) {
                if ((int) $assignment['id'] === $assignmentId) {
                    $selected = $assignment;
                    break;
                }
            }
        }

        if ($selected === null && $assignments !== []) {
            $selected = $assignments[0];
        }

        $selectedId = (int) ($selected['id'] ?? 0);
        $roleId = (int) ($selected['role_id'] ?? 0);

        return [
            'assignments' => $assignments,
            'selected_assignment' => $selected,
            'scope_types' => $roleId > 0
                ? $this->scopeTypesForRole($roleId)
                : [],
            'constraint_types' => $this->constraintTypes(),
            'assignment_scopes' => $selectedId > 0
                ? $this->assignmentScopes($selectedId)
                : [],
            'assignment_constraints' => $selectedId > 0
                ? $this->assignmentConstraints($selectedId)
                : [],
            'scope_options' => $this->scopeOptions(),
        ];
    }

    public function saveAssignmentPolicy(
        int $actorUserId,
        array $input,
        string $ip
    ): void {
        $this->authorizeAny(
            $actorUserId,
            [
                'access.manage',
                'access.scopes.manage',
                'access.users.manage',
            ],
            'access_scope_management_forbidden'
        );

        $assignmentId = max(
            0,
            (int) ($input['role_assignment_id'] ?? 0)
        );

        if ($assignmentId < 1) {
            throw new RuntimeException(
                'access_assignment_required'
            );
        }

        $assignment = $this->assignment($assignmentId);

        if ($assignment === null) {
            throw new RuntimeException(
                'access_assignment_not_found'
            );
        }

        $reason = trim((string) ($input['reason'] ?? ''));

        if (
            $this->length($reason) < 3
            || $this->length($reason) > 500
        ) {
            throw new RuntimeException('access_reason_required');
        }

        $allowedScopes = array_fill_keys(
            array_map(
                static fn (array $row): string =>
                    (string) $row['code'],
                $this->scopeTypesForRole(
                    (int) $assignment['role_id']
                )
            ),
            true
        );

        if ($allowedScopes === []) {
            throw new RuntimeException(
                'access_role_scope_policy_missing'
            );
        }

        $constraintCatalog = array_fill_keys(
            array_map(
                static fn (array $row): string =>
                    (string) $row['code'],
                $this->constraintTypes()
            ),
            true
        );

        $scopes = $this->normalizeScopes(
            is_array($input['scopes'] ?? null)
                ? $input['scopes']
                : [],
            $allowedScopes
        );

        if ($scopes === []) {
            throw new RuntimeException(
                'access_scope_at_least_one_required'
            );
        }

        $constraints = $this->normalizeConstraints(
            is_array($input['constraints'] ?? null)
                ? $input['constraints']
                : [],
            $constraintCatalog
        );

        $this->db->beginTransaction();

        try {
            $before = [
                'scopes' => $this->assignmentScopes($assignmentId),
                'constraints' =>
                    $this->assignmentConstraints($assignmentId),
            ];

            $delete = $this->db->prepare("
                DELETE FROM role_assignment_scopes
                WHERE role_assignment_id = ?
            ");
            $delete->execute([$assignmentId]);

            $insertScope = $this->db->prepare("
                INSERT INTO role_assignment_scopes (
                    role_assignment_id,
                    scope_type_code,
                    scope_reference,
                    effect_code,
                    include_descendants,
                    metadata_json,
                    created_by_user_id,
                    updated_by_user_id,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, NULL, ?, ?,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");

            foreach ($scopes as $scope) {
                $insertScope->execute([
                    $assignmentId,
                    $scope['type'],
                    $scope['reference'],
                    $scope['effect'],
                    $scope['include_descendants'] ? 1 : 0,
                    $actorUserId,
                    $actorUserId,
                ]);
            }

            $delete = $this->db->prepare("
                DELETE FROM role_assignment_constraints
                WHERE role_assignment_id = ?
            ");
            $delete->execute([$assignmentId]);

            $insertConstraint = $this->db->prepare("
                INSERT INTO role_assignment_constraints (
                    role_assignment_id,
                    constraint_type_code,
                    operator_code,
                    value_json,
                    effect_code,
                    sort_order,
                    created_by_user_id,
                    updated_by_user_id,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");

            $sortOrder = 10;

            foreach ($constraints as $constraint) {
                $insertConstraint->execute([
                    $assignmentId,
                    $constraint['type'],
                    $constraint['operator'],
                    json_encode(
                        $constraint['value'],
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_THROW_ON_ERROR
                    ),
                    $constraint['effect'],
                    $sortOrder,
                    $actorUserId,
                    $actorUserId,
                ]);

                $sortOrder += 10;
            }

            $after = [
                'scopes' => $scopes,
                'constraints' => $constraints,
            ];

            $after['lifecycle'] =
                (
                    new RoleAssignmentLifecycleService(
                        $this->db
                    )
                )->refreshAssignment(
                    $actorUserId,
                    $assignmentId
                );

            $this->audit(
                $actorUserId,
                'role_assignment',
                (int) $assignment['user_id'],
                $assignmentId,
                'assignment_scope_policy_updated',
                $before,
                $after,
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

    public function assignmentPolicy(int $assignmentId): array
    {
        return [
            'scopes' => $this->assignmentScopes($assignmentId),
            'constraints' =>
                $this->assignmentConstraints($assignmentId),
        ];
    }

    public function assignmentForUser(
        int $userId,
        int $assignmentId
    ): ?array {
        $statement = $this->db->prepare("
            SELECT
                assignments.*,
                roles.code AS role_code,
                roles.title AS role_title
            FROM user_role_assignments AS assignments
            INNER JOIN roles
                ON roles.id = assignments.role_id
            WHERE assignments.id = ?
              AND assignments.user_id = ?
              AND assignments.is_active = 1
              AND roles.is_active = 1
              AND (
                    assignments.starts_at IS NULL
                    OR assignments.starts_at
                        <= CURRENT_TIMESTAMP
                  )
              AND (
                    assignments.ends_at IS NULL
                    OR assignments.ends_at
                        >= CURRENT_TIMESTAMP
                  )
            LIMIT 1
        ");

        $statement->execute([
            $assignmentId,
            $userId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function roleHasExplicitScopePolicy(
        int $roleId
    ): bool {
        if (
            $roleId < 1
            || !Database::tableExists(
                'role_scope_policies'
            )
        ) {
            return false;
        }

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM role_scope_policies
                WHERE role_id = ?
            ");

        $statement->execute([
            $roleId,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }

    /**
     * Scope policies such as own/global/assigned require no external
     * entity reference. For exactly one required default policy of
     * this type, the role policy itself is sufficient to derive the
     * assignment scope.
     *
     * Concrete scopes (province/company/project/...) are never
     * inferred here and remain fail-closed until a reference is
     * explicitly assigned.
     */
    public function referenceFreeDefaultScopesForRole(
        int $roleId
    ): array {
        if (
            $roleId < 1
            || !Database::tableExists(
                'role_scope_policies'
            )
            || !Database::tableExists(
                'access_scope_types'
            )
        ) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    policies.scope_type_code,
                    policies.is_default
                FROM role_scope_policies
                    AS policies
                INNER JOIN access_scope_types
                    AS scope_types
                  ON scope_types.code =
                        policies.scope_type_code
                 AND scope_types.is_active = 1
                WHERE policies.role_id = ?
                  AND policies.is_required = 1
                ORDER BY
                    policies.is_default DESC,
                    policies.id
            ");

        $statement->execute([
            $roleId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        if (count($rows) !== 1) {
            return [];
        }

        $row = $rows[0];

        if (
            (int) (
                $row['is_default']
                ?? 0
            ) !== 1
        ) {
            return [];
        }

        $code =
            strtolower(
                trim(
                    (string) (
                        $row[
                            'scope_type_code'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            !in_array(
                $code,
                [
                    'global',
                    'own',
                    'assigned',
                ],
                true
            )
        ) {
            return [];
        }

        return [
            [
                'scope_type_code' =>
                    $code,
                'scope_reference' =>
                    '*',
                'effect_code' =>
                    'allow',
                'include_descendants' =>
                    0,
                'derived_from_role_policy' =>
                    1,
            ],
        ];
    }

    private function permissions(): array
    {
        return $this->db->query("
            SELECT id, code, module, resource, action, title
            FROM permissions
            WHERE is_active = 1
            ORDER BY module, resource, action, id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function activeLookup(string $table): array
    {
        if (!in_array($table, ['role_areas', 'role_kinds'], true)) {
            throw new RuntimeException('access_lookup_invalid');
        }

        $orderBy = 'sort_order, id';

        return $this->db->query("
            SELECT id, code, title
            FROM `{$table}`
            WHERE is_active = 1
            ORDER BY {$orderBy}
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function lookupId(
        string $table,
        string $code
    ): int {
        if (!in_array($table, ['role_areas', 'role_kinds'], true)) {
            return 0;
        }

        $statement = $this->db->prepare("
            SELECT id
            FROM `{$table}`
            WHERE code = ?
              AND is_active = 1
            LIMIT 1
        ");
        $statement->execute([$code]);

        return (int) ($statement->fetchColumn() ?: 0);
    }

    private function scopeTypes(): array
    {
        if (!Database::tableExists('access_scope_types')) {
            return [];
        }

        return $this->db->query("
            SELECT *
            FROM access_scope_types
            WHERE is_active = 1
            ORDER BY sort_order, id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function scopeTypesForRole(int $roleId): array
    {
        if (!Database::tableExists('role_scope_policies')) {
            return [];
        }

        $statement = $this->db->prepare("
            SELECT scope_types.*
            FROM role_scope_policies AS policies
            INNER JOIN access_scope_types AS scope_types
                ON scope_types.code = policies.scope_type_code
            WHERE policies.role_id = ?
              AND scope_types.is_active = 1
            ORDER BY scope_types.sort_order, scope_types.id
        ");
        $statement->execute([$roleId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        /*
         * Built-in/legacy roles predate role_scope_policies.
         * No policy on those roles preserves current behavior and
         * permits the administrator to add an explicit assignment policy.
         */
        return $rows !== []
            ? $rows
            : $this->scopeTypes();
    }

    private function constraintTypes(): array
    {
        if (!Database::tableExists('access_constraint_types')) {
            return [];
        }

        return $this->db->query("
            SELECT *
            FROM access_constraint_types
            WHERE is_active = 1
            ORDER BY sort_order, id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function assignments(): array
    {
        return $this->db->query("
            SELECT
                assignments.id,
                assignments.user_id,
                assignments.role_id,
                assignments.scope_type,
                '' AS scope_reference,
                assignments.is_active,
                assignments.lifecycle_status_code,
                assignments.starts_at,
                assignments.ends_at,
                roles.code AS role_code,
                roles.title AS role_title,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    NULLIF(users.email, ''),
                    CONCAT('کاربر ', users.id)
                ) AS user_title
            FROM user_role_assignments AS assignments
            INNER JOIN roles
                ON roles.id =
                    assignments.role_id
            INNER JOIN users
                ON users.id =
                    assignments.user_id
            LEFT JOIN persons
                ON persons.id =
                    users.person_id
            WHERE assignments.lifecycle_status_code
                    <> 'revoked'
              AND roles.is_active = 1
            ORDER BY
                user_title,
                roles.priority DESC,
                assignments.id
            LIMIT 2000
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function assignment(int $assignmentId): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                assignments.*,
                roles.code AS role_code,
                roles.title AS role_title
            FROM user_role_assignments AS assignments
            INNER JOIN roles
                ON roles.id = assignments.role_id
            WHERE assignments.id = ?
            LIMIT 1
        ");
        $statement->execute([$assignmentId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function assignmentScopes(int $assignmentId): array
    {
        if (!Database::tableExists('role_assignment_scopes')) {
            return [];
        }

        $statement = $this->db->prepare("
            SELECT *
            FROM role_assignment_scopes
            WHERE role_assignment_id = ?
            ORDER BY
                CASE effect_code
                    WHEN 'deny' THEN 0
                    ELSE 1
                END,
                id
        ");
        $statement->execute([$assignmentId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function assignmentConstraints(
        int $assignmentId
    ): array {
        if (!Database::tableExists('role_assignment_constraints')) {
            return [];
        }

        $statement = $this->db->prepare("
            SELECT *
            FROM role_assignment_constraints
            WHERE role_assignment_id = ?
            ORDER BY sort_order, id
        ");
        $statement->execute([$assignmentId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $decoded = json_decode(
                (string) ($row['value_json'] ?? ''),
                true
            );

            if (is_array($decoded)) {
                $row['value_text'] = implode(
                    ', ',
                    array_map('strval', $decoded)
                );
            } elseif ($decoded === null) {
                $row['value_text'] = '';
            } else {
                $row['value_text'] = (string) $decoded;
            }
        }
        unset($row);

        return $rows;
    }

    private function saveRolePermissions(
        int $roleId,
        array $codes
    ): void {
        $statement = $this->db->prepare("
            INSERT IGNORE INTO role_permissions (
                role_id,
                permission_id,
                created_at
            )
            SELECT ?, id, CURRENT_TIMESTAMP
            FROM permissions
            WHERE code = ?
              AND is_active = 1
        ");

        foreach ($codes as $code) {
            $check = $this->db->prepare("
                SELECT COUNT(*)
                FROM permissions
                WHERE code = ?
                  AND is_active = 1
            ");
            $check->execute([$code]);

            if ((int) $check->fetchColumn() !== 1) {
                throw new RuntimeException(
                    'access_permission_invalid'
                );
            }

            $statement->execute([
                $roleId,
                $code,
            ]);
        }
    }

    private function saveRoleScopePolicies(
        int $roleId,
        array $codes
    ): void {
        $insert = $this->db->prepare("
            INSERT INTO role_scope_policies (
                role_id,
                scope_type_code,
                is_required,
                is_default,
                created_at,
                updated_at
            )
            SELECT ?, code, 0, 0,
                   CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM access_scope_types
            WHERE code = ?
              AND is_active = 1
        ");

        foreach ($codes as $code) {
            $check = $this->db->prepare("
                SELECT COUNT(*)
                FROM access_scope_types
                WHERE code = ?
                  AND is_active = 1
            ");
            $check->execute([$code]);

            if ((int) $check->fetchColumn() !== 1) {
                throw new RuntimeException(
                    'access_scope_type_invalid'
                );
            }

            $insert->execute([
                $roleId,
                $code,
            ]);
        }
    }

    private function saveIdentityRequirements(
        int $roleId,
        array $fields
    ): void {
        $allowed = array_fill_keys(
            array_keys($this->identityRequirementFields()),
            true
        );

        $insert = $this->db->prepare("
            INSERT INTO role_identity_requirements (
                role_id,
                field_code,
                verification_mode_code,
                is_required,
                sort_order,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, 1, ?,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $sortOrder = 10;

        foreach ($fields as $field) {
            if (!isset($allowed[$field])) {
                throw new RuntimeException(
                    'access_identity_requirement_invalid'
                );
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

    private function normalizeScopes(
        array $rows,
        array $allowed
    ): array {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = strtolower(trim(
                (string) ($row['type'] ?? '')
            ));

            if ($type === '') {
                continue;
            }

            if (!isset($allowed[$type])) {
                throw new RuntimeException(
                    'access_scope_type_not_allowed'
                );
            }

            $reference = trim(
                (string) ($row['reference'] ?? '')
            );

            if (
                in_array(
                    $type,
                    ['global', 'own', 'assigned'],
                    true
                )
            ) {
                $reference = '*';
            }

            if (
                $reference === ''
                || $this->length($reference) > 190
            ) {
                throw new RuntimeException(
                    'access_scope_reference_invalid'
                );
            }

            $effect = strtolower(trim(
                (string) ($row['effect'] ?? 'allow')
            ));

            if (!in_array($effect, ['allow', 'deny'], true)) {
                throw new RuntimeException(
                    'access_scope_effect_invalid'
                );
            }

            $result[] = [
                'type' => $type,
                'reference' => $reference,
                'effect' => $effect,
                'include_descendants' =>
                    !empty($row['include_descendants']),
            ];
        }

        return $result;
    }

    private function normalizeConstraints(
        array $rows,
        array $catalog
    ): array {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = strtolower(trim(
                (string) ($row['type'] ?? '')
            ));

            if ($type === '') {
                continue;
            }

            if (!isset($catalog[$type])) {
                throw new RuntimeException(
                    'access_constraint_type_invalid'
                );
            }

            $operator = strtolower(trim(
                (string) ($row['operator'] ?? 'eq')
            ));

            if (
                !in_array(
                    $operator,
                    [
                        'eq',
                        'neq',
                        'in',
                        'not_in',
                        'contains',
                        'exists',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'access_constraint_operator_invalid'
                );
            }

            $effect = strtolower(trim(
                (string) ($row['effect'] ?? 'allow')
            ));

            if (!in_array($effect, ['allow', 'deny'], true)) {
                throw new RuntimeException(
                    'access_constraint_effect_invalid'
                );
            }

            $raw = trim((string) ($row['value'] ?? ''));

            if ($operator !== 'exists' && $raw === '') {
                throw new RuntimeException(
                    'access_constraint_value_required'
                );
            }

            $value = match ($operator) {
                'exists' => true,
                'in', 'not_in' =>
                    array_values(array_filter(
                        array_map('trim', explode(',', $raw)),
                        static fn (string $value): bool =>
                            $value !== ''
                    )),
                default => $raw,
            };

            $result[] = [
                'type' => $type,
                'operator' => $operator,
                'effect' => $effect,
                'value' => $value,
            ];
        }

        return $result;
    }

    private function identityRequirementFields(): array
    {
        return [
            'full_name' => 'نام و نام خانوادگی',
            'national_code' => 'کد ملی',
            'mobile' => 'موبایل تأییدشده',
            'email' => 'ایمیل تأییدشده',
            'province' => 'استان',
            'county' => 'شهرستان',
            'organization' => 'سازمان / شرکت',
            'position' => 'سمت / پست سازمانی',
        ];
    }

    private function scopeOptions(): array
    {
        return [
            'national' => $this->lookupOptions(
                ['organizations'],
                ['title', 'name', 'legal_name', 'display_name']
            ),
            'province' => $this->lookupOptions(
                ['provinces'],
                ['title', 'name', 'title_fa']
            ),
            'county' => $this->lookupOptions(
                ['counties', 'shahrestans'],
                ['title', 'name', 'title_fa']
            ),
            'district' => $this->lookupOptions(
                ['districts', 'bakhshs'],
                ['title', 'name', 'title_fa']
            ),
            'village' => $this->lookupOptions(
                ['villages', 'dehestans'],
                ['title', 'name', 'title_fa']
            ),
            'city' => $this->lookupOptions(
                ['cities'],
                ['title', 'name', 'title_fa']
            ),
            'organization' => $this->lookupOptions(
                ['organizations'],
                ['title', 'name', 'legal_name', 'display_name']
            ),
            'company' => $this->lookupOptions(
                ['organizations'],
                ['title', 'name', 'legal_name', 'display_name']
            ),
            'warehouse' => $this->lookupOptions(
                ['warehouses'],
                ['title', 'name', 'display_name']
            ),
            'center' => $this->lookupOptions(
                ['centers', 'organization_centers'],
                ['title', 'name', 'display_name']
            ),
            'org_unit' => $this->lookupOptions(
                [
                    'org_units',
                    'organization_units',
                ],
                ['title', 'name', 'display_name']
            ),
            'project' => $this->externalLookupOptions(
                'ticketing.primary',
                ['ticketing_projects', 'projects'],
                ['title', 'name', 'display_name']
            ),
        ];
    }

    private function externalLookupOptions(
        string $connection,
        array $tables,
        array $titleColumns
    ): array {
        try {
            $database = (new ConnectionResolver())->resolve($connection);
        } catch (Throwable) {
            return [];
        }

        foreach ($tables as $table) {
            try {
                $exists = $database->prepare('SHOW TABLES LIKE ?');
                $exists->execute([$table]);

                if (!$exists->fetchColumn()) {
                    continue;
                }

                $columns = $database->query(
                    "SHOW COLUMNS FROM `{$table}`"
                )->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $referenceColumn = null;

                foreach (['id', 'code', 'uuid', 'slug'] as $candidate) {
                    if (in_array($candidate, $columns, true)) {
                        $referenceColumn = $candidate;
                        break;
                    }
                }

                $titleColumn = null;

                foreach ($titleColumns as $candidate) {
                    if (in_array($candidate, $columns, true)) {
                        $titleColumn = $candidate;
                        break;
                    }
                }

                if ($referenceColumn === null || $titleColumn === null) {
                    continue;
                }

                $where = in_array('is_active', $columns, true)
                    ? 'WHERE is_active = 1'
                    : '';

                return $database->query("
                    SELECT
                        `{$referenceColumn}` AS option_value,
                        `{$titleColumn}` AS option_title
                    FROM `{$table}`
                    {$where}
                    ORDER BY `{$titleColumn}`
                    LIMIT 2000
                ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {
                continue;
            }
        }

        return [];
    }

    private function lookupOptions(
        array $tables,
        array $titleColumns
    ): array {
        foreach ($tables as $table) {
            if (!Database::tableExists($table)) {
                continue;
            }

            $referenceColumn = null;

            foreach (
                ['public_reference', 'code', 'id']
                as $candidate
            ) {
                if (Database::columnExists($table, $candidate)) {
                    $referenceColumn = $candidate;
                    break;
                }
            }

            if ($referenceColumn === null) {
                continue;
            }

            $titleColumn = null;

            foreach ($titleColumns as $candidate) {
                if (Database::columnExists($table, $candidate)) {
                    $titleColumn = $candidate;
                    break;
                }
            }

            if ($titleColumn === null) {
                continue;
            }

            $where = '';

            if (Database::columnExists($table, 'is_active')) {
                $where = 'WHERE is_active = 1';
            } elseif (Database::columnExists($table, 'status')) {
                $where = "WHERE status = 'active'";
            }

            return $this->db->query("
                SELECT
                    `{$referenceColumn}` AS option_value,
                    `{$titleColumn}` AS option_title
                FROM `{$table}`
                {$where}
                ORDER BY `{$titleColumn}`
                LIMIT 2000
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [];
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
                if (
                    $this->authorization->hasPermission(
                        $userId,
                        $permission
                    )
                ) {
                    return;
                }
            } catch (Throwable) {
            }
        }

        throw new RuntimeException($error);
    }

    private function audit(
        int $actorUserId,
        string $targetType,
        int $targetId,
        int $assignmentId,
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
                actor_user_id,
                target_type,
                target_id,
                role_assignment_id,
                change_type,
                old_value,
                new_value,
                reason,
                request_ip,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $statement->execute([
            $actorUserId,
            $targetType,
            $targetId,
            $assignmentId,
            $changeType,
            $oldValue === null
                ? null
                : json_encode(
                    $oldValue,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
            $newValue === null
                ? null
                : json_encode(
                    $newValue,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
            $reason !== '' ? $reason : null,
            $ip !== '' ? $ip : null,
        ]);
    }

    private function stringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(
            array_filter(
                array_map(
                    static fn ($item): string =>
                        trim((string) $item),
                    $value
                ),
                static fn (string $item): bool =>
                    $item !== ''
            )
        ));
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}

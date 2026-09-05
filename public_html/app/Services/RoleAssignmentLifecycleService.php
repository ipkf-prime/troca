<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

/**
 * ROLE_ASSIGNMENT_LIFECYCLE_SERVICE_V1
 *
 * A role assignment becomes effective only when:
 *
 * 1. required identity evidence exists;
 * 2. required role scopes are complete;
 * 3. the user account is active;
 * 4. the assignment itself is not revoked.
 *
 * Authorization continues to depend on user_role_assignments.is_active.
 * Pending assignments therefore fail closed in all existing RBAC paths.
 */
class RoleAssignmentLifecycleService extends BaseService
{
    private const REFERENCE_FREE_SCOPES = [
        'global',
        'own',
        'assigned',
    ];

    private PDO $db;

    public function __construct(
        ?PDO $db = null
    ) {
        $this->db =
            $db
            ?? (new ConnectionResolver())
                ->resolve('core.primary');
    }

    public function evaluate(
        int $userId,
        int $roleId,
        int $assignmentId = 0
    ): array {
        $user =
            $this->identitySnapshot(
                $userId
            );

        $role =
            $this->role(
                $roleId
            );

        if ($user === null) {
            throw new RuntimeException(
                'access_user_not_found'
            );
        }

        if ($role === null) {
            throw new RuntimeException(
                'access_role_not_found'
            );
        }

        $requirements =
            $this->identityRequirements(
                $roleId
            );

        $evidence =
            $this->identityEvidence(
                $user
            );

        $missingIdentity = [];

        if (
            (string) (
                $user['status']
                ?? ''
            ) !== 'active'
        ) {
            $missingIdentity[] =
                'account_active';
        }

        foreach (
            $requirements
            as $requirement
        ) {
            if (
                (int) (
                    $requirement[
                        'is_required'
                    ]
                    ?? 0
                ) !== 1
            ) {
                continue;
            }

            $field =
                (string) (
                    $requirement[
                        'field_code'
                    ]
                    ?? ''
                );

            $mode =
                strtolower(
                    trim(
                        (string) (
                            $requirement[
                                'verification_mode_code'
                            ]
                            ?? 'present'
                        )
                    )
                );

            if (
                !$this->identityRequirementSatisfied(
                    $field,
                    $mode,
                    $evidence
                )
            ) {
                $missingIdentity[] =
                    $field;
            }
        }

        $missingIdentity =
            array_values(
                array_unique(
                    $missingIdentity
                )
            );

        $scope =
            $this->scopeReadiness(
                $roleId,
                $assignmentId
            );

        $identityReady =
            $missingIdentity === [];

        $scopeReady =
            $scope[
                'missing_scope_types'
            ] === [];

        $eligible =
            $identityReady
            && $scopeReady;

        $pendingStatus =
            match (true) {
                !$identityReady
                    && !$scopeReady
                    => 'pending_identity_scope',

                !$identityReady
                    => 'pending_identity',

                !$scopeReady
                    => 'pending_scope',

                default
                    => 'eligible',
            };

        return [
            'eligible' =>
                $eligible,

            'pending_status_code' =>
                $pendingStatus,

            'missing_identity_fields' =>
                $missingIdentity,

            'missing_scope_types' =>
                $scope[
                    'missing_scope_types'
                ],

            'identity_requirements' =>
                $requirements,

            'required_scope_policies' =>
                $scope[
                    'required_scope_policies'
                ],

            'implicit_scope_types' =>
                $scope[
                    'implicit_scope_types'
                ],
        ];
    }

    public function requestAssignment(
        int $actorUserId,
        int $userId,
        int $roleId
    ): array {
        if (
            $actorUserId < 1
            || $userId < 1
            || $roleId < 1
        ) {
            throw new RuntimeException(
                'access_assignment_request_invalid'
            );
        }

        $role =
            $this->role(
                $roleId
            );

        if ($role === null) {
            throw new RuntimeException(
                'access_role_not_found'
            );
        }

        $this->assertActorCanAssignRole(
            $actorUserId,
            $roleId
        );

        if (
            (string) (
                $role['code']
                ?? ''
            ) === 'user'
        ) {
            throw new RuntimeException(
                'access_base_role_managed_separately'
            );
        }

        $this->assertLifecycleFoundation();

        $started =
            !$this->db
                ->inTransaction();

        if ($started) {
            $this->db
                ->beginTransaction();
        }

        try {
            $existing =
                $this->assignmentForRole(
                    $userId,
                    $roleId,
                    true
                );

            if ($existing === null) {
                $scopeType =
                    $this->defaultScopeType(
                        $roleId
                    );

                $insert =
                    $this->db->prepare("
                        INSERT INTO
                            user_role_assignments (
                                user_id,
                                role_id,
                                scope_type,
                                scope_id,
                                include_children,
                                starts_at,
                                ends_at,
                                is_active,
                                is_default,
                                lifecycle_status_code,
                                requested_at,
                                eligibility_checked_at,
                                eligible_at,
                                activated_at,
                                activated_by,
                                revoked_at,
                                revoked_by,
                                assigned_by,
                                created_at,
                                updated_at
                            )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            NULL,
                            0,
                            NULL,
                            NULL,
                            0,
                            0,
                            'pending_identity_scope',
                            CURRENT_TIMESTAMP,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            ?,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )
                    ");

                $insert->execute([
                    $userId,
                    $roleId,
                    $scopeType,
                    $actorUserId,
                ]);

                $assignmentId =
                    (int) $this->db
                        ->lastInsertId();

            } else {
                $assignmentId =
                    (int) $existing['id'];

                /*
                 * Re-requesting a previously revoked role must not
                 * silently restore an old concrete scope.
                 */
                if (
                    (string) (
                        $existing[
                            'lifecycle_status_code'
                        ]
                        ?? ''
                    ) === 'revoked'
                ) {
                    $this->clearAssignmentPolicy(
                        $assignmentId
                    );
                }

                $update =
                    $this->db->prepare("
                        UPDATE
                            user_role_assignments
                        SET
                            is_active = 0,
                            is_default = 0,
                            lifecycle_status_code =
                                'pending_identity_scope',
                            requested_at =
                                CURRENT_TIMESTAMP,
                            eligibility_checked_at =
                                NULL,
                            eligible_at = NULL,
                            revoked_at = NULL,
                            revoked_by = NULL,
                            assigned_by = ?,
                            updated_at =
                                CURRENT_TIMESTAMP
                        WHERE id = ?
                          AND user_id = ?
                          AND role_id = ?
                    ");

                $update->execute([
                    $actorUserId,
                    $assignmentId,
                    $userId,
                    $roleId,
                ]);
            }

            $result =
                $this->refreshAssignment(
                    $actorUserId,
                    $assignmentId
                );

            if ($started) {
                $this->db->commit();
            }

            return $result;

        } catch (Throwable $exception) {
            if (
                $started
                && $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        }
    }

    public function refreshAssignment(
        int $actorUserId,
        int $assignmentId
    ): array {
        $this->assertLifecycleFoundation();

        $assignment =
            $this->assignment(
                $assignmentId
            );

        if ($assignment === null) {
            throw new RuntimeException(
                'access_assignment_not_found'
            );
        }

        if (
            (string) (
                $assignment[
                    'lifecycle_status_code'
                ]
                ?? ''
            ) === 'revoked'
        ) {
            return [
                'assignment_id' =>
                    $assignmentId,

                'status' =>
                    'revoked',

                'active' =>
                    false,

                'eligible' =>
                    false,
            ];
        }

        $evaluation =
            $this->evaluate(
                (int) $assignment['user_id'],
                (int) $assignment['role_id'],
                $assignmentId
            );

        $active =
            (bool) (
                $evaluation['eligible']
                ?? false
            );

        $status =
            $active
                ? 'active'
                : (string) (
                    $evaluation[
                        'pending_status_code'
                    ]
                    ?? 'pending_identity_scope'
                );

        $activatedBy =
            $actorUserId > 0
                ? $actorUserId
                : null;

        $statement =
            $this->db->prepare("
                UPDATE user_role_assignments

                SET
                    is_active = ?,

                    is_default =
                        CASE
                            WHEN ? = 1
                                THEN is_default
                            ELSE 0
                        END,

                    lifecycle_status_code = ?,

                    eligibility_checked_at =
                        CURRENT_TIMESTAMP,

                    eligible_at =
                        CASE
                            WHEN ? = 1
                                THEN COALESCE(
                                    eligible_at,
                                    CURRENT_TIMESTAMP
                                )
                            ELSE NULL
                        END,

                    activated_at =
                        CASE
                            WHEN ? = 1
                                THEN COALESCE(
                                    activated_at,
                                    CURRENT_TIMESTAMP
                                )
                            ELSE activated_at
                        END,

                    activated_by =
                        CASE
                            WHEN ? = 1
                                THEN COALESCE(
                                    activated_by,
                                    ?
                                )
                            ELSE activated_by
                        END,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
            ");

        $flag =
            $active
                ? 1
                : 0;

        $statement->execute([
            $flag,
            $flag,
            $status,
            $flag,
            $flag,
            $flag,
            $activatedBy,
            $assignmentId,
        ]);

        return [
            'assignment_id' =>
                $assignmentId,

            'status' =>
                $status,

            'active' =>
                $active,

            'eligible' =>
                $active,

            'missing_identity_fields' =>
                $evaluation[
                    'missing_identity_fields'
                ] ?? [],

            'missing_scope_types' =>
                $evaluation[
                    'missing_scope_types'
                ] ?? [],
        ];
    }

    public function refreshUser(
        int $userId,
        int $actorUserId = 0
    ): array {
        $this->assertLifecycleFoundation();

        $statement =
            $this->db->prepare("
                SELECT
                    assignments.id
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.user_id = ?
                  AND roles.code <> 'user'
                  AND roles.is_active = 1
                  AND assignments.lifecycle_status_code
                        <> 'revoked'
                ORDER BY assignments.id
            ");

        $statement->execute([
            $userId,
        ]);

        $results = [];

        foreach (
            $statement->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: []
            as $assignmentId
        ) {
            $results[] =
                $this->refreshAssignment(
                    $actorUserId,
                    (int) $assignmentId
                );
        }

        return $results;
    }

    public function revokeAssignment(
        int $actorUserId,
        int $assignmentId
    ): void {
        if (
            $actorUserId < 1
            || $assignmentId < 1
        ) {
            throw new RuntimeException(
                'access_assignment_revoke_invalid'
            );
        }

        $this->assertLifecycleFoundation();

        $assignment =
            $this->assignment(
                $assignmentId
            );

        if ($assignment === null) {
            throw new RuntimeException(
                'access_assignment_not_found'
            );
        }

        $this->assertActorCanAssignRole(
            $actorUserId,
            (int) $assignment['role_id']
        );

        if (
            (string) (
                $assignment['role_code']
                ?? ''
            ) === 'user'
        ) {
            throw new RuntimeException(
                'access_base_role_revoke_forbidden'
            );
        }

        $statement =
            $this->db->prepare("
                UPDATE user_role_assignments
                SET
                    is_active = 0,
                    is_default = 0,
                    lifecycle_status_code =
                        'revoked',
                    revoked_at =
                        CURRENT_TIMESTAMP,
                    revoked_by = ?,
                    updated_at =
                        CURRENT_TIMESTAMP
                WHERE id = ?
            ");

        $statement->execute([
            $actorUserId,
            $assignmentId,
        ]);
    }

    public function syncSelection(
        int $actorUserId,
        int $userId,
        array $roleIds,
        array $preserveRoleIds = []
    ): array {
        $this->assertLifecycleFoundation();

        $desired =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            $roleIds
                        ),
                        static fn (
                            int $id
                        ): bool => $id > 0
                    )
                )
            );

        $preserve =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            $preserveRoleIds
                        ),
                        static fn (
                            int $id
                        ): bool => $id > 0
                    )
                )
            );

        $baseRoleId =
            $this->roleIdByCode(
                'user'
            );

        $desired =
            array_values(
                array_filter(
                    $desired,
                    static fn (
                        int $id
                    ): bool =>
                        $id !== $baseRoleId
                )
            );

        foreach ($desired as $roleId) {
            if ($this->role($roleId) === null) {
                throw new RuntimeException(
                    'access_role_not_found'
                );
            }

            $this->assertActorCanAssignRole(
                $actorUserId,
                $roleId
            );
        }

        $started =
            !$this->db
                ->inTransaction();

        if ($started) {
            $this->db
                ->beginTransaction();
        }

        try {
            $current =
                $this->activeOrPendingAssignments(
                    $userId
                );

            $desiredMap =
                array_fill_keys(
                    array_merge(
                        $desired,
                        $preserve
                    ),
                    true
                );

            foreach ($current as $assignment) {
                $roleId =
                    (int) $assignment['role_id'];

                if (
                    isset(
                        $desiredMap[
                            $roleId
                        ]
                    )
                ) {
                    continue;
                }

                $this->revokeAssignment(
                    $actorUserId,
                    (int) $assignment['id']
                );
            }

            $results = [];

            foreach ($desired as $roleId) {
                /*
                 * An explicitly preserved protected role is not
                 * re-evaluated merely because the user form was saved.
                 */
                if (
                    in_array(
                        $roleId,
                        $preserve,
                        true
                    )
                    && $this->assignmentForRole(
                        $userId,
                        $roleId
                    ) !== null
                ) {
                    continue;
                }

                $existing =
                    $this->assignmentForRole(
                        $userId,
                        $roleId
                    );

                if (
                    $existing === null
                    || (string) (
                        $existing[
                            'lifecycle_status_code'
                        ]
                        ?? ''
                    ) === 'revoked'
                ) {
                    $results[] =
                        $this->requestAssignment(
                            $actorUserId,
                            $userId,
                            $roleId
                        );

                    continue;
                }

                $results[] =
                    $this->refreshAssignment(
                        $actorUserId,
                        (int) $existing['id']
                    );
            }

            if ($started) {
                $this->db->commit();
            }

            return $results;

        } catch (Throwable $exception) {
            if (
                $started
                && $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        }
    }

    public function userRoleStates(
        int $userId
    ): array {
        if (
            !$this->columnExists(
                'user_role_assignments',
                'lifecycle_status_code'
            )
        ) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    assignments.id,
                    assignments.role_id,
                    assignments.is_active,
                    assignments.is_default,
                    assignments.lifecycle_status_code,
                    assignments.requested_at,
                    assignments.eligibility_checked_at,
                    assignments.eligible_at,
                    assignments.activated_at,
                    assignments.revoked_at,
                    roles.code AS role_code,
                    roles.title AS role_title
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.user_id = ?
                  AND roles.is_active = 1
                ORDER BY
                    roles.priority DESC,
                    assignments.id
            ");

        $statement->execute([
            $userId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    private function assertActorCanAssignRole(
        int $actorUserId,
        int $roleId
    ): void {
        if (
            $actorUserId < 1
            || $roleId < 1
        ) {
            throw new RuntimeException(
                'access_role_assignment_forbidden'
            );
        }

        $target =
            $this->role(
                $roleId
            );

        if ($target === null) {
            throw new RuntimeException(
                'access_role_not_found'
            );
        }

        $statement =
            $this->db->prepare("
                SELECT
                    roles.code,
                    roles.priority
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.user_id = ?
                  AND assignments.is_active = 1
                  AND assignments.lifecycle_status_code =
                        'active'
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
                ORDER BY
                    roles.priority DESC,
                    assignments.id
            ");

        $statement->execute([
            $actorUserId,
        ]);

        $actorRoles =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        if ($actorRoles === []) {
            throw new RuntimeException(
                'access_role_assignment_forbidden'
            );
        }

        foreach ($actorRoles as $actorRole) {
            if (
                (string) (
                    $actorRole['code']
                    ?? ''
                ) === 'super_admin'
            ) {
                return;
            }
        }

        $maximumPriority =
            max(
                array_map(
                    static fn (
                        array $row
                    ): int =>
                        (int) (
                            $row['priority']
                            ?? 0
                        ),
                    $actorRoles
                )
            );

        if (
            (string) (
                $target['code']
                ?? ''
            ) === 'super_admin'
            || (int) (
                $target['priority']
                ?? 0
            ) >= $maximumPriority
        ) {
            throw new RuntimeException(
                'access_role_assignment_forbidden'
            );
        }
    }

    private function identityRequirementSatisfied(
        string $field,
        string $mode,
        array $evidence
    ): bool {
        $available =
            (bool) (
                $evidence[$field]
                ?? false
            );

        if ($mode === 'verified') {
            return $available;
        }

        return $available;
    }

    private function identityEvidence(
        array $user
    ): array {
        $personId =
            (int) (
                $user['person_id']
                ?? 0
            );

        $geography =
            $this->geographyEvidence(
                $personId,
                $user
            );

        $organization =
            $this->organizationEvidence(
                (int) $user['id'],
                $personId
            );

        return [
            'full_name' =>
                trim(
                    (string) (
                        $user['full_name']
                        ?? ''
                    )
                ) !== '',

            'national_code' =>
                trim(
                    (string) (
                        $user['national_code']
                        ?? ''
                    )
                ) !== '',

            'mobile' =>
                trim(
                    (string) (
                        $user['mobile_norm']
                        ?? ''
                    )
                ) !== ''
                && !empty(
                    $user[
                        'mobile_verified_at'
                    ]
                ),

            'email' =>
                trim(
                    (string) (
                        $user['email_norm']
                        ?? ''
                    )
                ) !== ''
                && !empty(
                    $user[
                        'email_verified_at'
                    ]
                ),

            'province' =>
                isset(
                    $geography[
                        'province'
                    ]
                ),

            'county' =>
                isset(
                    $geography[
                        'county'
                    ]
                ),

            'organization' =>
                $organization[
                    'organization'
                ],

            'position' =>
                $organization[
                    'position'
                ],
        ];
    }

    private function identitySnapshot(
        int $userId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    users.id,
                    users.person_id,
                    users.status,
                    users.email_norm,
                    users.mobile_norm,
                    users.email_verified_at,
                    users.mobile_verified_at,

                    persons.full_name,
                    persons.national_code,
                    persons.province_id,
                    persons.city_id

                FROM users

                LEFT JOIN persons
                    ON persons.id =
                        users.person_id

                WHERE users.id = ?
                  AND users.deleted_at
                        IS NULL

                LIMIT 1
            ");

        $statement->execute([
            $userId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    private function geographyEvidence(
        int $personId,
        array $user
    ): array {
        $levels = [];

        if (
            (int) (
                $user['province_id']
                ?? 0
            ) > 0
        ) {
            $levels['province'] =
                true;
        }

        if (
            (int) (
                $user['city_id']
                ?? 0
            ) > 0
        ) {
            $levels['city'] =
                true;

            $this->legacyCityEvidence(
                (int) $user['city_id'],
                $levels
            );
        }

        if (
            $personId < 1
            || !$this->tableExists(
                'person_addresses'
            )
        ) {
            return $levels;
        }

        $hasDynamic =
            $this->columnExists(
                'person_addresses',
                'geographic_location_id'
            );

        $dynamicSelect =
            $hasDynamic
                ? 'geographic_location_id'
                : 'NULL AS geographic_location_id';

        $statement =
            $this->db->prepare("
                SELECT
                    province_id,
                    city_id,
                    {$dynamicSelect}
                FROM person_addresses
                WHERE person_id = ?
                  AND status = 'active'
                ORDER BY
                    is_primary DESC,
                    id
                LIMIT 20
            ");

        $statement->execute([
            $personId,
        ]);

        foreach (
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
            as $address
        ) {
            if (
                (int) (
                    $address['province_id']
                    ?? 0
                ) > 0
            ) {
                $levels['province'] =
                    true;
            }

            if (
                (int) (
                    $address['city_id']
                    ?? 0
                ) > 0
            ) {
                $levels['city'] =
                    true;

                $this->legacyCityEvidence(
                    (int) $address['city_id'],
                    $levels
                );
            }

            $locationId =
                (int) (
                    $address[
                        'geographic_location_id'
                    ]
                    ?? 0
                );

            if ($locationId > 0) {
                foreach (
                    $this->dynamicGeographyLevels(
                        $locationId
                    )
                    as $code
                ) {
                    $levels[$code] =
                        true;
                }
            }
        }

        return $levels;
    }

    private function legacyCityEvidence(
        int $cityId,
        array &$levels
    ): void {
        if (
            $cityId < 1
            || !$this->tableExists(
                'cities'
            )
        ) {
            return;
        }

        $columns = [];

        foreach (
            [
                'province_id',
                'county_id',
            ]
            as $column
        ) {
            if (
                $this->columnExists(
                    'cities',
                    $column
                )
            ) {
                $columns[] =
                    $column;
            }
        }

        if ($columns === []) {
            return;
        }

        $statement =
            $this->db->prepare(
                'SELECT '
                . implode(', ', $columns)
                . ' FROM cities '
                . 'WHERE id = ? LIMIT 1'
            );

        $statement->execute([
            $cityId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return;
        }

        if (
            (int) (
                $row['province_id']
                ?? 0
            ) > 0
        ) {
            $levels['province'] =
                true;
        }

        if (
            (int) (
                $row['county_id']
                ?? 0
            ) > 0
        ) {
            $levels['county'] =
                true;
        }
    }

    private function dynamicGeographyLevels(
        int $locationId
    ): array {
        if (
            !$this->tableExists(
                'geographic_locations'
            )
            || !$this->tableExists(
                'geographic_level_types'
            )
        ) {
            return [];
        }

        /*
         * Always include the selected location itself.
         */
        $direct =
            $this->db->prepare("
                SELECT levels.code
                FROM geographic_locations
                    AS locations
                INNER JOIN geographic_level_types
                    AS levels
                  ON levels.id =
                        locations.level_type_id
                WHERE locations.id = ?
                  AND locations.status =
                        'active'
                  AND levels.status =
                        'active'
                LIMIT 1
            ");

        $direct->execute([
            $locationId,
        ]);

        $codes = [];

        $directCode =
            $direct->fetchColumn();

        if ($directCode !== false) {
            $codes[] =
                (string) $directCode;
        }

        if (
            !$this->tableExists(
                'geographic_location_relations'
            )
            || !$this->tableExists(
                'geographic_relation_types'
            )
        ) {
            return
                array_values(
                    array_unique(
                        $codes
                    )
                );
        }

        try {
            $statement =
                $this->db->prepare("
                    WITH RECURSIVE ancestry AS (
                        SELECT
                            locations.id,
                            locations.level_type_id,
                            0 AS depth
                        FROM geographic_locations
                            AS locations
                        WHERE locations.id = ?

                        UNION ALL

                        SELECT
                            parent.id,
                            parent.level_type_id,
                            ancestry.depth + 1
                        FROM ancestry

                        INNER JOIN
                            geographic_location_relations
                            AS relations
                          ON relations.child_location_id =
                                ancestry.id
                         AND relations.status =
                                'active'

                        INNER JOIN
                            geographic_relation_types
                            AS relation_types
                          ON relation_types.id =
                                relations.relation_type_id
                         AND relation_types.status =
                                'active'
                         AND relation_types.is_hierarchical =
                                1

                        INNER JOIN
                            geographic_locations
                            AS parent
                          ON parent.id =
                                relations.parent_location_id
                         AND parent.status =
                                'active'

                        WHERE ancestry.depth < 12
                    )

                    SELECT DISTINCT
                        levels.code

                    FROM ancestry

                    INNER JOIN
                        geographic_level_types
                        AS levels
                      ON levels.id =
                            ancestry.level_type_id

                    WHERE levels.status =
                        'active'
                ");

            $statement->execute([
                $locationId,
            ]);

            foreach (
                $statement->fetchAll(
                    PDO::FETCH_COLUMN
                ) ?: []
                as $code
            ) {
                $codes[] =
                    (string) $code;
            }

        } catch (Throwable) {
            /*
             * Direct-level evidence remains valid even on a
             * database engine without recursive CTE support.
             */
        }

        return
            array_values(
                array_unique(
                    array_filter(
                        $codes
                    )
                )
            );
    }

    private function organizationEvidence(
        int $userId,
        int $personId
    ): array {
        $organization =
            false;

        $position =
            false;

        if (
            $this->tableExists(
                'user_org_assignments'
            )
        ) {
            $statement =
                $this->db->prepare("
                    SELECT
                        org_unit_id,
                        position_id
                    FROM user_org_assignments
                    WHERE user_id = ?
                      AND status = 'active'
                      AND (
                            started_at IS NULL
                            OR started_at
                                <= CURRENT_TIMESTAMP
                          )
                      AND (
                            ended_at IS NULL
                            OR ended_at
                                >= CURRENT_TIMESTAMP
                          )
                    ORDER BY
                        is_primary DESC,
                        id
                ");

            $statement->execute([
                $userId,
            ]);

            foreach (
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: []
                as $row
            ) {
                if (
                    (int) (
                        $row['org_unit_id']
                        ?? 0
                    ) > 0
                ) {
                    $organization =
                        true;
                }

                if (
                    (int) (
                        $row['position_id']
                        ?? 0
                    ) > 0
                ) {
                    $position =
                        true;
                }
            }
        }

        if (
            $personId > 0
            && $this->tableExists(
                'organization_appointments'
            )
        ) {
            $statement =
                $this->db->prepare("
                    SELECT
                        organization_id,
                        organization_position_id
                    FROM organization_appointments
                    WHERE person_id = ?
                      AND status = 'active'
                      AND revoked_at IS NULL
                      AND (
                            valid_from IS NULL
                            OR valid_from
                                <= CURRENT_DATE
                          )
                      AND (
                            valid_to IS NULL
                            OR valid_to
                                >= CURRENT_DATE
                          )
                    ORDER BY
                        is_primary DESC,
                        id
                ");

            $statement->execute([
                $personId,
            ]);

            foreach (
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: []
                as $row
            ) {
                if (
                    (int) (
                        $row['organization_id']
                        ?? 0
                    ) > 0
                ) {
                    $organization =
                        true;
                }

                if (
                    (int) (
                        $row[
                            'organization_position_id'
                        ]
                        ?? 0
                    ) > 0
                ) {
                    $position =
                        true;
                }
            }
        }

        return [
            'organization' =>
                $organization,

            'position' =>
                $position,
        ];
    }

    private function scopeReadiness(
        int $roleId,
        int $assignmentId
    ): array {
        if (
            !$this->tableExists(
                'role_scope_policies'
            )
        ) {
            return [
                'required_scope_policies' =>
                    [],

                'missing_scope_types' =>
                    [],

                'implicit_scope_types' =>
                    [],
            ];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    scope_type_code,
                    is_required,
                    is_default
                FROM role_scope_policies
                WHERE role_id = ?
                ORDER BY
                    is_default DESC,
                    id
            ");

        $statement->execute([
            $roleId,
        ]);

        $policies =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $required =
            array_values(
                array_filter(
                    $policies,
                    static fn (
                        array $row
                    ): bool =>
                        (int) (
                            $row[
                                'is_required'
                            ]
                            ?? 0
                        ) === 1
                )
            );

        $missing = [];
        $implicit = [];

        foreach ($required as $policy) {
            $code =
                strtolower(
                    trim(
                        (string) (
                            $policy[
                                'scope_type_code'
                            ]
                            ?? ''
                        )
                    )
                );

            if ($code === '') {
                continue;
            }

            if (
                in_array(
                    $code,
                    self::REFERENCE_FREE_SCOPES,
                    true
                )
                && (int) (
                    $policy[
                        'is_default'
                    ]
                    ?? 0
                ) === 1
            ) {
                $implicit[] =
                    $code;

                continue;
            }

            if (
                $assignmentId < 1
                || !$this->assignmentHasRequiredScope(
                    $assignmentId,
                    $code
                )
            ) {
                $missing[] =
                    $code;
            }
        }

        return [
            'required_scope_policies' =>
                $required,

            'missing_scope_types' =>
                array_values(
                    array_unique(
                        $missing
                    )
                ),

            'implicit_scope_types' =>
                array_values(
                    array_unique(
                        $implicit
                    )
                ),
        ];
    }

    private function assignmentHasRequiredScope(
        int $assignmentId,
        string $scopeType
    ): bool {
        if (
            !$this->tableExists(
                'role_assignment_scopes'
            )
        ) {
            return false;
        }

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM role_assignment_scopes
                WHERE role_assignment_id = ?
                  AND scope_type_code = ?
                  AND effect_code = 'allow'
                  AND TRIM(
                        scope_reference
                      ) <> ''
            ");

        $statement->execute([
            $assignmentId,
            $scopeType,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function identityRequirements(
        int $roleId
    ): array {
        if (
            !$this->tableExists(
                'role_identity_requirements'
            )
        ) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    field_code,
                    verification_mode_code,
                    is_required,
                    sort_order
                FROM role_identity_requirements
                WHERE role_id = ?
                ORDER BY
                    sort_order,
                    id
            ");

        $statement->execute([
            $roleId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    private function defaultScopeType(
        int $roleId
    ): string {
        if (
            !$this->tableExists(
                'role_scope_policies'
            )
        ) {
            return 'global';
        }

        $statement =
            $this->db->prepare("
                SELECT scope_type_code
                FROM role_scope_policies
                WHERE role_id = ?
                ORDER BY
                    is_default DESC,
                    is_required DESC,
                    id
                LIMIT 1
            ");

        $statement->execute([
            $roleId,
        ]);

        $code =
            strtolower(
                trim(
                    (string) (
                        $statement
                            ->fetchColumn()
                        ?: ''
                    )
                )
            );

        return
            $code !== ''
                ? $code
                : 'global';
    }

    private function role(
        int $roleId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM roles
                WHERE id = ?
                  AND is_active = 1
                LIMIT 1
            ");

        $statement->execute([
            $roleId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    private function roleIdByCode(
        string $code
    ): int {
        $statement =
            $this->db->prepare("
                SELECT id
                FROM roles
                WHERE code = ?
                  AND is_active = 1
                LIMIT 1
            ");

        $statement->execute([
            $code,
        ]);

        return
            (int) (
                $statement
                    ->fetchColumn()
                ?: 0
            );
    }

    private function assignment(
        int $assignmentId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    assignments.*,
                    roles.code AS role_code,
                    roles.title AS role_title
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.id = ?
                LIMIT 1
            ");

        $statement->execute([
            $assignmentId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    private function assignmentForRole(
        int $userId,
        int $roleId,
        bool $lock = false
    ): ?array {
        $sql = "
            SELECT
                assignments.*
            FROM user_role_assignments
                AS assignments
            WHERE assignments.user_id = ?
              AND assignments.role_id = ?
            ORDER BY
                assignments.is_active DESC,
                assignments.id
            LIMIT 1
        ";

        if (
            $lock
            && $this->db
                ->inTransaction()
        ) {
            $sql .= ' FOR UPDATE';
        }

        $statement =
            $this->db->prepare(
                $sql
            );

        $statement->execute([
            $userId,
            $roleId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }

    private function activeOrPendingAssignments(
        int $userId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    assignments.*
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.user_id = ?
                  AND roles.code <> 'user'
                  AND assignments.lifecycle_status_code
                        <> 'revoked'
                ORDER BY assignments.id
            ");

        $statement->execute([
            $userId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    private function clearAssignmentPolicy(
        int $assignmentId
    ): void {
        if (
            $this->tableExists(
                'role_assignment_scopes'
            )
        ) {
            $statement =
                $this->db->prepare("
                    DELETE FROM role_assignment_scopes
                    WHERE role_assignment_id = ?
                ");

            $statement->execute([
                $assignmentId,
            ]);
        }

        if (
            $this->tableExists(
                'role_assignment_constraints'
            )
        ) {
            $statement =
                $this->db->prepare("
                    DELETE FROM role_assignment_constraints
                    WHERE role_assignment_id = ?
                ");

            $statement->execute([
                $assignmentId,
            ]);
        }
    }

    private function assertLifecycleFoundation(): void
    {
        if (
            !$this->columnExists(
                'user_role_assignments',
                'lifecycle_status_code'
            )
        ) {
            throw new RuntimeException(
                'role_assignment_lifecycle_not_migrated'
            );
        }
    }

    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }
}

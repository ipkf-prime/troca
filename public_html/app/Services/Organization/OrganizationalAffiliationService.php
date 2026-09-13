<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Services\AuthorizationService;
use App\Services\DynamicAccessService;
use App\Services\RoleAssignmentLifecycleService;
use App\Services\ScopedAuthorizationService;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Generic Core organizational affiliation workflow.
 *
 * Structure, real-world position and system access remain separate:
 *
 * organization_memberships
 *     affiliation claim + verification
 *
 * organization_appointments
 *     approved real-world organizational position
 *
 * user_role_assignments + role_assignment_scopes
 *     application authorization
 */
final class OrganizationalAffiliationService
{
    private PDO $db;

    private AuthorizationService $authorization;

    private ScopedAuthorizationService $scopedAuthorization;

    private ?array $organizationMap = null;


    public function __construct(
        ?PDO $db = null,
        ?AuthorizationService $authorization = null,
        ?ScopedAuthorizationService $scopedAuthorization = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'core.primary'
            );

        $this->authorization =
            $authorization
            ?? new AuthorizationService();

        $this->scopedAuthorization =
            $scopedAuthorization
            ?? new ScopedAuthorizationService();
    }


    public function selfPage(
        int $userId
    ): array {

        return [
            'identity' =>
                $this->userIdentity(
                    $userId
                ),

            'organizations' =>
                $this->organizationOptions(),

            'positions' =>
                $this->positionOptions(),

            'memberships' =>
                $this->userMemberships(
                    $userId
                ),
        ];
    }


    public function request(
        int $userId,
        array $input
    ): string {

        $identity =
            $this->userIdentity(
                $userId
            );

        if (
            (int) (
                $identity[
                    'person_id'
                ]
                ?? 0
            ) < 1
            ||
            trim(
                (string) (
                    $identity[
                        'person_reference'
                    ]
                    ?? ''
                )
            ) === ''
        ) {
            throw new RuntimeException(
                'affiliation_person_required'
            );
        }


        $organizationReference =
            trim(
                (string) (
                    $input[
                        'organization_reference'
                    ]
                    ?? ''
                )
            );

        $positionReference =
            trim(
                (string) (
                    $input[
                        'position_reference'
                    ]
                    ?? ''
                )
            );

        $requestedPrimary =
            !empty(
                $input[
                    'is_primary'
                ]
            );


        if ($organizationReference === '') {
            throw new RuntimeException(
                'affiliation_organization_required'
            );
        }


        $organization =
            $this->organizationByReference(
                $organizationReference
            );

        if ($organization === null) {
            throw new RuntimeException(
                'affiliation_organization_invalid'
            );
        }


        $position = null;

        if ($positionReference !== '') {

            $position =
                $this->positionByReference(
                    $positionReference
                );

            if (
                $position === null
                ||
                (int) (
                    $position[
                        'organization_id'
                    ]
                    ?? 0
                )
                !==
                (int) $organization['id']
            ) {
                throw new RuntimeException(
                    'affiliation_position_invalid'
                );
            }
        }


        $started =
            !$this->db
                ->inTransaction();

        if ($started) {
            $this->db
                ->beginTransaction();
        }


        try {

            $existingStatement =
                $this->db
                    ->prepare("
                        SELECT *
                        FROM organization_memberships
                        WHERE user_id = ?
                          AND organization_id = ?
                          AND status = 'active'
                        ORDER BY id DESC
                        LIMIT 1
                        FOR UPDATE
                    ");

            $existingStatement->execute([
                $userId,
                (int) $organization['id'],
            ]);

            $membership =
                $existingStatement
                    ->fetch(
                        PDO::FETCH_ASSOC
                    )
                ?: null;


            if (
                is_array(
                    $membership
                )
                &&
                (
                    string
                ) (
                    $membership[
                        'verification_state_code'
                    ]
                    ?? ''
                )
                === 'verified'
            ) {
                throw new RuntimeException(
                    'affiliation_already_verified'
                );
            }


            $metadata =
                $this->decodeMetadata(
                    is_array(
                        $membership
                    )
                        ?
                        (string) (
                            $membership[
                                'metadata_json'
                            ]
                            ?? ''
                        )
                        : ''
                );


            $metadata[
                'workflow_code'
            ] =
                'core_organizational_affiliation';

            $metadata[
                'requested_position_reference'
            ] =
                $positionReference !== ''
                    ? $positionReference
                    : null;

            $metadata[
                'requested_position_title'
            ] =
                is_array(
                    $position
                )
                    ?
                    (
                        string
                    ) (
                        $position[
                            'display_path'
                        ]
                        ?? ''
                    )
                    : null;

            $metadata[
                'requested_primary'
            ] =
                $requestedPrimary
                    ? 1
                    : 0;

            $metadata[
                'requested_by_user_id'
            ] =
                $userId;

            $metadata[
                'requested_at'
            ] =
                gmdate(
                    'c'
                );


            $metadataJson =
                json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE
                    |
                    JSON_UNESCAPED_SLASHES
                    |
                    JSON_THROW_ON_ERROR
                );


            if (
                is_array(
                    $membership
                )
            ) {

                $membershipId =
                    (int) $membership['id'];

                $membershipReference =
                    (string) (
                        $membership[
                            'public_reference'
                        ]
                        ?? ''
                    );


                $update =
                    $this->db
                        ->prepare("
                            UPDATE organization_memberships
                            SET
                                person_id = ?,
                                role_code = 'member',
                                verification_state_code =
                                    'unverified',
                                source_code =
                                    'core_affiliation',
                                source_reference = ?,
                                metadata_json = ?,
                                updated_at =
                                    CURRENT_TIMESTAMP
                            WHERE id = ?
                        ");

                $update->execute([
                    (int) $identity['person_id'],
                    $organizationReference,
                    $metadataJson,
                    $membershipId,
                ]);

            } else {

                $insert =
                    $this->db
                        ->prepare("
                            INSERT INTO
                                organization_memberships (
                                    public_reference,
                                    organization_id,
                                    person_id,
                                    user_id,
                                    role_code,
                                    is_primary,
                                    verification_state_code,
                                    status,
                                    source_code,
                                    source_reference,
                                    metadata_json,
                                    created_at,
                                    updated_at
                                )
                            VALUES (
                                UUID(),
                                ?,
                                ?,
                                ?,
                                'member',
                                0,
                                'unverified',
                                'active',
                                'core_affiliation',
                                ?,
                                ?,
                                CURRENT_TIMESTAMP,
                                CURRENT_TIMESTAMP
                            )
                        ");

                $insert->execute([
                    (int) $organization['id'],
                    (int) $identity['person_id'],
                    $userId,
                    $organizationReference,
                    $metadataJson,
                ]);


                $membershipId =
                    (int) $this->db
                        ->lastInsertId();


                $reference =
                    $this->db
                        ->prepare("
                            SELECT public_reference
                            FROM organization_memberships
                            WHERE id = ?
                            LIMIT 1
                        ");

                $reference->execute([
                    $membershipId,
                ]);

                $membershipReference =
                    (string) (
                        $reference
                            ->fetchColumn()
                        ?: ''
                    );
            }


            if (
                $membershipId < 1
                ||
                $membershipReference === ''
            ) {
                throw new RuntimeException(
                    'affiliation_request_failed'
                );
            }


            $pending =
                $this->db
                    ->prepare("
                        SELECT id
                        FROM
                            organization_membership_verifications
                        WHERE membership_id = ?
                          AND verification_type_code =
                                'organizational_affiliation'
                          AND status_code = 'pending'
                        ORDER BY id DESC
                        LIMIT 1
                        FOR UPDATE
                    ");

            $pending->execute([
                $membershipId,
            ]);


            $pendingId =
                (int) (
                    $pending
                        ->fetchColumn()
                    ?: 0
                );


            if ($pendingId > 0) {

                $updateVerification =
                    $this->db
                        ->prepare("
                            UPDATE
                                organization_membership_verifications
                            SET
                                metadata_json = ?,
                                updated_at =
                                    CURRENT_TIMESTAMP
                            WHERE id = ?
                        ");

                $updateVerification->execute([
                    $metadataJson,
                    $pendingId,
                ]);

            } else {

                $insertVerification =
                    $this->db
                        ->prepare("
                            INSERT INTO
                                organization_membership_verifications (
                                    public_reference,
                                    membership_id,
                                    verification_type_code,
                                    status_code,
                                    metadata_json,
                                    created_at,
                                    updated_at
                                )
                            VALUES (
                                UUID(),
                                ?,
                                'organizational_affiliation',
                                'pending',
                                ?,
                                CURRENT_TIMESTAMP,
                                CURRENT_TIMESTAMP
                            )
                        ");

                $insertVerification->execute([
                    $membershipId,
                    $metadataJson,
                ]);
            }


            if ($started) {
                $this->db
                    ->commit();
            }


            return
                $membershipReference;

        } catch (Throwable $exception) {

            if (
                $started
                &&
                $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        }
    }


    public function managerPage(
        int $actorUserId,
        string $filter = 'pending'
    ): array {

        $this->authorizeManager(
            $actorUserId
        );


        $filter =
            in_array(
                $filter,
                [
                    'pending',
                    'verified',
                    'rejected',
                    'all',
                ],
                true
            )
                ? $filter
                : 'pending';


        $rows =
            $this->db
                ->query("
                    SELECT
                        memberships.*,

                        organizations.public_reference
                            AS organization_reference,

                        COALESCE(
                            NULLIF(
                                organizations.title_fa,
                                ''
                            ),
                            organizations.title
                        ) AS organization_title,

                        persons.public_reference
                            AS person_reference,

                        COALESCE(
                            NULLIF(
                                persons.display_name_fa,
                                ''
                            ),
                            persons.full_name
                        ) AS person_title,

                        users.username,

                        verifications.status_code
                            AS request_status_code,

                        verifications.verified_at
                            AS request_decided_at

                    FROM organization_memberships
                        AS memberships

                    INNER JOIN organizations
                        ON organizations.id =
                            memberships.organization_id

                    INNER JOIN persons
                        ON persons.id =
                            memberships.person_id

                    LEFT JOIN users
                        ON users.id =
                            memberships.user_id

                    LEFT JOIN
                        organization_membership_verifications
                        AS verifications

                        ON verifications.id = (
                            SELECT MAX(v2.id)
                            FROM
                                organization_membership_verifications
                                AS v2
                            WHERE
                                v2.membership_id =
                                    memberships.id
                              AND
                                v2.verification_type_code =
                                    'organizational_affiliation'
                        )

                    WHERE memberships.source_code =
                        'core_affiliation'

                    ORDER BY
                        memberships.updated_at DESC,
                        memberships.id DESC

                    LIMIT 500
                ")
                ->fetchAll(
                    PDO::FETCH_ASSOC
                )
            ?: [];


        $positionMap =
            $this->positionMap();

        $items = [];


        foreach ($rows as $row) {

            $organizationReference =
                (string) (
                    $row[
                        'organization_reference'
                    ]
                    ?? ''
                );


            if (
                !$this->canManageOrganization(
                    $actorUserId,
                    $organizationReference
                )
            ) {
                continue;
            }


            $state =
                $this->requestState(
                    $row
                );


            if (
                $filter !== 'all'
                &&
                $state !== $filter
            ) {
                continue;
            }


            $metadata =
                $this->decodeMetadata(
                    (string) (
                        $row[
                            'metadata_json'
                        ]
                        ?? ''
                    )
                );


            $positionReference =
                trim(
                    (string) (
                        $metadata[
                            'requested_position_reference'
                        ]
                        ?? ''
                    )
                );


            $row[
                'workflow_state'
            ] =
                $state;


            $row[
                'requested_position_reference'
            ] =
                $positionReference;


            $row[
                'requested_position_title'
            ] =
                (string) (
                    $positionMap[
                        $positionReference
                    ][
                        'display_path'
                    ]
                    ??
                    $metadata[
                        'requested_position_title'
                    ]
                    ??
                    ''
                );


            $row[
                'requested_primary'
            ] =
                !empty(
                    $metadata[
                        'requested_primary'
                    ]
                );


            $items[] =
                $row;
        }


        $canGrantAccess =
            $this->canGrantAccess(
                $actorUserId
            );


        return [
            'items' =>
                $items,

            'filter' =>
                $filter,

            'roles' =>
                $canGrantAccess
                    ? $this->grantableRoles()
                    : [],

            'can_grant_access' =>
                $canGrantAccess,
        ];
    }


    public function decide(
        int $actorUserId,
        string $membershipReference,
        string $decision,
        int $roleId,
        bool $includeDescendants,
        string $ip
    ): array {

        $this->authorizeManager(
            $actorUserId
        );


        $membershipReference =
            trim(
                $membershipReference
            );

        $decision =
            strtolower(
                trim(
                    $decision
                )
            );


        if (
            $membershipReference === ''
            ||
            !in_array(
                $decision,
                [
                    'approve',
                    'reject',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'affiliation_decision_invalid'
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

            $statement =
                $this->db
                    ->prepare("
                        SELECT
                            memberships.*,

                            organizations.public_reference
                                AS organization_reference,

                            persons.public_reference
                                AS person_reference

                        FROM organization_memberships
                            AS memberships

                        INNER JOIN organizations
                            ON organizations.id =
                                memberships.organization_id

                        INNER JOIN persons
                            ON persons.id =
                                memberships.person_id

                        WHERE
                            memberships.public_reference = ?
                          AND
                            memberships.source_code =
                                'core_affiliation'

                        LIMIT 1
                        FOR UPDATE
                    ");


            $statement->execute([
                $membershipReference,
            ]);


            $membership =
                $statement
                    ->fetch(
                        PDO::FETCH_ASSOC
                    )
                ?: null;


            if (
                !is_array(
                    $membership
                )
            ) {
                throw new RuntimeException(
                    'affiliation_membership_not_found'
                );
            }


            $organizationReference =
                (string) (
                    $membership[
                        'organization_reference'
                    ]
                    ?? ''
                );


            if (
                !$this->canManageOrganization(
                    $actorUserId,
                    $organizationReference
                )
            ) {
                throw new RuntimeException(
                    'affiliation_management_forbidden'
                );
            }


            $metadata =
                $this->decodeMetadata(
                    (string) (
                        $membership[
                            'metadata_json'
                        ]
                        ?? ''
                    )
                );


            $requestedPositionReference =
                trim(
                    (string) (
                        $metadata[
                            'requested_position_reference'
                        ]
                        ?? ''
                    )
                );


            $requestedPrimary =
                !empty(
                    $metadata[
                        'requested_primary'
                    ]
                );


            if ($decision === 'reject') {

                $update =
                    $this->db
                        ->prepare("
                            UPDATE organization_memberships
                            SET
                                status = 'inactive',
                                verification_state_code =
                                    'unverified',
                                is_primary = 0,
                                updated_at =
                                    CURRENT_TIMESTAMP
                            WHERE id = ?
                        ");

                $update->execute([
                    (int) $membership['id'],
                ]);


                $this->closePendingVerification(
                    (int) $membership['id'],
                    'rejected',
                    $actorUserId
                );


                if ($started) {
                    $this->db
                        ->commit();
                }


                return [
                    'status' =>
                        'rejected',

                    'access_status' =>
                        null,
                ];
            }


            if ($requestedPrimary) {

                $clearPrimary =
                    $this->db
                        ->prepare("
                            UPDATE organization_memberships
                            SET
                                is_primary = 0,
                                updated_at =
                                    CURRENT_TIMESTAMP
                            WHERE user_id = ?
                              AND id <> ?
                              AND status = 'active'
                        ");

                $clearPrimary->execute([
                    (int) $membership['user_id'],
                    (int) $membership['id'],
                ]);
            }


            $update =
                $this->db
                    ->prepare("
                        UPDATE organization_memberships
                        SET
                            status = 'active',
                            verification_state_code =
                                'verified',
                            is_primary = ?,
                            verified_at =
                                CURRENT_TIMESTAMP,
                            approved_by_user_id = ?,
                            updated_at =
                                CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");


            $update->execute([
                $requestedPrimary
                    ? 1
                    : 0,

                $actorUserId,

                (int) $membership['id'],
            ]);


            $this->closePendingVerification(
                (int) $membership['id'],
                'verified',
                $actorUserId
            );


            if (
                $requestedPositionReference
                !== ''
            ) {

                $position =
                    $this->positionByReference(
                        $requestedPositionReference
                    );


                if (
                    $position === null
                    ||
                    (
                        int
                    ) (
                        $position[
                            'organization_id'
                        ]
                        ?? 0
                    )
                    !==
                    (int) (
                        $membership[
                            'organization_id'
                        ]
                        ?? 0
                    )
                ) {
                    throw new RuntimeException(
                        'affiliation_position_invalid'
                    );
                }


                $exists =
                    $this->db
                        ->prepare("
                            SELECT COUNT(*)
                            FROM organization_appointments
                            WHERE person_id = ?
                              AND organization_position_id = ?
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
                        ");


                $exists->execute([
                    (int) $membership['person_id'],
                    (int) $position['id'],
                ]);


                if (
                    (int) (
                        $exists
                            ->fetchColumn()
                    ) === 0
                ) {

                    $appointment = [
                        'person_reference' =>
                            (string) $membership[
                                'person_reference'
                            ],

                        'position_reference' =>
                            $requestedPositionReference,

                        'appointment_kind' =>
                            'permanent',

                        'description' =>
                            'ایجادشده از گردش تأیید وابستگی سازمانی.',
                    ];


                    if ($requestedPrimary) {
                        $appointment[
                            'is_primary'
                        ] =
                            '1';
                    }


                    (
                        new OrganizationOperationsService(
                            $this->db
                        )
                    )->createAppointment(
                        $appointment,
                        $actorUserId
                    );
                }
            }


            if ($started) {
                $this->db
                    ->commit();
            }

        } catch (Throwable $exception) {

            if (
                $started
                &&
                $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        }


        /*
         * Access assignment is deliberately the second stage.
         *
         * Organizational truth is not rolled back merely because a
         * privileged role cannot be granted. Access remains fail-closed
         * and the manager can retry it from Access Control.
         */

        $accessStatus = null;
        $accessError = null;


        if ($roleId > 0) {

            try {

                $accessStatus =
                    $this->grantRoleScope(
                        $actorUserId,
                        (int) $membership[
                            'user_id'
                        ],
                        $roleId,
                        $organizationReference,
                        $includeDescendants,
                        $ip
                    );

            } catch (Throwable $exception) {

                $accessError =
                    $exception
                        ->getMessage();
            }
        }


        return [
            'status' =>
                'approved',

            'access_status' =>
                $accessStatus,

            'access_error' =>
                $accessError,
        ];
    }


    private function grantRoleScope(
        int $actorUserId,
        int $userId,
        int $roleId,
        string $organizationReference,
        bool $includeDescendants,
        string $ip
    ): string {

        if (
            !$this->canGrantAccess(
                $actorUserId
            )
        ) {
            throw new RuntimeException(
                'affiliation_access_grant_forbidden'
            );
        }


        $scopeType =
            $this->scopeTypeForRole(
                $roleId
            );


        $lifecycle =
            new RoleAssignmentLifecycleService(
                $this->db
            );


        /*
         * Never re-request an existing non-revoked assignment.
         * requestAssignment() intentionally resets lifecycle state;
         * doing that here would disturb an already-valid grant.
         */

        $existingStatement =
            $this->db
                ->prepare("
                    SELECT
                        id,
                        lifecycle_status_code
                    FROM user_role_assignments
                    WHERE user_id = ?
                      AND role_id = ?
                      AND lifecycle_status_code
                            <> 'revoked'
                    ORDER BY id DESC
                    LIMIT 1
                ");


        $existingStatement->execute([
            $userId,
            $roleId,
        ]);


        $existing =
            $existingStatement
                ->fetch(
                    PDO::FETCH_ASSOC
                )
            ?: null;


        $newlyRequested = false;


        if (
            is_array(
                $existing
            )
        ) {

            $assignmentId =
                (int) $existing['id'];

        } else {

            $requested =
                $lifecycle
                    ->requestAssignment(
                        $actorUserId,
                        $userId,
                        $roleId
                    );


            $assignmentId =
                (int) (
                    $requested[
                        'assignment_id'
                    ]
                    ?? 0
                );


            $newlyRequested = true;
        }


        if ($assignmentId < 1) {
            throw new RuntimeException(
                'affiliation_access_assignment_failed'
            );
        }


        $dynamic =
            new DynamicAccessService(
                $this->db,
                $this->authorization
            );


        /*
         * Preserve any pre-existing scopes and constraints.
         * The canonical writer replaces the complete policy set,
         * so the current set must be merged before saving.
         */

        $policy =
            $dynamic
                ->assignmentPolicy(
                    $assignmentId
                );


        $scopes = [];


        foreach (
            (array) (
                $policy[
                    'scopes'
                ]
                ?? []
            )
            as $scope
        ) {

            if (
                !is_array(
                    $scope
                )
            ) {
                continue;
            }


            $scopes[] = [
                'type' =>
                    (string) (
                        $scope[
                            'scope_type_code'
                        ]
                        ?? ''
                    ),

                'reference' =>
                    (string) (
                        $scope[
                            'scope_reference'
                        ]
                        ?? ''
                    ),

                'effect' =>
                    (string) (
                        $scope[
                            'effect_code'
                        ]
                        ?? 'allow'
                    ),

                'include_descendants' =>
                    !empty(
                        $scope[
                            'include_descendants'
                        ]
                    ),
            ];
        }


        $alreadyPresent = false;


        foreach ($scopes as $scope) {

            if (
                $scope['type']
                    === $scopeType
                &&
                $scope['reference']
                    === $organizationReference
                &&
                $scope['effect']
                    === 'allow'
            ) {
                $alreadyPresent = true;
                break;
            }
        }


        if (!$alreadyPresent) {

            $scopes[] = [
                'type' =>
                    $scopeType,

                'reference' =>
                    $organizationReference,

                'effect' =>
                    'allow',

                'include_descendants' =>
                    $includeDescendants,
            ];
        }


        $constraints = [];


        foreach (
            (array) (
                $policy[
                    'constraints'
                ]
                ?? []
            )
            as $constraint
        ) {

            if (
                !is_array(
                    $constraint
                )
            ) {
                continue;
            }


            $operator =
                (string) (
                    $constraint[
                        'operator_code'
                    ]
                    ?? 'eq'
                );


            $decoded =
                json_decode(
                    (string) (
                        $constraint[
                            'value_json'
                        ]
                        ?? 'null'
                    ),
                    true
                );


            if (is_array($decoded)) {

                $value =
                    implode(
                        ',',
                        array_map(
                            'strval',
                            $decoded
                        )
                    );

            } elseif (is_bool($decoded)) {

                $value =
                    $decoded
                        ? '1'
                        : '0';

            } elseif ($decoded === null) {

                $value = '';

            } else {

                $value =
                    (string) $decoded;
            }


            $constraints[] = [
                'type' =>
                    (string) (
                        $constraint[
                            'constraint_type_code'
                        ]
                        ?? ''
                    ),

                'operator' =>
                    $operator,

                'value' =>
                    $value,

                'effect' =>
                    (string) (
                        $constraint[
                            'effect_code'
                        ]
                        ?? 'allow'
                    ),
            ];
        }


        try {

            $dynamic
                ->saveAssignmentPolicy(
                    $actorUserId,
                    [
                        'role_assignment_id' =>
                            $assignmentId,

                        'scopes' =>
                            $scopes,

                        'constraints' =>
                            $constraints,

                        'reason' =>
                            'تأیید وابستگی سازمانی',
                    ],
                    $ip
                );

        } catch (Throwable $exception) {

            /*
             * A newly-created privileged assignment may temporarily
             * become lifecycle-eligible before its concrete scope is
             * saved. If scope assignment fails, revoke that new grant.
             *
             * Existing grants are never revoked by this failure path.
             */

            if ($newlyRequested) {

                try {

                    $lifecycle
                        ->revokeAssignment(
                            $actorUserId,
                            $assignmentId
                        );

                } catch (Throwable) {
                }
            }

            throw $exception;
        }


        $refreshed =
            $lifecycle
                ->refreshAssignment(
                    $actorUserId,
                    $assignmentId
                );


        return
            (string) (
                $refreshed[
                    'status'
                ]
                ?? 'pending'
            );
    }


    private function closePendingVerification(
        int $membershipId,
        string $status,
        int $actorUserId
    ): void {

        $update =
            $this->db
                ->prepare("
                    UPDATE
                        organization_membership_verifications
                    SET
                        status_code = ?,
                        verified_by_user_id = ?,
                        verified_at =
                            CURRENT_TIMESTAMP,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE membership_id = ?
                      AND verification_type_code =
                            'organizational_affiliation'
                      AND status_code = 'pending'
                ");


        $update->execute([
            $status,
            $actorUserId,
            $membershipId,
        ]);


        if (
            $update->rowCount()
            > 0
        ) {
            return;
        }


        $insert =
            $this->db
                ->prepare("
                    INSERT INTO
                        organization_membership_verifications (
                            public_reference,
                            membership_id,
                            verification_type_code,
                            status_code,
                            verified_by_user_id,
                            verified_at,
                            created_at,
                            updated_at
                        )
                    VALUES (
                        UUID(),
                        ?,
                        'organizational_affiliation',
                        ?,
                        ?,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");


        $insert->execute([
            $membershipId,
            $status,
            $actorUserId,
        ]);
    }


    private function userIdentity(
        int $userId
    ): array {

        if ($userId < 1) {
            throw new RuntimeException(
                'affiliation_user_invalid'
            );
        }


        $statement =
            $this->db
                ->prepare("
                    SELECT
                        users.id AS user_id,
                        users.person_id,
                        users.username,

                        persons.public_reference
                            AS person_reference,

                        COALESCE(
                            NULLIF(
                                persons.display_name_fa,
                                ''
                            ),
                            persons.full_name,
                            users.username
                        ) AS person_title

                    FROM users

                    LEFT JOIN persons
                        ON persons.id =
                            users.person_id

                    WHERE users.id = ?
                      AND users.status = 'active'
                      AND users.deleted_at IS NULL

                    LIMIT 1
                ");


        $statement->execute([
            $userId,
        ]);


        $row =
            $statement
                ->fetch(
                    PDO::FETCH_ASSOC
                );


        if (
            !is_array(
                $row
            )
        ) {
            throw new RuntimeException(
                'affiliation_user_not_found'
            );
        }


        return
            $row;
    }


    private function organizationByReference(
        string $reference
    ): ?array {

        $statement =
            $this->db
                ->prepare("
                    SELECT
                        id,
                        parent_id,
                        public_reference,

                        COALESCE(
                            NULLIF(
                                title_fa,
                                ''
                            ),
                            title
                        ) AS title

                    FROM organizations

                    WHERE public_reference = ?
                      AND is_active = 1
                      AND deleted_at IS NULL

                    LIMIT 1
                ");


        $statement->execute([
            $reference,
        ]);


        $row =
            $statement
                ->fetch(
                    PDO::FETCH_ASSOC
                );


        return
            is_array($row)
                ? $row
                : null;
    }


    private function positionByReference(
        string $reference
    ): ?array {

        $statement =
            $this->db
                ->prepare("
                    SELECT
                        org_positions.id,
                        org_positions.public_reference,
                        org_positions.organization_id,

                        organizations.public_reference
                            AS organization_reference,

                        COALESCE(
                            NULLIF(
                                org_positions.title_fa,
                                ''
                            ),
                            NULLIF(
                                org_positions.title_override,
                                ''
                            ),
                            position_catalog.title
                        ) AS title,

                        COALESCE(
                            NULLIF(
                                org_units.title_fa,
                                ''
                            ),
                            org_units.title
                        ) AS unit_title,

                        COALESCE(
                            NULLIF(
                                organizations.title_fa,
                                ''
                            ),
                            organizations.title
                        ) AS organization_title

                    FROM organization_positions
                        AS org_positions

                    INNER JOIN organizations
                        ON organizations.id =
                            org_positions.organization_id

                    INNER JOIN positions
                        AS position_catalog
                        ON position_catalog.id =
                            org_positions.position_id

                    LEFT JOIN org_units
                        ON org_units.id =
                            org_positions.org_unit_id

                    WHERE
                        org_positions.public_reference = ?
                      AND
                        org_positions.status = 'active'
                      AND
                        organizations.is_active = 1

                    LIMIT 1
                ");


        $statement->execute([
            $reference,
        ]);


        $row =
            $statement
                ->fetch(
                    PDO::FETCH_ASSOC
                );


        if (
            !is_array(
                $row
            )
        ) {
            return null;
        }


        $row[
            'display_path'
        ] =
            $this->positionDisplayPath(
                $row
            );


        return
            $row;
    }


    private function organizationOptions(): array
    {
        $map =
            $this->organizationMap();

        $rows = [];


        foreach ($map as $row) {

            if (
                (int) (
                    $row[
                        'is_active'
                    ]
                    ?? 0
                ) !== 1
            ) {
                continue;
            }


            $row[
                'display_path'
            ] =
                $this->organizationPath(
                    (int) $row['id'],
                    $map
                );


            $rows[] =
                $row;
        }


        usort(
            $rows,
            static function (
                array $a,
                array $b
            ): int {
                return strcmp(
                    (string) (
                        $a[
                            'display_path'
                        ]
                        ?? ''
                    ),
                    (string) (
                        $b[
                            'display_path'
                        ]
                        ?? ''
                    )
                );
            }
        );


        return
            $rows;
    }


    private function organizationMap(): array
    {
        if (
            $this->organizationMap
            !== null
        ) {
            return
                $this->organizationMap;
        }


        $rows =
            $this->db
                ->query("
                    SELECT
                        id,
                        parent_id,
                        public_reference,

                        COALESCE(
                            NULLIF(
                                title_fa,
                                ''
                            ),
                            title
                        ) AS title,

                        is_active

                    FROM organizations
                    WHERE deleted_at IS NULL
                    ORDER BY title, id
                ")
                ->fetchAll(
                    PDO::FETCH_ASSOC
                )
            ?: [];


        $this->organizationMap = [];


        foreach ($rows as $row) {

            $this->organizationMap[
                (int) $row['id']
            ] =
                $row;
        }


        return
            $this->organizationMap;
    }


    private function organizationPath(
        int $id,
        array $map
    ): string {

        $parts = [];
        $guard = 0;


        while (
            $id > 0
            &&
            isset(
                $map[$id]
            )
            &&
            $guard++ < 40
        ) {

            array_unshift(
                $parts,
                (string) (
                    $map[$id][
                        'title'
                    ]
                    ?? ''
                )
            );


            $id =
                (int) (
                    $map[$id][
                        'parent_id'
                    ]
                    ?? 0
                );
        }


        return
            implode(
                ' ← ',
                $parts
            );
    }


    private function organizationAncestorReferences(
        string $reference
    ): array {

        $organization =
            $this->organizationByReference(
                $reference
            );


        if (
            $organization === null
        ) {
            return [];
        }


        $map =
            $this->organizationMap();


        $id =
            (int) (
                $organization[
                    'parent_id'
                ]
                ?? 0
            );


        $references = [];
        $guard = 0;


        while (
            $id > 0
            &&
            isset(
                $map[$id]
            )
            &&
            $guard++ < 40
        ) {

            $ancestorReference =
                trim(
                    (string) (
                        $map[$id][
                            'public_reference'
                        ]
                        ?? ''
                    )
                );


            if (
                $ancestorReference
                !== ''
            ) {
                $references[] =
                    $ancestorReference;
            }


            $id =
                (int) (
                    $map[$id][
                        'parent_id'
                    ]
                    ?? 0
                );
        }


        return
            $references;
    }


    private function positionOptions(): array
    {
        $rows =
            $this->db
                ->query("
                    SELECT
                        org_positions.id,
                        org_positions.public_reference,

                        organizations.public_reference
                            AS organization_reference,

                        org_positions.organization_id,

                        COALESCE(
                            NULLIF(
                                org_positions.title_fa,
                                ''
                            ),
                            NULLIF(
                                org_positions.title_override,
                                ''
                            ),
                            position_catalog.title
                        ) AS title,

                        COALESCE(
                            NULLIF(
                                org_units.title_fa,
                                ''
                            ),
                            org_units.title
                        ) AS unit_title,

                        COALESCE(
                            NULLIF(
                                organizations.title_fa,
                                ''
                            ),
                            organizations.title
                        ) AS organization_title

                    FROM organization_positions
                        AS org_positions

                    INNER JOIN organizations
                        ON organizations.id =
                            org_positions.organization_id

                    INNER JOIN positions
                        AS position_catalog
                        ON position_catalog.id =
                            org_positions.position_id

                    LEFT JOIN org_units
                        ON org_units.id =
                            org_positions.org_unit_id

                    WHERE
                        org_positions.status = 'active'
                      AND
                        organizations.is_active = 1
                      AND
                        organizations.deleted_at IS NULL

                    ORDER BY
                        organization_title,
                        unit_title,
                        title,
                        org_positions.id

                    LIMIT 3000
                ")
                ->fetchAll(
                    PDO::FETCH_ASSOC
                )
            ?: [];


        foreach ($rows as &$row) {

            $row[
                'display_path'
            ] =
                $this->positionDisplayPath(
                    $row
                );
        }

        unset(
            $row
        );


        return
            $rows;
    }


    private function positionMap(): array
    {
        $map = [];


        foreach (
            $this->positionOptions()
            as $row
        ) {

            $map[
                (string) (
                    $row[
                        'public_reference'
                    ]
                    ?? ''
                )
            ] =
                $row;
        }


        return
            $map;
    }


    private function positionDisplayPath(
        array $row
    ): string {

        $parts = [
            trim(
                (string) (
                    $row[
                        'organization_title'
                    ]
                    ?? ''
                )
            ),

            trim(
                (string) (
                    $row[
                        'unit_title'
                    ]
                    ?? ''
                )
            ),

            trim(
                (string) (
                    $row[
                        'title'
                    ]
                    ?? ''
                )
            ),
        ];


        return
            implode(
                ' ← ',
                array_values(
                    array_filter(
                        $parts,
                        static fn (
                            string $part
                        ): bool =>
                            $part !== ''
                    )
                )
            );
    }


    private function userMemberships(
        int $userId
    ): array {

        $statement =
            $this->db
                ->prepare("
                    SELECT
                        memberships.*,

                        organizations.public_reference
                            AS organization_reference,

                        COALESCE(
                            NULLIF(
                                organizations.title_fa,
                                ''
                            ),
                            organizations.title
                        ) AS organization_title,

                        verifications.status_code
                            AS request_status_code

                    FROM organization_memberships
                        AS memberships

                    INNER JOIN organizations
                        ON organizations.id =
                            memberships.organization_id

                    LEFT JOIN
                        organization_membership_verifications
                        AS verifications

                        ON verifications.id = (
                            SELECT MAX(v2.id)
                            FROM
                                organization_membership_verifications
                                AS v2
                            WHERE
                                v2.membership_id =
                                    memberships.id
                              AND
                                v2.verification_type_code =
                                    'organizational_affiliation'
                        )

                    WHERE memberships.user_id = ?

                    ORDER BY
                        memberships.updated_at DESC,
                        memberships.id DESC

                    LIMIT 100
                ");


        $statement->execute([
            $userId,
        ]);


        $rows =
            $statement
                ->fetchAll(
                    PDO::FETCH_ASSOC
                )
            ?: [];


        $positionMap =
            $this->positionMap();


        foreach ($rows as &$row) {

            $metadata =
                $this->decodeMetadata(
                    (string) (
                        $row[
                            'metadata_json'
                        ]
                        ?? ''
                    )
                );


            $positionReference =
                trim(
                    (string) (
                        $metadata[
                            'requested_position_reference'
                        ]
                        ?? ''
                    )
                );


            $row[
                'workflow_state'
            ] =
                $this->requestState(
                    $row
                );


            $row[
                'requested_position_title'
            ] =
                (string) (
                    $positionMap[
                        $positionReference
                    ][
                        'display_path'
                    ]
                    ??
                    $metadata[
                        'requested_position_title'
                    ]
                    ??
                    ''
                );
        }

        unset(
            $row
        );


        return
            $rows;
    }


    private function requestState(
        array $row
    ): string {

        $requestStatus =
            strtolower(
                trim(
                    (string) (
                        $row[
                            'request_status_code'
                        ]
                        ?? ''
                    )
                )
            );


        if (
            $requestStatus
            === 'rejected'
        ) {
            return 'rejected';
        }


        if (
            (
                string
            ) (
                $row[
                    'verification_state_code'
                ]
                ?? ''
            )
            === 'verified'
            &&
            (
                string
            ) (
                $row[
                    'status'
                ]
                ?? ''
            )
            === 'active'
        ) {
            return 'verified';
        }


        return
            'pending';
    }


    private function grantableRoles(): array
    {
        return
            $this->db
                ->query("
                    SELECT
                        roles.id,
                        roles.code,
                        roles.title,
                        roles.priority,

                        COUNT(
                            policies.id
                        ) AS policy_count,

                        SUM(
                            CASE
                                WHEN
                                    policies.scope_type_code
                                    IN (
                                        'organization',
                                        'company'
                                    )
                                THEN 1
                                ELSE 0
                            END
                        ) AS compatible_scope_count

                    FROM roles

                    LEFT JOIN role_scope_policies
                        AS policies
                        ON policies.role_id =
                            roles.id

                    WHERE roles.is_active = 1
                      AND roles.code NOT IN (
                            'user',
                            'super_admin'
                          )

                    GROUP BY
                        roles.id,
                        roles.code,
                        roles.title,
                        roles.priority

                    HAVING
                        policy_count = 0
                        OR
                        compatible_scope_count > 0

                    ORDER BY
                        roles.priority DESC,
                        roles.title,
                        roles.id
                ")
                ->fetchAll(
                    PDO::FETCH_ASSOC
                )
            ?: [];
    }


    private function scopeTypeForRole(
        int $roleId
    ): string {

        $statement =
            $this->db
                ->prepare("
                    SELECT scope_type_code
                    FROM role_scope_policies
                    WHERE role_id = ?
                    ORDER BY
                        CASE scope_type_code
                            WHEN 'organization'
                                THEN 0
                            WHEN 'company'
                                THEN 1
                            ELSE 2
                        END,
                        id
                ");


        $statement->execute([
            $roleId,
        ]);


        $policies =
            array_map(
                'strval',
                $statement
                    ->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                ?: []
            );


        /*
         * Legacy roles without explicit policy retain existing
         * compatibility and receive an explicit organization scope.
         */

        if ($policies === []) {
            return 'organization';
        }


        if (
            in_array(
                'organization',
                $policies,
                true
            )
        ) {
            return 'organization';
        }


        if (
            in_array(
                'company',
                $policies,
                true
            )
        ) {
            return 'company';
        }


        throw new RuntimeException(
            'affiliation_role_scope_incompatible'
        );
    }


    private function authorizeManager(
        int $actorUserId
    ): void {

        if (
            $actorUserId > 0
            &&
            $this->authorization
                ->hasPermission(
                    $actorUserId,
                    'organizations.manage'
                )
        ) {
            return;
        }


        throw new RuntimeException(
            'affiliation_management_forbidden'
        );
    }


    private function canGrantAccess(
        int $actorUserId
    ): bool {

        foreach (
            [
                'access.manage',
                'access.users.manage',
                'access.scopes.manage',
            ]
            as $permission
        ) {

            if (
                $this->authorization
                    ->hasPermission(
                        $actorUserId,
                        $permission
                    )
            ) {
                return true;
            }
        }


        return false;
    }


    private function canManageOrganization(
        int $actorUserId,
        string $organizationReference
    ): bool {

        if (
            $organizationReference === ''
        ) {
            return false;
        }


        $ancestors =
            $this->organizationAncestorReferences(
                $organizationReference
            );


        /*
         * The generic structure is organization-based.
         * The same ancestor chain can satisfy legacy company/national
         * scope kinds while consumers migrate toward organization.
         */

        $ancestorMap = [
            'organization' =>
                $ancestors,

            'company' =>
                $ancestors,

            'national' =>
                $ancestors,
        ];


        if (
            $this->scopedAuthorization
                ->hasPermissionInContext(
                    $actorUserId,
                    'organizations.manage',
                    [
                        'scope_type' =>
                            'organization',

                        'scope_reference' =>
                            $organizationReference,

                        'ancestors' =>
                            $ancestorMap,

                        'attributes' =>
                            [],
                    ]
                )
        ) {
            return true;
        }


        /*
         * Exact company scopes with include_descendants=0 need a
         * company-typed context rather than only an ancestor match.
         */

        return
            $this->scopedAuthorization
                ->hasPermissionInContext(
                    $actorUserId,
                    'organizations.manage',
                    [
                        'scope_type' =>
                            'company',

                        'scope_reference' =>
                            $organizationReference,

                        'ancestors' =>
                            $ancestorMap,

                        'attributes' =>
                            [],
                    ]
                );
    }


    private function decodeMetadata(
        string $json
    ): array {

        if (
            trim(
                $json
            ) === ''
        ) {
            return [];
        }


        $decoded =
            json_decode(
                $json,
                true
            );


        return
            is_array(
                $decoded
            )
                ? $decoded
                : [];
    }
}

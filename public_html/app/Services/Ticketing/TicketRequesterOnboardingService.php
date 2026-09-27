<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Support\ApplicationUrlRegistry;
use PDO;
use Throwable;

final class TicketRequesterOnboardingService
{
    private PDO $core;
    private PDO $ticketing;

    private TicketProjectOrganizationAffiliationService
        $organizationAffiliations;


    public function __construct(
        ?ConnectionResolver $resolver = null
    ) {
        $resolver ??= new ConnectionResolver();

        $this->core =
            $resolver->resolve('core.primary');

        $this->ticketing =
            $resolver->resolve('ticketing.primary');

        $this->organizationAffiliations =
            new TicketProjectOrganizationAffiliationService(
                $resolver,
                $this->ticketing,
                $this->core
            );
    }


    public function page(int $userId): array
    {
        $reference = 'user:' . $userId;

        $memberships =
            $this->memberships($reference);

        $memberIds =
            array_map(
                static fn (array $row): int =>
                    (int) $row['id'],
                $memberships
            );

        $openProjects =
            $this->openProjects(
                $memberIds
            );

        foreach (
            $openProjects
            as &$openProject
        ) {
            $projectId = (int)($openProject['id'] ?? 0);

            $organizationContext =
                $this->organizationAffiliations
                    ->projectAffiliationOptions(
                        $projectId,
                        $userId
                    );

            $requestContext =
                $this->organizationAffiliations
                    ->affiliationRequestContext(
                        $projectId,
                        $userId
                    );

            $openProject['organization_context_required'] =
                !empty($organizationContext['required']);

            $openProject['eligible_affiliations'] =
                $organizationContext['items'] ?? [];

            $openProject['affiliation_pending'] =
                $openProject['eligible_affiliations'] === []
                && ($requestContext['pending'] ?? []) !== [];

            $openProject['affiliation_request_url'] =
                '/admin/support/ticketing/affiliation?project_reference='
                . rawurlencode((string)($openProject['public_reference'] ?? ''));

            $openProject['membership_request_pending'] =
                $this->pendingMembershipRequest($projectId, $userId) !== null;
        }

        unset($openProject);

        $verifiedAffiliations =
            $this->organizationAffiliations
                ->verifiedAffiliationsForUser(
                    $userId
                );

        $urls =
            new ApplicationUrlRegistry();

        return [
            'memberships' =>
                $memberships,

            'open_projects' =>
                $openProjects,

            'organization_affiliations' =>
                $verifiedAffiliations,

            'invite_enabled' =>
                $this->inviteAvailable(),

            'my_tickets_url' =>
                $urls->ticketing(
                    '/admin/ticketing/tickets'
                ),

            'create_ticket_url' =>
                $urls->ticketing(
                    '/admin/ticketing/tickets/create'
                ),
        ];
    }


    public function hasMembership(
        int $userId
    ): bool {
        if ($userId < 1) {
            return false;
        }

        $q =
            $this->ticketing->prepare("
                SELECT COUNT(*)
                FROM ticketing_support_project_members AS members
                INNER JOIN ticketing_support_projects AS projects
                  ON projects.id = members.project_id
                WHERE members.user_reference = ?
                  AND members.left_at IS NULL
                  AND projects.is_active = 1
                  AND projects.archived_at IS NULL
            ");

        $q->execute([
            'user:' . $userId,
        ]);

        return
            (int) $q->fetchColumn() > 0;
    }


    public function hasStaffMembership(
        int $userId
    ): bool {
        if ($userId < 1) {
            return false;
        }

        $statement =
            $this->ticketing->prepare("
                SELECT COUNT(*)

                FROM
                    ticketing_support_project_members
                        AS members

                INNER JOIN
                    ticketing_support_projects
                        AS projects
                  ON projects.id =
                        members.project_id

                WHERE
                    members.user_reference = ?
                    AND members.left_at IS NULL

                    AND members.role_code IN
                        ('member', 'manager')

                    AND projects.is_active = 1
                    AND projects.archived_at IS NULL
            ");

        $statement->execute([
            'user:' . $userId,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function joinOpen(
        string $projectReference,
        int $userId,
        ?string $organizationMembershipReference = null
    ): array {
        return
            $this->joinProject(
                $projectReference,
                $userId,
                true,
                $organizationMembershipReference
            );
    }


    public function joinWithCode(
        string $code,
        int $userId,
        ?string $organizationMembershipReference = null
    ): array {
        $normalized =
            $this->normalizeCode(
                $code
            );

        if (strlen($normalized) < 10) {
            return [
                'ok' => false,
                'error' =>
                    'requester_invite_invalid',
            ];
        }

        $this->ticketing->beginTransaction();

        try {
            $q =
                $this->ticketing->prepare("
                    SELECT
                        invites.id AS invite_id,
                        invites.public_reference AS invite_reference,
                        invites.project_id,
                        invites.status_code,
                        invites.max_uses,
                        invites.use_count,
                        invites.valid_from,
                        invites.valid_until,

                        projects.id,
                        projects.public_reference,
                        projects.code,
                        projects.title,

                        access.invite_join_enabled,
                        access.membership_mode,
                        access.approval_mode

                    FROM ticketing_support_project_invites AS invites

                    INNER JOIN ticketing_support_projects AS projects
                      ON projects.id = invites.project_id

                    INNER JOIN ticketing_support_project_requester_access AS access
                      ON access.project_id = projects.id

                    WHERE invites.code_hash = ?
                      AND projects.is_active = 1
                      AND projects.archived_at IS NULL

                    LIMIT 1
                    FOR UPDATE
                ");

            $q->execute([
                hash(
                    'sha256',
                    $normalized
                ),
            ]);

            $invite =
                $q->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($invite)) {
                throw new \RuntimeException(
                    'requester_invite_invalid'
                );
            }

            if (
                (string) $invite['status_code']
                    !== 'active'
                ||
                (int) $invite['invite_join_enabled']
                    !== 1
            ) {
                throw new \RuntimeException(
                    'requester_invite_inactive'
                );
            }

            $now = time();

            if (
                !empty($invite['valid_from'])
                &&
                strtotime(
                    (string) $invite['valid_from']
                ) > $now
            ) {
                throw new \RuntimeException(
                    'requester_invite_not_started'
                );
            }

            if (
                !empty($invite['valid_until'])
                &&
                strtotime(
                    (string) $invite['valid_until']
                ) < $now
            ) {
                throw new \RuntimeException(
                    'requester_invite_expired'
                );
            }

            if (
                $invite['max_uses'] !== null
                &&
                (int) $invite['use_count']
                    >=
                (int) $invite['max_uses']
            ) {
                throw new \RuntimeException(
                    'requester_invite_exhausted'
                );
            }

            $projectId = (int)$invite['project_id'];

            $affiliation =
                $this->organizationAffiliations
                    ->resolveForProject(
                        $projectId,
                        $userId,
                        $organizationMembershipReference
                    );

            $userReference = 'user:' . $userId;

            if (strtolower(trim((string)($invite['approval_mode'] ?? 'auto'))) === 'manager') {
                $request = $this->createMembershipRequest(
                    $projectId,
                    $userId,
                    $affiliation,
                    'invite',
                    (string)($invite['invite_reference'] ?? '')
                );

                if ((string)($request['state'] ?? '') === 'already_active') {
                    $membership = [
                        'id' => (int)($request['member_id'] ?? 0),
                        'state' => 'already_active',
                        'role_code' => (string)($request['role_code'] ?? 'requester'),
                    ];
                } else {
                    $membership = [
                        'id' => 0,
                        'state' => 'pending_approval',
                        'role_code' => 'requester',
                        'request_reference' => (string)($request['public_reference'] ?? ''),
                    ];
                }
            } else {
                $membership = $this->ensureMembership($projectId, $userId);

                $this->organizationAffiliations
                    ->applyToProjectMember(
                        (int)$membership['id'],
                        $affiliation,
                        $userReference,
                        (string)($membership['state'] ?? '') !== 'already_active'
                    );
            }

            $memberId = (int)($membership['id'] ?? 0);

            if ($memberId > 0) {
                $insertUse =
                    $this->ticketing->prepare("
                        INSERT IGNORE INTO
                            ticketing_support_project_invite_uses
                        (
                            invite_id,
                            project_member_id,
                            user_reference,
                            used_at,
                            created_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            UTC_TIMESTAMP(),
                            UTC_TIMESTAMP()
                        )
                    ");

                $insertUse->execute([
                    (int)$invite['invite_id'],
                    $memberId,
                    $userReference,
                ]);

                if ($insertUse->rowCount() === 1) {
                    $update =
                        $this->ticketing->prepare("
                            UPDATE ticketing_support_project_invites
                            SET
                                use_count = use_count + 1,
                                updated_at = UTC_TIMESTAMP()
                            WHERE id = ?
                        ");

                    $update->execute([
                        (int)$invite['invite_id'],
                    ]);
                }
            }

            $this->ticketing->commit();

            return [
                'ok' => true,
                'membership' =>
                    $membership,
            ];

        } catch (Throwable $exception) {

            if ($this->ticketing->inTransaction()) {
                $this->ticketing->rollBack();
            }

            return [
                'ok' => false,
                'error' =>
                    $exception->getMessage(),
            ];
        }
    }


    public function createInvite(
        string $projectReference,
        int $actorUserId,
        ?int $maxUses = null,
        ?string $validUntil = null
    ): array {
        $q =
            $this->ticketing->prepare("
                SELECT
                    projects.id,
                    projects.code,
                    projects.title
                FROM ticketing_support_projects AS projects
                INNER JOIN ticketing_support_project_requester_access AS access
                  ON access.project_id = projects.id
                WHERE projects.public_reference = ?
                  AND projects.is_active = 1
                  AND projects.archived_at IS NULL
                  AND access.invite_join_enabled = 1
                LIMIT 1
            ");

        $q->execute([
            trim($projectReference),
        ]);

        $project =
            $q->fetch(PDO::FETCH_ASSOC);

        if (!is_array($project)) {
            throw new \RuntimeException(
                'requester_project_not_found'
            );
        }

        $prefix =
            strtoupper(
                preg_replace(
                    '/[^A-Za-z0-9]/',
                    '',
                    (string) $project['code']
                )
                ?: 'TKT'
            );

        $raw =
            strtoupper(
                bin2hex(
                    random_bytes(8)
                )
            );

        $code =
            substr($prefix, 0, 8)
            . '-'
            . implode(
                '-',
                str_split($raw, 4)
            );

        $reference =
            'TINV-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );

        $q =
            $this->ticketing->prepare("
                INSERT INTO ticketing_support_project_invites
                (
                    public_reference,
                    project_id,
                    code_hash,
                    code_preview,
                    status_code,
                    max_uses,
                    use_count,
                    valid_from,
                    valid_until,
                    created_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'active',
                    ?,
                    0,
                    UTC_TIMESTAMP(),
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $q->execute([
            $reference,
            (int) $project['id'],
            hash(
                'sha256',
                $this->normalizeCode($code)
            ),
            substr($code, 0, 10) . '…',
            $maxUses,
            $validUntil,
            'user:' . $actorUserId,
        ]);

        return [
            'public_reference' =>
                $reference,

            'project_title' =>
                (string) $project['title'],

            'code' =>
                $code,

            'max_uses' =>
                $maxUses,

            'valid_until' =>
                $validUntil,
        ];
    }


    private function joinProject(
        string $reference,
        int $userId,
        bool $requireOpenJoin,
        ?string $organizationMembershipReference = null
    ): array {
        $this->ticketing->beginTransaction();

        try {
            $q =
                $this->ticketing->prepare("
                    SELECT
                        projects.id,
                        access.self_join_enabled,
                        access.membership_mode,
                        access.approval_mode
                    FROM ticketing_support_projects AS projects
                    INNER JOIN ticketing_support_project_requester_access AS access
                      ON access.project_id = projects.id
                    WHERE projects.public_reference = ?
                      AND projects.is_active = 1
                      AND projects.archived_at IS NULL
                    LIMIT 1
                    FOR UPDATE
                ");

            $q->execute([
                trim($reference),
            ]);

            $project =
                $q->fetch(PDO::FETCH_ASSOC);

            if (!is_array($project)) {
                throw new \RuntimeException(
                    'requester_project_not_found'
                );
            }

            $membershipMode = strtolower(trim((string)($project['membership_mode'] ?? 'public')));
            $approvalMode = strtolower(trim((string)($project['approval_mode'] ?? 'auto')));

            if (
                $requireOpenJoin
                && $membershipMode !== 'public'
                && (int)$project['self_join_enabled'] !== 1
            ) {
                throw new \RuntimeException('requester_open_join_disabled');
            }

            $projectId = (int)$project['id'];

            $affiliation =
                $this->organizationAffiliations
                    ->resolveForProject(
                        $projectId,
                        $userId,
                        $organizationMembershipReference
                    );

            if ($approvalMode === 'manager') {
                $request = $this->createMembershipRequest(
                    $projectId,
                    $userId,
                    $affiliation,
                    'open',
                    null
                );

                if ((string)($request['state'] ?? '') === 'already_active') {
                    $membership = [
                        'id' => (int)($request['member_id'] ?? 0),
                        'state' => 'already_active',
                        'role_code' => (string)($request['role_code'] ?? 'requester'),
                    ];
                } else {
                    $membership = [
                        'id' => 0,
                        'state' => 'pending_approval',
                        'role_code' => 'requester',
                        'request_reference' => (string)($request['public_reference'] ?? ''),
                    ];
                }
            } else {
                $membership = $this->ensureMembership($projectId, $userId);

                $this->organizationAffiliations
                    ->applyToProjectMember(
                        (int)$membership['id'],
                        $affiliation,
                        'user:' . $userId,
                        (string)($membership['state'] ?? '') !== 'already_active'
                    );
            }

            $this->ticketing->commit();

            return [
                'ok' => true,
                'membership' =>
                    $membership,
            ];

        } catch (Throwable $exception) {

            if ($this->ticketing->inTransaction()) {
                $this->ticketing->rollBack();
            }

            return [
                'ok' => false,
                'error' =>
                    $exception->getMessage(),
            ];
        }
    }


    /**
     * TICKETING_PARTICIPANT_LINKAGE_RUNTIME
     *
     * A Core user taking part in Ticketing must have one canonical
     * ticketing_participants identity. Project memberships and requester
     * tickets are linked to that identity in addition to the legacy
     * user_reference snapshot.
     */
    private function ensureMembership(
        int $projectId,
        int $userId
    ): array {
        $user =
            $this->coreUser(
                $userId
            );

        if (!is_array($user)) {
            throw new \RuntimeException(
                'requester_user_not_found'
            );
        }

        $reference =
            'user:' . $userId;

        $participantId =
            $this->ensureParticipantForCoreUser(
                $userId,
                $user
            );

        $q =
            $this->ticketing->prepare("
                SELECT
                    id,
                    participant_id,
                    role_code,
                    left_at

                FROM
                    ticketing_support_project_members

                WHERE project_id = ?
                  AND user_reference = ?

                LIMIT 1
                FOR UPDATE
            ");

        $q->execute([
            $projectId,
            $reference,
        ]);

        $member =
            $q->fetch(
                \PDO::FETCH_ASSOC
            );

        if (is_array($member)) {
            $memberId =
                (int) $member['id'];

            $linkedParticipantId =
                (int) (
                    $member['participant_id']
                    ?? 0
                );

            if (
                $linkedParticipantId > 0
                &&
                $linkedParticipantId !== $participantId
            ) {
                throw new \RuntimeException(
                    'requester_participant_link_conflict'
                );
            }

            if ($linkedParticipantId < 1) {
                $conflict =
                    $this->ticketing->prepare("
                        SELECT id

                        FROM
                            ticketing_support_project_members

                        WHERE project_id = ?
                          AND participant_id = ?
                          AND id <> ?

                        LIMIT 1
                        FOR UPDATE
                    ");

                $conflict->execute([
                    $projectId,
                    $participantId,
                    $memberId,
                ]);

                if ($conflict->fetchColumn() !== false) {
                    throw new \RuntimeException(
                        'requester_participant_membership_conflict'
                    );
                }

                $link =
                    $this->ticketing->prepare("
                        UPDATE
                            ticketing_support_project_members

                        SET
                            participant_id = ?,
                            updated_by_user_reference = ?,
                            updated_at = UTC_TIMESTAMP()

                        WHERE id = ?
                          AND participant_id IS NULL
                    ");

                $link->execute([
                    $participantId,
                    $reference,
                    $memberId,
                ]);
            }

            if (empty($member['left_at'])) {
                return [
                    'id' =>
                        $memberId,

                    'state' =>
                        'already_active',

                    'role_code' =>
                        (string) $member['role_code'],
                ];
            }

            $role =
                in_array(
                    (string) $member['role_code'],
                    [
                        'manager',
                        'member',
                    ],
                    true
                )
                    ? (string) $member['role_code']
                    : 'requester';

            $update =
                $this->ticketing->prepare("
                    UPDATE
                        ticketing_support_project_members

                    SET
                        participant_id = ?,
                        display_name_snapshot = ?,
                        role_code = ?,
                        joined_at = UTC_TIMESTAMP(),
                        left_at = NULL,
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()

                    WHERE id = ?
                ");

            $update->execute([
                $participantId,
                (string) $user['display_name'],
                $role,
                $reference,
                $memberId,
            ]);

            return [
                'id' =>
                    $memberId,

                'state' =>
                    'reactivated',

                'role_code' =>
                    $role,
            ];
        }

        $insert =
            $this->ticketing->prepare("
                INSERT INTO
                    ticketing_support_project_members
                (
                    project_id,
                    participant_id,
                    user_reference,
                    person_reference,
                    display_name_snapshot,
                    role_code,
                    joined_at,
                    left_at,
                    created_by_user_reference,
                    updated_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    NULL,
                    ?,
                    'requester',
                    UTC_TIMESTAMP(),
                    NULL,
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $insert->execute([
            $projectId,
            $participantId,
            $reference,
            (string) $user['display_name'],
            $reference,
            $reference,
        ]);

        return [
            'id' =>
                (int) $this->ticketing->lastInsertId(),

            'state' =>
                'created',

            'role_code' =>
                'requester',
        ];
    }


    private function ensureParticipantForCoreUser(
        int $userId,
        array $user
    ): int {
        if ($userId < 1) {
            throw new \RuntimeException(
                'requester_user_not_found'
            );
        }

        $reference =
            'user:' . $userId;

        $displayName =
            trim(
                (string) (
                    $user['display_name']
                    ?? ''
                )
            );

        if ($displayName === '') {
            $displayName =
                'کاربر ' . $userId;
        }

        $participantReference =
            'TPR-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );

        $statement =
            $this->ticketing->prepare("
                INSERT INTO
                    ticketing_participants
                (
                    public_reference,
                    origin_code,
                    core_user_reference,
                    full_name,
                    account_state,
                    linked_at,
                    created_by_user_reference,
                    updated_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'core',
                    ?,
                    ?,
                    'linked',
                    UTC_TIMESTAMP(),
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )

                ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id),
                    full_name = VALUES(full_name),
                    linked_at = COALESCE(
                        linked_at,
                        UTC_TIMESTAMP()
                    ),
                    updated_by_user_reference =
                        VALUES(
                            updated_by_user_reference
                        ),
                    updated_at = UTC_TIMESTAMP()
            ");

        $statement->execute([
            $participantReference,
            $reference,
            $displayName,
            $reference,
            $reference,
        ]);

        $participantId =
            (int) $this->ticketing->lastInsertId();

        if ($participantId < 1) {
            $lookup =
                $this->ticketing->prepare("
                    SELECT id

                    FROM ticketing_participants

                    WHERE core_user_reference = ?

                    LIMIT 1
                    FOR UPDATE
                ");

            $lookup->execute([
                $reference,
            ]);

            $participantId =
                (int) (
                    $lookup->fetchColumn()
                    ?: 0
                );
        }

        if ($participantId < 1) {
            throw new \RuntimeException(
                'requester_participant_unavailable'
            );
        }

        $participant =
            $this->ticketing->prepare("
                SELECT
                    id,
                    disabled_at,
                    archived_at

                FROM ticketing_participants

                WHERE id = ?

                LIMIT 1
                FOR UPDATE
            ");

        $participant->execute([
            $participantId,
        ]);

        $participantRow =
            $participant->fetch(
                \PDO::FETCH_ASSOC
            );

        if (
            !is_array($participantRow)
            ||
            !empty(
                $participantRow[
                    'disabled_at'
                ]
            )
            ||
            !empty(
                $participantRow[
                    'archived_at'
                ]
            )
        ) {
            throw new \RuntimeException(
                'requester_participant_inactive'
            );
        }

        /*
         * Reject an already-linked requester ticket that points to a
         * different participant. Null legacy values are safe to backfill.
         */
        $ticketConflict =
            $this->ticketing->prepare("
                SELECT id

                FROM ticketing_tickets

                WHERE requester_user_reference = ?
                  AND requester_participant_id IS NOT NULL
                  AND requester_participant_id <> ?

                LIMIT 1
                FOR UPDATE
            ");

        $ticketConflict->execute([
            $reference,
            $participantId,
        ]);

        if ($ticketConflict->fetchColumn() !== false) {
            throw new \RuntimeException(
                'requester_ticket_participant_conflict'
            );
        }

        $ticketLink =
            $this->ticketing->prepare("
                UPDATE ticketing_tickets

                SET
                    requester_participant_id = ?,
                    updated_at = UTC_TIMESTAMP()

                WHERE requester_user_reference = ?
                  AND requester_participant_id IS NULL
            ");

        $ticketLink->execute([
            $participantId,
            $reference,
        ]);

        return $participantId;
    }


    private function coreUser(
        int $userId
    ): ?array {
        $q =
            $this->core->prepare("
                SELECT
                    users.id,
                    COALESCE(
                        NULLIF(persons.full_name, ''),
                        NULLIF(users.username, ''),
                        NULLIF(users.email, ''),
                        CONCAT('کاربر ', users.id)
                    ) AS display_name
                FROM users
                LEFT JOIN persons
                  ON persons.id = users.person_id
                WHERE users.id = ?
                LIMIT 1
            ");

        $q->execute([$userId]);

        $row =
            $q->fetch(PDO::FETCH_ASSOC);

        return
            is_array($row)
                ? $row
                : null;
    }


    public function affiliationPage(string $projectReference, int $userId): array
    {
        $project = $this->projectByReference($projectReference);
        if ($project === null) {
            return ['ok' => false, 'state' => 'requester_project_not_found'];
        }

        return [
            'ok' => true,
            'project' => $project,
            'context' => $this->organizationAffiliations
                ->affiliationRequestContext((int)$project['id'], $userId),
        ];
    }


    public function requestAffiliation(
        string $projectReference,
        int $userId,
        array $input
    ): array {
        $project = $this->projectByReference($projectReference);
        if ($project === null) {
            return ['ok' => false, 'state' => 'requester_project_not_found'];
        }

        try {
            $reference = $this->organizationAffiliations
                ->requestAffiliationForProject((int)$project['id'], $userId, $input);

            return [
                'ok' => true,
                'state' => 'affiliation_pending',
                'membership_reference' => $reference,
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'state' => $exception->getMessage()];
        }
    }


    public function membershipRequestsForManager(string $projectReference, int $actorUserId): array
    {
        $project = $this->projectByReference($projectReference);
        if ($project === null) {
            return ['ok' => false, 'state' => 'requester_project_not_found', 'project' => null, 'requests' => []];
        }
        if (!$this->canManageMembershipRequests((int)$project['id'], $actorUserId)) {
            return ['ok' => false, 'state' => 'requester_membership_manage_forbidden', 'project' => $project, 'requests' => []];
        }

        $statement = $this->ticketing->prepare("\n            SELECT\n                requests.public_reference,\n                requests.user_reference,\n                requests.core_organization_membership_reference,\n                requests.organization_reference_snapshot,\n                requests.organization_title_snapshot,\n                requests.organization_role_code_snapshot,\n                requests.status_code,\n                requests.source_code,\n                requests.requested_at\n            FROM ticketing_support_project_membership_requests AS requests\n            WHERE requests.project_id = ?\n              AND requests.status_code = 'pending'\n            ORDER BY requests.requested_at, requests.id\n        ");
        $statement->execute([(int)$project['id']]);

        $requests = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($requests as &$request) {
            $request['requester_display_name'] = (string)($request['user_reference'] ?? '');
            if (preg_match('/^user:(\d+)$/', (string)($request['user_reference'] ?? ''), $match) === 1) {
                $user = $this->coreUser((int)$match[1]);
                if (is_array($user) && trim((string)($user['display_name'] ?? '')) !== '') {
                    $request['requester_display_name'] = (string)$user['display_name'];
                }
            }
        }
        unset($request);

        return [
            'ok' => true,
            'project' => $project,
            'requests' => $requests,
        ];
    }


    public function decideMembershipRequest(
        string $projectReference,
        string $requestReference,
        string $decision,
        int $actorUserId,
        string $decisionNote = ''
    ): array {
        $project = $this->projectByReference($projectReference);
        if ($project === null) {
            return ['ok' => false, 'state' => 'requester_project_not_found'];
        }
        if (!$this->canManageMembershipRequests((int)$project['id'], $actorUserId)) {
            return ['ok' => false, 'state' => 'requester_membership_manage_forbidden'];
        }

        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return ['ok' => false, 'state' => 'requester_membership_decision_invalid'];
        }

        $this->ticketing->beginTransaction();

        try {
            $statement = $this->ticketing->prepare("\n                SELECT *\n                FROM ticketing_support_project_membership_requests\n                WHERE public_reference = ?\n                  AND project_id = ?\n                  AND status_code = 'pending'\n                LIMIT 1\n                FOR UPDATE\n            ");
            $statement->execute([trim($requestReference), (int)$project['id']]);
            $request = $statement->fetch(PDO::FETCH_ASSOC);

            if (!is_array($request)) {
                $this->ticketing->rollBack();
                return ['ok' => false, 'state' => 'requester_membership_request_not_found'];
            }

            if ($decision === 'reject') {
                $update = $this->ticketing->prepare("\n                    UPDATE ticketing_support_project_membership_requests\n                    SET status_code = 'rejected',\n                        pending_lock_key = NULL,\n                        decided_by_user_reference = ?,\n                        decision_note = ?,\n                        decided_at = UTC_TIMESTAMP(),\n                        updated_at = UTC_TIMESTAMP()\n                    WHERE id = ?\n                ");
                $update->execute([
                    'user:' . $actorUserId,
                    mb_substr(trim($decisionNote), 0, 500),
                    (int)$request['id'],
                ]);
                $this->ticketing->commit();
                return ['ok' => true, 'state' => 'requester_membership_rejected'];
            }

            $userReference = trim((string)($request['user_reference'] ?? ''));
            if (preg_match('/^user:(\\d+)$/', $userReference, $match) !== 1) {
                throw new \RuntimeException('requester_user_reference_invalid');
            }

            $userId = (int)$match[1];
            $affiliation = $this->organizationAffiliations->resolveForProject(
                (int)$project['id'],
                $userId,
                (string)($request['core_organization_membership_reference'] ?? '')
            );

            $membership = $this->ensureMembership((int)$project['id'], $userId);
            $this->organizationAffiliations->applyToProjectMember(
                (int)$membership['id'],
                $affiliation,
                'user:' . $actorUserId,
                true
            );

            $update = $this->ticketing->prepare("\n                UPDATE ticketing_support_project_membership_requests\n                SET status_code = 'approved',\n                    pending_lock_key = NULL,\n                    decided_by_user_reference = ?,\n                    decision_note = ?,\n                    decided_at = UTC_TIMESTAMP(),\n                    updated_at = UTC_TIMESTAMP()\n                WHERE id = ?\n            ");
            $update->execute([
                'user:' . $actorUserId,
                mb_substr(trim($decisionNote), 0, 500),
                (int)$request['id'],
            ]);

            if ((string)($request['source_code'] ?? '') === 'invite') {
                $inviteReference = trim((string)($request['source_reference'] ?? ''));
                if ($inviteReference !== '') {
                    $invite = $this->ticketing->prepare("\n                        SELECT id, max_uses, use_count\n                        FROM ticketing_support_project_invites\n                        WHERE public_reference = ?\n                          AND status_code = 'active'\n                        LIMIT 1\n                        FOR UPDATE\n                    ");
                    $invite->execute([$inviteReference]);
                    $inviteRow = $invite->fetch(PDO::FETCH_ASSOC);
                    if (!is_array($inviteRow)) {
                        throw new \RuntimeException('requester_invite_inactive');
                    }
                    if ($inviteRow['max_uses'] !== null && (int)$inviteRow['use_count'] >= (int)$inviteRow['max_uses']) {
                        throw new \RuntimeException('requester_invite_exhausted');
                    }

                    $use = $this->ticketing->prepare("\n                        INSERT IGNORE INTO ticketing_support_project_invite_uses\n                        (invite_id, project_member_id, user_reference, used_at, created_at)\n                        VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())\n                    ");
                    $use->execute([(int)$inviteRow['id'], (int)$membership['id'], $userReference]);
                    if ($use->rowCount() === 1) {
                        $bump = $this->ticketing->prepare("\n                            UPDATE ticketing_support_project_invites\n                            SET use_count = use_count + 1, updated_at = UTC_TIMESTAMP()\n                            WHERE id = ?\n                        ");
                        $bump->execute([(int)$inviteRow['id']]);
                    }
                }
            }

            $this->ticketing->commit();
            return ['ok' => true, 'state' => 'requester_membership_approved', 'member_id' => (int)$membership['id']];

        } catch (Throwable $exception) {
            if ($this->ticketing->inTransaction()) {
                $this->ticketing->rollBack();
            }
            throw $exception;
        }
    }


    private function createMembershipRequest(
        int $projectId,
        int $userId,
        ?array $affiliation,
        string $sourceCode,
        ?string $sourceReference
    ): array {
        if ($projectId < 1 || $userId < 1 || !is_array($affiliation)) {
            throw new \RuntimeException('requester_affiliation_required');
        }

        $membershipReference = trim((string)($affiliation['membership_reference'] ?? ''));
        $organizationReference = trim((string)($affiliation['organization_reference'] ?? ''));
        if ($membershipReference === '' || $organizationReference === '') {
            throw new \RuntimeException('requester_affiliation_invalid');
        }

        $userReference = 'user:' . $userId;
        $active = $this->ticketing->prepare("\n            SELECT id, role_code\n            FROM ticketing_support_project_members\n            WHERE project_id = ? AND user_reference = ? AND left_at IS NULL\n            LIMIT 1\n            FOR UPDATE\n        ");
        $active->execute([$projectId, $userReference]);
        $activeMember = $active->fetch(PDO::FETCH_ASSOC);
        if (is_array($activeMember)) {
            return [
                'public_reference' => '',
                'state' => 'already_active',
                'member_id' => (int)$activeMember['id'],
                'role_code' => (string)($activeMember['role_code'] ?? 'requester'),
            ];
        }

        $pendingLockKey =
            (string)$projectId
            . '|'
            . $userReference;

        $reference =
            'TMR-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );

        $insert = $this->ticketing->prepare("\n            INSERT INTO ticketing_support_project_membership_requests\n            (\n                public_reference, project_id, user_reference,\n                core_organization_membership_reference,\n                organization_reference_snapshot, organization_title_snapshot,\n                organization_role_code_snapshot, status_code, pending_lock_key,\n                source_code, source_reference, requested_by_user_reference,\n                requested_at, created_at, updated_at\n            )\n            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())\n            ON DUPLICATE KEY UPDATE\n                core_organization_membership_reference = VALUES(core_organization_membership_reference),\n                organization_reference_snapshot = VALUES(organization_reference_snapshot),\n                organization_title_snapshot = VALUES(organization_title_snapshot),\n                organization_role_code_snapshot = VALUES(organization_role_code_snapshot),\n                source_code = VALUES(source_code),\n                source_reference = VALUES(source_reference),\n                requested_by_user_reference = VALUES(requested_by_user_reference),\n                requested_at = UTC_TIMESTAMP(),\n                updated_at = UTC_TIMESTAMP()\n        ");
        $insert->execute([
            $reference,
            $projectId,
            $userReference,
            $membershipReference,
            $organizationReference,
            (string)($affiliation['organization_title'] ?? ''),
            (string)($affiliation['organization_role_code'] ?? ''),
            $pendingLockKey,
            mb_substr(trim($sourceCode), 0, 30),
            $sourceReference !== null ? mb_substr(trim($sourceReference), 0, 100) : null,
            $userReference,
        ]);

        $lookup = $this->ticketing->prepare("\n            SELECT public_reference, status_code\n            FROM ticketing_support_project_membership_requests\n            WHERE pending_lock_key = ?\n              AND status_code = 'pending'\n            LIMIT 1\n            FOR UPDATE\n        ");
        $lookup->execute([$pendingLockKey]);
        $row = $lookup->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new \RuntimeException('requester_membership_request_failed');
        }

        return $row;
    }


    private function canManageMembershipRequests(int $projectId, int $actorUserId): bool
    {
        if ($projectId < 1 || $actorUserId < 1) return false;
        try {
            if ((new \App\Services\AuthorizationService())->hasPermission($actorUserId, 'ticketing.project.manage')) return true;
        } catch (\Throwable) {
        }
        try {
            return (new TicketingProjectScopedAccessService())->isProjectManager($actorUserId, $projectId);
        } catch (\Throwable) {
            return false;
        }
    }


    private function pendingMembershipRequest(int $projectId, int $userId): ?array
    {
        if ($projectId < 1 || $userId < 1) {
            return null;
        }

        $statement = $this->ticketing->prepare("\n            SELECT public_reference, status_code, requested_at\n            FROM ticketing_support_project_membership_requests\n            WHERE project_id = ?\n              AND user_reference = ?\n              AND status_code = 'pending'\n            ORDER BY id DESC\n            LIMIT 1\n        ");
        $statement->execute([$projectId, 'user:' . $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }


    private function projectByReference(string $reference): ?array
    {
        $statement = $this->ticketing->prepare("\n            SELECT id, public_reference, code, title, description\n            FROM ticketing_support_projects\n            WHERE public_reference = ?\n              AND is_active = 1\n              AND archived_at IS NULL\n            LIMIT 1\n        ");
        $statement->execute([trim($reference)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }


    /*
     * REQUESTER_PROJECT_SELF_LEAVE_RUNTIME
     *
     * Requesters may leave their own project only while
     * they have no ticket whose canonical status is open.
     *
     * Membership is never deleted. left_at is the lifecycle
     * boundary so history and future reactivation are preserved.
     */
    public function leave(
        string $projectReference,
        int $userId
    ): array {

        $projectReference =
            trim(
                $projectReference
            );

        if (
            $projectReference === ''
            ||
            $userId < 1
        ) {
            return [
                'ok' => false,
                'state' =>
                    'requester_membership_not_found',
                'error' =>
                    'requester_membership_not_found',
            ];
        }

        $userReference =
            'user:' . $userId;

        $projectStatement =
            $this->ticketing->prepare("
                SELECT
                    id,
                    public_reference,
                    code,
                    title

                FROM ticketing_support_projects

                WHERE public_reference = ?

                LIMIT 1
            ");

        $projectStatement->execute([
            $projectReference,
        ]);

        $project =
            $projectStatement->fetch(
                \PDO::FETCH_ASSOC
            );

        if (!is_array($project)) {
            return [
                'ok' => false,
                'state' =>
                    'requester_project_not_found',
                'error' =>
                    'requester_project_not_found',
            ];
        }

        $this->ticketing
            ->beginTransaction();

        try {

            $memberStatement =
                $this->ticketing->prepare("
                    SELECT
                        id,
                        project_id,
                        participant_id,
                        user_reference,
                        role_code,
                        left_at

                    FROM
                        ticketing_support_project_members

                    WHERE project_id = ?
                      AND user_reference = ?
                      AND left_at IS NULL

                    LIMIT 1

                    FOR UPDATE
                ");

            $memberStatement->execute([
                (int) $project['id'],
                $userReference,
            ]);

            $member =
                $memberStatement->fetch(
                    \PDO::FETCH_ASSOC
                );

            if (!is_array($member)) {

                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'state' =>
                        'requester_membership_not_found',
                    'error' =>
                        'requester_membership_not_found',
                ];
            }

            if (
                (string) (
                    $member['role_code']
                    ?? ''
                ) !== 'requester'
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'state' =>
                        'requester_self_leave_forbidden',
                    'error' =>
                        'requester_self_leave_forbidden',
                ];
            }

            if (
                $this->requesterHasOpenTickets(
                    $member
                )
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'state' =>
                        'requester_open_tickets',
                    'error' =>
                        'requester_open_tickets',
                ];
            }

            $leave =
                $this->ticketing->prepare("
                    UPDATE
                        ticketing_support_project_members

                    SET
                        left_at = UTC_TIMESTAMP(),
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()

                    WHERE id = ?
                      AND left_at IS NULL
                ");

            $leave->execute([
                $userReference,
                (int) $member['id'],
            ]);

            if ($leave->rowCount() !== 1) {
                throw new \RuntimeException(
                    'requester_leave_conflict'
                );
            }

            $this->ticketing
                ->commit();

            return [
                'ok' => true,
                'state' =>
                    'requester_left',
                'project_reference' =>
                    (string) $project[
                        'public_reference'
                    ],
            ];

        } catch (\Throwable $exception) {

            if (
                $this->ticketing
                    ->inTransaction()
            ) {
                $this->ticketing
                    ->rollBack();
            }

            throw $exception;
        }
    }


    /*
     * TICKETING_REQUESTER_MANAGER_REVOKE_RUNTIME
     *
     * This lifecycle slice deliberately manages requester
     * memberships only. Staff/manager membership lifecycle
     * remains separate because those memberships may own
     * assignments, queues or operational responsibilities.
     */
    public function requesterMembersForManager(
        string $projectReference
    ): array {

        $projectReference =
            trim(
                $projectReference
            );

        $projectStatement =
            $this->ticketing->prepare("
                SELECT
                    id,
                    public_reference,
                    code,
                    title

                FROM ticketing_support_projects

                WHERE public_reference = ?

                LIMIT 1
            ");

        $projectStatement->execute([
            $projectReference,
        ]);

        $project =
            $projectStatement->fetch(
                \PDO::FETCH_ASSOC
            );

        if (!is_array($project)) {
            return [
                'ok' => false,
                'project' => null,
                'members' => [],
            ];
        }

        $statement =
            $this->ticketing->prepare("
                SELECT
                    members.id,
                    members.project_id,
                    members.participant_id,
                    members.user_reference,
                    members.person_reference,
                    members.display_name_snapshot,
                    members.role_code,
                    members.joined_at,
                    members.left_at,

                    (
                        SELECT COUNT(*)

                        FROM
                            ticketing_tickets
                                AS tickets

                        INNER JOIN
                            ticketing_statuses
                                AS statuses
                          ON statuses.code =
                                tickets.status_code

                        WHERE
                            tickets.support_project_id =
                                members.project_id

                            AND statuses.is_closed = 0

                            AND
                            (
                                (
                                    members.participant_id
                                        IS NOT NULL

                                    AND
                                    tickets.requester_participant_id =
                                        members.participant_id
                                )

                                OR

                                (
                                    members.user_reference
                                        IS NOT NULL

                                    AND
                                    tickets.requester_user_reference =
                                        members.user_reference
                                )
                            )
                    ) AS open_ticket_count

                FROM
                    ticketing_support_project_members
                        AS members

                WHERE
                    members.project_id = ?

                    AND members.role_code =
                        'requester'

                    AND members.left_at
                        IS NULL

                ORDER BY
                    members.display_name_snapshot,
                    members.id
            ");

        $statement->execute([
            (int) $project['id'],
        ]);

        return [
            'ok' => true,
            'project' => $project,

            'members' =>
                $statement->fetchAll(
                    \PDO::FETCH_ASSOC
                ) ?: [],
        ];
    }


    public function revokeRequester(
        string $projectReference,
        int $memberId,
        int $actorUserId = 0
    ): array {

        $projectReference =
            trim(
                $projectReference
            );

        if (
            $projectReference === ''
            ||
            $memberId < 1
        ) {
            return [
                'ok' => false,
                'state' =>
                    'requester_membership_not_found',
            ];
        }

        $projectStatement =
            $this->ticketing->prepare("
                SELECT id
                FROM ticketing_support_projects
                WHERE public_reference = ?
                LIMIT 1
            ");

        $projectStatement->execute([
            $projectReference,
        ]);

        $projectId =
            (int) (
                $projectStatement
                    ->fetchColumn()
                ?: 0
            );

        if ($projectId < 1) {
            return [
                'ok' => false,
                'state' =>
                    'requester_project_not_found',
            ];
        }

        $this->ticketing
            ->beginTransaction();

        try {

            $memberStatement =
                $this->ticketing->prepare("
                    SELECT
                        id,
                        project_id,
                        participant_id,
                        user_reference,
                        role_code,
                        left_at

                    FROM
                        ticketing_support_project_members

                    WHERE id = ?
                      AND project_id = ?
                      AND left_at IS NULL

                    LIMIT 1

                    FOR UPDATE
                ");

            $memberStatement->execute([
                $memberId,
                $projectId,
            ]);

            $member =
                $memberStatement->fetch(
                    \PDO::FETCH_ASSOC
                );

            if (!is_array($member)) {

                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'state' =>
                        'requester_membership_not_found',
                ];
            }

            if (
                (string) (
                    $member['role_code']
                    ?? ''
                ) !== 'requester'
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'state' =>
                        'requester_revoke_forbidden',
                ];
            }

            if (
                $this->requesterHasOpenTickets(
                    $member
                )
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'state' =>
                        'requester_open_tickets',
                ];
            }

            $actorReference =
                $actorUserId > 0
                    ? 'user:' . $actorUserId
                    : null;

            $statement =
                $this->ticketing->prepare("
                    UPDATE
                        ticketing_support_project_members

                    SET
                        left_at = UTC_TIMESTAMP(),
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()

                    WHERE id = ?
                      AND project_id = ?
                      AND role_code = 'requester'
                      AND left_at IS NULL
                ");

            $statement->execute([
                $actorReference,
                $memberId,
                $projectId,
            ]);

            if ($statement->rowCount() !== 1) {
                throw new \RuntimeException(
                    'requester_revoke_conflict'
                );
            }

            $this->ticketing
                ->commit();

            return [
                'ok' => true,
                'state' =>
                    'requester_revoked',
            ];

        } catch (\Throwable $exception) {

            if (
                $this->ticketing
                    ->inTransaction()
            ) {
                $this->ticketing
                    ->rollBack();
            }

            throw $exception;
        }
    }


    private function requesterHasOpenTickets(
        array $member
    ): bool {

        $participantId =
            (int) (
                $member[
                    'participant_id'
                ]
                ?? 0
            );

        $userReference =
            trim(
                (string) (
                    $member[
                        'user_reference'
                    ]
                    ?? ''
                )
            );

        $identitySql = [];
        $bindings = [
            (int) $member['project_id'],
        ];

        if ($participantId > 0) {
            $identitySql[] =
                'tickets.requester_participant_id = ?';

            $bindings[] =
                $participantId;
        }

        if ($userReference !== '') {
            $identitySql[] =
                'tickets.requester_user_reference = ?';

            $bindings[] =
                $userReference;
        }

        if ($identitySql === []) {
            return false;
        }

        $statement =
            $this->ticketing->prepare("
                SELECT
                    tickets.id

                FROM
                    ticketing_tickets
                        AS tickets

                INNER JOIN
                    ticketing_statuses
                        AS statuses
                  ON statuses.code =
                        tickets.status_code

                WHERE
                    tickets.support_project_id = ?

                    AND statuses.is_closed = 0

                    AND
                    (
                        "
                        . implode(
                            ' OR ',
                            $identitySql
                        )
                        . "
                    )

                LIMIT 1

                FOR UPDATE
            ");

        $statement->execute(
            $bindings
        );

        return
            $statement->fetchColumn()
            !== false;
    }

    private function memberships(
        string $reference
    ): array {
        $q =
            $this->ticketing->prepare("
                SELECT
                    projects.id,
                    projects.public_reference,
                    projects.code,
                    projects.title,
                    projects.description,
                    members.role_code,
                    members.joined_at
                FROM ticketing_support_project_members AS members
                INNER JOIN ticketing_support_projects AS projects
                  ON projects.id = members.project_id
                WHERE members.user_reference = ?
                  AND members.left_at IS NULL
                  AND projects.is_active = 1
                  AND projects.archived_at IS NULL
                ORDER BY projects.sort_order, projects.title, projects.id
            ");

        $q->execute([$reference]);

        return
            $q->fetchAll(PDO::FETCH_ASSOC)
            ?: [];
    }


    private function openProjects(
        array $excludedIds
    ): array {
        $rows =
            $this->ticketing->query("
                SELECT
                    projects.id,
                    projects.public_reference,
                    projects.code,
                    projects.title,
                    projects.description
                FROM ticketing_support_projects AS projects
                INNER JOIN ticketing_support_project_requester_access AS access
                  ON access.project_id = projects.id
                WHERE projects.is_active = 1
                  AND projects.archived_at IS NULL
                  AND (access.membership_mode = 'public' OR access.self_join_enabled = 1)
                ORDER BY projects.sort_order, projects.title, projects.id
            ")->fetchAll(PDO::FETCH_ASSOC)
            ?: [];

        if ($excludedIds === []) {
            return $rows;
        }

        return
            array_values(
                array_filter(
                    $rows,
                    static fn (array $row): bool =>
                        !in_array(
                            (int) $row['id'],
                            $excludedIds,
                            true
                        )
                )
            );
    }


    private function inviteAvailable(): bool
    {
        return
            (int) $this->ticketing->query("
                SELECT COUNT(*)
                FROM ticketing_support_project_requester_access AS access
                INNER JOIN ticketing_support_projects AS projects
                  ON projects.id = access.project_id
                WHERE access.invite_join_enabled = 1
                  AND projects.is_active = 1
                  AND projects.archived_at IS NULL
            ")->fetchColumn()
            > 0;
    }


    private function normalizeCode(
        string $code
    ): string {
        $value =
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                trim($code)
            );

        return
            strtoupper(
                is_string($value)
                    ? $value
                    : ''
            );
    }
}

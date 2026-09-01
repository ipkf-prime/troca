<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use Throwable;

/**
 * TICKETING_PROJECT_MEMBER_ACCESS_CENTER_RUNTIME
 *
 * Canonical project-local membership and operational team-access manager.
 *
 * Project roles:
 *   requester
 *   member
 *   manager
 *
 * Staff capability is valid only when the project role is member/manager
 * and at least one current team membership is active.
 */
class TicketProjectMemberAccessService
{
    private PDO $ticketing;

    private const PROJECT_ROLES = [
        'requester',
        'member',
        'manager',
    ];

    private const STAFF_ROLES = [
        'agent',
        'supervisor',
        'manager',
    ];

    private const PROJECT_ROLE_TITLES = [
        'requester' =>
            'متقاضی',
        'member' =>
            'کارشناس',
        'manager' =>
            'مدیر پروژه',
    ];

    private const STAFF_ROLE_TITLES = [
        'agent' =>
            'کارشناس',
        'supervisor' =>
            'سرپرست',
        'manager' =>
            'مدیر',
    ];

    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $resolver =
            $connections
            ?? new ConnectionResolver();

        $this->ticketing =
            $resolver->resolve(
                'ticketing.primary'
            );
    }


    public function page(
        string $projectReference
    ): array {
        $project =
            $this->project(
                $projectReference
            );

        if (!is_array($project)) {
            return [
                'ok' => false,
                'not_found' => true,
            ];
        }

        $projectId =
            (int) $project['id'];

        $membersStatement =
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

                    participants.public_reference
                        AS participant_reference,

                    participants.origin_code
                        AS participant_origin_code,

                    participants.core_user_reference
                        AS participant_core_user_reference,

                    participants.full_name
                        AS participant_name,

                    (
                        SELECT COUNT(*)

                        FROM ticketing_tickets
                            AS requester_tickets

                        WHERE
                            requester_tickets.support_project_id =
                                members.project_id

                            AND
                            (
                                (
                                    members.participant_id IS NOT NULL

                                    AND
                                    requester_tickets.requester_participant_id =
                                        members.participant_id
                                )

                                OR

                                (
                                    members.user_reference IS NOT NULL

                                    AND members.user_reference <> ''

                                    AND
                                    requester_tickets.requester_user_reference =
                                        members.user_reference
                                )
                            )
                    ) AS requester_ticket_count,

                    (
                        SELECT COUNT(*)

                        FROM ticketing_tickets
                            AS requester_open_tickets

                        INNER JOIN ticketing_statuses
                            AS requester_open_status
                          ON requester_open_status.code =
                                requester_open_tickets.status_code

                        WHERE
                            requester_open_tickets.support_project_id =
                                members.project_id

                            AND requester_open_status.is_closed = 0

                            AND
                            (
                                (
                                    members.participant_id IS NOT NULL

                                    AND
                                    requester_open_tickets.requester_participant_id =
                                        members.participant_id
                                )

                                OR

                                (
                                    members.user_reference IS NOT NULL

                                    AND members.user_reference <> ''

                                    AND
                                    requester_open_tickets.requester_user_reference =
                                        members.user_reference
                                )
                            )
                    ) AS requester_open_ticket_count,

                    (
                        SELECT COUNT(*)

                        FROM ticketing_tickets
                            AS owned_tickets

                        INNER JOIN ticketing_statuses
                            AS owned_status
                          ON owned_status.code =
                                owned_tickets.status_code

                        WHERE
                            owned_tickets.support_project_id =
                                members.project_id

                            AND
                            owned_tickets.current_assignee_project_member_id =
                                members.id

                            AND owned_status.is_closed = 0
                    ) AS owned_open_ticket_count

                FROM
                    ticketing_support_project_members
                        AS members

                LEFT JOIN
                    ticketing_participants
                        AS participants
                  ON participants.id =
                        members.participant_id

                WHERE members.project_id = ?

                ORDER BY
                    members.left_at IS NOT NULL,

                    FIELD(
                        members.role_code,
                        'manager',
                        'member',
                        'requester'
                    ),

                    members.display_name_snapshot,
                    members.id
            ");

        $membersStatement->execute([
            $projectId,
        ]);

        $members =
            $membersStatement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $teamMembershipStatement =
            $this->ticketing->prepare("
                SELECT
                    team_members.id,
                    team_members.team_id,
                    team_members.project_member_id,
                    team_members.staff_role_code,
                    team_members.workload_weight,
                    team_members.can_assign,
                    team_members.can_observe,
                    team_members.can_assist,
                    team_members.can_takeover,
                    team_members.can_transfer,
                    team_members.status,
                    team_members.joined_at,
                    team_members.left_at,

                    teams.public_reference
                        AS team_reference,

                    teams.code
                        AS team_code,

                    teams.title
                        AS team_title,

                    teams.sort_order
                        AS team_sort_order

                FROM
                    ticketing_support_team_members
                        AS team_members

                INNER JOIN
                    ticketing_support_project_members
                        AS project_members
                  ON project_members.id =
                        team_members.project_member_id

                INNER JOIN
                    ticketing_support_teams
                        AS teams
                  ON teams.id =
                        team_members.team_id

                WHERE
                    project_members.project_id = ?

                ORDER BY
                    team_members.left_at IS NOT NULL,
                    teams.sort_order,
                    teams.id,
                    team_members.id
            ");

        $teamMembershipStatement->execute([
            $projectId,
        ]);

        $teamRows =
            $teamMembershipStatement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $byMember = [];

        foreach ($teamRows as $teamRow) {
            $memberId =
                (int) (
                    $teamRow[
                        'project_member_id'
                    ]
                    ?? 0
                );

            if ($memberId < 1) {
                continue;
            }

            $teamRow['active'] =
                (
                    (string) (
                        $teamRow['status']
                        ?? ''
                    ) === 'active'
                    &&
                    empty(
                        $teamRow['left_at']
                    )
                );

            $teamRow['staff_role_title'] =
                self::STAFF_ROLE_TITLES[
                    (string) (
                        $teamRow[
                            'staff_role_code'
                        ]
                        ?? ''
                    )
                ]
                ?? (string) (
                    $teamRow[
                        'staff_role_code'
                    ]
                    ?? ''
                );

            $byMember[$memberId][] =
                $teamRow;
        }

        $summary = [
            'total' => count($members),
            'active' => 0,
            'inactive' => 0,
            'requester' => 0,
            'staff' => 0,
            'manager' => 0,
        ];

        foreach ($members as &$member) {
            $memberId =
                (int) $member['id'];

            $active =
                empty(
                    $member['left_at']
                );

            $role =
                (string) (
                    $member['role_code']
                    ?? ''
                );

            $member['active'] =
                $active;

            $member['role_title'] =
                self::PROJECT_ROLE_TITLES[
                    $role
                ]
                ?? $role;

            $member['teams'] =
                $byMember[$memberId]
                ?? [];

            $member['active_team_count'] =
                count(
                    array_filter(
                        $member['teams'],
                        static fn (
                            array $team
                        ): bool =>
                            !empty(
                                $team['active']
                            )
                    )
                );

            if ($active) {
                $summary['active']++;

                if ($role === 'requester') {
                    $summary['requester']++;
                } else {
                    $summary['staff']++;

                    if ($role === 'manager') {
                        $summary['manager']++;
                    }
                }
            } else {
                $summary['inactive']++;
            }
        }

        unset($member);

        $teamsStatement =
            $this->ticketing->prepare("
                SELECT
                    id,
                    public_reference,
                    code,
                    title,
                    sort_order

                FROM ticketing_support_teams

                WHERE project_id = ?
                  AND status = 'active'

                ORDER BY
                    sort_order,
                    id
            ");

        $teamsStatement->execute([
            $projectId,
        ]);

        return [
            'ok' => true,

            'project' =>
                $project,

            'members' =>
                $members,

            'teams' =>
                $teamsStatement->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [],

            'project_role_options' =>
                self::PROJECT_ROLE_TITLES,

            'staff_role_options' =>
                self::STAFF_ROLE_TITLES,

            'summary' =>
                $summary,
        ];
    }


    public function changeRole(
        string $projectReference,
        int $memberId,
        string $roleCode,
        int $actorUserId
    ): array {
        $roleCode =
            trim($roleCode);

        if (
            $memberId < 1
            ||
            !in_array(
                $roleCode,
                self::PROJECT_ROLES,
                true
            )
        ) {
            return [
                'ok' => false,
                'error' =>
                    'member_invalid',
            ];
        }

        $this->ticketing
            ->beginTransaction();

        try {
            $project =
                $this->lockProject(
                    $projectReference
                );

            if (!is_array($project)) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'project_not_found',
                ];
            }

            $member =
                $this->lockMember(
                    (int) $project['id'],
                    $memberId
                );

            if (!is_array($member)) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'member_invalid',
                ];
            }

            if (!empty($member['left_at'])) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'member_inactive',
                ];
            }

            $currentRole =
                (string) (
                    $member['role_code']
                    ?? ''
                );

            if ($currentRole === $roleCode) {
                $this->ticketing
                    ->commit();

                return [
                    'ok' => true,
                    'state' =>
                        'member_role_saved',
                ];
            }

            /*
             * One project membership cannot be both requester and staff.
             *
             * Therefore a requester with an open requester ticket cannot
             * be promoted until their requester conversation is complete.
             */
            if (
                $currentRole === 'requester'
                &&
                in_array(
                    $roleCode,
                    [
                        'member',
                        'manager',
                    ],
                    true
                )
                &&
                $this->requesterOpenTicketCount(
                    $member
                ) > 0
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'requester_open_tickets',
                ];
            }

            /*
             * Staff may not become requester while still owning an open
             * operational ticket.
             */
            if (
                in_array(
                    $currentRole,
                    [
                        'member',
                        'manager',
                    ],
                    true
                )
                &&
                $roleCode === 'requester'
            ) {
                if (
                    $this->ownedOpenTicketCount(
                        (int) $project['id'],
                        $memberId
                    ) > 0
                ) {
                    $this->ticketing
                        ->rollBack();

                    return [
                        'ok' => false,
                        'error' =>
                            'member_owned_open_tickets',
                    ];
                }

                $this->disableTeams(
                    $memberId
                );
            }

            $statement =
                $this->ticketing->prepare("
                    UPDATE
                        ticketing_support_project_members

                    SET
                        role_code = ?,
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()

                    WHERE id = ?
                      AND project_id = ?
                      AND left_at IS NULL
                ");

            $statement->execute([
                $roleCode,
                $this->actorReference(
                    $actorUserId
                ),
                $memberId,
                (int) $project['id'],
            ]);

            $this->ticketing
                ->commit();

            return [
                'ok' => true,
                'state' =>
                    'member_role_saved',
            ];

        } catch (Throwable $exception) {
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


    public function revoke(
        string $projectReference,
        int $memberId,
        int $actorUserId
    ): array {
        if ($memberId < 1) {
            return [
                'ok' => false,
                'error' =>
                    'member_invalid',
            ];
        }

        $this->ticketing
            ->beginTransaction();

        try {
            $project =
                $this->lockProject(
                    $projectReference
                );

            if (!is_array($project)) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'project_not_found',
                ];
            }

            $member =
                $this->lockMember(
                    (int) $project['id'],
                    $memberId
                );

            if (!is_array($member)) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'member_invalid',
                ];
            }

            if (!empty($member['left_at'])) {
                $this->ticketing
                    ->commit();

                return [
                    'ok' => true,
                    'state' =>
                        'member_revoked',
                ];
            }

            /*
             * Preserve requester history regardless of the member's current
             * project role. A person can have historical requester tickets
             * after later becoming staff.
             */
            if (
                $this->requesterOpenTicketCount(
                    $member
                ) > 0
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'requester_open_tickets',
                ];
            }

            if (
                $this->ownedOpenTicketCount(
                    (int) $project['id'],
                    $memberId
                ) > 0
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'member_owned_open_tickets',
                ];
            }

            $this->disableTeams(
                $memberId
            );

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
                      AND left_at IS NULL
                ");

            $statement->execute([
                $this->actorReference(
                    $actorUserId
                ),
                $memberId,
                (int) $project['id'],
            ]);

            $this->ticketing
                ->commit();

            return [
                'ok' => true,
                'state' =>
                    'member_revoked',
            ];

        } catch (Throwable $exception) {
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


    public function restore(
        string $projectReference,
        int $memberId,
        int $actorUserId
    ): array {
        if ($memberId < 1) {
            return [
                'ok' => false,
                'error' =>
                    'member_invalid',
            ];
        }

        $this->ticketing
            ->beginTransaction();

        try {
            $project =
                $this->lockProject(
                    $projectReference
                );

            if (!is_array($project)) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'project_not_found',
                ];
            }

            $member =
                $this->lockMember(
                    (int) $project['id'],
                    $memberId
                );

            if (!is_array($member)) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'member_invalid',
                ];
            }

            if (empty($member['left_at'])) {
                $this->ticketing
                    ->commit();

                return [
                    'ok' => true,
                    'state' =>
                        'member_restored',
                ];
            }

            /*
             * Team memberships are deliberately NOT restored here.
             * Operational access must be granted explicitly after restore.
             */
            $statement =
                $this->ticketing->prepare("
                    UPDATE
                        ticketing_support_project_members

                    SET
                        joined_at = UTC_TIMESTAMP(),
                        left_at = NULL,
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()

                    WHERE id = ?
                      AND project_id = ?
                ");

            $statement->execute([
                $this->actorReference(
                    $actorUserId
                ),
                $memberId,
                (int) $project['id'],
            ]);

            $this->ticketing
                ->commit();

            return [
                'ok' => true,
                'state' =>
                    'member_restored',
            ];

        } catch (Throwable $exception) {
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


    public function saveTeam(
        string $projectReference,
        int $memberId,
        array $input,
        int $actorUserId
    ): array {
        $teamId =
            max(
                0,
                (int) (
                    $input['team_id']
                    ?? 0
                )
            );

        $staffRole =
            trim(
                (string) (
                    $input[
                        'staff_role_code'
                    ]
                    ?? ''
                )
            );

        if (
            $memberId < 1
            ||
            $teamId < 1
            ||
            !in_array(
                $staffRole,
                self::STAFF_ROLES,
                true
            )
        ) {
            return [
                'ok' => false,
                'error' =>
                    'team_invalid',
            ];
        }

        $flags = [
            'can_assign' =>
                $this->flag(
                    $input[
                        'can_assign'
                    ]
                    ?? 0
                ),

            'can_observe' =>
                $this->flag(
                    $input[
                        'can_observe'
                    ]
                    ?? 0
                ),

            'can_assist' =>
                $this->flag(
                    $input[
                        'can_assist'
                    ]
                    ?? 0
                ),

            'can_takeover' =>
                $this->flag(
                    $input[
                        'can_takeover'
                    ]
                    ?? 0
                ),

            'can_transfer' =>
                $this->flag(
                    $input[
                        'can_transfer'
                    ]
                    ?? 0
                ),
        ];

        $this->ticketing
            ->beginTransaction();

        try {
            $project =
                $this->lockProject(
                    $projectReference
                );

            if (!is_array($project)) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'project_not_found',
                ];
            }

            $member =
                $this->lockMember(
                    (int) $project['id'],
                    $memberId
                );

            if (
                !is_array($member)
                ||
                !empty($member['left_at'])
                ||
                !in_array(
                    (string) (
                        $member['role_code']
                        ?? ''
                    ),
                    [
                        'member',
                        'manager',
                    ],
                    true
                )
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'team_staff_role_required',
                ];
            }

            $teamStatement =
                $this->ticketing->prepare("
                    SELECT id

                    FROM ticketing_support_teams

                    WHERE id = ?
                      AND project_id = ?
                      AND status = 'active'

                    LIMIT 1
                    FOR UPDATE
                ");

            $teamStatement->execute([
                $teamId,
                (int) $project['id'],
            ]);

            if (
                $teamStatement->fetchColumn()
                === false
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'team_invalid',
                ];
            }

            $existingStatement =
                $this->ticketing->prepare("
                    SELECT id

                    FROM
                        ticketing_support_team_members

                    WHERE team_id = ?
                      AND project_member_id = ?

                    LIMIT 1
                    FOR UPDATE
                ");

            $existingStatement->execute([
                $teamId,
                $memberId,
            ]);

            $existingId =
                (int) (
                    $existingStatement
                        ->fetchColumn()
                    ?: 0
                );

            $metadata =
                json_encode(
                    [
                        'managed_via' =>
                            'project_member_access_center',

                        'updated_by' =>
                            $this->actorReference(
                                $actorUserId
                            ),
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );

            if ($existingId > 0) {
                $statement =
                    $this->ticketing->prepare("
                        UPDATE
                            ticketing_support_team_members

                        SET
                            staff_role_code = ?,
                            workload_weight = 1.0000,
                            can_assign = ?,
                            can_observe = ?,
                            can_assist = ?,
                            can_takeover = ?,
                            can_transfer = ?,
                            status = 'active',
                            joined_at = UTC_TIMESTAMP(),
                            left_at = NULL,
                            metadata_json = ?,
                            updated_at = UTC_TIMESTAMP()

                        WHERE id = ?
                    ");

                $statement->execute([
                    $staffRole,
                    $flags['can_assign'],
                    $flags['can_observe'],
                    $flags['can_assist'],
                    $flags['can_takeover'],
                    $flags['can_transfer'],
                    $metadata,
                    $existingId,
                ]);
            } else {
                $statement =
                    $this->ticketing->prepare("
                        INSERT INTO
                            ticketing_support_team_members
                        (
                            team_id,
                            project_member_id,
                            staff_role_code,
                            workload_weight,
                            can_assign,
                            can_observe,
                            can_assist,
                            can_takeover,
                            can_transfer,
                            status,
                            joined_at,
                            left_at,
                            metadata_json,
                            created_at,
                            updated_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            1.0000,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            'active',
                            UTC_TIMESTAMP(),
                            NULL,
                            ?,
                            UTC_TIMESTAMP(),
                            UTC_TIMESTAMP()
                        )
                    ");

                $statement->execute([
                    $teamId,
                    $memberId,
                    $staffRole,
                    $flags['can_assign'],
                    $flags['can_observe'],
                    $flags['can_assist'],
                    $flags['can_takeover'],
                    $flags['can_transfer'],
                    $metadata,
                ]);
            }

            $this->ticketing
                ->commit();

            return [
                'ok' => true,
                'state' =>
                    'team_saved',
            ];

        } catch (Throwable $exception) {
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


    public function removeTeam(
        string $projectReference,
        int $memberId,
        int $teamId,
        int $actorUserId
    ): array {
        if (
            $memberId < 1
            ||
            $teamId < 1
        ) {
            return [
                'ok' => false,
                'error' =>
                    'team_invalid',
            ];
        }

        $this->ticketing
            ->beginTransaction();

        try {
            $project =
                $this->lockProject(
                    $projectReference
                );

            if (!is_array($project)) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'project_not_found',
                ];
            }

            $member =
                $this->lockMember(
                    (int) $project['id'],
                    $memberId
                );

            if (
                !is_array($member)
                ||
                !empty($member['left_at'])
                ||
                !in_array(
                    (string) (
                        $member['role_code']
                        ?? ''
                    ),
                    [
                        'member',
                        'manager',
                    ],
                    true
                )
            ) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'team_staff_role_required',
                ];
            }

            $team =
                $this->ticketing->prepare("
                    SELECT id

                    FROM ticketing_support_teams

                    WHERE id = ?
                      AND project_id = ?

                    LIMIT 1
                    FOR UPDATE
                ");

            $team->execute([
                $teamId,
                (int) $project['id'],
            ]);

            if ($team->fetchColumn() === false) {
                $this->ticketing
                    ->rollBack();

                return [
                    'ok' => false,
                    'error' =>
                        'team_invalid',
                ];
            }

            $statement =
                $this->ticketing->prepare("
                    UPDATE
                        ticketing_support_team_members

                    SET
                        status = 'inactive',
                        left_at = COALESCE(
                            left_at,
                            UTC_TIMESTAMP()
                        ),
                        metadata_json = ?,
                        updated_at = UTC_TIMESTAMP()

                    WHERE team_id = ?
                      AND project_member_id = ?
                      AND status = 'active'
                      AND left_at IS NULL
                ");

            $statement->execute([
                json_encode(
                    [
                        'managed_via' =>
                            'project_member_access_center',

                        'removed_by' =>
                            $this->actorReference(
                                $actorUserId
                            ),
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
                $teamId,
                $memberId,
            ]);

            $this->ticketing
                ->commit();

            return [
                'ok' => true,
                'state' =>
                    'team_removed',
            ];

        } catch (Throwable $exception) {
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


    private function project(
        string $projectReference
    ): ?array {
        $reference =
            trim($projectReference);

        if ($reference === '') {
            return null;
        }

        $statement =
            $this->ticketing->prepare("
                SELECT
                    id,
                    public_reference,
                    code,
                    title,
                    is_active,
                    archived_at

                FROM
                    ticketing_support_projects

                WHERE public_reference = ?

                LIMIT 1
            ");

        $statement->execute([
            $reference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }


    private function lockProject(
        string $projectReference
    ): ?array {
        $reference =
            trim($projectReference);

        if ($reference === '') {
            return null;
        }

        $statement =
            $this->ticketing->prepare("
                SELECT
                    id,
                    public_reference,
                    code,
                    title,
                    is_active,
                    archived_at

                FROM
                    ticketing_support_projects

                WHERE public_reference = ?

                LIMIT 1
                FOR UPDATE
            ");

        $statement->execute([
            $reference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }


    private function lockMember(
        int $projectId,
        int $memberId
    ): ?array {
        if (
            $projectId < 1
            ||
            $memberId < 1
        ) {
            return null;
        }

        $statement =
            $this->ticketing->prepare("
                SELECT
                    id,
                    project_id,
                    participant_id,
                    user_reference,
                    display_name_snapshot,
                    role_code,
                    joined_at,
                    left_at

                FROM
                    ticketing_support_project_members

                WHERE id = ?
                  AND project_id = ?

                LIMIT 1
                FOR UPDATE
            ");

        $statement->execute([
            $memberId,
            $projectId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }


    private function requesterOpenTicketCount(
        array $member
    ): int {
        $projectId =
            (int) (
                $member['project_id']
                ?? 0
            );

        $participantId =
            (int) (
                $member['participant_id']
                ?? 0
            );

        $userReference =
            trim(
                (string) (
                    $member['user_reference']
                    ?? ''
                )
            );

        if (
            $projectId < 1
            ||
            (
                $participantId < 1
                &&
                $userReference === ''
            )
        ) {
            return 0;
        }

        $statement =
            $this->ticketing->prepare("
                SELECT COUNT(*)

                FROM ticketing_tickets
                    AS tickets

                INNER JOIN ticketing_statuses
                    AS statuses
                  ON statuses.code =
                        tickets.status_code

                WHERE tickets.support_project_id = ?

                  AND statuses.is_closed = 0

                  AND
                  (
                      (
                          ? > 0

                          AND
                          tickets.requester_participant_id = ?
                      )

                      OR

                      (
                          ? <> ''

                          AND
                          tickets.requester_user_reference = ?
                      )
                  )
            ");

        $statement->execute([
            $projectId,
            $participantId,
            $participantId,
            $userReference,
            $userReference,
        ]);

        return
            (int) $statement
                ->fetchColumn();
    }


    private function ownedOpenTicketCount(
        int $projectId,
        int $memberId
    ): int {
        if (
            $projectId < 1
            ||
            $memberId < 1
        ) {
            return 0;
        }

        $statement =
            $this->ticketing->prepare("
                SELECT COUNT(*)

                FROM ticketing_tickets
                    AS tickets

                INNER JOIN ticketing_statuses
                    AS statuses
                  ON statuses.code =
                        tickets.status_code

                WHERE
                    tickets.support_project_id = ?

                    AND
                    tickets.current_assignee_project_member_id = ?

                    AND statuses.is_closed = 0
            ");

        $statement->execute([
            $projectId,
            $memberId,
        ]);

        return
            (int) $statement
                ->fetchColumn();
    }


    private function disableTeams(
        int $memberId
    ): void {
        if ($memberId < 1) {
            return;
        }

        $statement =
            $this->ticketing->prepare("
                UPDATE
                    ticketing_support_team_members

                SET
                    status = 'inactive',
                    left_at = COALESCE(
                        left_at,
                        UTC_TIMESTAMP()
                    ),
                    updated_at = UTC_TIMESTAMP()

                WHERE project_member_id = ?
                  AND status = 'active'
                  AND left_at IS NULL
            ");

        $statement->execute([
            $memberId,
        ]);
    }


    private function actorReference(
        int $actorUserId
    ): ?string {
        return $actorUserId > 0
            ? 'user:' . $actorUserId
            : null;
    }


    private function flag(
        mixed $value
    ): int {
        return in_array(
            $value,
            [
                1,
                '1',
                true,
                'true',
                'on',
                'yes',
            ],
            true
        )
            ? 1
            : 0;
    }
}

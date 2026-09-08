<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

final class TicketLifecycleTransitionRepository
{
    private PDO $db;

    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $this->db =
            (
                $connections
                ?? new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );
    }


    /*
     * TICKETING_RESOLVE_CLOSE_REOPEN_DOMAIN
     *
     * Authorization truth:
     * - ticket support_project_id is authoritative
     * - Resolve: exact current operational owner
     * - Close: exact current operational owner OR project manager
     * - Reopen: project manager OR exact requester
     *
     * Route/RBAC permission is only a coarse outer gate.
     */
    public function capabilities(
        string $publicReference,
        string $actorUserReference
    ): array {
        $ticket =
            $this->ticketContext(
                trim($publicReference),
                trim($actorUserReference),
                false
            );

        if ($ticket === null) {
            return [
                'found' => false,
                'can_resolve' => false,
                'can_close' => false,
                'can_reopen' => false,
            ];
        }

        return
            $this->decorateCapabilities(
                $ticket,
                trim($actorUserReference)
            );
    }


    public function transition(
        string $publicReference,
        string $action,
        string $actorUserReference,
        string $actorDisplayName
    ): array {
        $publicReference =
            trim($publicReference);

        $action =
            strtolower(
                trim($action)
            );

        $actorUserReference =
            trim($actorUserReference);

        $actorDisplayName =
            trim($actorDisplayName);

        if (
            $publicReference === ''
            ||
            $actorUserReference === ''
            ||
            !in_array(
                $action,
                [
                    'resolve',
                    'close',
                    'reopen',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'lifecycle_invalid_action'
            );
        }

        if ($actorDisplayName === '') {
            $actorDisplayName =
                $actorUserReference;
        }

        $this->db->beginTransaction();

        try {
            $ticket =
                $this->ticketContext(
                    $publicReference,
                    $actorUserReference,
                    true
                );

            if ($ticket === null) {
                throw new RuntimeException(
                    'ticket_not_found'
                );
            }

            $capabilities =
                $this->decorateCapabilities(
                    $ticket,
                    $actorUserReference
                );

            $previousStatus =
                trim(
                    (string) (
                        $ticket['status_code']
                        ?? ''
                    )
                );

            $targetStatus = '';
            $eventCode = '';

            if ($action === 'resolve') {
                if (
                    $previousStatus ===
                        'waiting_requester'
                ) {
                    throw new RuntimeException(
                        'lifecycle_waiting_requester'
                    );
                }

                if (
                    !empty(
                        $ticket[
                            'status_is_closed'
                        ]
                    )
                ) {
                    throw new RuntimeException(
                        'lifecycle_invalid_state'
                    );
                }

                if (
                    empty(
                        $capabilities[
                            'is_current_owner'
                        ]
                    )
                ) {
                    throw new RuntimeException(
                        'lifecycle_owner_required'
                    );
                }

                $targetStatus =
                    'resolved';

                $eventCode =
                    'ticket_resolved';
            }

            if ($action === 'close') {
                if (
                    $previousStatus !==
                        'resolved'
                ) {
                    throw new RuntimeException(
                        'lifecycle_resolve_first'
                    );
                }

                if (
                    empty(
                        $capabilities[
                            'is_current_owner'
                        ]
                    )
                    &&
                    empty(
                        $capabilities[
                            'is_project_manager'
                        ]
                    )
                ) {
                    throw new RuntimeException(
                        'lifecycle_close_forbidden'
                    );
                }

                $targetStatus =
                    'closed';

                $eventCode =
                    'ticket_closed';
            }

            if ($action === 'reopen') {
                if (
                    $previousStatus !==
                        'closed'
                ) {
                    throw new RuntimeException(
                        'lifecycle_reopen_invalid_state'
                    );
                }

                if (
                    empty(
                        $capabilities[
                            'is_project_manager'
                        ]
                    )
                    &&
                    empty(
                        $capabilities[
                            'is_requester'
                        ]
                    )
                ) {
                    throw new RuntimeException(
                        'lifecycle_reopen_forbidden'
                    );
                }

                $targetStatus =
                    'in_progress';

                $eventCode =
                    'ticket_reopened';
            }

            if (
                $targetStatus === ''
                ||
                $eventCode === ''
            ) {
                throw new RuntimeException(
                    'lifecycle_invalid_action'
                );
            }


            if ($action === 'resolve') {
                $update =
                    $this->db->prepare("
                        UPDATE ticketing_tickets

                        SET
                            status_code =
                                'resolved',

                            resolved_at =
                                COALESCE(
                                    resolved_at,
                                    UTC_TIMESTAMP()
                                ),

                            closed_at = NULL,

                            last_activity_at =
                                UTC_TIMESTAMP(),

                            updated_by_user_reference = ?,
                            updated_at =
                                UTC_TIMESTAMP()

                        WHERE id = ?
                          AND status_code = ?
                    ");
            } elseif ($action === 'close') {
                $update =
                    $this->db->prepare("
                        UPDATE ticketing_tickets

                        SET
                            status_code =
                                'closed',

                            resolved_at =
                                COALESCE(
                                    resolved_at,
                                    UTC_TIMESTAMP()
                                ),

                            closed_at =
                                UTC_TIMESTAMP(),

                            last_activity_at =
                                UTC_TIMESTAMP(),

                            updated_by_user_reference = ?,
                            updated_at =
                                UTC_TIMESTAMP()

                        WHERE id = ?
                          AND status_code =
                                'resolved'
                    ");
            } else {
                $update =
                    $this->db->prepare("
                        UPDATE ticketing_tickets

                        SET
                            status_code =
                                'in_progress',

                            resolved_at = NULL,
                            closed_at = NULL,

                            last_activity_at =
                                UTC_TIMESTAMP(),

                            updated_by_user_reference = ?,
                            updated_at =
                                UTC_TIMESTAMP()

                        WHERE id = ?
                          AND status_code =
                                'closed'
                    ");
            }

            $parameters = [
                $actorUserReference,
                (int) $ticket['id'],
            ];

            if ($action === 'resolve') {
                $parameters[] =
                    $previousStatus;
            }

            $update->execute(
                $parameters
            );

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'lifecycle_transition_conflict'
                );
            }


            $payload =
                [
                    'action' =>
                        $action,

                    'previous_status_code' =>
                        $previousStatus,

                    'resulting_status_code' =>
                        $targetStatus,

                    'assignment_preserved' =>
                        true,

                    'routing_preserved' =>
                        true,

                    'actor_project_role' =>
                        (string) (
                            $ticket[
                                'actor_project_role_code'
                            ]
                            ?? ''
                        ),

                    'actor_staff_role' =>
                        (string) (
                            $ticket[
                                'actor_staff_role_code'
                            ]
                            ?? ''
                        ),
                ];

            $this->recordEvent(
                (int) $ticket['id'],
                $eventCode,
                $actorUserReference,
                $actorDisplayName,
                $previousStatus,
                $targetStatus,
                $payload
            );

            $this->db->commit();

            return [
                'ticket_id' =>
                    (int) $ticket['id'],

                'public_reference' =>
                    (string) $ticket[
                        'public_reference'
                    ],

                'ticket_number' =>
                    (string) (
                        $ticket[
                            'ticket_number'
                        ]
                        ?? ''
                    ),

                'action' =>
                    $action,

                'previous_status_code' =>
                    $previousStatus,

                'resulting_status_code' =>
                    $targetStatus,

                'event_code' =>
                    $eventCode,

                'assignment_preserved' =>
                    true,

                'routing_preserved' =>
                    true,
            ];

        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    /*
     * TICKETING_AUTO_CLOSE_SYSTEM_TRANSITION_V1
     *
     * This is intentionally separate from the human
     * transition() authorization path.
     *
     * Only the exact internal Auto-close actor is accepted.
     * Row lock, resolved-state guard, atomic ticket update
     * and canonical ticket_closed audit event are preserved.
     */
    public function closeResolvedBySystem(
        string $publicReference,
        int $expectedProjectId,
        string $actorUserReference,
        string $actorDisplayName,
        int $policyId,
        int $delayHours,
        string $eligibleResolvedFrom,
        string $cutoffAt
    ): array {
        $publicReference =
            trim(
                $publicReference
            );

        $actorUserReference =
            trim(
                $actorUserReference
            );

        $actorDisplayName =
            trim(
                $actorDisplayName
            );

        $eligibleResolvedFrom =
            trim(
                $eligibleResolvedFrom
            );

        $cutoffAt =
            trim(
                $cutoffAt
            );

        if (
            $publicReference === ''
            ||
            $expectedProjectId < 1
            ||
            $actorUserReference !==
                'system:ticketing-auto-close'
            ||
            $policyId < 1
            ||
            $delayHours < 1
            ||
            $eligibleResolvedFrom === ''
            ||
            $cutoffAt === ''
        ) {
            throw new RuntimeException(
                'auto_close_transition_invalid'
            );
        }

        if ($actorDisplayName === '') {
            $actorDisplayName =
                'بستن خودکار سامانه';
        }

        $this->db->beginTransaction();

        try {
            /*
             * Revalidate the exact policy under row lock.
             *
             * This prevents a stale Scheduler execution
             * from continuing after an operator disables
             * the policy or changes its delay/boundary.
             */
            $policyStatement =
                $this->db->prepare("
                    SELECT
                        id,
                        support_project_id,
                        is_enabled,
                        delay_hours,
                        eligible_resolved_from

                    FROM
                        ticketing_auto_close_policies

                    WHERE id = ?
                      AND support_project_id = ?

                    LIMIT 1

                    FOR UPDATE
                ");

            $policyStatement->execute([
                $policyId,
                $expectedProjectId,
            ]);

            $activePolicy =
                $policyStatement->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($activePolicy)) {
                throw new RuntimeException(
                    'auto_close_policy_not_found'
                );
            }

            if (
                (int) (
                    $activePolicy[
                        'is_enabled'
                    ]
                    ?? 0
                ) !== 1
            ) {
                throw new RuntimeException(
                    'auto_close_policy_disabled'
                );
            }

            $activeDelayHours =
                (int) (
                    $activePolicy[
                        'delay_hours'
                    ]
                    ?? 0
                );

            $activeEligibleResolvedFrom =
                trim(
                    (string) (
                        $activePolicy[
                            'eligible_resolved_from'
                        ]
                        ?? ''
                    )
                );

            if (
                $activeDelayHours
                    !== $delayHours
                ||
                $activeEligibleResolvedFrom
                    === ''
                ||
                !hash_equals(
                    $activeEligibleResolvedFrom,
                    $eligibleResolvedFrom
                )
            ) {
                throw new RuntimeException(
                    'auto_close_policy_changed'
                );
            }

            $statement =
                $this->db->prepare("
                    SELECT
                        id,
                        public_reference,
                        ticket_number,
                        support_project_id,
                        status_code,
                        resolved_at,
                        closed_at

                    FROM ticketing_tickets

                    WHERE public_reference = ?

                    LIMIT 1

                    FOR UPDATE
                ");

            $statement->execute([
                $publicReference,
            ]);

            $ticket =
                $statement->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($ticket)) {
                throw new RuntimeException(
                    'ticket_not_found'
                );
            }

            if (
                (int) (
                    $ticket[
                        'support_project_id'
                    ]
                    ?? 0
                )
                !==
                $expectedProjectId
            ) {
                throw new RuntimeException(
                    'auto_close_project_mismatch'
                );
            }

            if (
                trim(
                    (string) (
                        $ticket['status_code']
                        ?? ''
                    )
                ) !== 'resolved'
                ||
                empty(
                    $ticket['resolved_at']
                )
                ||
                !empty(
                    $ticket['closed_at']
                )
            ) {
                throw new RuntimeException(
                    'auto_close_ticket_not_resolved'
                );
            }

            $resolvedAt =
                trim(
                    (string)
                    $ticket['resolved_at']
                );

            /*
             * Critical race guard.
             *
             * Candidate discovery is only advisory.
             * The exact eligibility boundary and cutoff
             * are rechecked after locking the ticket.
             *
             * DATETIME values use canonical
             * YYYY-MM-DD HH:MM:SS storage format, so
             * strcmp() preserves chronological ordering.
             */
            if (
                strcmp(
                    $resolvedAt,
                    $eligibleResolvedFrom
                ) < 0
                ||
                strcmp(
                    $resolvedAt,
                    $cutoffAt
                ) > 0
            ) {
                throw new RuntimeException(
                    'auto_close_ticket_not_due'
                );
            }

            $update =
                $this->db->prepare("
                    UPDATE ticketing_tickets

                    SET
                        status_code =
                            'closed',

                        closed_at =
                            UTC_TIMESTAMP(),

                        last_activity_at =
                            UTC_TIMESTAMP(),

                        updated_by_user_reference = ?,

                        updated_at =
                            UTC_TIMESTAMP()

                    WHERE id = ?
                      AND support_project_id = ?
                      AND status_code =
                            'resolved'
                      AND resolved_at
                            IS NOT NULL

                      AND resolved_at >= ?
                      AND resolved_at <= ?

                      AND closed_at IS NULL
                ");

            $update->execute([
                $actorUserReference,
                (int) $ticket['id'],
                $expectedProjectId,
                $eligibleResolvedFrom,
                $cutoffAt,
            ]);

            if (
                $update->rowCount()
                !== 1
            ) {
                throw new RuntimeException(
                    'auto_close_transition_conflict'
                );
            }

            $payload = [
                'action' =>
                    'close',

                'previous_status_code' =>
                    'resolved',

                'resulting_status_code' =>
                    'closed',

                'assignment_preserved' =>
                    true,

                'routing_preserved' =>
                    true,

                'actor_type' =>
                    'system',

                'automation_key' =>
                    'ticketing.ticket.auto_close',

                'policy_id' =>
                    $policyId,

                'project_id' =>
                    $expectedProjectId,

                'delay_hours' =>
                    $delayHours,

                'eligible_resolved_from' =>
                    $eligibleResolvedFrom,

                'cutoff_at' =>
                    $cutoffAt,

                'ticket_resolved_at' =>
                    (string)
                    $ticket['resolved_at'],
            ];

            $this->recordEvent(
                (int) $ticket['id'],
                'ticket_closed',
                $actorUserReference,
                $actorDisplayName,
                'resolved',
                'closed',
                $payload
            );

            $this->db->commit();

            return [
                'ticket_id' =>
                    (int) $ticket['id'],

                'public_reference' =>
                    (string)
                    $ticket[
                        'public_reference'
                    ],

                'ticket_number' =>
                    (string) (
                        $ticket[
                            'ticket_number'
                        ]
                        ?? ''
                    ),

                'action' =>
                    'close',

                'previous_status_code' =>
                    'resolved',

                'resulting_status_code' =>
                    'closed',

                'event_code' =>
                    'ticket_closed',

                'actor_user_reference' =>
                    $actorUserReference,

                'assignment_preserved' =>
                    true,

                'routing_preserved' =>
                    true,
            ];

        } catch (Throwable $exception) {
            if (
                $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        }
    }


    private function ticketContext(
        string $publicReference,
        string $actorUserReference,
        bool $lock
    ): ?array {
        if (
            $publicReference === ''
            ||
            $actorUserReference === ''
        ) {
            return null;
        }

        $sql = "
            SELECT
                tickets.id,
                tickets.public_reference,
                tickets.ticket_number,
                tickets.support_project_id,

                tickets.requester_user_reference,
                tickets.requester_display_name_snapshot,

                tickets.status_code,
                statuses.is_closed
                    AS status_is_closed,

                tickets.resolved_at,
                tickets.closed_at,

                tickets.current_support_team_id,
                tickets.current_assignee_project_member_id,

                actor_pm.id
                    AS actor_project_member_id,

                actor_pm.role_code
                    AS actor_project_role_code,

                actor_tm.id
                    AS actor_team_member_id,

                actor_tm.staff_role_code
                    AS actor_staff_role_code

            FROM ticketing_tickets
                AS tickets

            INNER JOIN ticketing_statuses
                AS statuses
              ON statuses.code =
                    tickets.status_code

            LEFT JOIN
                ticketing_support_project_members
                AS actor_pm
              ON actor_pm.project_id =
                    tickets.support_project_id

             AND actor_pm.user_reference = ?

             AND actor_pm.left_at IS NULL

            LEFT JOIN
                ticketing_support_team_members
                AS actor_tm
              ON actor_tm.project_member_id =
                    actor_pm.id

             AND actor_tm.team_id =
                    tickets.current_support_team_id

             AND actor_tm.status =
                    'active'

             AND actor_tm.left_at IS NULL

             AND actor_tm.staff_role_code IN (
                    'agent',
                    'supervisor',
                    'manager'
             )

            WHERE
                tickets.public_reference = ?

            LIMIT 1
        ";

        if ($lock) {
            $sql .= " FOR UPDATE";
        }

        $statement =
            $this->db->prepare(
                $sql
            );

        $statement->execute([
            $actorUserReference,
            $publicReference,
        ]);

        $ticket =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($ticket)
                ? $ticket
                : null;
    }


    private function decorateCapabilities(
        array $ticket,
        string $actorUserReference
    ): array {
        $projectRole =
            trim(
                (string) (
                    $ticket[
                        'actor_project_role_code'
                    ]
                    ?? ''
                )
            );

        $staffRole =
            trim(
                (string) (
                    $ticket[
                        'actor_staff_role_code'
                    ]
                    ?? ''
                )
            );

        $actorMemberId =
            (int) (
                $ticket[
                    'actor_project_member_id'
                ]
                ?? 0
            );

        $actorTeamMemberId =
            (int) (
                $ticket[
                    'actor_team_member_id'
                ]
                ?? 0
            );

        $currentAssigneeId =
            (int) (
                $ticket[
                    'current_assignee_project_member_id'
                ]
                ?? 0
            );

        $status =
            trim(
                (string) (
                    $ticket[
                        'status_code'
                    ]
                    ?? ''
                )
            );

        $statusIsClosed =
            (int) (
                $ticket[
                    'status_is_closed'
                ]
                ?? 0
            ) === 1;

        $isOperationalStaff =
            in_array(
                $projectRole,
                [
                    'member',
                    'manager',
                ],
                true
            )
            &&
            $actorTeamMemberId > 0
            &&
            in_array(
                $staffRole,
                [
                    'agent',
                    'supervisor',
                    'manager',
                ],
                true
            );

        $isCurrentOwner =
            $isOperationalStaff
            &&
            $actorMemberId > 0
            &&
            $currentAssigneeId > 0
            &&
            $actorMemberId ===
                $currentAssigneeId;

        $isProjectManager =
            $projectRole ===
                'manager';

        $requesterReference =
            trim(
                (string) (
                    $ticket[
                        'requester_user_reference'
                    ]
                    ?? ''
                )
            );

        $isRequester =
            $actorUserReference !== ''
            &&
            $requesterReference !== ''
            &&
            hash_equals(
                $requesterReference,
                $actorUserReference
            );


        $canResolve =
            $isCurrentOwner
            &&
            !$statusIsClosed
            &&
            $status !==
                'waiting_requester';

        $canClose =
            $status ===
                'resolved'
            &&
            (
                $isCurrentOwner
                ||
                $isProjectManager
            );

        $canReopen =
            $status ===
                'closed'
            &&
            (
                $isProjectManager
                ||
                $isRequester
            );


        return [
            'found' => true,

            'status_code' =>
                $status,

            'is_current_owner' =>
                $isCurrentOwner,

            'is_project_manager' =>
                $isProjectManager,

            'is_requester' =>
                $isRequester,

            'is_operational_staff' =>
                $isOperationalStaff,

            'can_resolve' =>
                $canResolve,

            'can_close' =>
                $canClose,

            'can_reopen' =>
                $canReopen,

            'actor_project_role_code' =>
                $projectRole,

            'actor_staff_role_code' =>
                $staffRole,

            'ticket' =>
                $ticket,
        ];
    }


    private function recordEvent(
        int $ticketId,
        string $eventCode,
        string $actorUserReference,
        string $actorDisplayName,
        string $previousStatus,
        string $resultingStatus,
        array $payload
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_events
                (
                    public_reference,
                    ticket_id,
                    event_code,
                    actor_user_reference,
                    actor_display_name_snapshot,
                    previous_status_code,
                    resulting_status_code,
                    payload_json,
                    occurred_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    UTC_TIMESTAMP()
                )
            ");

        $statement->execute([
            $this->reference('TEVT'),
            $ticketId,
            $eventCode,
            $actorUserReference,
            $actorDisplayName,
            $previousStatus !== ''
                ? $previousStatus
                : null,
            $resultingStatus,
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }


    private function reference(
        string $prefix
    ): string {
        return
            $prefix
            . '-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );
    }
}

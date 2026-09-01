<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class TicketStaffReplyAccessService
{
    private PDO $ticketing;


    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $this->ticketing =
            (
                $connections
                ?? new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );
    }


    /*
     * TICKETING_STAFF_REPLY_OWNERSHIP_GUARD
     * TICKETING_MULTI_PROJECT_REPLY_ISOLATION
     *
     * Permission:
     *     may the user perform staff reply operations at all?
     *
     * Assignment:
     *     is THIS ticket currently owned by THIS user inside
     *     THIS exact support project?
     *
     * A ticket from Project A can never be answered through
     * membership / assignment belonging to Project B.
     */
    public function evaluate(
        string $publicReference,
        int $userId
    ): array {

        $publicReference =
            trim(
                $publicReference
            );

        if (
            $publicReference === ''
            ||
            $userId < 1
        ) {
            return [
                'ok' => false,
                'can_reply' => false,
                'state' => 'reply_invalid',
            ];
        }


        $actorReference =
            'user:' . $userId;


        $statement =
            $this->ticketing->prepare("
                SELECT
                    tickets.id,
                    tickets.public_reference,
                    tickets.ticket_number,

                    tickets.support_project_id,

                    tickets.current_support_layer_id,
                    tickets.current_support_node_id,
                    tickets.current_support_queue_id,
                    tickets.current_support_team_id,

                    tickets.current_assignee_project_member_id,

                    tickets.status_code,

                    statuses.is_closed
                        AS status_is_closed,

                    assignee.id
                        AS assignee_member_id,

                    assignee.project_id
                        AS assignee_project_id,

                    assignee.user_reference
                        AS assignee_user_reference,

                    assignee.role_code
                        AS assignee_project_role_code,

                    assignee.left_at
                        AS assignee_left_at,

                    assignee_team.id
                        AS assignee_team_member_id,

                    assignee_team.staff_role_code
                        AS assignee_staff_role_code

                FROM
                    ticketing_tickets AS tickets

                INNER JOIN
                    ticketing_statuses AS statuses
                  ON statuses.code =
                        tickets.status_code

                LEFT JOIN
                    ticketing_support_project_members
                        AS assignee
                  ON assignee.id =
                        tickets.current_assignee_project_member_id

                 AND assignee.project_id =
                        tickets.support_project_id

                 AND assignee.left_at IS NULL

                LEFT JOIN
                    ticketing_support_team_members
                        AS assignee_team
                  ON assignee_team.project_member_id =
                        assignee.id

                 AND assignee_team.team_id =
                        tickets.current_support_team_id

                 AND assignee_team.status =
                        'active'

                 AND assignee_team.left_at
                        IS NULL

                 AND assignee_team.staff_role_code
                        IN (
                            'agent',
                            'supervisor',
                            'manager'
                        )

                WHERE
                    tickets.public_reference = ?

                LIMIT 1
            ");

        $statement->execute([
            $publicReference,
        ]);

        $ticket =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!is_array($ticket)) {
            return [
                'ok' => false,
                'can_reply' => false,
                'state' => 'ticket_not_found',
            ];
        }


        $statusCode =
            trim(
                (string) (
                    $ticket['status_code']
                    ?? ''
                )
            );


        /*
         * Terminal lifecycle.
         */
        if (
            (int) (
                $ticket['status_is_closed']
                ?? 0
            ) === 1
        ) {
            return [
                'ok' => true,
                'can_reply' => false,
                'state' => 'reply_closed',
                'ticket' => $ticket,
            ];
        }


        /*
         * Conversation turn belongs exclusively to requester.
         */
        if (
            $statusCode ===
            'waiting_requester'
        ) {
            return [
                'ok' => true,
                'can_reply' => false,
                'state' =>
                    'reply_waiting_requester',
                'ticket' => $ticket,
            ];
        }


        $assigneeMemberId =
            (int) (
                $ticket[
                    'current_assignee_project_member_id'
                ]
                ?? 0
            );


        /*
         * Ticket has not yet been taken by a staff member.
         */
        if ($assigneeMemberId < 1) {
            return [
                'ok' => true,
                'can_reply' => false,
                'state' =>
                    'reply_takeover_required',
                'ticket' => $ticket,
            ];
        }


        $resolvedAssigneeId =
            (int) (
                $ticket[
                    'assignee_member_id'
                ]
                ?? 0
            );

        $ticketProjectId =
            (int) (
                $ticket[
                    'support_project_id'
                ]
                ?? 0
            );

        $assigneeProjectId =
            (int) (
                $ticket[
                    'assignee_project_id'
                ]
                ?? 0
            );


        /*
         * Defensive multi-project isolation.
         *
         * An assignee row is only valid if it resolves from the
         * exact project to which the ticket belongs.
         */
        if (
            $resolvedAssigneeId < 1
            ||
            $resolvedAssigneeId
                !== $assigneeMemberId
            ||
            $ticketProjectId < 1
            ||
            $assigneeProjectId
                !== $ticketProjectId
        ) {
            return [
                'ok' => true,
                'can_reply' => false,
                'state' =>
                    'reply_assignment_invalid',
                'ticket' => $ticket,
            ];
        }


        /*
         * TICKETING_OPERATIONAL_STAFF_ROLE_ALIGNMENT
         *
         * An assignment is operational only when:
         * - project membership belongs to this ticket project;
         * - project role is member or manager;
         * - active membership exists in the ticket's CURRENT team;
         * - team staff role is agent/supervisor/manager.
         *
         * A requester row can never become staff merely because a
         * stale Team membership points to it.
         */
        $assigneeProjectRole =
            trim(
                (string) (
                    $ticket[
                        'assignee_project_role_code'
                    ]
                    ?? ''
                )
            );

        $assigneeTeamMemberId =
            (int) (
                $ticket[
                    'assignee_team_member_id'
                ]
                ?? 0
            );

        $assigneeStaffRole =
            trim(
                (string) (
                    $ticket[
                        'assignee_staff_role_code'
                    ]
                    ?? ''
                )
            );

        if (
            !in_array(
                $assigneeProjectRole,
                [
                    'member',
                    'manager',
                ],
                true
            )
            ||
            $assigneeTeamMemberId < 1
            ||
            !in_array(
                $assigneeStaffRole,
                [
                    'agent',
                    'supervisor',
                    'manager',
                ],
                true
            )
        ) {
            return [
                'ok' => true,
                'can_reply' => false,
                'state' =>
                    'reply_assignment_invalid',
                'ticket' => $ticket,
            ];
        }


        $assigneeReference =
            trim(
                (string) (
                    $ticket[
                        'assignee_user_reference'
                    ]
                    ?? ''
                )
            );


        /*
         * Exact assigned user only.
         *
         * Being staff/manager globally or being a member of some
         * other Ticketing project grants no reply authority here.
         */
        if (
            $assigneeReference === ''
            ||
            !hash_equals(
                $actorReference,
                $assigneeReference
            )
        ) {
            return [
                'ok' => true,
                'can_reply' => false,
                'state' =>
                    'reply_not_assignee',
                'ticket' => $ticket,
            ];
        }


        return [
            'ok' => true,
            'can_reply' => true,
            'state' => 'reply_owned',
            'ticket' => $ticket,
        ];
    }
}

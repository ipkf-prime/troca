<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

final class TicketLifecycleRepository
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


    public function staffReply(
        string $publicReference,
        string $body,
        string $actorUserReference,
        string $fallbackDisplayName
    ): array {
        $this->db->beginTransaction();

        try {
            $ticketStatement =
                $this->db->prepare("
                    SELECT
                        tickets.id,
                        tickets.public_reference,
                        tickets.ticket_number,
                        tickets.subject,
                        tickets.priority_code,
                        tickets.requester_user_reference,
                        tickets.requester_display_name_snapshot,
                        tickets.support_project_id,
                        tickets.status_code,
                        tickets.first_response_at,

                        COALESCE(
                            statuses.is_closed,
                            0
                        ) AS status_is_closed

                    FROM ticketing_tickets
                        AS tickets

                    LEFT JOIN ticketing_statuses
                        AS statuses
                      ON statuses.code =
                            tickets.status_code

                    WHERE tickets.public_reference = ?

                    LIMIT 1
                    FOR UPDATE
                ");

            $ticketStatement->execute([
                trim($publicReference),
            ]);

            $ticket =
                $ticketStatement->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($ticket)) {
                throw new RuntimeException(
                    'ticket_not_found'
                );
            }

            if (
                (int) (
                    $ticket['status_is_closed']
                    ?? 0
                ) === 1
            ) {
                throw new RuntimeException(
                    'ticket_closed'
                );
            }

            $projectId =
                (int) (
                    $ticket[
                        'support_project_id'
                    ]
                    ?? 0
                );

            if ($projectId < 1) {
                throw new RuntimeException(
                    'ticket_reply_membership_required'
                );
            }

            /*
             * Ticketing membership is checked inside
             * the same transaction as the reply.
             *
             * Core RBAC determines operation permission.
             * Ticketing membership determines project scope.
             */
            $membershipStatement =
                $this->db->prepare("
                    SELECT
                        id,
                        display_name_snapshot,
                        role_code

                    FROM
                        ticketing_support_project_members

                    WHERE project_id = ?
                      AND user_reference = ?
                      AND left_at IS NULL

                    ORDER BY id DESC
                    LIMIT 1
                ");

            $membershipStatement->execute([
                $projectId,
                trim($actorUserReference),
            ]);

            $membership =
                $membershipStatement->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($membership)) {
                throw new RuntimeException(
                    'ticket_reply_membership_required'
                );
            }

            $actorDisplayName =
                trim(
                    (string) (
                        $membership[
                            'display_name_snapshot'
                        ]
                        ?? ''
                    )
                );

            if ($actorDisplayName === '') {
                $actorDisplayName =
                    trim($fallbackDisplayName);
            }

            if ($actorDisplayName === '') {
                $actorDisplayName =
                    trim($actorUserReference);
            }

            $previousStatus =
                trim(
                    (string) (
                        $ticket['status_code']
                        ?? ''
                    )
                );

            $firstResponseRecorded =
                empty(
                    $ticket['first_response_at']
                );

            $messageReference =
                $this->reference('TMSG');

            $messageStatement =
                $this->db->prepare("
                    INSERT INTO ticketing_messages
                    (
                        public_reference,
                        ticket_id,
                        message_kind,
                        visibility_code,
                        author_kind,
                        author_user_reference,
                        author_display_name_snapshot,
                        body,
                        source_code,
                        created_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'reply',
                        'public',
                        'staff',
                        ?,
                        ?,
                        ?,
                        'portal',
                        UTC_TIMESTAMP()
                    )
                ");

            /*
             * $body is intentionally stored verbatim.
             * Validation trims only for blank detection.
             */
            $messageStatement->execute([
                $messageReference,
                (int) $ticket['id'],
                trim($actorUserReference),
                $actorDisplayName,
                $body,
            ]);

            $messageId =
                (int) $this->db->lastInsertId();

            if ($messageId < 1) {
                throw new RuntimeException(
                    'ticket_reply_message_insert_failed'
                );
            }

            /*
             * COALESCE is the write-once first-response
             * contract. Further staff replies never
             * overwrite first_response_at.
             */
            $ticketUpdate =
                $this->db->prepare("
                    UPDATE ticketing_tickets

                    SET
                        status_code =
                            'waiting_requester',

                        first_response_at =
                            COALESCE(
                                first_response_at,
                                UTC_TIMESTAMP()
                            ),

                        last_activity_at =
                            UTC_TIMESTAMP(),

                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()

                    WHERE id = ?
                ");

            $ticketUpdate->execute([
                trim($actorUserReference),
                (int) $ticket['id'],
            ]);

            if ($ticketUpdate->rowCount() !== 1) {
                throw new RuntimeException(
                    'ticket_reply_ticket_update_failed'
                );
            }

            $eventReference =
                $this->reference('TEVT');

            $payload =
                json_encode(
                    [
                        'message_id' =>
                            $messageId,

                        'message_reference' =>
                            $messageReference,

                        'first_response_recorded' =>
                            $firstResponseRecorded,

                        'previous_status_code' =>
                            $previousStatus,

                        'resulting_status_code' =>
                            'waiting_requester',
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );

            $eventStatement =
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
                        'ticket_staff_replied',
                        ?,
                        ?,
                        ?,
                        'waiting_requester',
                        ?,
                        UTC_TIMESTAMP()
                    )
                ");

            $eventStatement->execute([
                $eventReference,
                (int) $ticket['id'],
                trim($actorUserReference),
                $actorDisplayName,
                $previousStatus !== ''
                    ? $previousStatus
                    : null,
                $payload,
            ]);

            $this->db->commit();

            return [
                'ticket_id' =>
                    (int) $ticket['id'],

                'public_reference' =>
                    (string) $ticket[
                        'public_reference'
                    ],

                'event_reference' =>
                    $eventReference,

                'ticket_number' =>
                    (string) (
                        $ticket[
                            'ticket_number'
                        ]
                        ?? ''
                    ),

                'subject' =>
                    (string) (
                        $ticket[
                            'subject'
                        ]
                        ?? ''
                    ),

                'priority_code' =>
                    (string) (
                        $ticket[
                            'priority_code'
                        ]
                        ?? 'normal'
                    ),

                'requester_user_reference' =>
                    (string) (
                        $ticket[
                            'requester_user_reference'
                        ]
                        ?? ''
                    ),

                'actor_user_reference' =>
                    trim(
                        $actorUserReference
                    ),

                'message_id' =>
                    $messageId,

                'message_reference' =>
                    $messageReference,

                'first_response_recorded' =>
                    $firstResponseRecorded,

                'previous_status_code' =>
                    $previousStatus,

                'resulting_status_code' =>
                    'waiting_requester',

                'actor_display_name' =>
                    $actorDisplayName,
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function requesterReply(
        string $publicReference,
        string $body,
        string $actorUserReference
    ): array {
        $this->db->beginTransaction();

        try {
            $ticketStatement =
                $this->db->prepare("
                    SELECT
                        tickets.id,
                        tickets.public_reference,
                        tickets.ticket_number,
                        tickets.subject,
                        tickets.priority_code,

                        tickets.requester_user_reference,
                        tickets.requester_display_name_snapshot,

                        tickets.status_code,
                        tickets.first_response_at,

                        tickets.current_support_layer_id,
                        tickets.current_support_node_id,
                        tickets.current_support_queue_id,
                        tickets.current_support_team_id,
                        tickets.current_assignee_project_member_id,

                        current_assignee.user_reference
                            AS assignee_user_reference

                    FROM ticketing_tickets
                        AS tickets

                    LEFT JOIN
                        ticketing_support_project_members
                            AS current_assignee
                      ON current_assignee.id =
                            tickets.current_assignee_project_member_id

                    WHERE tickets.public_reference = ?

                    LIMIT 1
                    FOR UPDATE
                ");

            $ticketStatement->execute([
                trim($publicReference),
            ]);

            $ticket =
                $ticketStatement->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($ticket)) {
                throw new RuntimeException(
                    'ticket_not_found'
                );
            }

            $actorUserReference =
                trim($actorUserReference);

            $requesterUserReference =
                trim(
                    (string) (
                        $ticket[
                            'requester_user_reference'
                        ]
                        ?? ''
                    )
                );

            /*
             * Requester authorization is ownership-based.
             * Staff/core permissions do not grant this action.
             */
            if (
                $actorUserReference === ''
                ||
                $requesterUserReference === ''
                ||
                !hash_equals(
                    $requesterUserReference,
                    $actorUserReference
                )
            ) {
                throw new RuntimeException(
                    'requester_reply_forbidden'
                );
            }

            if (
                (string) (
                    $ticket['status_code']
                    ?? ''
                )
                !== 'waiting_requester'
            ) {
                throw new RuntimeException(
                    'requester_reply_not_expected'
                );
            }

            $actorDisplayName =
                trim(
                    (string) (
                        $ticket[
                            'requester_display_name_snapshot'
                        ]
                        ?? ''
                    )
                );

            if ($actorDisplayName === '') {
                $actorDisplayName =
                    $actorUserReference;
            }

            $messageReference =
                $this->reference(
                    'TMSG'
                );

            $messageStatement =
                $this->db->prepare("
                    INSERT INTO ticketing_messages
                    (
                        public_reference,
                        ticket_id,
                        message_kind,
                        visibility_code,
                        author_kind,
                        author_user_reference,
                        author_display_name_snapshot,
                        body,
                        source_code,
                        created_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'reply',
                        'public',
                        'requester',
                        ?,
                        ?,
                        ?,
                        'portal',
                        UTC_TIMESTAMP()
                    )
                ");

            $messageStatement->execute([
                $messageReference,
                (int) $ticket['id'],
                $actorUserReference,
                $actorDisplayName,
                $body,
            ]);

            $messageId =
                (int) $this->db
                    ->lastInsertId();

            if ($messageId < 1) {
                throw new RuntimeException(
                    'requester_reply_message_insert_failed'
                );
            }

            /*
             * Do not touch assignment/routing.
             * The same assigned support owner continues work.
             */
            $ticketUpdate =
                $this->db->prepare("
                    UPDATE ticketing_tickets

                    SET
                        status_code =
                            'in_progress',

                        last_activity_at =
                            UTC_TIMESTAMP(),

                        updated_by_user_reference = ?,
                        updated_at =
                            UTC_TIMESTAMP()

                    WHERE id = ?
                      AND status_code =
                            'waiting_requester'
                ");

            $ticketUpdate->execute([
                $actorUserReference,
                (int) $ticket['id'],
            ]);

            if (
                $ticketUpdate->rowCount()
                !== 1
            ) {
                throw new RuntimeException(
                    'requester_reply_ticket_update_failed'
                );
            }

            $eventReference =
                $this->reference(
                    'TEVT'
                );

            $payload =
                json_encode(
                    [
                        'message_id' =>
                            $messageId,

                        'message_reference' =>
                            $messageReference,

                        'previous_status_code' =>
                            'waiting_requester',

                        'resulting_status_code' =>
                            'in_progress',

                        'assignment_preserved' =>
                            true,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );

            $eventStatement =
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
                        'ticket_requester_replied',
                        ?,
                        ?,
                        'waiting_requester',
                        'in_progress',
                        ?,
                        UTC_TIMESTAMP()
                    )
                ");

            $eventStatement->execute([
                $eventReference,
                (int) $ticket['id'],
                $actorUserReference,
                $actorDisplayName,
                $payload,
            ]);

            $this->db->commit();

            return [
                'ticket_id' =>
                    (int) $ticket['id'],

                'public_reference' =>
                    (string) $ticket[
                        'public_reference'
                    ],

                'event_reference' =>
                    $eventReference,

                'ticket_number' =>
                    (string) (
                        $ticket[
                            'ticket_number'
                        ]
                        ?? ''
                    ),

                'subject' =>
                    (string) (
                        $ticket[
                            'subject'
                        ]
                        ?? ''
                    ),

                'priority_code' =>
                    (string) (
                        $ticket[
                            'priority_code'
                        ]
                        ?? 'normal'
                    ),

                'assignee_user_reference' =>
                    (string) (
                        $ticket[
                            'assignee_user_reference'
                        ]
                        ?? ''
                    ),

                'actor_user_reference' =>
                    $actorUserReference,

                'message_id' =>
                    $messageId,

                'message_reference' =>
                    $messageReference,

                'previous_status_code' =>
                    'waiting_requester',

                'resulting_status_code' =>
                    'in_progress',

                'actor_display_name' =>
                    $actorDisplayName,

                'assignment_preserved' =>
                    true,
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
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

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
        string $fallbackDisplayName,
        array $attachments = []
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
             * TICKETING_REPLY_MESSAGE_ATTACHMENTS
             *
             * Prepared private attachments are persisted
             * against the exact reply message ID created
             * in this same database transaction.
             */
            $this->persistReplyAttachments(
                (int) $ticket['id'],
                $messageId,
                $attachments
            );

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

            $this->cleanupReplyAttachmentFiles(
                $attachments
            );

            throw $exception;
        }
    }


    public function requesterReply(
        string $publicReference,
        string $body,
        string $actorUserReference,
        array $attachments = []
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
                        tickets.support_project_id,

                        tickets.requester_user_reference,
                        tickets.requester_display_name_snapshot,

                        tickets.status_code,
                        tickets.first_response_at,
                        tickets.resolved_at,

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

            if (
                $actorUserReference === ''
                || $requesterUserReference === ''
                || !hash_equals(
                    $requesterUserReference,
                    $actorUserReference
                )
            ) {
                throw new RuntimeException(
                    'requester_reply_forbidden'
                );
            }

            $previousStatusCode =
                trim(
                    (string) (
                        $ticket['status_code']
                        ?? ''
                    )
                );

            $allowedStatuses = [
                'new',
                'in_progress',
                'waiting_requester',
                'waiting_internal',
                'resolved',
            ];

            if (
                !in_array(
                    $previousStatusCode,
                    $allowedStatuses,
                    true
                )
            ) {
                throw new RuntimeException(
                    'requester_update_forbidden_state'
                );
            }

            $resultingStatusCode =
                in_array(
                    $previousStatusCode,
                    [
                        'waiting_requester',
                        'resolved',
                    ],
                    true
                )
                    ? 'in_progress'
                    : $previousStatusCode;

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
                $this->reference('TMSG');

            $messageStatement =
                $this->db->prepare("
                    INSERT INTO ticketing_messages (
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
                    ) VALUES (
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
                (int) $this->db->lastInsertId();

            if ($messageId < 1) {
                throw new RuntimeException(
                    'requester_reply_message_insert_failed'
                );
            }

            /*
             * TICKETING_REPLY_MESSAGE_ATTACHMENTS
             *
             * Link prepared private attachments to the exact
             * requester message created in this transaction.
             */
            $this->persistReplyAttachments(
                (int) $ticket['id'],
                $messageId,
                $attachments
            );

            $updateStatement =
                $this->db->prepare("
                    UPDATE ticketing_tickets
                    SET
                        status_code = ?,
                        resolved_at = CASE
                            WHEN ? = 'resolved'
                                THEN NULL
                            ELSE resolved_at
                        END,
                        last_activity_at = UTC_TIMESTAMP(),
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()
                    WHERE id = ?
                ");

            $updateStatement->execute([
                $resultingStatusCode,
                $previousStatusCode,
                $actorUserReference,
                (int) $ticket['id'],
            ]);

            $eventReference =
                $this->reference('TEVT');

            $eventPayload =
                json_encode(
                    [
                        'message_id' => $messageId,
                        'message_reference' =>
                            $messageReference,
                        'assignment_preserved' => true,
                        'reopened_from_resolved' =>
                            $previousStatusCode ===
                            'resolved',
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );

            $eventStatement =
                $this->db->prepare("
                    INSERT INTO ticketing_events (
                        public_reference,
                        ticket_id,
                        event_code,
                        actor_user_reference,
                        actor_display_name_snapshot,
                        previous_status_code,
                        resulting_status_code,
                        payload_json,
                        occurred_at
                    ) VALUES (
                        ?,
                        ?,
                        'ticket_requester_updated',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        UTC_TIMESTAMP()
                    )
                ");

            $eventStatement->execute([
                $eventReference,
                (int) $ticket['id'],
                $actorUserReference,
                $actorDisplayName,
                $previousStatusCode,
                $resultingStatusCode,
                $eventPayload,
            ]);

            $this->db->commit();

            return [
                'ticket_id' =>
                    (int) $ticket['id'],
                'public_reference' =>
                    (string) $ticket['public_reference'],
                'event_reference' =>
                    $eventReference,
                'ticket_number' =>
                    (string) ($ticket['ticket_number'] ?? ''),
                'subject' =>
                    (string) ($ticket['subject'] ?? ''),
                'priority_code' =>
                    (string) ($ticket['priority_code'] ?? ''),
                'support_project_id' =>
                    (int) ($ticket['support_project_id'] ?? 0),
                'requester_user_reference' =>
                    $requesterUserReference,
                'requester_display_name' =>
                    $actorDisplayName,
                'assignee_user_reference' =>
                    trim(
                        (string) (
                            $ticket['assignee_user_reference']
                            ?? ''
                        )
                    ),
                'actor_user_reference' =>
                    $actorUserReference,
                'actor_display_name' =>
                    $actorDisplayName,
                'message_id' =>
                    $messageId,
                'message_reference' =>
                    $messageReference,
                'previous_status_code' =>
                    $previousStatusCode,
                'resulting_status_code' =>
                    $resultingStatusCode,
                'assignment_preserved' => true,
            ];

        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->cleanupReplyAttachmentFiles(
                $attachments
            );

            throw $exception;
        }
    }

    public function requesterResolve(
        string $publicReference,
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
                        tickets.resolved_at,
                        tickets.current_assignee_project_member_id
                    FROM ticketing_tickets
                        AS tickets
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

            if (
                $actorUserReference === ''
                || $requesterUserReference === ''
                || !hash_equals(
                    $requesterUserReference,
                    $actorUserReference
                )
            ) {
                throw new RuntimeException(
                    'requester_reply_forbidden'
                );
            }

            $previousStatusCode =
                trim(
                    (string) (
                        $ticket['status_code']
                        ?? ''
                    )
                );

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

            if ($previousStatusCode === 'resolved') {
                $this->db->commit();

                return [
                    'ticket_id' =>
                        (int) $ticket['id'],
                    'public_reference' =>
                        (string) $ticket['public_reference'],
                    'event_reference' => null,
                    'ticket_number' =>
                        (string) ($ticket['ticket_number'] ?? ''),
                    'subject' =>
                        (string) ($ticket['subject'] ?? ''),
                    'priority_code' =>
                        (string) ($ticket['priority_code'] ?? ''),
                    'requester_user_reference' =>
                        $requesterUserReference,
                    'actor_user_reference' =>
                        $actorUserReference,
                    'actor_display_name' =>
                        $actorDisplayName,
                    'previous_status_code' =>
                        'resolved',
                    'resulting_status_code' =>
                        'resolved',
                    'assignment_preserved' => true,
                    'already_resolved' => true,
                ];
            }

            if (
                in_array(
                    $previousStatusCode,
                    [
                        'closed',
                        'cancelled',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'requester_resolve_forbidden_state'
                );
            }

            if (
                !in_array(
                    $previousStatusCode,
                    [
                        'new',
                        'in_progress',
                        'waiting_requester',
                        'waiting_internal',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'requester_resolve_forbidden_state'
                );
            }

            $updateStatement =
                $this->db->prepare("
                    UPDATE ticketing_tickets
                    SET
                        status_code = 'resolved',
                        resolved_at = COALESCE(
                            resolved_at,
                            UTC_TIMESTAMP()
                        ),
                        last_activity_at = UTC_TIMESTAMP(),
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()
                    WHERE id = ?
                ");

            $updateStatement->execute([
                $actorUserReference,
                (int) $ticket['id'],
            ]);

            $eventReference =
                $this->reference('TEVT');

            $eventPayload =
                json_encode(
                    [
                        'source' => 'requester',
                        'assignment_preserved' => true,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );

            $eventStatement =
                $this->db->prepare("
                    INSERT INTO ticketing_events (
                        public_reference,
                        ticket_id,
                        event_code,
                        actor_user_reference,
                        actor_display_name_snapshot,
                        previous_status_code,
                        resulting_status_code,
                        payload_json,
                        occurred_at
                    ) VALUES (
                        ?,
                        ?,
                        'ticket_requester_resolved',
                        ?,
                        ?,
                        ?,
                        'resolved',
                        ?,
                        UTC_TIMESTAMP()
                    )
                ");

            $eventStatement->execute([
                $eventReference,
                (int) $ticket['id'],
                $actorUserReference,
                $actorDisplayName,
                $previousStatusCode,
                $eventPayload,
            ]);

            $this->db->commit();

            return [
                'ticket_id' =>
                    (int) $ticket['id'],
                'public_reference' =>
                    (string) $ticket['public_reference'],
                'event_reference' =>
                    $eventReference,
                'ticket_number' =>
                    (string) ($ticket['ticket_number'] ?? ''),
                'subject' =>
                    (string) ($ticket['subject'] ?? ''),
                'priority_code' =>
                    (string) ($ticket['priority_code'] ?? ''),
                'requester_user_reference' =>
                    $requesterUserReference,
                'actor_user_reference' =>
                    $actorUserReference,
                'actor_display_name' =>
                    $actorDisplayName,
                'previous_status_code' =>
                    $previousStatusCode,
                'resulting_status_code' =>
                    'resolved',
                'assignment_preserved' => true,
                'already_resolved' => false,
            ];

        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function persistReplyAttachments(
        int $ticketId,
        int $messageId,
        array $attachments
    ): void {
        if ($attachments === []) {
            return;
        }

        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_attachments
                (
                    public_reference,
                    ticket_id,
                    message_id,
                    storage_disk,
                    storage_key,
                    original_name,
                    mime_type,
                    size_bytes,
                    checksum_sha256,
                    scan_status_code,
                    uploaded_by_user_reference,
                    deleted_at,
                    created_at
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
                    ?,
                    ?,
                    ?,
                    NULL,
                    UTC_TIMESTAMP()
                )
            ");

        foreach ($attachments as $attachment) {

            $reference =
                trim(
                    (string) (
                        $attachment[
                            'public_reference'
                        ]
                        ?? ''
                    )
                );

            $storageDisk =
                trim(
                    (string) (
                        $attachment[
                            'storage_disk'
                        ]
                        ?? ''
                    )
                );

            $storageKey =
                trim(
                    (string) (
                        $attachment[
                            'storage_key'
                        ]
                        ?? ''
                    )
                );

            $originalName =
                trim(
                    (string) (
                        $attachment[
                            'original_name'
                        ]
                        ?? ''
                    )
                );

            $mimeType =
                trim(
                    (string) (
                        $attachment[
                            'mime_type'
                        ]
                        ?? ''
                    )
                );

            $sizeBytes =
                (int) (
                    $attachment[
                        'size_bytes'
                    ]
                    ?? 0
                );

            $checksum =
                strtolower(
                    trim(
                        (string) (
                            $attachment[
                                'checksum_sha256'
                            ]
                            ?? ''
                        )
                    )
                );

            $scanStatus =
                trim(
                    (string) (
                        $attachment[
                            'scan_status_code'
                        ]
                        ?? 'pending'
                    )
                );

            $uploadedBy =
                trim(
                    (string) (
                        $attachment[
                            'uploaded_by_user_reference'
                        ]
                        ?? ''
                    )
                );

            if (
                preg_match(
                    '/^TKA-[A-F0-9]{24}$/',
                    $reference
                ) !== 1

                || $storageDisk
                    !== 'ticketing_private'

                || $storageKey === ''

                || substr(
                    $storageKey,
                    0,
                    1
                ) === '/'

                || strpos(
                    $storageKey,
                    '..'
                ) !== false

                || $originalName === ''

                || $mimeType === ''

                || $sizeBytes < 1

                || preg_match(
                    '/^[a-f0-9]{64}$/',
                    $checksum
                ) !== 1

                || $scanStatus !== 'pending'
            ) {
                throw new \RuntimeException(
                    'ticket_attachment_contract_invalid'
                );
            }

            $statement->execute([
                $reference,
                $ticketId,
                $messageId,
                $storageDisk,
                $storageKey,
                $originalName,
                $mimeType,
                $sizeBytes,
                $checksum,
                $scanStatus,

                $uploadedBy !== ''
                    ? $uploadedBy
                    : null,
            ]);
        }
    }


    private function cleanupReplyAttachmentFiles(
        array $attachments
    ): void {
        foreach ($attachments as $attachment) {

            $path =
                (string) (
                    $attachment[
                        'absolute_path'
                    ]
                    ?? ''
                );

            if (
                $path !== ''
                && is_file($path)
            ) {
                @unlink($path);
            }
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

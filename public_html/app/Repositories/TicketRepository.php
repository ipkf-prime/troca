<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class TicketRepository
{
    private PDO $db;

    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $this->db = (
            $connections
            ?? new ConnectionResolver()
        )->resolve('ticketing.primary');
    }


    public function statuses(): array
    {
        $statement = $this->db->query("
            SELECT
                id,
                code,
                title,
                category,
                color,
                sort_order,
                is_closed
            FROM ticketing_statuses
            WHERE is_active = 1
            ORDER BY sort_order, id
        ");

        return
            $statement->fetchAll(PDO::FETCH_ASSOC)
            ?: [];
    }


    public function priorities(): array
    {
        $statement = $this->db->query("
            SELECT
                id,
                code,
                title,
                severity,
                color,
                sort_order
            FROM ticketing_priorities
            WHERE is_active = 1
            ORDER BY sort_order, id
        ");

        return
            $statement->fetchAll(PDO::FETCH_ASSOC)
            ?: [];
    }


    public function categories(): array
    {
        $statement = $this->db->query("
            SELECT
                id,
                public_reference,
                parent_id,
                code,
                title,
                description,
                sort_order
            FROM ticketing_categories
            WHERE is_active = 1
            ORDER BY sort_order, title, id
        ");

        return
            $statement->fetchAll(PDO::FETCH_ASSOC)
            ?: [];
    }


    public function dashboard(
        ?string $requesterUserReference = null
    ): array {
        $where = [
            't.archived_at IS NULL',
        ];

        $parameters = [];

        if (
            $requesterUserReference !== null
            && trim($requesterUserReference) !== ''
        ) {
            $where[] =
                't.requester_user_reference = ?';

            $parameters[] =
                trim($requesterUserReference);
        }

        $statement = $this->db->prepare("
            SELECT
                COUNT(*) AS total_count,

                SUM(
                    CASE
                        WHEN s.is_closed = 0
                        THEN 1
                        ELSE 0
                    END
                ) AS open_count,

                SUM(
                    CASE
                        WHEN s.category = 'waiting'
                        THEN 1
                        ELSE 0
                    END
                ) AS waiting_count,

                SUM(
                    CASE
                        WHEN s.is_closed = 1
                        THEN 1
                        ELSE 0
                    END
                ) AS closed_count

            FROM ticketing_tickets t

            INNER JOIN ticketing_statuses s
                ON s.code = t.status_code

            WHERE " . implode(
                ' AND ',
                $where
            ) . "
        ");

        $statement->execute(
            $parameters
        );

        $counts =
            $statement->fetch(
                PDO::FETCH_ASSOC
            ) ?: [];

        $recentFilters = [
            'limit' => 5,
        ];

        if (
            $requesterUserReference !== null
            && trim($requesterUserReference) !== ''
        ) {
            $recentFilters[
                'requester_user_reference'
            ] = trim(
                $requesterUserReference
            );
        }

        $recent =
            $this->index(
                $recentFilters
            );

        return [
            'total' =>
                (int) (
                    $counts['total_count']
                    ?? 0
                ),

            'open' =>
                (int) (
                    $counts['open_count']
                    ?? 0
                ),

            'waiting' =>
                (int) (
                    $counts['waiting_count']
                    ?? 0
                ),

            'closed' =>
                (int) (
                    $counts['closed_count']
                    ?? 0
                ),

            'recent' => $recent,
        ];
    }


    public function index(
        array $filters = []
    ): array {
        $where = [
            't.archived_at IS NULL',
        ];

        $parameters = [];

        $q = trim(
            (string) ($filters['q'] ?? '')
        );

        $status = trim(
            (string) ($filters['status'] ?? '')
        );

        $priority = trim(
            (string) ($filters['priority'] ?? '')
        );

        $requesterUserReference = trim(
            (string) (
                $filters[
                    'requester_user_reference'
                ]
                ?? ''
            )
        );

        if ($q !== '') {
            $where[] = "(
                t.public_reference LIKE ?
                OR t.subject LIKE ?
                OR t.requester_display_name_snapshot LIKE ?
                OR t.requester_organization_snapshot LIKE ?
                OR t.source_reference LIKE ?
                OR CAST(t.id AS CHAR) LIKE ?
            )";

            $needle = '%' . $q . '%';

            for ($i = 0; $i < 6; $i++) {
                $parameters[] = $needle;
            }
        }

        if ($status !== '') {
            $where[] = 't.status_code = ?';
            $parameters[] = $status;
        }

        if ($priority !== '') {
            $where[] = 't.priority_code = ?';
            $parameters[] = $priority;
        }

        if ($requesterUserReference !== '') {
            $where[] =
                't.requester_user_reference = ?';

            $parameters[] =
                $requesterUserReference;
        }

        $limit = max(
            1,
            min(
                200,
                (int) ($filters['limit'] ?? 200)
            )
        );

        $statement = $this->db->prepare("
            SELECT
                t.id,
                t.public_reference,
                t.subject,

                t.status_code,
                s.title AS status_title,
                s.category AS status_category,
                s.color AS status_color,
                s.is_closed,

                t.priority_code,
                p.title AS priority_title,
                p.severity AS priority_severity,
                p.color AS priority_color,

                t.category_id,
                c.code AS category_code,
                c.title AS category_title,

                t.requester_user_reference,
                t.requester_person_reference,
                t.requester_display_name_snapshot,
                t.requester_email_snapshot,
                t.requester_mobile_snapshot,
                t.requester_organization_reference,
                t.requester_organization_snapshot,

                t.source_code,
                t.source_reference,

                t.last_activity_at,
                t.first_response_at,
                t.resolved_at,
                t.closed_at,

                t.created_at,
                t.updated_at

            FROM ticketing_tickets t

            INNER JOIN ticketing_statuses s
                ON s.code = t.status_code

            INNER JOIN ticketing_priorities p
                ON p.code = t.priority_code

            LEFT JOIN ticketing_categories c
                ON c.id = t.category_id

            WHERE " . implode(
                ' AND ',
                $where
            ) . "

            ORDER BY
                t.last_activity_at DESC,
                t.id DESC

            LIMIT {$limit}
        ");

        $statement->execute($parameters);

        return
            $statement->fetchAll(PDO::FETCH_ASSOC)
            ?: [];
    }


    public function findByReference(
        string $publicReference,
        ?string $requesterUserReference = null
    ): ?array {
        $statement = $this->db->prepare("
            SELECT
                t.id,
                t.public_reference,
                t.subject,

                t.status_code,
                s.title AS status_title,
                s.category AS status_category,
                s.color AS status_color,
                s.is_closed,

                t.priority_code,
                p.title AS priority_title,
                p.severity AS priority_severity,
                p.color AS priority_color,

                t.category_id,
                c.code AS category_code,
                c.title AS category_title,

                t.requester_user_reference,
                t.requester_person_reference,
                t.requester_display_name_snapshot,
                t.requester_email_snapshot,
                t.requester_mobile_snapshot,
                t.requester_organization_reference,
                t.requester_organization_snapshot,

                t.source_code,
                t.source_reference,

                t.created_by_user_reference,
                t.updated_by_user_reference,

                t.last_activity_at,
                t.first_response_at,
                t.resolved_at,
                t.closed_at,
                t.archived_at,

                t.created_at,
                t.updated_at

            FROM ticketing_tickets t

            INNER JOIN ticketing_statuses s
                ON s.code = t.status_code

            INNER JOIN ticketing_priorities p
                ON p.code = t.priority_code

            LEFT JOIN ticketing_categories c
                ON c.id = t.category_id

            WHERE t.public_reference = ?

              AND (
                    ? IS NULL
                    OR t.requester_user_reference = ?
              )

            LIMIT 1
        ");

        $requester =
            $requesterUserReference === null
            || trim($requesterUserReference) === ''
                ? null
                : trim($requesterUserReference);

        $statement->execute([
            trim($publicReference),
            $requester,
            $requester,
        ]);

        $ticket =
            $statement->fetch(PDO::FETCH_ASSOC);

        return $ticket ?: null;
    }


    public function messages(
        int $ticketId
    ): array {
        $statement = $this->db->prepare("
            SELECT
                id,
                public_reference,
                message_kind,
                visibility_code,
                author_kind,
                author_user_reference,
                author_display_name_snapshot,
                body,
                source_code,
                created_at

            FROM ticketing_messages

            WHERE ticket_id = ?

            ORDER BY created_at, id
        ");

        $statement->execute([
            $ticketId,
        ]);

        return
            $statement->fetchAll(PDO::FETCH_ASSOC)
            ?: [];
    }


    public function events(
        int $ticketId
    ): array {
        $statement = $this->db->prepare("
            SELECT
                id,
                public_reference,
                event_code,
                actor_user_reference,
                actor_display_name_snapshot,
                previous_status_code,
                resulting_status_code,
                payload_json,
                occurred_at

            FROM ticketing_events

            WHERE ticket_id = ?

            ORDER BY occurred_at, id
        ");

        $statement->execute([
            $ticketId,
        ]);

        return
            $statement->fetchAll(PDO::FETCH_ASSOC)
            ?: [];
    }


    public function create(
        array $data
    ): array {
        $this->db->beginTransaction();

        try {

            $ticket = $this->db->prepare("
                INSERT INTO ticketing_tickets
                (
                    public_reference,

                    status_code,
                    priority_code,
                    category_id,

                    subject,

                    requester_user_reference,
                    requester_person_reference,
                    requester_display_name_snapshot,
                    requester_email_snapshot,
                    requester_mobile_snapshot,
                    requester_organization_reference,
                    requester_organization_snapshot,

                    source_code,
                    source_reference,

                    created_by_user_reference,
                    updated_by_user_reference,

                    last_activity_at,

                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'new',
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
                    'portal',
                    NULL,
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

            $ticket->execute([
                $data['public_reference'],

                $data['priority_code'],
                $data['category_id'],

                $data['subject'],

                $data['requester_user_reference'],
                $data['requester_person_reference'],
                $data['requester_display_name_snapshot'],
                $data['requester_email_snapshot'],
                $data['requester_mobile_snapshot'],
                $data['requester_organization_reference'],
                $data['requester_organization_snapshot'],

                $data['actor_user_reference'],
                $data['actor_user_reference'],
            ]);

            $ticketId =
                (int) $this->db->lastInsertId();


            $message = $this->db->prepare("
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
                    'initial',
                    'public',
                    'requester',
                    ?,
                    ?,
                    ?,
                    'portal',
                    UTC_TIMESTAMP()
                )
            ");

            $message->execute([
                $data['message_reference'],
                $ticketId,
                $data['actor_user_reference'],
                $data['requester_display_name_snapshot'],
                $data['body'],
            ]);


            $payload = json_encode(
                [
                    'subject' =>
                        $data['subject'],

                    'priority_code' =>
                        $data['priority_code'],

                    'category_id' =>
                        $data['category_id'],

                    'source_code' =>
                        'portal',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

            if (!is_string($payload)) {
                throw new \RuntimeException(
                    'Ticket event payload encoding failed.'
                );
            }


            $event = $this->db->prepare("
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
                    'ticket_created',
                    ?,
                    ?,
                    NULL,
                    'new',
                    ?,
                    UTC_TIMESTAMP()
                )
            ");

            $event->execute([
                $data['event_reference'],
                $ticketId,
                $data['actor_user_reference'],
                $data['requester_display_name_snapshot'],
                $payload,
            ]);


            $this->db->commit();

            return [
                'id' => $ticketId,

                'public_reference' =>
                    (string) $data[
                        'public_reference'
                    ],
            ];

        } catch (\Throwable $exception) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
}

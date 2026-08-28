<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;

final class TicketingSlaRepository
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


    public function initializationCandidates(
        int $limit = 100
    ): array {
        $limit =
            max(
                1,
                min(
                    500,
                    $limit
                )
            );


        $statement =
            $this->db->query("
                SELECT
                    t.id,
                    t.public_reference,
                    t.ticket_number,

                    t.support_project_id,
                    t.current_support_queue_id,

                    t.priority_code,
                    t.status_code,

                    t.current_support_node_id,

                    t.created_at,
                    t.first_response_at,
                    t.resolved_at,
                    t.closed_at

                FROM ticketing_tickets t

                INNER JOIN
                    ticketing_statuses s
                    ON s.code =
                        t.status_code

                   AND s.is_active = 1

                LEFT JOIN
                    ticketing_ticket_sla_states ss
                    ON ss.ticket_id =
                        t.id

                WHERE ss.id IS NULL

                  AND s.is_closed = 0

                  AND t.current_support_node_id
                        IS NOT NULL

                ORDER BY t.id

                LIMIT {$limit}
            ");


        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    public function policyForTicket(
        array $ticket
    ): ?array {
        $projectId =
            (int) (
                $ticket[
                    'support_project_id'
                ]
                ?? 0
            );

        $queueId =
            (int) (
                $ticket[
                    'current_support_queue_id'
                ]
                ?? 0
            );

        $priority =
            trim(
                (string) (
                    $ticket[
                        'priority_code'
                    ]
                    ?? ''
                )
            );

        $createdAt =
            trim(
                (string) (
                    $ticket['created_at']
                    ?? ''
                )
            );


        if (
            $projectId <= 0
            || $priority === ''
            || $createdAt === ''
        ) {
            return null;
        }


        $statement =
            $this->db->prepare("
                SELECT
                    p.*,

                    c.code
                        AS calendar_code,

                    c.timezone
                        AS calendar_timezone

                FROM ticketing_sla_policies p

                INNER JOIN
                    ticketing_business_calendars c
                    ON c.id =
                        p.calendar_id

                   AND c.status =
                        'active'

                WHERE p.status =
                        'active'

                  AND p.priority_code = ?

                  AND p.effective_from_at
                        <= ?

                  AND
                  (
                      p.effective_to_at
                        IS NULL

                      OR p.effective_to_at
                            > ?
                  )

                  AND
                  (
                      p.project_id IS NULL

                      OR p.project_id = ?
                  )

                  AND
                  (
                      p.queue_id IS NULL

                      OR p.queue_id = ?
                  )

                ORDER BY
                    CASE
                        WHEN p.queue_id
                                IS NOT NULL
                            THEN 1
                        ELSE 0
                    END DESC,

                    CASE
                        WHEN p.project_id
                                IS NOT NULL
                            THEN 1
                        ELSE 0
                    END DESC,

                    p.sort_order DESC,
                    p.id DESC

                LIMIT 1
            ");


        $statement->execute([
            $priority,
            $createdAt,
            $createdAt,
            $projectId,
            $queueId > 0
                ? $queueId
                : -1,
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


    public function calendar(
        int $calendarId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    code,
                    title,
                    timezone

                FROM
                    ticketing_business_calendars

                WHERE id = ?
                  AND status = 'active'

                LIMIT 1
            ");

        $statement->execute([
            $calendarId,
        ]);


        $calendar =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!is_array($calendar)) {
            return null;
        }


        $hours =
            $this->db->prepare("
                SELECT
                    weekday_iso,
                    segment_order,
                    start_time,
                    end_time,
                    is_working

                FROM
                    ticketing_business_calendar_hours

                WHERE calendar_id = ?

                ORDER BY
                    weekday_iso,
                    segment_order,
                    id
            ");

        $hours->execute([
            $calendarId,
        ]);


        $exceptions =
            $this->db->prepare("
                SELECT
                    exception_date,
                    exception_type_code,
                    segment_order,
                    title,
                    start_time,
                    end_time

                FROM
                    ticketing_business_calendar_exceptions

                WHERE calendar_id = ?

                ORDER BY
                    exception_date,
                    segment_order,
                    id
            ");

        $exceptions->execute([
            $calendarId,
        ]);


        $calendar['hours'] =
            $hours->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        $calendar['exceptions'] =
            $exceptions->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];


        return $calendar;
    }


    public function createState(
        array $state
    ): bool {
        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO
                    ticketing_ticket_sla_states
                (
                    public_reference,

                    ticket_id,
                    policy_id,
                    calendar_id,

                    policy_scope_key_snapshot,
                    priority_code_snapshot,

                    response_target_minutes,
                    resolution_target_minutes,

                    response_started_at,
                    response_due_at,

                    resolution_started_at,
                    resolution_due_at,

                    next_action_at,

                    state_code,

                    initialized_at,
                    last_calculated_at
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
                    ?,

                    ?,

                    'active',

                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");


        $statement->execute([
            $this->stateReference(
                (int) $state['ticket_id']
            ),

            $state['ticket_id'],
            $state['policy_id'],
            $state['calendar_id'],

            $state[
                'policy_scope_key'
            ],

            $state[
                'priority_code'
            ],

            $state[
                'response_minutes'
            ],

            $state[
                'resolution_minutes'
            ],

            $state[
                'response_started_at'
            ],

            $state[
                'response_due_at'
            ],

            $state[
                'resolution_started_at'
            ],

            $state[
                'resolution_due_at'
            ],

            $state[
                'next_action_at'
            ],
        ]);


        if ($statement->rowCount() !== 1) {
            return false;
        }


        $stateId =
            (int) $this->db
                ->lastInsertId();


        $this->recordSlaEvent(
            (int) $state['ticket_id'],
            $stateId,
            'sla_initialized',
            [
                'policy_id' =>
                    (int) $state[
                        'policy_id'
                    ],

                'calendar_id' =>
                    (int) $state[
                        'calendar_id'
                    ],

                'priority_code' =>
                    (string) $state[
                        'priority_code'
                    ],

                'response_due_at' =>
                    (string) $state[
                        'response_due_at'
                    ],

                'resolution_due_at' =>
                    (string) $state[
                        'resolution_due_at'
                    ],
            ]
        );


        return true;
    }


    public function stateCount(): int
    {
        return
            (int) $this->db->query("
                SELECT COUNT(*)
                FROM ticketing_ticket_sla_states
            ")->fetchColumn();
    }


    private function recordSlaEvent(
        int $ticketId,
        ?int $stateId,
        string $eventCode,
        array $payload
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO
                    ticketing_sla_events
                (
                    public_reference,

                    ticket_id,
                    sla_state_id,

                    event_code,

                    actor_user_reference,
                    actor_display_name_snapshot,

                    payload_json,

                    occurred_at
                )
                VALUES
                (
                    ?,

                    ?,
                    ?,

                    ?,

                    'system:ticketing-sla',
                    'موتور SLA تیکتینگ',

                    ?,

                    UTC_TIMESTAMP()
                )
            ");


        $statement->execute([
            $this->eventReference(),

            $ticketId,
            $stateId,

            $eventCode,

            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }


    private function stateReference(
        int $ticketId
    ): string {
        return
            'TSLS-'
            . strtoupper(
                substr(
                    hash(
                        'sha256',
                        'ticketing-sla-state|'
                        . $ticketId
                    ),
                    0,
                    20
                )
            );
    }


    private function eventReference(): string
    {
        try {
            return
                'TSE-'
                . strtoupper(
                    bin2hex(
                        random_bytes(10)
                    )
                );
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'ticketing_sla_event_reference_failed',
                0,
                $exception
            );
        }
    }
}

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
        int $limit = 100,
        ?int $projectId = null
    ): array {
        $limit =
            max(
                1,
                min(
                    500,
                    $limit
                )
            );



        $projectFilter =
            $projectId !== null
            && $projectId > 0
                ? 't.support_project_id = '
                    . (int) $projectId
                : '1 = 1';

        $statement =
            $this->db->query("
                SELECT
                    t.id,
                    t.public_reference,
                    t.ticket_number,

                    t.support_project_id,
                    t.support_service_id,
                    t.support_topic_id,

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

                  AND {$projectFilter}

                  AND s.is_closed = 0

                  AND t.current_support_node_id
                        IS NOT NULL

                  /*
                   * Do not rescan historical/open tickets
                   * which cannot match any effective active
                   * SLA policy.
                   */
                  AND EXISTS
                  (
                      SELECT 1

                      FROM
                          ticketing_sla_policies candidate_policy

                      INNER JOIN
                          ticketing_business_calendars candidate_calendar
                          ON candidate_calendar.id =
                                candidate_policy.calendar_id

                         AND candidate_calendar.status =
                                'active'

                      WHERE candidate_policy.status =
                                'active'

                        AND candidate_policy.priority_code =
                                t.priority_code

                        AND candidate_policy.effective_from_at
                                <= t.created_at

                        AND
                        (
                            candidate_policy.effective_to_at
                                IS NULL

                            OR candidate_policy.effective_to_at
                                > t.created_at
                        )

                        AND
                        (
                            candidate_policy.project_id
                                IS NULL

                            OR candidate_policy.project_id =
                                t.support_project_id
                        )

                        AND
                        (
                            candidate_policy.service_id
                                IS NULL

                            OR candidate_policy.service_id =
                                t.support_service_id
                        )

                        AND
                        (
                            candidate_policy.topic_id
                                IS NULL

                            OR candidate_policy.topic_id =
                                t.support_topic_id
                        )

                        AND
                        (
                            candidate_policy.queue_id
                                IS NULL

                            OR candidate_policy.queue_id =
                                t.current_support_queue_id
                        )
                  )

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

        $serviceId =
            (int) (
                $ticket[
                    'support_service_id'
                ]
                ?? 0
            );

        $topicId =
            (int) (
                $ticket[
                    'support_topic_id'
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
                      p.service_id IS NULL

                      OR p.service_id = ?
                  )

                  AND
                  (
                      p.topic_id IS NULL

                      OR p.topic_id = ?
                  )

                  AND
                  (
                      p.queue_id IS NULL

                      OR p.queue_id = ?
                  )

                ORDER BY
                    /*
                     * TICKETING_DYNAMIC_SLA_SCOPE_PRECEDENCE_V1
                     *
                     * Topic
                     *   > Service
                     *   > Queue
                     *   > Project
                     *   > Global
                     */
                    CASE
                        WHEN p.topic_id
                                IS NOT NULL
                            THEN 1
                        ELSE 0
                    END DESC,

                    CASE
                        WHEN p.service_id
                                IS NOT NULL
                            THEN 1
                        ELSE 0
                    END DESC,

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

            $serviceId > 0
                ? $serviceId
                : -1,

            $topicId > 0
                ? $topicId
                : -1,

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


    public function runtimeCandidates(
        int $limit = 200,
        ?int $projectId = null
    ): array {
        $limit =
            max(
                1,
                min(
                    1000,
                    $limit
                )
            );



        $projectFilter =
            $projectId !== null
            && $projectId > 0
                ? 't.support_project_id = '
                    . (int) $projectId
                : '1 = 1';

        $statement =
            $this->db->query("
                SELECT
                    ss.*,

                    t.public_reference
                        AS ticket_public_reference,

                    t.ticket_number,
                    t.status_code,

                    t.first_response_at,
                    t.resolved_at,
                    t.closed_at,
                    t.last_activity_at,

                    t.current_support_node_id,
                    t.current_support_queue_id,
                    t.current_support_team_id,
                    t.current_assignee_project_member_id,

                    s.is_closed
                        AS status_is_closed,

                    p.pause_statuses_json,
                    p.breach_action_code,
                    p.max_auto_escalations,
                    p.escalation_repeat_minutes

                FROM
                    ticketing_ticket_sla_states ss

                INNER JOIN
                    ticketing_tickets t
                    ON t.id =
                        ss.ticket_id

                INNER JOIN
                    ticketing_statuses s
                    ON s.code =
                        t.status_code

                INNER JOIN
                    ticketing_sla_policies p
                    ON p.id =
                        ss.policy_id

                WHERE ss.state_code IN
                (
                    'active',
                    'paused',
                    'breached'
                )

                  AND {$projectFilter}

                  /*
                   * Cron must receive only actionable states.
                   *
                   * Paused states remain observable so a status
                   * change can resume them immediately.
                   *
                   * Canonical lifecycle timestamps override
                   * scheduling and must be reconciled immediately.
                   *
                   * Explicit due-date predicates are intentionally
                   * retained even when next_action_at is NULL.
                   * This guarantees that a state which already
                   * reached its escalation ceiling can still record
                   * a later resolution breach.
                   */
                  AND
                  (
                      ss.state_code =
                            'paused'

                      OR
                      (
                          t.first_response_at
                                IS NOT NULL

                          AND ss.response_met_at
                                IS NULL
                      )

                      OR
                      (
                          t.resolved_at
                                IS NOT NULL

                          AND ss.resolution_met_at
                                IS NULL
                      )

                      OR
                      (
                          t.closed_at
                                IS NOT NULL

                          AND ss.resolution_met_at
                                IS NULL
                      )

                      OR
                      (
                          s.is_closed = 1

                          AND ss.resolution_met_at
                                IS NULL
                      )

                      OR
                      (
                          ss.next_action_at
                                IS NOT NULL

                          AND ss.next_action_at
                                <= UTC_TIMESTAMP()
                      )

                      OR
                      (
                          ss.response_met_at
                                IS NULL

                          AND ss.response_breached_at
                                IS NULL

                          AND ss.response_due_at
                                <= UTC_TIMESTAMP()
                      )

                      OR
                      (
                          ss.resolution_met_at
                                IS NULL

                          AND ss.resolution_breached_at
                                IS NULL

                          AND ss.resolution_due_at
                                <= UTC_TIMESTAMP()
                      )

                      OR
                      (
                          ss.state_code <>
                                'paused'

                          AND LOCATE(
                              CONCAT(
                                  '\"',
                                  t.status_code,
                                  '\"'
                              ),
                              COALESCE(
                                  p.pause_statuses_json,
                                  '[]'
                              )
                          ) > 0
                      )
                  )

                ORDER BY

                    CASE
                        WHEN ss.state_code =
                                'paused'
                            THEN 0

                        WHEN t.first_response_at
                                IS NOT NULL
                             AND ss.response_met_at
                                IS NULL
                            THEN 0

                        WHEN t.resolved_at
                                IS NOT NULL
                             AND ss.resolution_met_at
                                IS NULL
                            THEN 0

                        WHEN t.closed_at
                                IS NOT NULL
                             AND ss.resolution_met_at
                                IS NULL
                            THEN 0

                        WHEN s.is_closed = 1
                             AND ss.resolution_met_at
                                IS NULL
                            THEN 0

                        WHEN ss.next_action_at
                                IS NOT NULL
                             AND ss.next_action_at
                                <= UTC_TIMESTAMP()
                            THEN 0

                        ELSE 1
                    END,

                    ss.last_calculated_at,
                    ss.id

                LIMIT {$limit}
            ");


        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    public function markResponseMet(
        int $stateId,
        string $at
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_ticket_sla_states

                SET
                    response_met_at = ?,

                    next_action_at =
                        CASE
                            WHEN state_code =
                                    'paused'
                                THEN NULL

                            ELSE
                                resolution_due_at
                        END,

                    last_calculated_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
                  AND response_met_at
                        IS NULL
            ");

        $statement->execute([
            $at,
            $stateId,
        ]);


        return
            $statement->rowCount()
            === 1;
    }


    public function markResponseBreached(
        int $stateId,
        string $at
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_ticket_sla_states

                SET
                    response_breached_at = ?,

                    state_code =
                        CASE
                            WHEN state_code =
                                    'paused'
                                THEN 'paused'

                            ELSE
                                'breached'
                        END,

                    last_calculated_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
                  AND response_breached_at
                        IS NULL
            ");

        $statement->execute([
            $at,
            $stateId,
        ]);


        return
            $statement->rowCount()
            === 1;
    }


    public function markResolutionMet(
        int $stateId,
        string $at
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_ticket_sla_states

                SET
                    resolution_met_at = ?,

                    state_code =
                        'completed',

                    pause_status_code =
                        NULL,

                    paused_at =
                        NULL,

                    next_action_at =
                        NULL,

                    last_calculated_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
                  AND resolution_met_at
                        IS NULL
            ");

        $statement->execute([
            $at,
            $stateId,
        ]);


        return
            $statement->rowCount()
            === 1;
    }


    public function markResolutionBreached(
        int $stateId,
        string $at
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_ticket_sla_states

                SET
                    resolution_breached_at = ?,

                    state_code =
                        CASE
                            WHEN state_code =
                                    'paused'
                                THEN 'paused'

                            ELSE
                                'breached'
                        END,

                    last_calculated_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
                  AND resolution_breached_at
                        IS NULL
            ");

        $statement->execute([
            $at,
            $stateId,
        ]);


        return
            $statement->rowCount()
            === 1;
    }


    public function pauseState(
        int $stateId,
        string $statusCode,
        string $pausedAt
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_ticket_sla_states

                SET
                    state_code =
                        'paused',

                    pause_status_code = ?,

                    paused_at = ?,

                    next_action_at =
                        NULL,

                    last_calculated_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
                  AND paused_at
                        IS NULL

                  AND resolution_met_at
                        IS NULL
            ");

        $statement->execute([
            $statusCode,
            $pausedAt,
            $stateId,
        ]);


        return
            $statement->rowCount()
            === 1;
    }


    public function resumeState(
        int $stateId,
        string $responseDueAt,
        string $resolutionDueAt,
        int $pauseBusinessMinutes,
        ?string $nextActionAt
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_ticket_sla_states

                SET
                    response_due_at =
                        CASE
                            WHEN response_met_at
                                    IS NULL
                                THEN ?

                            ELSE
                                response_due_at
                        END,

                    resolution_due_at =
                        CASE
                            WHEN resolution_met_at
                                    IS NULL
                                THEN ?

                            ELSE
                                resolution_due_at
                        END,

                    accumulated_pause_business_minutes =
                        accumulated_pause_business_minutes
                        + ?,

                    pause_status_code =
                        NULL,

                    paused_at =
                        NULL,

                    state_code =
                        CASE
                            WHEN resolution_breached_at
                                    IS NOT NULL
                                THEN 'breached'

                            WHEN response_breached_at
                                    IS NOT NULL
                                 AND response_met_at
                                    IS NULL
                                THEN 'breached'

                            ELSE
                                'active'
                        END,

                    next_action_at = ?,

                    last_calculated_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
                  AND paused_at
                        IS NOT NULL

                  AND resolution_met_at
                        IS NULL
            ");

        $statement->execute([
            $responseDueAt,
            $resolutionDueAt,
            max(
                0,
                $pauseBusinessMinutes
            ),
            $nextActionAt,
            $stateId,
        ]);


        return
            $statement->rowCount()
            === 1;
    }


    public function markAutoEscalated(
        int $stateId,
        ?int $nodeId,
        ?string $nextActionAt,
        string $at
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_ticket_sla_states

                SET
                    auto_escalation_count =
                        auto_escalation_count
                        + 1,

                    last_auto_escalated_at =
                        ?,

                    last_escalation_node_id =
                        ?,

                    next_action_at =
                        ?,

                    state_code =
                        'breached',

                    last_calculated_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
                  AND resolution_met_at
                        IS NULL
            ");

        $statement->execute([
            $at,
            $nodeId,
            $nextActionAt,
            $stateId,
        ]);


        return
            $statement->rowCount()
            === 1;
    }


    public function scheduleNextAction(
        int $stateId,
        ?string $nextActionAt
    ): void {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_ticket_sla_states

                SET
                    next_action_at = ?,

                    last_calculated_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
            ");

        $statement->execute([
            $nextActionAt,
            $stateId,
        ]);
    }


    public function touchState(
        int $stateId
    ): void {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_ticket_sla_states

                SET
                    last_calculated_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
            ");

        $statement->execute([
            $stateId,
        ]);
    }


    public function currentTicketRouting(
        int $ticketId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    public_reference,
                    ticket_number,

                    current_support_node_id,
                    current_support_queue_id,
                    current_support_team_id,
                    current_assignee_project_member_id

                FROM ticketing_tickets

                WHERE id = ?

                LIMIT 1
            ");

        $statement->execute([
            $ticketId,
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


    public function recordSlaEvent(
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

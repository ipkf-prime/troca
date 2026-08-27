<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;

final class TicketCreateRoutingRepository
{
    private PDO $db;


    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $this->db =
            (
                $connections
                ?? new ConnectionResolver()
            )->resolve('ticketing.primary');
    }


    public function optionsForUser(
        string $userReference
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    pm.id AS project_member_id,

                    p.id AS project_id,
                    p.public_reference
                        AS project_reference,
                    p.code AS project_code,
                    p.title AS project_title,

                    s.id AS service_id,
                    s.public_reference
                        AS service_reference,
                    s.code AS service_code,
                    s.title AS service_title,
                    s.is_default

                FROM
                    ticketing_support_project_members pm

                INNER JOIN
                    ticketing_support_projects p
                    ON p.id = pm.project_id
                   AND p.is_active = 1
                   AND p.archived_at IS NULL

                INNER JOIN
                    ticketing_support_services s
                    ON s.project_id = p.id
                   AND s.is_active = 1

                WHERE pm.user_reference = ?
                  AND pm.left_at IS NULL

                ORDER BY
                    p.sort_order,
                    p.title,
                    s.is_default DESC,
                    s.sort_order,
                    s.title,
                    s.id
            ");

        $statement->execute([
            trim($userReference),
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $projects = [];
        $services = [];

        foreach ($rows as $row) {
            $projectId =
                (int) $row['project_id'];

            $serviceId =
                (int) $row['service_id'];

            $projects[$projectId] = [
                'id' =>
                    $projectId,

                'reference' =>
                    (string) $row[
                        'project_reference'
                    ],

                'code' =>
                    (string) $row[
                        'project_code'
                    ],

                'title' =>
                    (string) $row[
                        'project_title'
                    ],
            ];

            $services[$serviceId] = [
                'id' =>
                    $serviceId,

                'project_id' =>
                    $projectId,

                'reference' =>
                    (string) $row[
                        'service_reference'
                    ],

                'code' =>
                    (string) $row[
                        'service_code'
                    ],

                'title' =>
                    (string) $row[
                        'service_title'
                    ],

                'is_default' =>
                    (int) $row[
                        'is_default'
                    ],
            ];
        }

        return [
            'projects' =>
                $projects,

            'services' =>
                $services,
        ];
    }


    public function selectionForUser(
        string $userReference,
        int $projectId,
        int $serviceId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    pm.id
                        AS project_member_id,

                    pm.participant_id,
                    pm.person_reference,
                    pm.user_reference,
                    pm.display_name_snapshot,

                    pm.organization_reference,
                    pm.organization_title_snapshot,
                    pm.organization_role_code_snapshot,

                    p.id AS project_id,
                    p.public_reference
                        AS project_reference,
                    p.code AS project_code,
                    p.title AS project_title,

                    s.id AS service_id,
                    s.public_reference
                        AS service_reference,
                    s.code AS service_code,
                    s.title AS service_title

                FROM
                    ticketing_support_project_members pm

                INNER JOIN
                    ticketing_support_projects p
                    ON p.id = pm.project_id
                   AND p.is_active = 1
                   AND p.archived_at IS NULL

                INNER JOIN
                    ticketing_support_services s
                    ON s.project_id = p.id
                   AND s.is_active = 1

                WHERE pm.user_reference = ?
                  AND pm.left_at IS NULL
                  AND p.id = ?
                  AND s.id = ?

                LIMIT 1
            ");

        $statement->execute([
            trim($userReference),
            $projectId,
            $serviceId,
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


    public function create(
        array $data
    ): array {
        $this->db->beginTransaction();

        try {

            /*
             * Re-validate membership and service inside
             * the same DB transaction as ticket creation.
             */
            $selection =
                $this->selectionForUser(
                    (string) $data[
                        'requester_user_reference'
                    ],
                    (int) $data[
                        'support_project_id'
                    ],
                    (int) $data[
                        'support_service_id'
                    ]
                );

            if ($selection === null) {
                throw new RuntimeException(
                    'Support project/service membership is no longer valid.'
                );
            }


            $route =
                $this->intakeRoute(
                    (int) $selection[
                        'project_id'
                    ]
                );

            if ($route === null) {
                throw new RuntimeException(
                    'No operational intake route exists for this support project.'
                );
            }


            $assignee =
                null;

            if (
                (string) $route[
                    'assignment_mode_code'
                ] === 'least_loaded'
            ) {
                $assignee =
                    $this->leastLoadedAssignee(
                        (int) $route[
                            'team_id'
                        ],
                        isset(
                            $route[
                                'max_open_per_agent'
                            ]
                        )
                        && $route[
                            'max_open_per_agent'
                        ] !== null
                            ? (int) $route[
                                'max_open_per_agent'
                            ]
                            : null
                    );
            }


            $ticket =
                $this->db->prepare("
                    INSERT INTO ticketing_tickets
                    (
                        public_reference,

                        support_project_id,
                        support_service_id,
                        support_project_title_snapshot,
                        support_service_title_snapshot,

                        requester_participant_id,

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

                        current_support_layer_id,
                        current_support_node_id,
                        current_support_queue_id,
                        current_support_team_id,
                        current_assignee_project_member_id,

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

                        ?, ?, ?, ?,

                        ?,

                        'new',
                        ?,
                        ?,

                        ?,

                        ?, ?, ?, ?, ?, ?, ?,

                        ?, ?, ?, ?, ?,

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

                (int) $selection[
                    'project_id'
                ],

                (int) $selection[
                    'service_id'
                ],

                (string) $selection[
                    'project_title'
                ],

                (string) $selection[
                    'service_title'
                ],

                !empty(
                    $selection[
                        'participant_id'
                    ]
                )
                    ? (int) $selection[
                        'participant_id'
                    ]
                    : null,

                $data['priority_code'],
                $data['category_id'],

                $data['subject'],

                $selection[
                    'user_reference'
                ],

                $selection[
                    'person_reference'
                ]
                    ?: null,

                $selection[
                    'display_name_snapshot'
                ],

                $data[
                    'requester_email_snapshot'
                ]
                    ?? null,

                $data[
                    'requester_mobile_snapshot'
                ]
                    ?? null,

                $selection[
                    'organization_reference'
                ]
                    ?: null,

                $selection[
                    'organization_title_snapshot'
                ]
                    ?: null,

                (int) $route[
                    'layer_id'
                ],

                (int) $route[
                    'node_id'
                ],

                (int) $route[
                    'queue_id'
                ],

                (int) $route[
                    'team_id'
                ],

                $assignee !== null
                    ? (int) $assignee[
                        'project_member_id'
                    ]
                    : null,

                $data[
                    'actor_user_reference'
                ],

                $data[
                    'actor_user_reference'
                ],
            ]);

            $ticketId =
                (int) $this->db->lastInsertId();


            $message =
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
                $data[
                    'message_reference'
                ],

                $ticketId,

                $data[
                    'actor_user_reference'
                ],

                $selection[
                    'display_name_snapshot'
                ],

                $data['body'],
            ]);


            $this->event(
                $ticketId,
                (string) $data[
                    'created_event_reference'
                ],
                'ticket_created',
                (string) $data[
                    'actor_user_reference'
                ],
                (string) $selection[
                    'display_name_snapshot'
                ],
                null,
                'new',
                [
                    'subject' =>
                        $data['subject'],

                    'priority_code' =>
                        $data[
                            'priority_code'
                        ],

                    'category_id' =>
                        $data[
                            'category_id'
                        ],

                    'support_project_id' =>
                        (int) $selection[
                            'project_id'
                        ],

                    'support_service_id' =>
                        (int) $selection[
                            'service_id'
                        ],

                    'source_code' =>
                        'portal',
                ]
            );


            $this->event(
                $ticketId,
                (string) $data[
                    'routing_event_reference'
                ],
                'ticket_routed',
                'system:ticketing-router',
                'مسیریاب تیکتینگ',
                'new',
                'new',
                [
                    'layer_id' =>
                        (int) $route[
                            'layer_id'
                        ],

                    'node_id' =>
                        (int) $route[
                            'node_id'
                        ],

                    'queue_id' =>
                        (int) $route[
                            'queue_id'
                        ],

                    'team_id' =>
                        (int) $route[
                            'team_id'
                        ],

                    'assignment_mode_code' =>
                        (string) $route[
                            'assignment_mode_code'
                        ],
                ]
            );


            if ($assignee !== null) {

                $assignment =
                    $this->db->prepare("
                        INSERT INTO
                            ticketing_assignments
                        (
                            ticket_id,

                            assignee_kind,
                            assignee_reference,
                            assignee_display_name_snapshot,
                            assignment_role,

                            assigned_by_user_reference,
                            assigned_at,
                            unassigned_at,

                            project_member_id,
                            support_node_id,
                            support_queue_id,
                            support_team_id,

                            assignment_mode_code,
                            assignment_reason
                        )
                        VALUES
                        (
                            ?,

                            'user',
                            ?,
                            ?,
                            'owner',

                            'system:ticketing-router',
                            UTC_TIMESTAMP(),
                            NULL,

                            ?,
                            ?,
                            ?,
                            ?,

                            'least_loaded',
                            'automatic-intake-routing'
                        )
                    ");

                $assignment->execute([
                    $ticketId,

                    $assignee[
                        'user_reference'
                    ],

                    $assignee[
                        'display_name_snapshot'
                    ],

                    (int) $assignee[
                        'project_member_id'
                    ],

                    (int) $route[
                        'node_id'
                    ],

                    (int) $route[
                        'queue_id'
                    ],

                    (int) $route[
                        'team_id'
                    ],
                ]);


                $this->event(
                    $ticketId,
                    (string) $data[
                        'assignment_event_reference'
                    ],
                    'ticket_assigned',
                    'system:ticketing-router',
                    'مسیریاب تیکتینگ',
                    'new',
                    'new',
                    [
                        'project_member_id' =>
                            (int) $assignee[
                                'project_member_id'
                            ],

                        'assignee_reference' =>
                            (string) $assignee[
                                'user_reference'
                            ],

                        'team_id' =>
                            (int) $route[
                                'team_id'
                            ],

                        'queue_id' =>
                            (int) $route[
                                'queue_id'
                            ],

                        'assignment_mode_code' =>
                            'least_loaded',
                    ]
                );
            }


            $this->db->commit();

            return [
                'id' =>
                    $ticketId,

                'public_reference' =>
                    (string) $data[
                        'public_reference'
                    ],

                'project_id' =>
                    (int) $selection[
                        'project_id'
                    ],

                'service_id' =>
                    (int) $selection[
                        'service_id'
                    ],

                'layer_id' =>
                    (int) $route[
                        'layer_id'
                    ],

                'node_id' =>
                    (int) $route[
                        'node_id'
                    ],

                'queue_id' =>
                    (int) $route[
                        'queue_id'
                    ],

                'team_id' =>
                    (int) $route[
                        'team_id'
                    ],

                'assignee_project_member_id' =>
                    $assignee !== null
                        ? (int) $assignee[
                            'project_member_id'
                        ]
                        : null,
            ];

        } catch (\Throwable $exception) {

            if (
                $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    private function intakeRoute(
        int $projectId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    l.id AS layer_id,

                    n.id AS node_id,

                    q.id AS queue_id,
                    q.assignment_mode_code,
                    q.max_open_per_agent,

                    t.id AS team_id

                FROM
                    ticketing_support_nodes n

                INNER JOIN
                    ticketing_support_layers l
                    ON l.id = n.layer_id
                   AND l.project_id = n.project_id
                   AND l.status = 'active'

                INNER JOIN
                    ticketing_support_queues q
                    ON q.project_id = n.project_id
                   AND q.node_id = n.id
                   AND q.is_default = 1
                   AND q.status = 'active'

                INNER JOIN
                    ticketing_support_team_queues tq
                    ON tq.queue_id = q.id
                   AND tq.status = 'active'

                INNER JOIN
                    ticketing_support_teams t
                    ON t.id = tq.team_id
                   AND t.project_id = n.project_id
                   AND t.status = 'active'

                INNER JOIN
                    ticketing_support_team_nodes tn
                    ON tn.team_id = t.id
                   AND tn.node_id = n.id
                   AND tn.status = 'active'

                WHERE n.project_id = ?
                  AND n.is_intake_node = 1
                  AND n.status = 'active'

                ORDER BY
                    l.rank_order,
                    n.sort_order,
                    q.sort_order,
                    t.sort_order,
                    t.id

                LIMIT 1
            ");

        $statement->execute([
            $projectId,
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


    private function leastLoadedAssignee(
        int $teamId,
        ?int $maxOpenPerAgent
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    tm.project_member_id,

                    tm.workload_weight,

                    pm.user_reference,
                    pm.display_name_snapshot,

                    SUM(
                        CASE
                            WHEN st.is_closed = 0
                            THEN 1
                            ELSE 0
                        END
                    ) AS open_ticket_count

                FROM
                    ticketing_support_team_members tm

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.id =
                        tm.project_member_id
                   AND pm.left_at IS NULL
                   AND pm.user_reference IS NOT NULL
                   AND pm.user_reference <> ''

                LEFT JOIN
                    ticketing_tickets tk
                    ON tk.current_assignee_project_member_id =
                        pm.id
                   AND tk.archived_at IS NULL

                LEFT JOIN
                    ticketing_statuses st
                    ON st.code = tk.status_code

                WHERE tm.team_id = ?
                  AND tm.status = 'active'
                  AND tm.left_at IS NULL
                  AND tm.staff_role_code IN
                      (
                          'agent',
                          'supervisor',
                          'manager'
                      )

                GROUP BY
                    tm.project_member_id,
                    tm.workload_weight,
                    pm.user_reference,
                    pm.display_name_snapshot

                HAVING
                    ? IS NULL
                    OR open_ticket_count < ?

                ORDER BY
                    (
                        open_ticket_count
                        /
                        CASE
                            WHEN tm.workload_weight > 0
                            THEN tm.workload_weight
                            ELSE 1
                        END
                    ) ASC,

                    open_ticket_count ASC,
                    tm.project_member_id ASC

                LIMIT 1
            ");

        $statement->execute([
            $teamId,
            $maxOpenPerAgent,
            $maxOpenPerAgent,
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


    private function event(
        int $ticketId,
        string $reference,
        string $eventCode,
        ?string $actorReference,
        ?string $actorName,
        ?string $previousStatus,
        ?string $resultingStatus,
        array $payload
    ): void {
        $json =
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );

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
            $reference,
            $ticketId,
            $eventCode,
            $actorReference,
            $actorName,
            $previousStatus,
            $resultingStatus,
            $json,
        ]);
    }
}

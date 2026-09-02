<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;

/*
 * TICKETING_ROUTING_OPERATIONAL_STAFF_ROLE_FILTER
 *
 * Automatic routing may only select active staff project
 * memberships. A requester row is never a routing assignee,
 * even when stale team-member data exists.
 */
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

        $topics =
            $this->selectableTopicsForUser(
                $userReference
            );

        return [
            'projects' =>
                $projects,

            'services' =>
                $services,

            'topics' =>
                $topics,
        ];
    }


    public function hasSelectableTopics(
        string $userReference,
        int $projectId,
        int $serviceId
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM ticketing_support_topics tp

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.project_id = tp.project_id
                   AND pm.user_reference = ?
                   AND pm.left_at IS NULL

                WHERE tp.project_id = ?
                  AND tp.status = 'active'
                  AND tp.is_selectable = 1

                  AND (
                        tp.service_id IS NULL
                        OR tp.service_id = ?
                  )
            ");

        $statement->execute([
            trim($userReference),
            $projectId,
            $serviceId,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function topicForSelection(
        string $userReference,
        int $projectId,
        int $serviceId,
        int $topicId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    tp.id,
                    tp.public_reference,
                    tp.project_id,
                    tp.service_id,
                    tp.parent_topic_id,
                    tp.code,
                    tp.title,
                    tp.is_default

                FROM ticketing_support_topics tp

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.project_id = tp.project_id
                   AND pm.user_reference = ?
                   AND pm.left_at IS NULL

                WHERE tp.id = ?
                  AND tp.project_id = ?
                  AND tp.status = 'active'
                  AND tp.is_selectable = 1

                  AND (
                        tp.service_id IS NULL
                        OR tp.service_id = ?
                  )

                LIMIT 1
            ");

        $statement->execute([
            trim($userReference),
            $topicId,
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


    private function selectableTopicsForUser(
        string $userReference
    ): array {
        $statement =
            $this->db->prepare("
                SELECT DISTINCT
                    tp.id,
                    tp.public_reference
                        AS reference,
                    tp.project_id,
                    tp.service_id,
                    tp.parent_topic_id,
                    tp.code,
                    tp.title,
                    tp.is_default,
                    tp.sort_order,

                    parent.title
                        AS parent_title

                FROM ticketing_support_topics tp

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.project_id = tp.project_id
                   AND pm.user_reference = ?
                   AND pm.left_at IS NULL

                INNER JOIN
                    ticketing_support_projects p
                    ON p.id = tp.project_id
                   AND p.is_active = 1
                   AND p.archived_at IS NULL

                LEFT JOIN
                    ticketing_support_topics parent
                    ON parent.id =
                        tp.parent_topic_id

                WHERE tp.status = 'active'
                  AND tp.is_selectable = 1

                ORDER BY
                    tp.project_id,
                    tp.service_id,
                    tp.sort_order,
                    tp.title,
                    tp.id
            ");

        $statement->execute([
            trim($userReference),
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $topics = [];

        foreach ($rows as $row) {
            $topics[
                (int) $row['id']
            ] = [
                'id' =>
                    (int) $row['id'],

                'reference' =>
                    (string) $row[
                        'reference'
                    ],

                'project_id' =>
                    (int) $row[
                        'project_id'
                    ],

                'service_id' =>
                    $row['service_id']
                    !== null
                        ? (int) $row[
                            'service_id'
                        ]
                        : null,

                'parent_topic_id' =>
                    $row[
                        'parent_topic_id'
                    ] !== null
                        ? (int) $row[
                            'parent_topic_id'
                        ]
                        : null,

                'code' =>
                    (string) $row['code'],

                'title' =>
                    (string) $row['title'],

                'parent_title' =>
                    $row['parent_title']
                    !== null
                        ? (string) $row[
                            'parent_title'
                        ]
                        : null,

                'is_default' =>
                    (int) $row[
                        'is_default'
                    ],
            ];
        }

        return $topics;
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
        array $data,
        array $attachments = []): array {
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


            $topicId =
                (int) (
                    $data[
                        'support_topic_id'
                    ]
                    ?? 0
                );

            $topic =
                null;

            $topicsRequired =
                $this->hasSelectableTopics(
                    (string) $data[
                        'requester_user_reference'
                    ],

                    (int) $selection[
                        'project_id'
                    ],

                    (int) $selection[
                        'service_id'
                    ]
                );

            if (
                $topicsRequired
                && $topicId <= 0
            ) {
                throw new RuntimeException(
                    'A support topic is required for this project/service.'
                );
            }

            if ($topicId > 0) {
                $topic =
                    $this->topicForSelection(
                        (string) $data[
                            'requester_user_reference'
                        ],

                        (int) $selection[
                            'project_id'
                        ],

                        (int) $selection[
                            'service_id'
                        ],

                        $topicId
                    );

                if ($topic === null) {
                    throw new RuntimeException(
                        'Support topic is no longer valid.'
                    );
                }
            }

            $route =
                $this->resolveRoute(
                    (int) $selection[
                        'project_id'
                    ],

                    (int) $selection[
                        'service_id'
                    ],

                    $topic !== null
                        ? (int) $topic['id']
                        : null,

                    trim(
                        (string) (
                            $selection[
                                'organization_reference'
                            ]
                            ?? ''
                        )
                    )
                );

            if ($route === null) {
                $route =
                    $this->intakeRoute(
                        (int) $selection[
                            'project_id'
                        ]
                    );

                if ($route !== null) {
                    $route[
                        'routing_rule_id'
                    ] = null;

                    $route[
                        'routing_rule_reference'
                    ] = null;

                    $route[
                        'fixed_project_member_id'
                    ] = null;
                }
            }

            if ($route === null) {
                throw new RuntimeException(
                    'No operational support route exists for this project.'
                );
            }


            $assignmentMode =
                strtolower(
                    trim(
                        (string) (
                            $route[
                                'assignment_mode_code'
                            ]
                            ?? 'manual'
                        )
                    )
                );

            $assignee =
                null;

            if (
                $assignmentMode
                === 'fixed'
            ) {
                $assignee =
                    $this->fixedAssignee(
                        (int) $route[
                            'team_id'
                        ],

                        (int) (
                            $route[
                                'fixed_project_member_id'
                            ]
                            ?? 0
                        )
                    );

            } elseif (
                $assignmentMode
                === 'least_loaded'
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

            } elseif (
                $assignmentMode
                === 'round_robin'
            ) {
                $assignee =
                    $this->roundRobinAssignee(
                        (int) $route[
                            'team_id'
                        ]
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

                        support_topic_id,
                        support_topic_title_snapshot,
                        matched_routing_rule_id,

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

                        ?, ?, ?,

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

                $topic !== null
                    ? (int) $topic['id']
                    : null,

                $topic !== null
                    ? (string) $topic[
                        'title'
                    ]
                    : null,

                !empty(
                    $route[
                        'routing_rule_id'
                    ]
                )
                    ? (int) $route[
                        'routing_rule_id'
                    ]
                    : null,

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


            $ticketPrefix =
                strtoupper(
                    trim(
                        (string) (
                            $selection[
                                'project_code'
                            ]
                            ?? ''
                        )
                    )
                );

            $ticketPrefix =
                preg_replace(
                    '/[^A-Z0-9]+/',
                    '-',
                    $ticketPrefix
                )
                ?? '';

            $ticketPrefix =
                trim(
                    $ticketPrefix,
                    '-'
                );

            if ($ticketPrefix === '') {
                $ticketPrefix = 'TKT';
            }

            $ticketNumber =
                substr(
                    $ticketPrefix,
                    0,
                    40
                )
                . '-'
                . str_pad(
                    (string) $ticketId,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $ticketNumberUpdate =
                $this->db->prepare("
                    UPDATE ticketing_tickets
                    SET ticket_number = ?
                    WHERE id = ?
                ");

            $ticketNumberUpdate->execute([
                $ticketNumber,
                $ticketId,
            ]);


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

            $messageId =
                (int) $this->db->lastInsertId();



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
                        $assignmentMode,

                    'support_topic_id' =>
                        $topic !== null
                            ? (int) $topic[
                                'id'
                            ]
                            : null,

                    'routing_rule_id' =>
                        !empty(
                            $route[
                                'routing_rule_id'
                            ]
                        )
                            ? (int) $route[
                                'routing_rule_id'
                            ]
                            : null,

                    'routing_rule_reference' =>
                        $route[
                            'routing_rule_reference'
                        ]
                        ?? null,
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

                            ?,
                            ?
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

                    $assignmentMode,

                    !empty(
                        $route[
                            'routing_rule_reference'
                        ]
                    )
                        ? 'routing-rule:'
                            . (string) $route[
                                'routing_rule_reference'
                            ]
                        : 'automatic-intake-routing',
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
                            $assignmentMode,

                        'routing_rule_id' =>
                            !empty(
                                $route[
                                    'routing_rule_id'
                                ]
                            )
                                ? (int) $route[
                                    'routing_rule_id'
                                ]
                                : null,
                    ]
                );
            }


            $this->persistInitialAttachments(
                $ticketId,
                $messageId,
                $attachments
            );

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

            $this->cleanupInitialAttachmentFiles(
                $attachments
            );

            throw $exception;
        }
    }


    /*
     * TICKETING_ROUTING_RECOVERY_V1
     *
     * Recover only an open, fully-unrouted legacy ticket.
     * The selected topic is validated canonically and the route is
     * resolved through the standard rule path only. No intake fallback.
     */
    public function recoverMissingTopic(
        string $publicReference,
        int $topicId,
        string $actorUserReference,
        string $actorDisplayName
    ): array {
        $publicReference = trim($publicReference);
        $actorUserReference = trim($actorUserReference);
        $actorDisplayName = trim($actorDisplayName);

        if (
            $publicReference === ''
            || $topicId < 1
            || $actorUserReference === ''
        ) {
            throw new \RuntimeException('routing_recovery_invalid');
        }

        $this->db->beginTransaction();

        try {
            $ticket = $this->missingTopicRecoveryTicket(
                $publicReference,
                true
            );

            if ($ticket === null) {
                throw new \RuntimeException('ticket_not_found');
            }

            if (
                !$this->isFullyUnroutedTicket($ticket)
                || (int) ($ticket['status_is_closed'] ?? 1) !== 0
            ) {
                throw new \RuntimeException(
                    'routing_recovery_not_eligible'
                );
            }

            $projectId = (int) ($ticket['support_project_id'] ?? 0);
            $serviceId = (int) ($ticket['support_service_id'] ?? 0);

            if ($projectId < 1 || $serviceId < 1) {
                throw new \RuntimeException(
                    'routing_recovery_invalid_scope'
                );
            }

            /*
             * Topic eligibility belongs to the original requester.
             * The manager remains the authorized recovery actor only.
             */
            $requesterUserReference = trim(
                (string) (
                    $ticket['requester_user_reference']
                    ?? ''
                )
            );

            if ($requesterUserReference === '') {
                throw new \RuntimeException(
                    'routing_recovery_requester_identity_missing'
                );
            }

            $validatedTopic = $this->topicForSelection(
                $requesterUserReference,
                $projectId,
                $serviceId,
                $topicId
            );

            if ($validatedTopic === null) {
                throw new \RuntimeException(
                    'routing_recovery_invalid_topic'
                );
            }

            $topic = $this->missingTopicRecoveryTopicSnapshot(
                $projectId,
                $topicId
            );

            if ($topic === null) {
                throw new \RuntimeException(
                    'routing_recovery_invalid_topic'
                );
            }

            $organizationReference = trim(
                (string) (
                    $ticket['requester_organization_reference']
                    ?? ''
                )
            );

            $route = $this->resolveRoute(
                $projectId,
                $serviceId,
                $topicId,
                $organizationReference
            );

            if ($route === null) {
                throw new \RuntimeException(
                    'routing_recovery_no_route'
                );
            }

            foreach ([
                'routing_rule_id',
                'layer_id',
                'node_id',
                'queue_id',
                'team_id',
            ] as $field) {
                if ((int) ($route[$field] ?? 0) < 1) {
                    throw new \RuntimeException(
                        'routing_recovery_invalid_topology'
                    );
                }
            }

            $assignmentMode =
                $this->missingTopicRecoveryAssignmentMode($route);

            $assignee =
                $this->missingTopicRecoveryAssignee(
                    $route,
                    $assignmentMode
                );

            if (
                $assignmentMode !== 'manual'
                && $assignee === null
            ) {
                throw new \RuntimeException(
                    'routing_recovery_no_eligible_assignee'
                );
            }

            $update = $this->db->prepare("
                UPDATE ticketing_tickets
                SET
                    support_topic_id = ?,
                    support_topic_title_snapshot = ?,
                    matched_routing_rule_id = ?,
                    current_support_layer_id = ?,
                    current_support_node_id = ?,
                    current_support_queue_id = ?,
                    current_support_team_id = ?,
                    current_assignee_project_member_id = ?,
                    updated_by_user_reference = ?,
                    updated_at = UTC_TIMESTAMP()
                WHERE id = ?
                  AND archived_at IS NULL
                  AND support_topic_id IS NULL
                  AND matched_routing_rule_id IS NULL
                  AND current_support_layer_id IS NULL
                  AND current_support_node_id IS NULL
                  AND current_support_queue_id IS NULL
                  AND current_support_team_id IS NULL
                  AND current_assignee_project_member_id IS NULL
            ");

            $update->execute([
                $topicId,
                (string) ($topic['title'] ?? ''),
                (int) $route['routing_rule_id'],
                (int) $route['layer_id'],
                (int) $route['node_id'],
                (int) $route['queue_id'],
                (int) $route['team_id'],
                $assignee !== null
                    ? (int) ($assignee['project_member_id'] ?? 0)
                    : null,
                $actorUserReference,
                (int) $ticket['id'],
            ]);

            if ($update->rowCount() !== 1) {
                throw new \RuntimeException(
                    'routing_recovery_conflict'
                );
            }

            $this->event(
                (int) $ticket['id'],
                $this->missingTopicRecoveryEventReference(),
                'ticket_routed',
                'system:ticketing-router',
                'مسیریاب تیکتینگ',
                (string) $ticket['status_code'],
                (string) $ticket['status_code'],
                [
                    'recovery_source' => 'missing_topic_recovery',
                    'recovered_by_user_reference' =>
                        $actorUserReference,
                    'recovered_by_display_name' =>
                        $actorDisplayName,
                    'topic_id' => $topicId,
                    'topic_title' => (string) ($topic['title'] ?? ''),
                    'route_source' => 'routing-rule',
                    'routing_rule_id' =>
                        (int) $route['routing_rule_id'],
                    'routing_rule_reference' =>
                        $route['routing_rule_reference'] ?? null,
                    'layer_id' => (int) $route['layer_id'],
                    'node_id' => (int) $route['node_id'],
                    'queue_id' => (int) $route['queue_id'],
                    'team_id' => (int) $route['team_id'],
                    'assignment_mode_code' => $assignmentMode,
                ]
            );

            if ($assignee !== null) {
                $assignment = $this->db->prepare("
                    INSERT INTO ticketing_assignments (
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
                    ) VALUES (
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
                        ?,
                        ?
                    )
                ");

                $assignmentReason =
                    'routing-rule:'
                    . (string) (
                        $route['routing_rule_reference']
                        ?? ''
                    );

                $assignment->execute([
                    (int) $ticket['id'],
                    (string) ($assignee['user_reference'] ?? ''),
                    (string) (
                        $assignee['display_name_snapshot']
                        ?? ''
                    ),
                    (int) ($assignee['project_member_id'] ?? 0),
                    (int) $route['node_id'],
                    (int) $route['queue_id'],
                    (int) $route['team_id'],
                    $assignmentMode,
                    $assignmentReason,
                ]);

                $this->event(
                    (int) $ticket['id'],
                    $this->missingTopicRecoveryEventReference(),
                    'ticket_assigned',
                    'system:ticketing-router',
                    'مسیریاب تیکتینگ',
                    (string) $ticket['status_code'],
                    (string) $ticket['status_code'],
                    [
                        'recovery_source' =>
                            'missing_topic_recovery',
                        'recovered_by_user_reference' =>
                            $actorUserReference,
                        'recovered_by_display_name' =>
                            $actorDisplayName,
                        'project_member_id' =>
                            (int) (
                                $assignee['project_member_id']
                                ?? 0
                            ),
                        'assignee_reference' =>
                            (string) (
                                $assignee['user_reference']
                                ?? ''
                            ),
                        'team_id' => (int) $route['team_id'],
                        'queue_id' => (int) $route['queue_id'],
                        'assignment_mode_code' =>
                            $assignmentMode,
                        'routing_rule_id' =>
                            (int) $route['routing_rule_id'],
                    ]
                );
            }

            $this->db->commit();

            return [
                'ticket_id' => (int) $ticket['id'],
                'ticket_number' =>
                    (string) ($ticket['ticket_number'] ?? ''),
                'public_reference' =>
                    (string) ($ticket['public_reference'] ?? ''),
                'topic_id' => $topicId,
                'topic_title' => (string) ($topic['title'] ?? ''),
                'routing_rule_id' =>
                    (int) $route['routing_rule_id'],
                'layer_id' => (int) $route['layer_id'],
                'node_id' => (int) $route['node_id'],
                'queue_id' => (int) $route['queue_id'],
                'team_id' => (int) $route['team_id'],
                'assignment_mode_code' => $assignmentMode,
                'project_member_id' => $assignee !== null
                    ? (int) ($assignee['project_member_id'] ?? 0)
                    : null,
            ];

        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    private function missingTopicRecoveryTicket(
        string $publicReference,
        bool $forUpdate
    ): ?array {
        $sql = "
            SELECT
                t.id,
                t.ticket_number,
                t.public_reference,
                t.status_code,
                t.support_project_id,
                t.support_service_id,
                t.support_topic_id,
                t.support_topic_title_snapshot,
                t.matched_routing_rule_id,
                t.current_support_layer_id,
                t.current_support_node_id,
                t.current_support_queue_id,
                t.current_support_team_id,
                t.current_assignee_project_member_id,
                t.requester_user_reference,
                t.requester_organization_reference,
                t.archived_at,
                s.is_closed AS status_is_closed
            FROM ticketing_tickets t
            INNER JOIN ticketing_statuses s
                ON s.code = t.status_code
            WHERE t.public_reference = ?
            LIMIT 1
        ";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $statement = $this->db->prepare($sql);
        $statement->execute([$publicReference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }


    private function isFullyUnroutedTicket(
        array $ticket
    ): bool {
        foreach ([
            'support_topic_id',
            'matched_routing_rule_id',
            'current_support_layer_id',
            'current_support_node_id',
            'current_support_queue_id',
            'current_support_team_id',
            'current_assignee_project_member_id',
        ] as $field) {
            if (($ticket[$field] ?? null) !== null) {
                return false;
            }
        }

        return true;
    }


    private function missingTopicRecoveryTopicSnapshot(
        int $projectId,
        int $topicId
    ): ?array {
        $statement = $this->db->prepare("
            SELECT id, title
            FROM ticketing_support_topics
            WHERE id = ?
              AND project_id = ?
              AND status = 'active'
              AND is_selectable = 1
            LIMIT 1
        ");

        $statement->execute([$topicId, $projectId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }


    private function missingTopicRecoveryAssignmentMode(
        array $route
    ): string {
        $ruleMode = strtolower(trim(
            (string) (
                $route['rule_assignment_mode_code']
                ?? ''
            )
        ));

        $queueMode = strtolower(trim(
            (string) (
                $route['queue_assignment_mode_code']
                ?? ''
            )
        ));

        $mode = $ruleMode;

        if (in_array(
            $mode,
            ['', 'inherit', 'queue', 'default'],
            true
        )) {
            $mode = $queueMode;
        }

        if ($mode === '') {
            $mode = 'manual';
        }

        if (!in_array(
            $mode,
            ['manual', 'fixed', 'least_loaded', 'round_robin'],
            true
        )) {
            throw new \RuntimeException(
                'routing_recovery_assignment_mode_invalid'
            );
        }

        return $mode;
    }


    private function missingTopicRecoveryAssignee(
        array $route,
        string $assignmentMode
    ): ?array {
        $teamId = (int) ($route['team_id'] ?? 0);

        if ($teamId < 1) {
            throw new \RuntimeException(
                'routing_recovery_no_team'
            );
        }

        return match ($assignmentMode) {
            'fixed' => $this->fixedAssignee(
                $teamId,
                (int) (
                    $route['fixed_project_member_id']
                    ?? 0
                )
            ),
            'least_loaded' => $this->leastLoadedAssignee(
                $teamId,
                isset($route['max_open_per_agent'])
                    && $route['max_open_per_agent'] !== null
                        ? (int) $route['max_open_per_agent']
                        : null
            ),
            'round_robin' => $this->roundRobinAssignee(
                $teamId
            ),
            'manual' => null,
            default => null,
        };
    }


    private function missingTopicRecoveryEventReference(): string
    {
        return
            'TEVT-'
            . strtoupper(
                bin2hex(random_bytes(12))
            );
    }


    private function resolveRoute(
        int $projectId,
        int $serviceId,
        ?int $topicId,
        string $organizationReference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    r.id
                        AS routing_rule_id,

                    r.public_reference
                        AS routing_rule_reference,

                    r.assignment_mode_code
                        AS rule_assignment_mode_code,

                    r.fixed_project_member_id,

                    l.id AS layer_id,
                    n.id AS node_id,

                    q.id AS queue_id,
                    q.assignment_mode_code
                        AS queue_assignment_mode_code,
                    q.max_open_per_agent,

                    t.id AS team_id

                FROM
                    ticketing_support_routing_rules r

                INNER JOIN
                    ticketing_support_layers l
                    ON l.id =
                        r.target_layer_id
                   AND l.project_id =
                        r.project_id
                   AND l.status = 'active'

                INNER JOIN
                    ticketing_support_nodes n
                    ON n.id =
                        r.target_node_id
                   AND n.project_id =
                        r.project_id
                   AND n.layer_id = l.id
                   AND n.status = 'active'

                INNER JOIN
                    ticketing_support_queues q
                    ON q.id =
                        r.target_queue_id
                   AND q.project_id =
                        r.project_id
                   AND q.node_id = n.id
                   AND q.status = 'active'

                INNER JOIN
                    ticketing_support_teams t
                    ON t.id =
                        r.target_team_id
                   AND t.project_id =
                        r.project_id
                   AND t.status = 'active'

                INNER JOIN
                    ticketing_support_team_nodes tn
                    ON tn.team_id = t.id
                   AND tn.node_id = n.id
                   AND tn.status = 'active'

                INNER JOIN
                    ticketing_support_team_queues tq
                    ON tq.team_id = t.id
                   AND tq.queue_id = q.id
                   AND tq.status = 'active'

                WHERE r.project_id = ?
                  AND r.status = 'active'

                  AND (
                        r.service_id IS NULL
                        OR r.service_id = ?
                  )

                  AND (
                        r.topic_id IS NULL
                        OR r.topic_id = ?
                  )

                  AND (
                        r.scope_type_code = 'all'

                        OR (
                            r.scope_type_code =
                                'organization'

                            AND ? <> ''

                            AND r.scope_reference = ?
                        )
                  )

                ORDER BY

                    CASE
                        WHEN
                            r.scope_type_code =
                                'organization'
                            AND r.scope_reference = ?
                            AND ? <> ''
                        THEN 2

                        WHEN
                            r.scope_type_code = 'all'
                        THEN 1

                        ELSE 0
                    END DESC,

                    CASE
                        WHEN
                            r.topic_id = ?
                            AND ? > 0
                        THEN 2

                        WHEN r.topic_id IS NULL
                        THEN 1

                        ELSE 0
                    END DESC,

                    CASE
                        WHEN r.service_id = ?
                        THEN 2

                        WHEN r.service_id IS NULL
                        THEN 1

                        ELSE 0
                    END DESC,

                    r.priority DESC,
                    r.sort_order ASC,
                    r.id ASC

                LIMIT 1
            ");

        $topicValue =
            $topicId !== null
                ? $topicId
                : 0;

        $statement->execute([
            $projectId,
            $serviceId,
            $topicValue,

            $organizationReference,
            $organizationReference,

            $organizationReference,
            $organizationReference,

            $topicValue,
            $topicValue,

            $serviceId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return null;
        }

        $mode =
            strtolower(
                trim(
                    (string) (
                        $row[
                            'rule_assignment_mode_code'
                        ]
                        ?? 'inherit'
                    )
                )
            );

        if ($mode === 'inherit') {
            $mode =
                strtolower(
                    trim(
                        (string) (
                            $row[
                                'queue_assignment_mode_code'
                            ]
                            ?? 'manual'
                        )
                    )
                );
        }

        if (
            !in_array(
                $mode,
                [
                    'manual',
                    'least_loaded',
                    'round_robin',
                    'fixed',
                ],
                true
            )
        ) {
            $mode = 'manual';
        }

        $row[
            'assignment_mode_code'
        ] = $mode;

        return $row;
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


    private function fixedAssignee(
        int $teamId,
        int $projectMemberId
    ): ?array {
        if ($projectMemberId <= 0) {
            return null;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    tm.project_member_id,

                    pm.user_reference,
                    pm.display_name_snapshot

                FROM
                    ticketing_support_team_members tm

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.id =
                        tm.project_member_id

                   AND pm.left_at IS NULL
                   AND pm.role_code IN ('member', 'manager')

                   AND pm.user_reference
                        IS NOT NULL

                   AND pm.user_reference <> ''

                WHERE tm.team_id = ?
                  AND tm.project_member_id = ?
                  AND tm.status = 'active'
                  AND tm.left_at IS NULL

                LIMIT 1
            ");

        $statement->execute([
            $teamId,
            $projectMemberId,
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


    private function roundRobinAssignee(
        int $teamId
    ): ?array {
        /*
         * Durable stateless round-robin:
         *
         * choose the active team member with the
         * fewest historical assignments in this team;
         * ties go to the least-recently assigned member
         * and then stable project-member id.
         */
        $statement =
            $this->db->prepare("
                SELECT
                    candidate.project_member_id,
                    candidate.user_reference,
                    candidate.display_name_snapshot,
                    candidate.assignment_count,
                    candidate.last_assigned_at

                FROM
                (
                    SELECT
                        tm.project_member_id,

                        pm.user_reference,
                        pm.display_name_snapshot,

                        COUNT(a.id)
                            AS assignment_count,

                        MAX(a.assigned_at)
                            AS last_assigned_at

                    FROM
                        ticketing_support_team_members tm

                    INNER JOIN
                        ticketing_support_project_members pm
                        ON pm.id =
                            tm.project_member_id

                       AND pm.left_at IS NULL
                       AND pm.role_code IN ('member', 'manager')

                       AND pm.user_reference
                            IS NOT NULL

                       AND pm.user_reference <> ''

                    LEFT JOIN
                        ticketing_assignments a
                        ON a.project_member_id =
                            tm.project_member_id

                       AND a.support_team_id =
                            tm.team_id

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
                        pm.user_reference,
                        pm.display_name_snapshot
                ) candidate

                ORDER BY
                    candidate.assignment_count ASC,

                    CASE
                        WHEN
                            candidate.last_assigned_at
                            IS NULL
                        THEN 0
                        ELSE 1
                    END ASC,

                    candidate.last_assigned_at ASC,

                    candidate.project_member_id ASC

                LIMIT 1
            ");

        $statement->execute([
            $teamId,
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
        /*
         * Aggregate first, then filter/order in the outer query.
         *
         * MariaDB does not reliably allow an aggregate alias
         * such as open_ticket_count to be reused inside another
         * expression in the same SELECT/HAVING scope.
         */
        $statement =
            $this->db->prepare("
                SELECT
                    candidate.project_member_id,
                    candidate.workload_weight,
                    candidate.user_reference,
                    candidate.display_name_snapshot,
                    candidate.open_ticket_count

                FROM
                (
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
                       AND pm.role_code IN ('member', 'manager')

                       AND pm.user_reference
                            IS NOT NULL

                       AND pm.user_reference <> ''

                    LEFT JOIN
                        ticketing_tickets tk

                        ON
                            tk.current_assignee_project_member_id
                            =
                            pm.id

                       AND tk.archived_at IS NULL

                    LEFT JOIN
                        ticketing_statuses st

                        ON st.code =
                            tk.status_code

                    WHERE tm.team_id = ?

                      AND tm.status =
                            'active'

                      AND tm.left_at
                            IS NULL

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
                ) candidate

                WHERE
                    ? IS NULL

                    OR candidate.open_ticket_count
                        < ?

                ORDER BY
                    (
                        candidate.open_ticket_count
                        /
                        CASE
                            WHEN candidate.workload_weight > 0
                            THEN candidate.workload_weight
                            ELSE 1
                        END
                    ) ASC,

                    candidate.open_ticket_count ASC,

                    candidate.project_member_id ASC

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


    private function persistInitialAttachments(
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

                || !in_array(
                    $scanStatus,
                    [
                        'pending',
                        'clean',
                        'approved',
                    ],
                    true
                )
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


    private function cleanupInitialAttachmentFiles(
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

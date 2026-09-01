<?php

declare(strict_types=1);

namespace App\Repositories;

use DomainException;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use Throwable;

/*
 * TICKETING_OPERATIONAL_PROJECT_ROLE_FILTER
 *
 * Team staff_role_code is necessary but not sufficient.
 * The linked project membership must also be an active
 * member/manager membership of the same support project.
 */
final class TicketStaffOperationsRepository
{
    private PDO $db;


    public function __construct(
        ?PDO $db = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );
    }


    public function isStaff(
        string $userReference
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM
                    ticketing_support_project_members pm

                INNER JOIN
                    ticketing_support_team_members tm
                    ON tm.project_member_id = pm.id

                WHERE pm.user_reference = ?
                  AND pm.left_at IS NULL
                  AND pm.role_code IN ('member', 'manager')

                  AND tm.status = 'active'
                  AND tm.left_at IS NULL
            ");

        $statement->execute([
            trim($userReference),
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function cartable(
        string $userReference,
        string $scope = 'all'
    ): array {
        $userReference =
            trim($userReference);

        $memberships =
            $this->actorMemberships(
                $userReference
            );

        if ($memberships === []) {
            return [];
        }


        $memberIds = [];

        foreach ($memberships as $membership) {
            $memberId =
                (int) (
                    $membership[
                        'project_member_id'
                    ]
                    ?? 0
                );

            if ($memberId > 0) {
                $memberIds[$memberId] =
                    true;
            }
        }


        $visibleByProject =
            $this->visibleNodesByProject(
                $memberships
            );


        $scope =
            in_array(
                $scope,
                [
                    'all',
                    'my',
                    'unassigned',
                ],
                true
            )
                ? $scope
                : 'all';


        $where = [
            't.archived_at IS NULL',
        ];

        $parameters = [];


        if ($scope === 'my') {

            if ($memberIds === []) {
                return [];
            }

            $marks =
                implode(
                    ',',
                    array_fill(
                        0,
                        count($memberIds),
                        '?'
                    )
                );

            $where[] =
                "t.current_assignee_project_member_id
                    IN ({$marks})";

            foreach (
                array_keys($memberIds)
                as $memberId
            ) {
                $parameters[] =
                    $memberId;
            }

        } elseif (
            $scope === 'unassigned'
        ) {

            $visibleClause =
                $this->visibleNodeClause(
                    $visibleByProject,
                    $parameters
                );

            if ($visibleClause === '') {
                return [];
            }

            $where[] =
                $visibleClause;

            $where[] =
                't.current_assignee_project_member_id
                    IS NULL';

        } else {

            $access = [];


            if ($memberIds !== []) {

                $marks =
                    implode(
                        ',',
                        array_fill(
                            0,
                            count($memberIds),
                            '?'
                        )
                    );

                $access[] =
                    "t.current_assignee_project_member_id
                        IN ({$marks})";

                foreach (
                    array_keys($memberIds)
                    as $memberId
                ) {
                    $parameters[] =
                        $memberId;
                }
            }


            $visibleParameters = [];

            $visibleClause =
                $this->visibleNodeClause(
                    $visibleByProject,
                    $visibleParameters
                );

            if ($visibleClause !== '') {
                $access[] =
                    $visibleClause;

                foreach (
                    $visibleParameters
                    as $parameter
                ) {
                    $parameters[] =
                        $parameter;
                }
            }


            if ($access === []) {
                return [];
            }

            $where[] =
                '('
                . implode(
                    ' OR ',
                    $access
                )
                . ')';
        }


        $statement =
            $this->db->prepare("
                SELECT
                    t.id,
                    t.public_reference,
                    t.ticket_number,

                    t.support_project_id,
                    t.support_project_title_snapshot,
                    p.title AS project_title,

                    t.support_topic_title_snapshot,

                    t.subject,

                    t.status_code,
                    s.title AS status_title,

                    t.priority_code,
                    pr.title AS priority_title,
                    pr.color AS priority_color,

                    t.current_support_layer_id,
                    l.title AS layer_title,

                    t.current_support_node_id,
                    n.title AS node_title,

                    t.current_support_queue_id,
                    q.title AS queue_title,

                    t.current_support_team_id,
                    tm.title AS team_title,

                    t.current_assignee_project_member_id,
                    assignee.user_reference
                        AS assignee_user_reference,
                    assignee.display_name_snapshot
                        AS assignee_name,

                    t.requester_display_name_snapshot,

                    t.last_activity_at,
                    t.created_at

                FROM ticketing_tickets t

                LEFT JOIN
                    ticketing_support_projects p
                    ON p.id =
                        t.support_project_id

                INNER JOIN
                    ticketing_statuses s
                    ON s.code =
                        t.status_code

                INNER JOIN
                    ticketing_priorities pr
                    ON pr.code =
                        t.priority_code

                LEFT JOIN
                    ticketing_support_layers l
                    ON l.id =
                        t.current_support_layer_id

                LEFT JOIN
                    ticketing_support_nodes n
                    ON n.id =
                        t.current_support_node_id

                LEFT JOIN
                    ticketing_support_queues q
                    ON q.id =
                        t.current_support_queue_id

                LEFT JOIN
                    ticketing_support_teams tm
                    ON tm.id =
                        t.current_support_team_id

                LEFT JOIN
                    ticketing_support_project_members assignee
                    ON assignee.id =
                        t.current_assignee_project_member_id

                WHERE
                    " . implode(
                        ' AND ',
                        $where
                    ) . "

                ORDER BY
                pr.severity DESC,
                t.last_activity_at DESC,
                t.id DESC
            LIMIT 200
            ");

        $statement->execute(
            $parameters
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    public function actionContext(
        int $ticketId,
        string $userReference
    ): array {
        $ticket =
            $this->ticketById(
                $ticketId
            );

        if ($ticket === null) {
            return [
                'can_takeover' => false,
                'can_transfer' => false,
                'can_escalate' => false,
                'transfer_targets' => [],
                'escalation_target_title' => '',
            ];
        }


        $memberships =
            $this->actorMemberships(
                $userReference,
                (int) $ticket[
                    'support_project_id'
                ]
            );


        $takeover =
            $this->takeoverMembership(
                $ticket,
                $memberships
            );


        $canTakeover =
            is_array($takeover)
            && !(
                (int) $ticket[
                    'current_assignee_project_member_id'
                ]
                ===
                (int) $takeover[
                    'project_member_id'
                ]
                &&
                (int) $ticket[
                    'current_support_node_id'
                ]
                ===
                (int) $takeover[
                    'node_id'
                ]
            );


        $currentMembership =
            $this->membershipForTeam(
                $memberships,
                (int) (
                    $ticket[
                        'current_support_team_id'
                    ]
                    ?? 0
                )
            );


        $canTransfer =
            is_array($currentMembership)
            && !empty(
                $currentMembership[
                    'can_transfer'
                ]
            );


        $targets =
            $canTransfer
                ? $this->transferTargets(
                    (int) $ticket[
                        'current_support_team_id'
                    ],
                    (int) (
                        $ticket[
                            'current_assignee_project_member_id'
                        ]
                        ?? 0
                    )
                )
                : [];


        $escalation =
            null;

        $canEscalate =
            is_array($currentMembership)
            && (
                !empty(
                    $currentMembership[
                        'can_transfer'
                    ]
                )
                ||
                !empty(
                    $currentMembership[
                        'can_takeover'
                    ]
                )
            );

        if ($canEscalate) {
            $escalation =
                $this->nextEscalationRelation(
                    (int) $ticket[
                        'support_project_id'
                    ],
                    (int) (
                        $ticket[
                            'current_support_node_id'
                        ]
                        ?? 0
                    )
                );

            $canEscalate =
                is_array($escalation);
        }


        return [
            'can_takeover' =>
                $canTakeover,

            'can_transfer' =>
                $canTransfer,

            'can_escalate' =>
                $canEscalate,

            'transfer_targets' =>
                $targets,

            'escalation_target_title' =>
                is_array($escalation)
                    ? (string) (
                        $escalation[
                            'parent_title'
                        ]
                        ?? ''
                    )
                    : '',
        ];
    }


    public function takeOver(
        string $publicReference,
        string $actorUserReference,
        string $actorDisplayName
    ): void {
        $this->db->beginTransaction();

        try {

            $ticket =
                $this->lockTicket(
                    $publicReference
                );

            $this->assertOperational(
                $ticket
            );


            $memberships =
                $this->actorMemberships(
                    $actorUserReference,
                    (int) $ticket[
                        'support_project_id'
                    ]
                );


            $target =
                $this->takeoverMembership(
                    $ticket,
                    $memberships
                );

            if (!is_array($target)) {
                throw new DomainException(
                    'not_allowed'
                );
            }


            if (
                (int) (
                    $ticket[
                        'current_assignee_project_member_id'
                    ]
                    ?? 0
                )
                ===
                (int) $target[
                    'project_member_id'
                ]
                &&
                (int) (
                    $ticket[
                        'current_support_node_id'
                    ]
                    ?? 0
                )
                ===
                (int) $target[
                    'node_id'
                ]
            ) {
                throw new DomainException(
                    'already_owner'
                );
            }


            $previousAssignee =
                (int) (
                    $ticket[
                        'current_assignee_project_member_id'
                    ]
                    ?? 0
                );


            $this->replaceAssignment(
                $ticket,
                $target,
                $actorUserReference,
                'manual',
                'manual-takeover'
            );


            $this->recordEvent(
                (int) $ticket['id'],
                'ticket_taken_over',
                $actorUserReference,
                $actorDisplayName,
                (string) $ticket[
                    'status_code'
                ],
                [
                    'previous_assignee_project_member_id' =>
                        $previousAssignee,

                    'assignee_project_member_id' =>
                        (int) $target[
                            'project_member_id'
                        ],

                    'target_node_id' =>
                        (int) $target[
                            'node_id'
                        ],

                    'target_team_id' =>
                        (int) $target[
                            'team_id'
                        ],
                ]
            );


            $this->db->commit();

        } catch (Throwable $exception) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function transfer(
        string $publicReference,
        int $targetProjectMemberId,
        string $actorUserReference,
        string $actorDisplayName
    ): void {
        $this->db->beginTransaction();

        try {

            $ticket =
                $this->lockTicket(
                    $publicReference
                );

            $this->assertOperational(
                $ticket
            );


            $memberships =
                $this->actorMemberships(
                    $actorUserReference,
                    (int) $ticket[
                        'support_project_id'
                    ]
                );


            $actorMembership =
                $this->membershipForTeam(
                    $memberships,
                    (int) (
                        $ticket[
                            'current_support_team_id'
                        ]
                        ?? 0
                    )
                );


            if (
                !is_array($actorMembership)
                ||
                empty(
                    $actorMembership[
                        'can_transfer'
                    ]
                )
            ) {
                throw new DomainException(
                    'not_allowed'
                );
            }


            if (
                $targetProjectMemberId
                <= 0
            ) {
                throw new DomainException(
                    'target_invalid'
                );
            }


            if (
                $targetProjectMemberId
                ===
                (int) (
                    $ticket[
                        'current_assignee_project_member_id'
                    ]
                    ?? 0
                )
            ) {
                throw new DomainException(
                    'same_assignee'
                );
            }


            $target =
                $this->memberInTeam(
                    (int) $ticket[
                        'current_support_team_id'
                    ],
                    $targetProjectMemberId
                );


            if (!is_array($target)) {
                throw new DomainException(
                    'target_invalid'
                );
            }


            $target[
                'node_id'
            ] =
                (int) $ticket[
                    'current_support_node_id'
                ];

            $target[
                'layer_id'
            ] =
                (int) $ticket[
                    'current_support_layer_id'
                ];

            $target[
                'queue_id'
            ] =
                (int) $ticket[
                    'current_support_queue_id'
                ];

            $target[
                'team_id'
            ] =
                (int) $ticket[
                    'current_support_team_id'
                ];


            $previousAssignee =
                (int) (
                    $ticket[
                        'current_assignee_project_member_id'
                    ]
                    ?? 0
                );


            $this->replaceAssignment(
                $ticket,
                $target,
                $actorUserReference,
                'manual',
                'manual-transfer'
            );


            $this->recordEvent(
                (int) $ticket['id'],
                'ticket_transferred',
                $actorUserReference,
                $actorDisplayName,
                (string) $ticket[
                    'status_code'
                ],
                [
                    'previous_assignee_project_member_id' =>
                        $previousAssignee,

                    'assignee_project_member_id' =>
                        $targetProjectMemberId,

                    'support_team_id' =>
                        (int) $ticket[
                            'current_support_team_id'
                        ],
                ]
            );


            $this->db->commit();

        } catch (Throwable $exception) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function escalate(
        string $publicReference,
        string $actorUserReference,
        string $actorDisplayName
    ): void {
        $this->executeEscalation(
            $publicReference,
            $actorUserReference,
            $actorDisplayName,
            true,
            'manual-escalation'
        );
    }


    public function escalateSystem(
        string $publicReference
    ): void {
        $this->executeEscalation(
            trim($publicReference),
            'system:ticketing-sla',
            'موتور SLA تیکتینگ',
            false,
            'sla-auto-escalation'
        );
    }


    private function executeEscalation(
        string $publicReference,
        string $actorUserReference,
        string $actorDisplayName,
        bool $authorizeActor,
        string $assignmentReason
    ): void {
        $this->db->beginTransaction();

        try {

            $ticket =
                $this->lockTicket(
                    $publicReference
                );

            $this->assertOperational(
                $ticket
            );


            if ($authorizeActor) {
                $memberships =
                    $this->actorMemberships(
                        $actorUserReference,
                        (int) $ticket[
                            'support_project_id'
                        ]
                    );


                $actorMembership =
                    $this->membershipForTeam(
                        $memberships,
                        (int) (
                            $ticket[
                                'current_support_team_id'
                            ]
                            ?? 0
                        )
                    );


                if (
                    !is_array($actorMembership)
                    ||
                    (
                        empty(
                            $actorMembership[
                                'can_transfer'
                            ]
                        )
                        &&
                        empty(
                            $actorMembership[
                                'can_takeover'
                            ]
                        )
                    )
                ) {
                    throw new DomainException(
                        'not_allowed'
                    );
                }


            }


            $relation =
                $this->nextEscalationRelation(
                    (int) $ticket[
                        'support_project_id'
                    ],
                    (int) (
                        $ticket[
                            'current_support_node_id'
                        ]
                        ?? 0
                    )
                );


            if (!is_array($relation)) {
                throw new DomainException(
                    'no_escalation_path'
                );
            }


            $route =
                $this->routeForNode(
                    (int) $ticket[
                        'support_project_id'
                    ],
                    (int) $relation[
                        'parent_node_id'
                    ]
                );


            if (!is_array($route)) {
                throw new DomainException(
                    'no_escalation_route'
                );
            }


            $assignee =
                $this->leastLoadedMember(
                    (int) $route[
                        'team_id'
                    ],
                    isset(
                        $route[
                            'max_open_per_agent'
                        ]
                    )
                        ? (
                            $route[
                                'max_open_per_agent'
                            ] !== null
                                ? (int) $route[
                                    'max_open_per_agent'
                                ]
                                : null
                        )
                        : null
                );


            if (!is_array($assignee)) {
                throw new DomainException(
                    'no_assignee'
                );
            }


            $target = [
                'project_member_id' =>
                    (int) $assignee[
                        'project_member_id'
                    ],

                'user_reference' =>
                    (string) $assignee[
                        'user_reference'
                    ],

                'display_name_snapshot' =>
                    (string) $assignee[
                        'display_name_snapshot'
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
            ];


            $from = [
                'layer_id' =>
                    (int) (
                        $ticket[
                            'current_support_layer_id'
                        ]
                        ?? 0
                    ),

                'node_id' =>
                    (int) (
                        $ticket[
                            'current_support_node_id'
                        ]
                        ?? 0
                    ),

                'queue_id' =>
                    (int) (
                        $ticket[
                            'current_support_queue_id'
                        ]
                        ?? 0
                    ),

                'team_id' =>
                    (int) (
                        $ticket[
                            'current_support_team_id'
                        ]
                        ?? 0
                    ),

                'assignee_project_member_id' =>
                    (int) (
                        $ticket[
                            'current_assignee_project_member_id'
                        ]
                        ?? 0
                    ),
            ];


            $this->replaceAssignment(
                $ticket,
                $target,
                $actorUserReference,
                'escalation',
                $assignmentReason
            );


            $this->recordEvent(
                (int) $ticket['id'],
                'ticket_escalated',
                $actorUserReference,
                $actorDisplayName,
                (string) $ticket[
                    'status_code'
                ],
                [
                    'from' => $from,

                    'to' => [
                        'layer_id' =>
                            $target['layer_id'],

                        'node_id' =>
                            $target['node_id'],

                        'queue_id' =>
                            $target['queue_id'],

                        'team_id' =>
                            $target['team_id'],

                        'assignee_project_member_id' =>
                            $target[
                                'project_member_id'
                            ],
                    ],
                ]
            );


            $this->recordEvent(
                (int) $ticket['id'],
                'ticket_assigned',
                $actorUserReference,
                $actorDisplayName,
                (string) $ticket[
                    'status_code'
                ],
                [
                    'assignment_mode_code' =>
                        'escalation',

                    'project_member_id' =>
                        $target[
                            'project_member_id'
                        ],

                    'support_node_id' =>
                        $target[
                            'node_id'
                        ],

                    'support_queue_id' =>
                        $target[
                            'queue_id'
                        ],

                    'support_team_id' =>
                        $target[
                            'team_id'
                        ],
                ]
            );


            $this->db->commit();

        } catch (Throwable $exception) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function displayNameForUserReference(
        string $userReference
    ): ?string {
        $userReference =
            trim(
                $userReference
            );

        if ($userReference === '') {
            return null;
        }


        $statement =
            $this->db->prepare("
                SELECT
                    display_name_snapshot

                FROM
                    ticketing_support_project_members

                WHERE user_reference = ?
                  AND left_at IS NULL

                  AND display_name_snapshot
                        IS NOT NULL

                  AND TRIM(
                        display_name_snapshot
                      ) <> ''

                ORDER BY id

                LIMIT 1
            ");

        $statement->execute([
            $userReference,
        ]);


        $value =
            $statement->fetchColumn();


        if (!is_string($value)) {
            return null;
        }


        $value =
            trim(
                $value
            );


        return
            $value !== ''
                ? $value
                : null;
    }


    private function actorMemberships(
        string $userReference,
        ?int $projectId = null
    ): array {
        $where = [
            'pm.user_reference = ?',
            'pm.left_at IS NULL',
            'pm.role_code IN (\'member\', \'manager\')',
            "tm.status = 'active'",
            'tm.left_at IS NULL',
            "t.status = 'active'",
        ];

        $parameters = [
            trim($userReference),
        ];


        if (
            $projectId !== null
            && $projectId > 0
        ) {
            $where[] =
                'pm.project_id = ?';

            $parameters[] =
                $projectId;
        }


        $statement =
            $this->db->prepare("
                SELECT
                    pm.project_id,
                    pm.id AS project_member_id,
                    pm.user_reference,
                    pm.display_name_snapshot,

                    tm.team_id,
                    tm.staff_role_code,
                    tm.can_assign,
                    tm.can_observe,
                    tm.can_assist,
                    tm.can_takeover,
                    tm.can_transfer,

                    q.id AS queue_id,
                    q.node_id,
                    q.is_default AS queue_is_default,
                    q.sort_order AS queue_sort_order,

                    n.layer_id,

                    t.title AS team_title

                FROM
                    ticketing_support_project_members pm

                INNER JOIN
                    ticketing_support_team_members tm
                    ON tm.project_member_id = pm.id

                INNER JOIN
                    ticketing_support_teams t
                    ON t.id = tm.team_id

                LEFT JOIN
                    ticketing_support_team_queues tq
                    ON tq.team_id = t.id
                   AND tq.status = 'active'

                LEFT JOIN
                    ticketing_support_queues q
                    ON q.id = tq.queue_id
                   AND q.status = 'active'

                LEFT JOIN
                    ticketing_support_nodes n
                    ON n.id = q.node_id
                   AND n.status = 'active'

                WHERE
                    " . implode(
                        ' AND ',
                        $where
                    ) . "

                ORDER BY
                    pm.project_id,
                    tm.team_id,
                    q.is_default DESC,
                    q.sort_order,
                    q.id
            ");

        $statement->execute(
            $parameters
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    private function visibleNodesByProject(
        array $memberships
    ): array {
        $result = [];


        foreach (
            $memberships
            as $membership
        ) {
            $projectId =
                (int) (
                    $membership[
                        'project_id'
                    ]
                    ?? 0
                );

            $nodeId =
                (int) (
                    $membership[
                        'node_id'
                    ]
                    ?? 0
                );


            if (
                $projectId <= 0
                || $nodeId <= 0
            ) {
                continue;
            }


            if (
                empty(
                    $membership[
                        'can_observe'
                    ]
                )
                &&
                empty(
                    $membership[
                        'can_takeover'
                    ]
                )
            ) {
                continue;
            }


            if (!isset($result[$projectId])) {
                $result[$projectId] = [];
            }


            $result[$projectId][$nodeId] =
                true;


            foreach (
                $this->descendantNodes(
                    $projectId,
                    $nodeId
                )
                as $descendantId
            ) {
                $result[
                    $projectId
                ][
                    $descendantId
                ] = true;
            }
        }


        return $result;
    }


    private function descendantNodes(
        int $projectId,
        int $rootNodeId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    parent_node_id,
                    child_node_id

                FROM
                    ticketing_support_node_relations

                WHERE project_id = ?
                  AND status = 'active'
                  AND relation_type_code =
                      'hierarchy'
            ");

        $statement->execute([
            $projectId,
        ]);

        $children = [];

        foreach (
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
            as $relation
        ) {
            $parent =
                (int) $relation[
                    'parent_node_id'
                ];

            $child =
                (int) $relation[
                    'child_node_id'
                ];

            $children[$parent][] =
                $child;
        }


        $seen = [];
        $queue = [
            $rootNodeId,
        ];

        while ($queue !== []) {

            $current =
                array_shift($queue);

            foreach (
                $children[$current]
                ?? []
                as $child
            ) {
                if (isset($seen[$child])) {
                    continue;
                }

                $seen[$child] =
                    true;

                $queue[] =
                    $child;
            }
        }


        return
            array_map(
                'intval',
                array_keys($seen)
            );
    }


    private function visibleNodeClause(
        array $visibleByProject,
        array &$parameters
    ): string {
        $groups = [];


        foreach (
            $visibleByProject
            as $projectId => $nodes
        ) {
            if ($nodes === []) {
                continue;
            }

            $nodeIds =
                array_map(
                    'intval',
                    array_keys($nodes)
                );

            $marks =
                implode(
                    ',',
                    array_fill(
                        0,
                        count($nodeIds),
                        '?'
                    )
                );

            $groups[] =
                "(
                    t.support_project_id = ?
                    AND
                    t.current_support_node_id
                        IN ({$marks})
                )";

            $parameters[] =
                (int) $projectId;

            foreach ($nodeIds as $nodeId) {
                $parameters[] =
                    $nodeId;
            }
        }


        return
            $groups !== []
                ? '('
                    . implode(
                        ' OR ',
                        $groups
                    )
                    . ')'
                : '';
    }


    private function ticketById(
        int $ticketId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM ticketing_tickets
                WHERE id = ?
                  AND archived_at IS NULL
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


    private function lockTicket(
        string $publicReference
    ): array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM ticketing_tickets
                WHERE public_reference = ?
                  AND archived_at IS NULL
                LIMIT 1
                FOR UPDATE
            ");

        $statement->execute([
            trim($publicReference),
        ]);

        $ticket =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($ticket)) {
            throw new DomainException(
                'ticket_not_found'
            );
        }

        return $ticket;
    }


    private function assertOperational(
        array $ticket
    ): void {
        if (
            in_array(
                (string) (
                    $ticket[
                        'status_code'
                    ]
                    ?? ''
                ),
                [
                    'resolved',
                    'closed',
                    'cancelled',
                ],
                true
            )
        ) {
            throw new DomainException(
                'ticket_closed'
            );
        }


        if (
            (int) (
                $ticket[
                    'current_support_node_id'
                ]
                ?? 0
            ) <= 0
            ||
            (int) (
                $ticket[
                    'current_support_team_id'
                ]
                ?? 0
            ) <= 0
        ) {
            throw new DomainException(
                'ticket_not_routed'
            );
        }
    }


    private function membershipForTeam(
        array $memberships,
        int $teamId
    ): ?array {
        foreach (
            $memberships
            as $membership
        ) {
            if (
                (int) (
                    $membership[
                        'team_id'
                    ]
                    ?? 0
                )
                === $teamId
            ) {
                return $membership;
            }
        }

        return null;
    }


    private function takeoverMembership(
        array $ticket,
        array $memberships
    ): ?array {
        $projectId =
            (int) $ticket[
                'support_project_id'
            ];

        $currentNodeId =
            (int) (
                $ticket[
                    'current_support_node_id'
                ]
                ?? 0
            );


        if ($currentNodeId <= 0) {
            return null;
        }


        $distance =
            $this->ancestorDistances(
                $projectId,
                $currentNodeId
            );


        $eligible = [];


        foreach (
            $memberships
            as $membership
        ) {
            if (
                empty(
                    $membership[
                        'can_takeover'
                    ]
                )
            ) {
                continue;
            }

            $nodeId =
                (int) (
                    $membership[
                        'node_id'
                    ]
                    ?? 0
                );

            $queueId =
                (int) (
                    $membership[
                        'queue_id'
                    ]
                    ?? 0
                );

            if (
                $nodeId <= 0
                || $queueId <= 0
                || !isset(
                    $distance[$nodeId]
                )
            ) {
                continue;
            }


            $membership[
                '_distance'
            ] =
                $distance[$nodeId];

            $eligible[] =
                $membership;
        }


        if ($eligible === []) {
            return null;
        }


        usort(
            $eligible,
            static function (
                array $left,
                array $right
            ): int {
                $compare =
                    (int) $left[
                        '_distance'
                    ]
                    <=>
                    (int) $right[
                        '_distance'
                    ];

                if ($compare !== 0) {
                    return $compare;
                }

                return
                    (int) $left[
                        'team_id'
                    ]
                    <=>
                    (int) $right[
                        'team_id'
                    ];
            }
        );


        return $eligible[0];
    }


    private function ancestorDistances(
        int $projectId,
        int $startNodeId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    parent_node_id,
                    child_node_id

                FROM
                    ticketing_support_node_relations

                WHERE project_id = ?
                  AND status = 'active'
                  AND relation_type_code =
                      'hierarchy'
            ");

        $statement->execute([
            $projectId,
        ]);

        $parents = [];

        foreach (
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
            as $relation
        ) {
            $child =
                (int) $relation[
                    'child_node_id'
                ];

            $parent =
                (int) $relation[
                    'parent_node_id'
                ];

            $parents[$child][] =
                $parent;
        }


        $distance = [
            $startNodeId => 0,
        ];

        $queue = [
            $startNodeId,
        ];


        while ($queue !== []) {

            $current =
                array_shift($queue);

            $currentDistance =
                (int) $distance[
                    $current
                ];


            foreach (
                $parents[$current]
                ?? []
                as $parent
            ) {
                if (isset($distance[$parent])) {
                    continue;
                }

                $distance[$parent] =
                    $currentDistance + 1;

                $queue[] =
                    $parent;
            }
        }


        return $distance;
    }


    private function transferTargets(
        int $teamId,
        int $excludeProjectMemberId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    pm.id AS project_member_id,
                    pm.user_reference,
                    pm.display_name_snapshot,

                    tm.staff_role_code

                FROM
                    ticketing_support_team_members tm

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.id =
                        tm.project_member_id

                WHERE tm.team_id = ?
                  AND tm.status = 'active'
                  AND tm.left_at IS NULL

                  AND pm.left_at IS NULL
                  AND pm.role_code IN ('member', 'manager')

                  AND pm.user_reference
                        IS NOT NULL

                  AND pm.user_reference
                        <> ''

                  AND tm.staff_role_code
                        IN
                        (
                            'agent',
                            'supervisor',
                            'manager'
                        )

                ORDER BY
                    pm.display_name_snapshot,
                    pm.id
            ");

        $statement->execute([
            $teamId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];


        return
            array_values(
                array_filter(
                    $rows,
                    static function (
                        array $row
                    ) use (
                        $excludeProjectMemberId
                    ): bool {
                        return
                            (int) (
                                $row[
                                    'project_member_id'
                                ]
                                ?? 0
                            )
                            !==
                            $excludeProjectMemberId;
                    }
                )
            );
    }


    private function memberInTeam(
        int $teamId,
        int $projectMemberId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    pm.id AS project_member_id,
                    pm.user_reference,
                    pm.display_name_snapshot

                FROM
                    ticketing_support_team_members tm

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.id =
                        tm.project_member_id

                WHERE tm.team_id = ?
                  AND pm.id = ?

                  AND tm.status = 'active'
                  AND tm.left_at IS NULL

                  AND pm.left_at IS NULL
                  AND pm.role_code IN ('member', 'manager')

                  AND pm.user_reference
                        IS NOT NULL

                  AND pm.user_reference
                        <> ''

                  AND tm.staff_role_code
                        IN
                        (
                            'agent',
                            'supervisor',
                            'manager'
                        )

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


    private function nextEscalationRelation(
        int $projectId,
        int $currentNodeId
    ): ?array {
        if ($currentNodeId <= 0) {
            return null;
        }


        $statement =
            $this->db->prepare("
                SELECT
                    r.parent_node_id,
                    r.child_node_id,

                    parent.title
                        AS parent_title

                FROM
                    ticketing_support_node_relations r

                INNER JOIN
                    ticketing_support_nodes parent
                    ON parent.id =
                        r.parent_node_id

                WHERE r.project_id = ?
                  AND r.child_node_id = ?

                  AND r.status = 'active'
                  AND r.relation_type_code =
                        'hierarchy'

                  AND r.allow_escalation = 1

                  AND parent.status = 'active'

                ORDER BY
                    r.is_primary_path DESC,
                    r.sort_order,
                    r.id

                LIMIT 1
            ");

        $statement->execute([
            $projectId,
            $currentNodeId,
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


    private function routeForNode(
        int $projectId,
        int $nodeId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    n.id AS node_id,
                    n.layer_id,

                    q.id AS queue_id,
                    q.max_open_per_agent,

                    t.id AS team_id,
                    t.title AS team_title

                FROM
                    ticketing_support_nodes n

                INNER JOIN
                    ticketing_support_queues q
                    ON q.node_id = n.id
                   AND q.project_id =
                        n.project_id

                INNER JOIN
                    ticketing_support_team_queues tq
                    ON tq.queue_id = q.id
                   AND tq.status = 'active'

                INNER JOIN
                    ticketing_support_teams t
                    ON t.id = tq.team_id
                   AND t.project_id =
                        n.project_id

                WHERE n.project_id = ?
                  AND n.id = ?

                  AND n.status = 'active'
                  AND q.status = 'active'
                  AND t.status = 'active'

                ORDER BY
                    q.is_default DESC,
                    q.sort_order,
                    q.id,
                    t.sort_order,
                    t.id

                LIMIT 1
            ");

        $statement->execute([
            $projectId,
            $nodeId,
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


    private function leastLoadedMember(
        int $teamId,
        ?int $maxOpen
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    pm.id AS project_member_id,
                    pm.user_reference,
                    pm.display_name_snapshot,

                    tm.workload_weight,

                    (
                        SELECT COUNT(*)

                        FROM ticketing_assignments a

                        INNER JOIN
                            ticketing_tickets ot
                            ON ot.id =
                                a.ticket_id

                        INNER JOIN
                            ticketing_statuses os
                            ON os.code =
                                ot.status_code

                        WHERE
                            a.project_member_id =
                                pm.id

                            AND a.unassigned_at
                                IS NULL

                            AND ot.archived_at
                                IS NULL

                            AND os.is_closed = 0
                    ) AS open_ticket_count

                FROM
                    ticketing_support_team_members tm

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.id =
                        tm.project_member_id

                WHERE tm.team_id = ?

                  AND tm.status = 'active'
                  AND tm.left_at IS NULL

                  AND pm.left_at IS NULL
                  AND pm.role_code IN ('member', 'manager')

                  AND pm.user_reference
                        IS NOT NULL

                  AND pm.user_reference
                        <> ''

                  AND tm.staff_role_code
                        IN
                        (
                            'agent',
                            'supervisor',
                            'manager'
                        )

                ORDER BY
                    pm.id
            ");

        $statement->execute([
            $teamId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];


        $eligible = [];


        foreach ($rows as $row) {

            $open =
                (int) (
                    $row[
                        'open_ticket_count'
                    ]
                    ?? 0
                );


            if (
                $maxOpen !== null
                && $maxOpen > 0
                && $open >= $maxOpen
            ) {
                continue;
            }


            $weight =
                (float) (
                    $row[
                        'workload_weight'
                    ]
                    ?? 1
                );

            if ($weight <= 0) {
                $weight = 1.0;
            }


            $row['_score'] =
                $open / $weight;

            $eligible[] =
                $row;
        }


        if ($eligible === []) {
            return null;
        }


        usort(
            $eligible,
            static function (
                array $left,
                array $right
            ): int {
                $score =
                    (float) $left[
                        '_score'
                    ]
                    <=>
                    (float) $right[
                        '_score'
                    ];

                if ($score !== 0) {
                    return $score;
                }

                return
                    (int) $left[
                        'project_member_id'
                    ]
                    <=>
                    (int) $right[
                        'project_member_id'
                    ];
            }
        );


        return $eligible[0];
    }


    private function replaceAssignment(
        array $ticket,
        array $target,
        string $actorUserReference,
        string $mode,
        string $reason
    ): void {
        foreach ([
            'project_member_id',
            'user_reference',
            'display_name_snapshot',
            'layer_id',
            'node_id',
            'queue_id',
            'team_id',
        ] as $required) {
            if (
                !array_key_exists(
                    $required,
                    $target
                )
            ) {
                throw new DomainException(
                    'target_invalid'
                );
            }
        }


        $close =
            $this->db->prepare("
                UPDATE
                    ticketing_assignments

                SET
                    unassigned_at =
                        UTC_TIMESTAMP()

                WHERE ticket_id = ?
                  AND unassigned_at
                        IS NULL
            ");

        $close->execute([
            (int) $ticket['id'],
        ]);


        $update =
            $this->db->prepare("
                UPDATE ticketing_tickets

                SET
                    current_support_layer_id = ?,
                    current_support_node_id = ?,
                    current_support_queue_id = ?,
                    current_support_team_id = ?,

                    current_assignee_project_member_id = ?,

                    updated_by_user_reference = ?,

                    last_activity_at =
                        UTC_TIMESTAMP(),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?
            ");

        $update->execute([
            (int) $target['layer_id'],
            (int) $target['node_id'],
            (int) $target['queue_id'],
            (int) $target['team_id'],

            (int) $target[
                'project_member_id'
            ],

            $actorUserReference,

            (int) $ticket['id'],
        ]);


        $insert =
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
                    ?,
                    UTC_TIMESTAMP(),
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

        $insert->execute([
            (int) $ticket['id'],

            (string) $target[
                'user_reference'
            ],

            (string) $target[
                'display_name_snapshot'
            ],

            $actorUserReference,

            (int) $target[
                'project_member_id'
            ],

            (int) $target['node_id'],
            (int) $target['queue_id'],
            (int) $target['team_id'],

            $mode,
            $reason,
        ]);
    }


    private function recordEvent(
        int $ticketId,
        string $eventCode,
        string $actorUserReference,
        string $actorDisplayName,
        string $statusCode,
        array $payload
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO
                    ticketing_events
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
            $this->eventReference(),
            $ticketId,
            $eventCode,

            $actorUserReference,
            $actorDisplayName,

            $statusCode,
            $statusCode,

            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }


    private function eventReference(): string
    {
        return
            'TEV-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );
    }
}

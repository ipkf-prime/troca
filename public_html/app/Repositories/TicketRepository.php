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

        $q =
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            );

        $status =
            trim(
                (string) (
                    $filters['status']
                    ?? ''
                )
            );

        $priority =
            trim(
                (string) (
                    $filters['priority']
                    ?? ''
                )
            );

        $projectReference =
            trim(
                (string) (
                    $filters[
                        'project_reference'
                    ]
                    ?? ''
                )
            );

        $layerId =
            max(
                0,
                (int) (
                    $filters['layer_id']
                    ?? 0
                )
            );

        $assigneeId =
            max(
                0,
                (int) (
                    $filters['assignee_id']
                    ?? 0
                )
            );

        $requesterUserReference =
            trim(
                (string) (
                    $filters[
                        'requester_user_reference'
                    ]
                    ?? ''
                )
            );

        $viewerUserReference =
            trim(
                (string) (
                    $filters[
                        'viewer_user_reference'
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
                OR sp.title LIKE ?
                OR tp.title LIKE ?
                OR sl.title LIKE ?
                OR st.title LIKE ?
                OR apm.display_name_snapshot LIKE ?
            )";

            $needle =
                '%'
                . $q
                . '%';

            for ($i = 0; $i < 11; $i++) {
                $parameters[] =
                    $needle;
            }
        }


        if ($status !== '') {
            $where[] =
                't.status_code = ?';

            $parameters[] =
                $status;
        }


        if ($priority !== '') {
            $where[] =
                't.priority_code = ?';

            $parameters[] =
                $priority;
        }


        if ($projectReference !== '') {
            $where[] =
                'sp.public_reference = ?';

            $parameters[] =
                $projectReference;
        }


        if ($layerId > 0) {
            $where[] =
                't.current_support_layer_id = ?';

            $parameters[] =
                $layerId;
        }


        if ($assigneeId > 0) {
            $where[] =
                't.current_assignee_project_member_id = ?';

            $parameters[] =
                $assigneeId;
        }


        if ($requesterUserReference !== '') {
            $where[] =
                't.requester_user_reference = ?';

            $parameters[] =
                $requesterUserReference;
        }


        if ($viewerUserReference !== '') {
            $where[] = "(
                t.requester_user_reference = ?

                OR EXISTS
                (
                    SELECT 1
                    FROM ticketing_assignments va
                    WHERE va.ticket_id = t.id
                      AND va.assignee_kind = 'user'
                      AND va.assignee_reference = ?
                      AND va.unassigned_at IS NULL
                )
            )";

            $parameters[] =
                $viewerUserReference;

            $parameters[] =
                $viewerUserReference;
        }


        $sortMap = [
            'last_activity' =>
                't.last_activity_at',

            'created_at' =>
                't.created_at',

            'priority' =>
                'p.severity',

            'status' =>
                't.status_code',

            'project' =>
                'sp.title',

            'stage' =>
                'sl.rank_order',

            'assignee' =>
                'apm.display_name_snapshot',

            'subject' =>
                't.subject',

            'id' =>
                't.id',
        ];


        $sort1 =
            trim(
                (string) (
                    $filters['sort1']
                    ?? 'last_activity'
                )
            );

        $sort2 =
            trim(
                (string) (
                    $filters['sort2']
                    ?? 'created_at'
                )
            );


        if (!isset($sortMap[$sort1])) {
            $sort1 =
                'last_activity';
        }

        if (!isset($sortMap[$sort2])) {
            $sort2 =
                'created_at';
        }

        if ($sort2 === $sort1) {
            $sort2 =
                $sort1 === 'created_at'
                    ? 'id'
                    : 'created_at';
        }


        $dir1 =
            strtolower(
                trim(
                    (string) (
                        $filters['dir1']
                        ?? 'desc'
                    )
                )
            );

        $dir2 =
            strtolower(
                trim(
                    (string) (
                        $filters['dir2']
                        ?? 'desc'
                    )
                )
            );

        if (!in_array(
            $dir1,
            ['asc', 'desc'],
            true
        )) {
            $dir1 = 'desc';
        }

        if (!in_array(
            $dir2,
            ['asc', 'desc'],
            true
        )) {
            $dir2 = 'desc';
        }


        $limit =
            max(
                1,
                min(
                    500,
                    (int) (
                        $filters['limit']
                        ?? 200
                    )
                )
            );


        $order1 =
            $sortMap[$sort1];

        $order2 =
            $sortMap[$sort2];


        $statement =
            $this->db->prepare("
                SELECT
                    t.id,
                    t.public_reference,
                t.ticket_number,
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

                    t.support_project_id,

                    COALESCE(
                        sp.title,
                        t.support_project_title_snapshot
                    ) AS project_title,

                    sp.public_reference
                        AS project_reference,

                    sp.code
                        AS project_code,

                    t.support_service_id,

                    COALESCE(
                        ss.title,
                        t.support_service_title_snapshot
                    ) AS service_title,

                    t.support_topic_id,

                    COALESCE(
                        tp.title,
                        t.support_topic_title_snapshot
                    ) AS topic_title,

                    t.current_support_layer_id,
                    sl.title AS layer_title,
                    sl.rank_order AS layer_rank,

                    t.current_support_node_id,
                    sn.title AS node_title,

                    t.current_support_queue_id,
                    sq.title AS queue_title,

                    t.current_support_team_id,
                    st.title AS team_title,

                    t.current_assignee_project_member_id,

                    apm.user_reference
                        AS assignee_user_reference,

                    apm.display_name_snapshot
                        AS assignee_name,

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

                LEFT JOIN ticketing_support_projects sp
                    ON sp.id =
                        t.support_project_id

                LEFT JOIN ticketing_support_services ss
                    ON ss.id =
                        t.support_service_id

                LEFT JOIN ticketing_support_topics tp
                    ON tp.id =
                        t.support_topic_id

                LEFT JOIN ticketing_support_layers sl
                    ON sl.id =
                        t.current_support_layer_id

                LEFT JOIN ticketing_support_nodes sn
                    ON sn.id =
                        t.current_support_node_id

                LEFT JOIN ticketing_support_queues sq
                    ON sq.id =
                        t.current_support_queue_id

                LEFT JOIN ticketing_support_teams st
                    ON st.id =
                        t.current_support_team_id

                LEFT JOIN ticketing_support_project_members apm
                    ON apm.id =
                        t.current_assignee_project_member_id

                WHERE "
                . implode(
                    ' AND ',
                    $where
                )
                . "

                ORDER BY
                    {$order1} {$dir1},
                    {$order2} {$dir2},
                    t.id DESC

                LIMIT {$limit}
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


    public function viewerProjectTabs(
        string $viewerUserReference
    ): array {
        $viewerUserReference =
            trim(
                $viewerUserReference
            );

        if ($viewerUserReference === '') {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    sp.id,
                    sp.public_reference,
                    sp.code,
                    sp.title,

                    COUNT(
                        DISTINCT t.id
                    ) AS ticket_count,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN
                                    t.id IS NOT NULL
                                    AND ts.is_closed = 0
                                THEN 1
                                ELSE 0
                            END
                        ),
                        0
                    ) AS open_ticket_count,

                    MAX(
                        t.last_activity_at
                    ) AS last_ticket_activity

                FROM
                (
                    SELECT DISTINCT
                        project_id
                    FROM
                        ticketing_support_project_members
                    WHERE user_reference = ?
                      AND left_at IS NULL
                ) membership

                INNER JOIN ticketing_support_projects sp
                    ON sp.id =
                        membership.project_id

                   AND sp.is_active = 1
                   AND sp.archived_at IS NULL

                LEFT JOIN ticketing_tickets t
                    ON t.support_project_id =
                        sp.id

                   AND t.archived_at IS NULL

                   AND
                   (
                        t.requester_user_reference = ?

                        OR EXISTS
                        (
                            SELECT 1

                            FROM
                                ticketing_assignments a

                            WHERE a.ticket_id = t.id

                              AND a.assignee_kind =
                                    'user'

                              AND a.assignee_reference = ?

                              AND a.unassigned_at
                                    IS NULL
                        )
                   )

                LEFT JOIN ticketing_statuses ts
                    ON ts.code =
                        t.status_code

                GROUP BY
                    sp.id,
                    sp.public_reference,
                    sp.code,
                    sp.title

                ORDER BY

                    CASE
                        WHEN
                            MAX(
                                t.last_activity_at
                            ) IS NULL
                        THEN 1
                        ELSE 0
                    END,

                    MAX(
                        t.last_activity_at
                    ) DESC,

                    sp.title,
                    sp.id
            ");

        $statement->execute([
            $viewerUserReference,
            $viewerUserReference,
            $viewerUserReference,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    public function viewerLayers(
        string $viewerUserReference
    ): array {
        $statement =
            $this->db->prepare("
                SELECT DISTINCT
                    sl.id,
                    sl.title,
                    sl.rank_order

                FROM ticketing_tickets t

                INNER JOIN ticketing_support_layers sl
                    ON sl.id =
                        t.current_support_layer_id

                WHERE t.archived_at IS NULL

                  AND
                  (
                        t.requester_user_reference = ?

                        OR EXISTS
                        (
                            SELECT 1

                            FROM ticketing_assignments a

                            WHERE a.ticket_id = t.id

                              AND a.assignee_kind =
                                    'user'

                              AND a.assignee_reference = ?

                              AND a.unassigned_at
                                    IS NULL
                        )
                  )

                ORDER BY
                    sl.rank_order,
                    sl.title,
                    sl.id
            ");

        $viewer =
            trim(
                $viewerUserReference
            );

        $statement->execute([
            $viewer,
            $viewer,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    public function viewerAssignees(
        string $viewerUserReference
    ): array {
        $statement =
            $this->db->prepare("
                SELECT DISTINCT
                    pm.id,
                    pm.display_name_snapshot

                FROM ticketing_tickets t

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.id =
                        t.current_assignee_project_member_id

                WHERE t.archived_at IS NULL

                  AND
                  (
                        t.requester_user_reference = ?

                        OR EXISTS
                        (
                            SELECT 1

                            FROM ticketing_assignments a

                            WHERE a.ticket_id = t.id

                              AND a.assignee_kind =
                                    'user'

                              AND a.assignee_reference = ?

                              AND a.unassigned_at
                                    IS NULL
                        )
                  )

                ORDER BY
                    pm.display_name_snapshot,
                    pm.id
            ");

        $viewer =
            trim(
                $viewerUserReference
            );

        $statement->execute([
            $viewer,
            $viewer,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    public function findByReference(
        string $publicReference,
        ?string $viewerUserReference = null
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    t.id,
                    t.public_reference,
                t.ticket_number,
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

                    t.support_project_id,

                    COALESCE(
                        sp.title,
                        t.support_project_title_snapshot
                    ) AS project_title,

                    sp.public_reference
                        AS project_reference,

                    sp.code
                        AS project_code,

                    t.support_service_id,

                    COALESCE(
                        ss.title,
                        t.support_service_title_snapshot
                    ) AS service_title,

                    t.support_topic_id,

                    COALESCE(
                        tp.title,
                        t.support_topic_title_snapshot
                    ) AS topic_title,

                    t.current_support_layer_id,
                    sl.title AS layer_title,
                    sl.rank_order AS layer_rank,

                    t.current_support_node_id,
                    sn.title AS node_title,

                    t.current_support_queue_id,
                    sq.title AS queue_title,

                    t.current_support_team_id,
                    st.title AS team_title,

                    t.current_assignee_project_member_id,

                    apm.user_reference
                        AS assignee_user_reference,

                    apm.display_name_snapshot
                        AS assignee_name,

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
                    ON s.code =
                        t.status_code

                INNER JOIN ticketing_priorities p
                    ON p.code =
                        t.priority_code

                LEFT JOIN ticketing_categories c
                    ON c.id =
                        t.category_id

                LEFT JOIN ticketing_support_projects sp
                    ON sp.id =
                        t.support_project_id

                LEFT JOIN ticketing_support_services ss
                    ON ss.id =
                        t.support_service_id

                LEFT JOIN ticketing_support_topics tp
                    ON tp.id =
                        t.support_topic_id

                LEFT JOIN ticketing_support_layers sl
                    ON sl.id =
                        t.current_support_layer_id

                LEFT JOIN ticketing_support_nodes sn
                    ON sn.id =
                        t.current_support_node_id

                LEFT JOIN ticketing_support_queues sq
                    ON sq.id =
                        t.current_support_queue_id

                LEFT JOIN ticketing_support_teams st
                    ON st.id =
                        t.current_support_team_id

                LEFT JOIN
                    ticketing_support_project_members apm
                    ON apm.id =
                        t.current_assignee_project_member_id

                WHERE t.public_reference = ?

                  AND
                  (
                        ? IS NULL

                        OR t.requester_user_reference = ?

                        OR EXISTS
                        (
                            SELECT 1

                            FROM ticketing_assignments va

                            WHERE va.ticket_id = t.id

                              AND va.assignee_kind =
                                    'user'

                              AND va.assignee_reference = ?

                              AND va.unassigned_at
                                    IS NULL
                        )
                  )

                LIMIT 1
            ");

        $viewer =
            $viewerUserReference === null
            || trim(
                $viewerUserReference
            ) === ''
                ? null
                : trim(
                    $viewerUserReference
                );

        $statement->execute([
            trim(
                $publicReference
            ),
            $viewer,
            $viewer,
            $viewer,
        ]);

        $ticket =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            $ticket
                ?: null;
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

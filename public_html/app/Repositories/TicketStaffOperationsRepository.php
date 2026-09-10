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
/*
 * TICKETING_STRICT_OPERATIONAL_REALM_STAFF_V1
 *
 * Normal Takeover / Transfer / Escalation operations are Realm-local.
 * Cross-Realm movement belongs to the explicit Handoff contract.
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
        string $scope = 'all',
        ?string $publicReference = null
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

        $publicReference =
            $publicReference === null
                ? ''
                : trim(
                    $publicReference
                );

        if ($publicReference !== '') {
            $where[] =
                't.public_reference = ?';

            $parameters[] =
                $publicReference;
        }


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


        /*
         * Dynamic Data Scope is an intersection with the existing
         * operational A5G visibility. Assignment never bypasses it.
         */
        $where[] =
            $this->dataScopeClause(
                $userReference,
                $parameters
            );

        $limit =
            $publicReference !== ''
                ? 1
                : 200;


        $statement =
            $this->db->prepare("
                SELECT
                    t.id,
                    t.public_reference,
                    t.ticket_number,

                    t.support_project_id,
                    t.support_project_title_snapshot,
                    p.title AS project_title,

                    t.support_topic_id,
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


    /*
     * =========================================================================
     * TICKETING_T1_SERVER_SIDE_CARTABLE_V1
     *
     * The operational cartable is intentionally separate from legacy
     * cartable(), because cartable() is also used as an authorization/
     * detail-visibility primitive and must continue to see closed tickets.
     *
     * List concerns:
     * - canonical staff visibility
     * - Dynamic Data Scope
     * - server-side search/filter
     * - deterministic sorting
     * - COUNT + LIMIT/OFFSET pagination
     * =========================================================================
     */
    public function cartablePage(
        string $userReference,
        array $filters = []
    ): array {
        $perPage =
            (int) (
                $filters['per_page']
                ?? 25
            );

        if (
            !in_array(
                $perPage,
                [
                    25,
                    50,
                ],
                true
            )
        ) {
            $perPage = 25;
        }


        $requestedPage =
            max(
                1,
                (int) (
                    $filters['page']
                    ?? 1
                )
            );


        $context =
            $this->cartableListContext(
                $userReference,
                $filters
            );


        if (empty($context['available'])) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 1,
            ];
        }


        $total =
            $this->cartableCountFromContext(
                $context
            );

        $totalPages =
            max(
                1,
                (int) ceil(
                    $total
                    / $perPage
                )
            );

        $page =
            min(
                $requestedPage,
                $totalPages
            );

        $offset =
            ($page - 1)
            * $perPage;


        $orderBy =
            $this->cartableListOrderBy(
                (string) (
                    $filters['sort']
                    ?? 'priority_desc'
                )
            );


        $sql =
            "
                SELECT
                    t.id,
                    t.public_reference,
                    t.ticket_number,

                    t.support_project_id,
                    t.support_project_title_snapshot,
                    p.title AS project_title,

                    t.support_topic_id,
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

                "
                . $this->cartableListFromSql()
                . "

                WHERE
                    "
                . implode(
                    ' AND ',
                    $context['where']
                )
                . "

                ORDER BY
                    "
                . $orderBy
                . "

                LIMIT "
                . $perPage
                . "
                OFFSET "
                . $offset;


        $statement =
            $this->db->prepare(
                $sql
            );

        $statement->execute(
            $context['parameters']
        );


        return [
            'items' =>
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: [],

            'total' =>
                $total,

            'page' =>
                $page,

            'per_page' =>
                $perPage,

            'total_pages' =>
                $totalPages,
        ];
    }


    public function cartableCount(
        string $userReference,
        array $filters = []
    ): int {
        $context =
            $this->cartableListContext(
                $userReference,
                $filters
            );

        if (empty($context['available'])) {
            return 0;
        }

        return
            $this->cartableCountFromContext(
                $context
            );
    }


    public function cartableFilterOptions(
        string $userReference
    ): array {
        $statuses =
            $this->db->query("
                SELECT
                    code,
                    title,
                    category,
                    is_closed,
                    sort_order

                FROM
                    ticketing_statuses

                WHERE
                    is_active = 1

                ORDER BY
                    sort_order,
                    id
            ")->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];


        $priorities =
            $this->db->query("
                SELECT
                    code,
                    title,
                    severity,
                    sort_order

                FROM
                    ticketing_priorities

                WHERE
                    is_active = 1

                ORDER BY
                    severity DESC,
                    sort_order DESC,
                    id DESC
            ")->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];


        $layers =
            $this->db->query("
                SELECT
                    id,
                    code,
                    title

                FROM
                    ticketing_support_layers

                ORDER BY
                    id
            ")->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];


        /*
         * Assignee options are visibility-scoped. Do not expose staff
         * identities that the current operator cannot encounter in the
         * canonical all-scope cartable.
         */
        $assigneeContext =
            $this->cartableListContext(
                $userReference,
                [
                    'scope' => 'all',
                    'ticket_status' => 'all',
                    'priority' => '',
                    'topic_id' => 0,
                    'layer_id' => 0,
                    'assignee' => '',
                    'q' => '',
                ]
            );

        $assignees = [];

        if (!empty($assigneeContext['available'])) {

            $statement =
                $this->db->prepare(
                    "
                        SELECT DISTINCT
                            assignee.user_reference,
                            assignee.display_name_snapshot
                                AS display_name

                        "
                        . $this->cartableListFromSql()
                        . "

                        WHERE
                            "
                        . implode(
                            ' AND ',
                            $assigneeContext['where']
                        )
                        . "
                            AND assignee.user_reference
                                IS NOT NULL

                        ORDER BY
                            assignee.display_name_snapshot,
                            assignee.user_reference
                    "
                );

            $statement->execute(
                $assigneeContext[
                    'parameters'
                ]
            );

            $assignees =
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: [];
        }


        /*
         * TICKETING_STAFF_TOPIC_FILTER_OPTIONS_T3G
         *
         * Topics are derived from the canonical Staff cartable visibility
         * context. Data Scope therefore remains the option-source boundary.
         */
        $topicContext =
            $this->cartableListContext(
                $userReference,
                [
                    'scope' =>
                        'all',

                    'ticket_status' =>
                        'all',

                    'priority' =>
                        '',

                    'topic_id' =>
                        0,

                    'layer_id' =>
                        0,

                    'assignee' =>
                        '',

                    'q' =>
                        '',
                ]
            );

        $topics = [];

        if (
            !empty(
                $topicContext[
                    'available'
                ]
            )
        ) {
            $statement =
                $this->db->prepare(
                    "
                        SELECT DISTINCT
                            t.support_topic_id
                                AS id,

                            COALESCE(
                                NULLIF(
                                    t.support_topic_title_snapshot,
                                    ''
                                ),
                                NULLIF(
                                    topic_option.title,
                                    ''
                                ),
                                CONCAT(
                                    'موضوع #',
                                    t.support_topic_id
                                )
                            ) AS title,

                            t.support_project_id
                                AS project_id,

                            COALESCE(
                                NULLIF(
                                    p.title,
                                    ''
                                ),
                                t.support_project_title_snapshot,
                                ''
                            ) AS project_title

                        "
                        . $this->cartableListFromSql()
                        . "

                        LEFT JOIN
                            ticketing_support_topics topic_option
                            ON topic_option.id =
                                t.support_topic_id

                        WHERE
                            "
                        . implode(
                            ' AND ',
                            $topicContext[
                                'where'
                            ]
                        )
                        . "

                            AND t.support_topic_id
                                IS NOT NULL

                            AND t.support_topic_id > 0

                        ORDER BY
                            project_title,
                            title,
                            id
                    "
                );

            $statement->execute(
                $topicContext[
                    'parameters'
                ]
            );

            $topics =
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: [];
        }

        return [
            'statuses' =>
                $statuses,

            'priorities' =>
                $priorities,

            'topics' =>
                $topics,

            'layers' =>
                $layers,

            'assignees' =>
                $assignees,
        ];
    }


    private function cartableListContext(
        string $userReference,
        array $filters
    ): array {
        $userReference =
            trim(
                $userReference
            );

        if ($userReference === '') {
            return [
                'available' => false,
                'where' => [],
                'parameters' => [],
            ];
        }


        $memberships =
            $this->actorMemberships(
                $userReference
            );

        if ($memberships === []) {
            return [
                'available' => false,
                'where' => [],
                'parameters' => [],
            ];
        }


        $memberIds = [];

        foreach (
            $memberships
            as $membership
        ) {
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
            trim(
                (string) (
                    $filters['scope']
                    ?? 'all'
                )
            );

        if (
            !in_array(
                $scope,
                [
                    'all',
                    'my',
                    'unassigned',
                ],
                true
            )
        ) {
            $scope = 'all';
        }


        $where = [
            't.archived_at IS NULL',
        ];

        $parameters = [];


        /*
         * Keep this visibility contract byte-for-byte equivalent in
         * meaning to the legacy cartable() implementation.
         */
        if ($scope === 'my') {

            if ($memberIds === []) {
                return [
                    'available' => false,
                    'where' => [],
                    'parameters' => [],
                ];
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
                array_keys(
                    $memberIds
                )
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
                return [
                    'available' => false,
                    'where' => [],
                    'parameters' => [],
                ];
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
                    array_keys(
                        $memberIds
                    )
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
                return [
                    'available' => false,
                    'where' => [],
                    'parameters' => [],
                ];
            }


            $where[] =
                '('
                . implode(
                    ' OR ',
                    $access
                )
                . ')';
        }


        /*
         * Dynamic Data Scope remains an intersection. Being assigned
         * to a ticket never bypasses Data Scope.
         */
        $where[] =
            $this->dataScopeClause(
                $userReference,
                $parameters
            );


        /*
         * The default operational cartable contains all non-closed
         * workflow states, not a hardcoded list of status codes.
         */
        $ticketStatus =
            trim(
                (string) (
                    $filters[
                        'ticket_status'
                    ]
                    ?? 'active'
                )
            );

        if ($ticketStatus === '') {
            $ticketStatus =
                'active';
        }


        if ($ticketStatus === 'active') {

            $where[] =
                's.is_closed = 0';

        } elseif (
            $ticketStatus !== 'all'
        ) {

            $where[] =
                't.status_code = ?';

            $parameters[] =
                $ticketStatus;
        }


        $priority =
            trim(
                (string) (
                    $filters[
                        'priority'
                    ]
                    ?? ''
                )
            );

        if ($priority !== '') {
            $where[] =
                't.priority_code = ?';

            $parameters[] =
                $priority;
        }


        /*
         * TICKETING_STAFF_TOPIC_FILTER_T3G
         */
        $topicId =
            max(
                0,
                (int) (
                    $filters[
                        'topic_id'
                    ]
                    ?? 0
                )
            );

        if ($topicId > 0) {
            $where[] =
                't.support_topic_id = ?';

            $parameters[] =
                $topicId;
        }


        $layerId =
            max(
                0,
                (int) (
                    $filters[
                        'layer_id'
                    ]
                    ?? 0
                )
            );

        if ($layerId > 0) {
            $where[] =
                't.current_support_layer_id = ?';

            $parameters[] =
                $layerId;
        }


        $assignee =
            trim(
                (string) (
                    $filters[
                        'assignee'
                    ]
                    ?? ''
                )
            );

        if (
            $scope !== 'unassigned'
            &&
            $assignee !== ''
        ) {
            $where[] =
                'assignee.user_reference = ?';

            $parameters[] =
                $assignee;
        }


        $query =
            trim(
                (string) (
                    $filters[
                        'q'
                    ]
                    ?? ''
                )
            );

        if ($query !== '') {

            /*
             * Escape SQL LIKE wildcard characters so free-text search
             * remains literal and deterministic.
             */
            $escapedQuery =
                str_replace(
                    [
                        '\\',
                        '%',
                        '_',
                    ],
                    [
                        '\\\\',
                        '\\%',
                        '\\_',
                    ],
                    $query
                );

            $like =
                '%'
                . $escapedQuery
                . '%';


            /*
             * TICKETING_CARTABLE_UTF8_SEARCH_COLLATION_T3E
             *
             * Some identifier columns intentionally use ascii_bin while
             * descriptive text uses utf8mb4. A Persian search parameter
             * therefore cannot safely participate in one mixed-collation
             * LIKE expression without an explicit common character set.
             *
             * This is query-local normalization only:
             * - no schema/collation migration
             * - no identifier storage change
             * - no Data Scope or visibility change
             */
            $search = [
                "CONVERT(
                    t.ticket_number
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci
                    LIKE
                CONVERT(? USING utf8mb4)
                    COLLATE utf8mb4_unicode_ci",

                "CONVERT(
                    t.subject
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci
                    LIKE
                CONVERT(? USING utf8mb4)
                    COLLATE utf8mb4_unicode_ci",

                "CONVERT(
                    t.support_topic_title_snapshot
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci
                    LIKE
                CONVERT(? USING utf8mb4)
                    COLLATE utf8mb4_unicode_ci",

                "CONVERT(
                    t.support_project_title_snapshot
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci
                    LIKE
                CONVERT(? USING utf8mb4)
                    COLLATE utf8mb4_unicode_ci",

                "CONVERT(
                    p.title
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci
                    LIKE
                CONVERT(? USING utf8mb4)
                    COLLATE utf8mb4_unicode_ci",

                "CONVERT(
                    assignee.display_name_snapshot
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci
                    LIKE
                CONVERT(? USING utf8mb4)
                    COLLATE utf8mb4_unicode_ci",

                "CONVERT(
                    t.requester_display_name_snapshot
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci
                    LIKE
                CONVERT(? USING utf8mb4)
                    COLLATE utf8mb4_unicode_ci",

                "CONVERT(
                    t.requester_organization_snapshot
                    USING utf8mb4
                ) COLLATE utf8mb4_unicode_ci
                    LIKE
                CONVERT(? USING utf8mb4)
                    COLLATE utf8mb4_unicode_ci",
            ];

            $searchParameters =
                array_fill(
                    0,
                    count($search),
                    $like
                );


            /*
             * Preserve the former convenience where entering just the
             * numeric sequence can find e.g. NP-000016 by "16".
             */
            if (
                preg_match(
                    '/^0*(\d{1,18})$/',
                    $query,
                    $match
                ) === 1
            ) {
                array_unshift(
                    $search,
                    "CAST(
                        SUBSTRING_INDEX(
                            t.ticket_number,
                            '-',
                            -1
                        )
                        AS UNSIGNED
                    ) = ?"
                );

                array_unshift(
                    $searchParameters,
                    (int) $match[1]
                );
            }


            $where[] =
                '('
                . implode(
                    ' OR ',
                    $search
                )
                . ')';

            foreach (
                $searchParameters
                as $parameter
            ) {
                $parameters[] =
                    $parameter;
            }
        }


        return [
            'available' => true,
            'where' => $where,
            'parameters' => $parameters,
        ];
    }


    private function cartableCountFromContext(
        array $context
    ): int {
        $statement =
            $this->db->prepare(
                "
                    SELECT
                        COUNT(*)

                    "
                    . $this->cartableListFromSql()
                    . "

                    WHERE
                        "
                    . implode(
                        ' AND ',
                        $context['where']
                    )
            );

        $statement->execute(
            $context['parameters']
        );

        return
            (int) $statement->fetchColumn();
    }


    private function cartableListFromSql(): string
    {
        return
            "
                FROM
                    ticketing_tickets t

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
            ";
    }


    private function cartableListOrderBy(
        string $sort
    ): string {
        $sort =
            trim(
                $sort
            );

        $orders = [
            'priority_desc' =>
                'pr.severity DESC, '
                . 't.last_activity_at DESC, '
                . 't.id DESC',

            'activity_desc' =>
                't.last_activity_at DESC, '
                . 't.id DESC',

            'activity_asc' =>
                't.last_activity_at ASC, '
                . 't.id ASC',

            'created_desc' =>
                't.created_at DESC, '
                . 't.id DESC',

            'created_asc' =>
                't.created_at ASC, '
                . 't.id ASC',
        ];

        return
            $orders[$sort]
            ?? $orders[
                'priority_desc'
            ];
    }



    /*
     * TICKETING_STAFF_DASHBOARD_STATUS_COUNTS_V1
     *
     * Aggregate directly in the database.
     * Visibility must match canonical staff/all scope and
     * must not inherit cartable() LIMIT 200.
     */
    public function dashboardStatusCounts(
        string $userReference
    ): array {
        $userReference =
            trim($userReference);

        if ($userReference === '') {
            return [];
        }


        $memberships =
            $this->actorMemberships(
                $userReference
            );

        if ($memberships === []) {
            return [];
        }


        $memberIds = [];

        foreach (
            $memberships
            as $membership
        ) {
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


        $where = [
            't.archived_at IS NULL',
        ];

        $parameters = [];
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


        /*
         * KPI aggregation must use the exact same Dynamic Data Scope
         * as staff cartable visibility.
         */
        $where[] =
            $this->dataScopeClause(
                $userReference,
                $parameters
            );


        $statement =
            $this->db->prepare("
                SELECT
                    t.status_code,
                    COUNT(*) AS ticket_count

                FROM ticketing_tickets t

                INNER JOIN ticketing_statuses s
                    ON s.code =
                        t.status_code

                WHERE
                    " . implode(
                        ' AND ',
                        $where
                    ) . "

                GROUP BY
                    t.status_code
            ");

        $statement->execute(
            $parameters
        );


        $counts = [];

        foreach (
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: []
            as $row
        ) {
            $code =
                trim(
                    (string) (
                        $row[
                            'status_code'
                        ]
                        ?? ''
                    )
                );

            if ($code === '') {
                continue;
            }

            $counts[$code] =
                (int) (
                    $row[
                        'ticket_count'
                    ]
                    ?? 0
                );
        }


        return $counts;
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


        /*
         * TICKETING_STAFF_DATA_SCOPE_ACTION_CONTEXT_GUARD_V1
         */
        if (
            !$this->staffCanViewTicket(
                (string) (
                    $ticket[
                        'public_reference'
                    ]
                    ?? ''
                ),
                $userReference
            )
        ) {
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


        /*
         * TICKETING_OPERATION_PERMISSION_SPLIT_V1
         *
         * can_assign:
         *     reassign inside the current support Team.
         *
         * can_transfer:
         *     move the ticket through the support topology,
         *     currently only by direct-parent escalation.
         */
        $canTransfer =
            is_array($currentMembership)
            && !empty(
                $currentMembership[
                    'can_assign'
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
            && !empty(
                $currentMembership[
                    'can_transfer'
                ]
            );

        if ($canEscalate) {
            $escalation =
                $this->nextEscalationRelation(
                    (int) $ticket[
                        'support_project_id'
                    ],
                    (int) (
                        $ticket[
                            'realm_id'
                        ]
                        ?? 0
                    ),
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

            /*
             * TICKETING_STAFF_DATA_SCOPE_MUTATION_GUARD_V1
             */
            $this->assertStaffDataScopeVisible(
                $ticket,
                $actorUserReference
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

            /*
             * TICKETING_STAFF_DATA_SCOPE_MUTATION_GUARD_V1
             */
            $this->assertStaffDataScopeVisible(
                $ticket,
                $actorUserReference
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
                        'can_assign'
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

                /*
                 * Manual staff escalation is subject to the same
                 * canonical Dynamic Data Scope.
                 *
                 * escalateSystem() deliberately enters this method
                 * with authorizeActor=false and remains governed by
                 * the separate SLA/System contract.
                 */
                $this->assertStaffDataScopeVisible(
                    $ticket,
                    $actorUserReference
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


            }


            $relation =
                $this->nextEscalationRelation(
                    (int) $ticket[
                        'support_project_id'
                    ],
                    (int) (
                        $ticket[
                            'realm_id'
                        ]
                        ?? 0
                    ),
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
                    (int) (
                        $ticket[
                            'realm_id'
                        ]
                        ?? 0
                    ),
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


    /*
     * TICKETING_CANONICAL_STAFF_DATA_SCOPE_SQL_V1
     *
     * Compatibility:
     *   A project with neither an active Scope Dimension nor an
     *   active/effective staff Grant keeps the pre-A5L5 behaviour.
     *
     * Enforcement:
     *   As soon as a Dimension or Grant becomes active, staff data
     *   access becomes fail-closed and requires one matching Grant.
     *
     * Composition:
     *   Grant OR Grant.
     *   Rules inside one Grant use AND.
     *   Rule values use ANY / ALL.
     *   Descendant matching uses the normalized closure table.
     *
     * Explicit unrestricted:
     *   Full project data scope is never inferred from manager role.
     *   It requires is_unrestricted=1 on a Grant with no active
     *   restrictions.
     */
    private function dataScopeClause(
        string $userReference,
        array &$parameters
    ): string {
        $userReference =
            trim(
                $userReference
            );

        if ($userReference === '') {
            return '(1 = 0)';
        }

        /*
         * Exactly one viewer parameter is consumed by the correlated
         * matching-Grant EXISTS below.
         */
        $parameters[] =
            $userReference;

        return <<<'SQL'
(
    /*
     * Legacy compatibility is allowed only while the project has
     * absolutely no active Dynamic Data Scope configuration.
     */
    (
        NOT EXISTS
        (
            SELECT 1

            FROM ticketing_scope_dimensions cfg_dimension

            WHERE cfg_dimension.project_id =
                    t.support_project_id

              AND cfg_dimension.status =
                    'active'
        )

        AND

        NOT EXISTS
        (
            SELECT 1

            FROM ticketing_access_grants cfg_grant

            INNER JOIN
                ticketing_support_project_members cfg_member
                ON cfg_member.id =
                    cfg_grant.project_member_id

            WHERE cfg_member.project_id =
                    t.support_project_id

              AND cfg_member.left_at
                    IS NULL

              AND cfg_member.role_code
                    IN ('member', 'manager')

              AND cfg_grant.status =
                    'active'

              AND
              (
                    cfg_grant.valid_from IS NULL
                    OR cfg_grant.valid_from <=
                        UTC_TIMESTAMP()
              )

              AND
              (
                    cfg_grant.valid_until IS NULL
                    OR cfg_grant.valid_until >
                        UTC_TIMESTAMP()
              )
        )
    )

    OR

    /*
     * Scope-enabled project:
     * one complete active Grant must match.
     */
    EXISTS
    (
        SELECT 1

        FROM
            ticketing_support_project_members scope_member

        INNER JOIN
            ticketing_access_grants scope_grant
            ON scope_grant.project_member_id =
                scope_member.id

        WHERE scope_member.project_id =
                t.support_project_id

          AND scope_member.user_reference = ?

          AND scope_member.left_at
                IS NULL

          AND scope_member.role_code
                IN ('member', 'manager')

          AND scope_grant.status =
                'active'

          AND
          (
                scope_grant.valid_from IS NULL
                OR scope_grant.valid_from <=
                    UTC_TIMESTAMP()
          )

          AND
          (
                scope_grant.valid_until IS NULL
                OR scope_grant.valid_until >
                    UTC_TIMESTAMP()
          )

          AND
          (
                /*
                 * Explicit unrestricted Grant.
                 *
                 * An unrestricted flag does not override an active
                 * malformed/restricted rule. This mirrors the pure
                 * TicketingAccessGrantEvaluator contract.
                 */
                (
                    scope_grant.is_unrestricted = 1

                    AND NOT EXISTS
                    (
                        SELECT 1

                        FROM
                            ticketing_access_grant_dimension_rules
                                unrestricted_dimension_rule

                        WHERE unrestricted_dimension_rule
                                .access_grant_id =
                                    scope_grant.id

                          AND unrestricted_dimension_rule
                                .status =
                                    'active'
                    )

                    AND NOT EXISTS
                    (
                        SELECT 1

                        FROM
                            ticketing_access_grant_resource_rules
                                unrestricted_resource_rule

                        WHERE unrestricted_resource_rule
                                .access_grant_id =
                                    scope_grant.id

                          AND unrestricted_resource_rule
                                .status =
                                    'active'
                    )
                )

                OR

                /*
                 * Restricted Grant.
                 *
                 * At least one active rule must exist.
                 */
                (
                    (
                        EXISTS
                        (
                            SELECT 1

                            FROM
                                ticketing_access_grant_dimension_rules
                                    any_dimension_rule

                            WHERE any_dimension_rule
                                    .access_grant_id =
                                        scope_grant.id

                              AND any_dimension_rule
                                    .status =
                                        'active'
                        )

                        OR

                        EXISTS
                        (
                            SELECT 1

                            FROM
                                ticketing_access_grant_resource_rules
                                    any_resource_rule

                            WHERE any_resource_rule
                                    .access_grant_id =
                                        scope_grant.id

                              AND any_resource_rule
                                    .status =
                                        'active'
                        )
                    )

                    /*
                     * Every active Dimension Rule must match.
                     */
                    AND NOT EXISTS
                    (
                        SELECT 1

                        FROM
                            ticketing_access_grant_dimension_rules
                                dimension_rule

                        WHERE dimension_rule.access_grant_id =
                                scope_grant.id

                          AND dimension_rule.status =
                                'active'

                          AND
                          (
                                dimension_rule.match_mode_code
                                    NOT IN ('any', 'all')

                                OR

                                /*
                                 * Empty active rule is fail-closed.
                                 */
                                NOT EXISTS
                                (
                                    SELECT 1

                                    FROM
                                        ticketing_access_grant_dimension_values
                                            selected_dimension_value

                                    WHERE selected_dimension_value
                                            .dimension_rule_id =
                                                dimension_rule.id

                                      AND selected_dimension_value
                                            .dimension_id =
                                                dimension_rule.dimension_id

                                      AND selected_dimension_value
                                            .status =
                                                'active'
                                )

                                OR

                                /*
                                 * ANY:
                                 * at least one selected value matches
                                 * one current immutable snapshot value.
                                 */
                                (
                                    dimension_rule.match_mode_code =
                                        'any'

                                    AND NOT EXISTS
                                    (
                                        SELECT 1

                                        FROM
                                            ticketing_access_grant_dimension_values
                                                selected_any

                                        WHERE selected_any
                                                .dimension_rule_id =
                                                    dimension_rule.id

                                          AND selected_any
                                                .dimension_id =
                                                    dimension_rule.dimension_id

                                          AND selected_any.status =
                                                'active'

                                          AND EXISTS
                                          (
                                                SELECT 1

                                                FROM
                                                    ticketing_ticket_scope_states
                                                        scope_state

                                                INNER JOIN
                                                    ticketing_ticket_scope_snapshot_values
                                                        actual_scope_value

                                                    ON actual_scope_value
                                                        .snapshot_id =
                                                            scope_state
                                                                .current_snapshot_id

                                                   AND actual_scope_value
                                                        .dimension_id =
                                                            dimension_rule
                                                                .dimension_id

                                                WHERE scope_state.ticket_id =
                                                        t.id

                                                  AND
                                                  (
                                                        actual_scope_value
                                                            .dimension_value_id =
                                                                selected_any
                                                                    .dimension_value_id

                                                        OR

                                                        (
                                                            dimension_rule
                                                                .include_descendants = 1

                                                            AND EXISTS
                                                            (
                                                                SELECT 1

                                                                FROM
                                                                    ticketing_scope_dimension_value_paths
                                                                        descendant_path

                                                                WHERE descendant_path
                                                                        .dimension_id =
                                                                            dimension_rule
                                                                                .dimension_id

                                                                  AND descendant_path
                                                                        .ancestor_value_id =
                                                                            selected_any
                                                                                .dimension_value_id

                                                                  AND descendant_path
                                                                        .descendant_value_id =
                                                                            actual_scope_value
                                                                                .dimension_value_id
                                                            )
                                                        )
                                                  )
                                          )
                                    )
                                )

                                OR

                                /*
                                 * ALL:
                                 * no selected value may remain unmatched.
                                 */
                                (
                                    dimension_rule.match_mode_code =
                                        'all'

                                    AND EXISTS
                                    (
                                        SELECT 1

                                        FROM
                                            ticketing_access_grant_dimension_values
                                                selected_all

                                        WHERE selected_all
                                                .dimension_rule_id =
                                                    dimension_rule.id

                                          AND selected_all
                                                .dimension_id =
                                                    dimension_rule.dimension_id

                                          AND selected_all.status =
                                                'active'

                                          AND NOT EXISTS
                                          (
                                                SELECT 1

                                                FROM
                                                    ticketing_ticket_scope_states
                                                        scope_state_all

                                                INNER JOIN
                                                    ticketing_ticket_scope_snapshot_values
                                                        actual_scope_value_all

                                                    ON actual_scope_value_all
                                                        .snapshot_id =
                                                            scope_state_all
                                                                .current_snapshot_id

                                                   AND actual_scope_value_all
                                                        .dimension_id =
                                                            dimension_rule
                                                                .dimension_id

                                                WHERE scope_state_all.ticket_id =
                                                        t.id

                                                  AND
                                                  (
                                                        actual_scope_value_all
                                                            .dimension_value_id =
                                                                selected_all
                                                                    .dimension_value_id

                                                        OR

                                                        (
                                                            dimension_rule
                                                                .include_descendants = 1

                                                            AND EXISTS
                                                            (
                                                                SELECT 1

                                                                FROM
                                                                    ticketing_scope_dimension_value_paths
                                                                        descendant_path_all

                                                                WHERE descendant_path_all
                                                                        .dimension_id =
                                                                            dimension_rule
                                                                                .dimension_id

                                                                  AND descendant_path_all
                                                                        .ancestor_value_id =
                                                                            selected_all
                                                                                .dimension_value_id

                                                                  AND descendant_path_all
                                                                        .descendant_value_id =
                                                                            actual_scope_value_all
                                                                                .dimension_value_id
                                                            )
                                                        )
                                                  )
                                          )
                                    )
                                )
                          )
                    )

                    /*
                     * Every active Resource Rule must match.
                     *
                     * Current canonical resource types:
                     * project/realm/service/topic/layer/node/queue/team.
                     */
                    AND NOT EXISTS
                    (
                        SELECT 1

                        FROM
                            ticketing_access_grant_resource_rules
                                resource_rule

                        WHERE resource_rule.access_grant_id =
                                scope_grant.id

                          AND resource_rule.status =
                                'active'

                          AND
                          (
                                resource_rule.resource_type_code
                                    NOT IN
                                    (
                                        'project',
                                        'realm',
                                        'service',
                                        'topic',
                                        'layer',
                                        'node',
                                        'queue',
                                        'team'
                                    )

                                OR

                                resource_rule.match_mode_code
                                    NOT IN ('any', 'all')

                                OR

                                NOT EXISTS
                                (
                                    SELECT 1

                                    FROM
                                        ticketing_access_grant_resource_values
                                            selected_resource_value

                                    WHERE selected_resource_value
                                            .resource_rule_id =
                                                resource_rule.id

                                      AND selected_resource_value
                                            .resource_type_code =
                                                resource_rule
                                                    .resource_type_code

                                      AND selected_resource_value
                                            .status =
                                                'active'
                                )

                                OR

                                CASE
                                    WHEN resource_rule.resource_type_code =
                                        'project'
                                    THEN CAST(
                                        t.support_project_id
                                        AS CHAR
                                    )

                                    WHEN resource_rule.resource_type_code =
                                        'service'
                                    THEN CAST(
                                        t.support_service_id
                                        AS CHAR
                                    )

                                    WHEN resource_rule.resource_type_code =
                                        'topic'
                                    THEN CAST(
                                        t.support_topic_id
                                        AS CHAR
                                    )

                                    WHEN resource_rule.resource_type_code =
                                        'layer'
                                    THEN CAST(
                                        t.current_support_layer_id
                                        AS CHAR
                                    )

                                    WHEN resource_rule.resource_type_code =
                                        'node'
                                    THEN CAST(
                                        t.current_support_node_id
                                        AS CHAR
                                    )

                                    WHEN resource_rule.resource_type_code =
                                        'queue'
                                    THEN CAST(
                                        t.current_support_queue_id
                                        AS CHAR
                                    )

                                    WHEN resource_rule.resource_type_code =
                                        'team'
                                    THEN CAST(
                                        t.current_support_team_id
                                        AS CHAR
                                    )

                                    WHEN resource_rule.resource_type_code = 'realm'
THEN CAST(t.realm_id AS CHAR)

ELSE NULL
                                END IS NULL

                                OR

                                (
                                    resource_rule.match_mode_code =
                                        'any'

                                    AND NOT EXISTS
                                    (
                                        SELECT 1

                                        FROM
                                            ticketing_access_grant_resource_values
                                                selected_resource_any

                                        WHERE selected_resource_any
                                                .resource_rule_id =
                                                    resource_rule.id

                                          AND selected_resource_any
                                                .resource_type_code =
                                                    resource_rule
                                                        .resource_type_code

                                          AND selected_resource_any
                                                .status =
                                                    'active'

                                          AND selected_resource_any
                                                .resource_reference =
                                                CASE
                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'project'
                                                    THEN CAST(
                                                        t.support_project_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'service'
                                                    THEN CAST(
                                                        t.support_service_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'topic'
                                                    THEN CAST(
                                                        t.support_topic_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'layer'
                                                    THEN CAST(
                                                        t.current_support_layer_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'node'
                                                    THEN CAST(
                                                        t.current_support_node_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'queue'
                                                    THEN CAST(
                                                        t.current_support_queue_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'team'
                                                    THEN CAST(
                                                        t.current_support_team_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule.resource_type_code = 'realm'
THEN CAST(t.realm_id AS CHAR)

ELSE NULL
                                                END
                                    )
                                )

                                OR

                                (
                                    resource_rule.match_mode_code =
                                        'all'

                                    AND EXISTS
                                    (
                                        SELECT 1

                                        FROM
                                            ticketing_access_grant_resource_values
                                                selected_resource_all

                                        WHERE selected_resource_all
                                                .resource_rule_id =
                                                    resource_rule.id

                                          AND selected_resource_all
                                                .resource_type_code =
                                                    resource_rule
                                                        .resource_type_code

                                          AND selected_resource_all
                                                .status =
                                                    'active'

                                          AND selected_resource_all
                                                .resource_reference <>
                                                CASE
                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'project'
                                                    THEN CAST(
                                                        t.support_project_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'service'
                                                    THEN CAST(
                                                        t.support_service_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'topic'
                                                    THEN CAST(
                                                        t.support_topic_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'layer'
                                                    THEN CAST(
                                                        t.current_support_layer_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'node'
                                                    THEN CAST(
                                                        t.current_support_node_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'queue'
                                                    THEN CAST(
                                                        t.current_support_queue_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule
                                                        .resource_type_code =
                                                            'team'
                                                    THEN CAST(
                                                        t.current_support_team_id
                                                        AS CHAR
                                                    )

                                                    WHEN resource_rule.resource_type_code = 'realm'
THEN CAST(t.realm_id AS CHAR)

ELSE NULL
                                                END
                                    )
                                )
                          )
                    )
                )
          )
    )
)
SQL;
    }


    private function staffCanViewTicket(
        string $publicReference,
        string $userReference
    ): bool {
        $publicReference =
            trim(
                $publicReference
            );

        $userReference =
            trim(
                $userReference
            );

        if (
            $publicReference === ''
            || $userReference === ''
        ) {
            return false;
        }

        return
            $this->cartable(
                $userReference,
                'all',
                $publicReference
            ) !== [];
    }


    private function assertStaffDataScopeVisible(
        array $ticket,
        string $userReference
    ): void {
        $publicReference =
            trim(
                (string) (
                    $ticket[
                        'public_reference'
                    ]
                    ?? ''
                )
            );

        if (
            !$this->staffCanViewTicket(
                $publicReference,
                $userReference
            )
        ) {
            throw new DomainException(
                'not_allowed'
            );
        }
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
                    pm.role_code AS project_role_code,
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
                    l.can_observe_descendants,

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

                LEFT JOIN
                    ticketing_support_layers l
                    ON l.id = n.layer_id
                   AND l.status = 'active'

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


    /*
     * TICKETING_HIERARCHICAL_VISIBILITY_V1
     *
     * Canonical Ticketing staff visibility:
     *
     * 1. Project-role manager:
     *      all tickets of that support project.
     *
     * 2. Ordinary active staff:
     *      own operational node.
     *
     * 3. If the current Layer explicitly allows observing
     *    descendants:
     *      direct hierarchy children only.
     *
     * Recursive descendant traversal is intentionally
     * forbidden here so a staff member cannot see data
     * several organizational layers below.
     */
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


            if ($projectId <= 0) {
                continue;
            }


            if (!isset($result[$projectId])) {
                $result[$projectId] = [
                    'all' => false,
                    'nodes' => [],
                ];
            }


            /*
             * Project management is intentionally distinct
             * from team staff_role_code=manager.
             *
             * A Team manager is still scoped by topology.
             * A Project manager sees the complete project.
             */
            if (
                trim(
                    (string) (
                        $membership[
                            'project_role_code'
                        ]
                        ?? ''
                    )
                )
                === 'manager'
            ) {
                $result[$projectId] = [
                    'all' => true,
                    'nodes' => [],
                ];

                continue;
            }


            /*
             * Another membership of the same actor may
             * already have granted full project scope.
             */
            if (
                !empty(
                    $result[$projectId]['all']
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


            if ($nodeId <= 0) {
                continue;
            }


            /*
             * Membership in an active support team is
             * sufficient to see that team's own node.
             *
             * Action permissions such as takeover/transfer
             * remain independent from read visibility.
             */
            $result[
                $projectId
            ][
                'nodes'
            ][
                $nodeId
            ] = true;


            /*
             * Lower-level observation remains dynamically
             * governed by the current Layer configuration,
             * but is capped at exactly one hierarchy edge.
             */
            if (
                empty(
                    $membership[
                        'can_observe_descendants'
                    ]
                )
            ) {
                continue;
            }


            foreach (
                $this->directChildNodes(
                    $projectId,
                    $nodeId
                )
                as $childId
            ) {
                $result[
                    $projectId
                ][
                    'nodes'
                ][
                    $childId
                ] = true;
            }
        }


        return $result;
    }


    private function directChildNodes(
        int $projectId,
        int $parentNodeId
    ): array {
        if (
            $projectId <= 0
            || $parentNodeId <= 0
        ) {
            return [];
        }


        $statement =
            $this->db->prepare("
                SELECT DISTINCT
                    child_node_id

                FROM
                    ticketing_support_node_relations

                WHERE project_id = ?
                  AND parent_node_id = ?

                  AND status = 'active'

                  AND relation_type_code =
                      'hierarchy'

                  AND is_primary_path = 1

                ORDER BY
                    child_node_id
            ");

        $statement->execute([
            $projectId,
            $parentNodeId,
        ]);


        return
            array_values(
                array_unique(
                    array_map(
                        'intval',
                        $statement->fetchAll(
                            PDO::FETCH_COLUMN
                        ) ?: []
                    )
                )
            );
    }


    private function visibleNodeClause(
        array $visibleByProject,
        array &$parameters
    ): string {
        $groups = [];


        foreach (
            $visibleByProject
            as $projectId => $scope
        ) {
            $projectId =
                (int) $projectId;


            if (
                $projectId <= 0
                || !is_array($scope)
            ) {
                continue;
            }


            /*
             * Project managers receive full project read
             * visibility, including routed tickets from all
             * operational layers.
             */
            if (!empty($scope['all'])) {

                $groups[] =
                    "(
                        t.support_project_id = ?
                    )";

                $parameters[] =
                    $projectId;

                continue;
            }


            $nodes =
                is_array(
                    $scope['nodes']
                    ?? null
                )
                    ? $scope['nodes']
                    : [];


            if ($nodes === []) {
                continue;
            }


            $nodeIds =
                array_values(
                    array_filter(
                        array_map(
                            'intval',
                            array_keys($nodes)
                        ),
                        static fn (
                            int $nodeId
                        ): bool =>
                            $nodeId > 0
                    )
                );


            if ($nodeIds === []) {
                continue;
            }


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
                $projectId;


            foreach (
                $nodeIds
                as $nodeId
            ) {
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
                    'realm_id'
                ]
                ?? 0
            ) <= 0
        ) {
            throw new DomainException(
                'ticket_realm_missing'
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


    /*
     * TICKETING_SAME_NODE_TAKEOVER_V1
     *
     * Read visibility of a direct child does not grant
     * operational takeover rights on that child.
     *
     * Takeover is strictly local:
     *     same project
     *     same current node
     *     same current team
     *
     * Cross-layer movement must go through the audited
     * escalation path.
     */
    private function takeoverMembership(
        array $ticket,
        array $memberships
    ): ?array {
        $currentNodeId =
            (int) (
                $ticket[
                    'current_support_node_id'
                ]
                ?? 0
            );

        $currentTeamId =
            (int) (
                $ticket[
                    'current_support_team_id'
                ]
                ?? 0
            );


        if (
            $currentNodeId <= 0
            || $currentTeamId <= 0
        ) {
            return null;
        }


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


            if (
                (int) (
                    $membership[
                        'node_id'
                    ]
                    ?? 0
                )
                !== $currentNodeId
            ) {
                continue;
            }


            if (
                (int) (
                    $membership[
                        'team_id'
                    ]
                    ?? 0
                )
                !== $currentTeamId
            ) {
                continue;
            }


            if (
                (int) (
                    $membership[
                        'queue_id'
                    ]
                    ?? 0
                )
                <= 0
            ) {
                continue;
            }


            return $membership;
        }


        return null;
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


    /*
     * TICKETING_DIRECT_PARENT_ESCALATION_V1
     *
     * A manual or automatic upward transition may traverse
     * exactly one active primary hierarchy edge.
     */
    private function nextEscalationRelation(
        int $projectId,
        int $realmId,
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
                   AND parent.realm_id =
                        r.realm_id

                WHERE r.project_id = ?
                  AND r.realm_id = ?
                  AND r.child_node_id = ?

                  AND r.status = 'active'
                  AND r.relation_type_code =
                        'hierarchy'

                  AND r.allow_escalation = 1
                  AND r.is_primary_path = 1

                  AND parent.status = 'active'

                ORDER BY
                    r.is_primary_path DESC,
                    r.sort_order,
                    r.id

                LIMIT 1
            ");

        $statement->execute([
            $projectId,
            $realmId,
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
        int $realmId,
        int $nodeId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    n.id AS node_id,
                    n.layer_id,
                    n.realm_id,

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
                   AND q.realm_id =
                        n.realm_id

                INNER JOIN
                    ticketing_support_team_queues tq
                    ON tq.queue_id = q.id
                   AND tq.realm_id =
                        n.realm_id
                   AND tq.status = 'active'

                INNER JOIN
                    ticketing_support_teams t
                    ON t.id = tq.team_id
                   AND t.project_id =
                        n.project_id
                   AND t.realm_id =
                        n.realm_id

                WHERE n.project_id = ?
                  AND n.realm_id = ?
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
            $realmId,
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


    private function routeRealmForAssignmentTarget(
        array $ticket,
        array $target
    ): int {

        $statement =
            $this->db->prepare("
                SELECT
                    n.realm_id

                FROM
                    ticketing_support_nodes n

                INNER JOIN
                    ticketing_support_layers l
                    ON l.id = ?
                   AND l.project_id =
                        n.project_id
                   AND l.realm_id =
                        n.realm_id
                   AND l.status = 'active'

                INNER JOIN
                    ticketing_support_queues q
                    ON q.id = ?
                   AND q.project_id =
                        n.project_id
                   AND q.node_id =
                        n.id
                   AND q.realm_id =
                        n.realm_id
                   AND q.status = 'active'

                INNER JOIN
                    ticketing_support_teams tm
                    ON tm.id = ?
                   AND tm.project_id =
                        n.project_id
                   AND tm.realm_id =
                        n.realm_id
                   AND tm.status = 'active'

                INNER JOIN
                    ticketing_support_team_nodes tn
                    ON tn.team_id =
                        tm.id
                   AND tn.node_id =
                        n.id
                   AND tn.realm_id =
                        n.realm_id
                   AND tn.status = 'active'

                INNER JOIN
                    ticketing_support_team_queues tq
                    ON tq.team_id =
                        tm.id
                   AND tq.queue_id =
                        q.id
                   AND tq.realm_id =
                        n.realm_id
                   AND tq.status = 'active'

                WHERE n.id = ?
                  AND n.project_id = ?
                  AND n.status = 'active'

                LIMIT 1
            ");

        $statement->execute([
            (int) $target['layer_id'],
            (int) $target['queue_id'],
            (int) $target['team_id'],
            (int) $target['node_id'],
            (int) $ticket['support_project_id'],
        ]);

        $realmId =
            (int) (
                $statement->fetchColumn()
                ?: 0
            );

        if ($realmId < 1) {
            throw new DomainException(
                'target_invalid'
            );
        }

        return $realmId;
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


        $ticketRealmId =
            (int) (
                $ticket[
                    'realm_id'
                ]
                ?? 0
            );

        if ($ticketRealmId < 1) {
            throw new DomainException(
                'ticket_realm_missing'
            );
        }

        $targetRealmId =
            $this->routeRealmForAssignmentTarget(
                $ticket,
                $target
            );

        if ($targetRealmId !== $ticketRealmId) {
            throw new DomainException(
                'cross_realm_handoff_required'
            );
        }


        $close =
            $this->db->prepare("
                UPDATE
                    ticketing_assignments

                SET
                    unassigned_at =
                        UTC_TIMESTAMP()

                WHERE ticket_id = ?
                  AND realm_id = ?
                  AND unassigned_at
                        IS NULL
            ");

        $close->execute([
            (int) $ticket['id'],
            $ticketRealmId,
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
                  AND realm_id = ?
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
            $ticketRealmId,
        ]);


        $insert =
            $this->db->prepare("
                INSERT INTO
                    ticketing_assignments
                (
                    ticket_id,
                    realm_id,

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
            $ticketRealmId,

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

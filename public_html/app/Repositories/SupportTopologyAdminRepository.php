<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class SupportTopologyAdminRepository
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


    public function projectByReference(
        string $reference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    public_reference,
                    code,
                    title,
                    description,
                    is_active,
                    archived_at
                FROM ticketing_support_projects
                WHERE public_reference = ?
                LIMIT 1
            ");

        $statement->execute([
            trim($reference),
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


    public function pageData(
        int $projectId
    ): array {
        return [
            'layers' =>
                $this->layers($projectId),

            'nodes' =>
                $this->nodes($projectId),

            'relations' =>
                $this->relations($projectId),

            'teams' =>
                $this->teams($projectId),

            'queues' =>
                $this->queues($projectId),

            'team_nodes' =>
                $this->teamNodes($projectId),

            'team_queues' =>
                $this->teamQueues($projectId),

            'team_members' =>
                $this->teamMembers($projectId),

            'staff_candidates' =>
                $this->staffCandidates(
                    $projectId
                ),
        ];
    }


    public function layers(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM ticketing_support_layers
                WHERE project_id = ?
                ORDER BY
                    rank_order,
                    sort_order,
                    id
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function nodes(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    n.*,
                    l.title AS layer_title,
                    l.code AS layer_code,
                    l.rank_order
                FROM ticketing_support_nodes n
                INNER JOIN ticketing_support_layers l
                    ON l.id = n.layer_id
                WHERE n.project_id = ?
                ORDER BY
                    l.rank_order,
                    n.sort_order,
                    n.title,
                    n.id
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function relations(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    r.*,

                    p.title
                        AS parent_title,

                    pl.rank_order
                        AS parent_rank,

                    c.title
                        AS child_title,

                    cl.rank_order
                        AS child_rank

                FROM
                    ticketing_support_node_relations r

                INNER JOIN
                    ticketing_support_nodes p
                    ON p.id = r.parent_node_id

                INNER JOIN
                    ticketing_support_layers pl
                    ON pl.id = p.layer_id

                INNER JOIN
                    ticketing_support_nodes c
                    ON c.id = r.child_node_id

                INNER JOIN
                    ticketing_support_layers cl
                    ON cl.id = c.layer_id

                WHERE r.project_id = ?

                ORDER BY
                    pl.rank_order DESC,
                    cl.rank_order DESC,
                    p.title,
                    c.title,
                    r.id
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function teams(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM ticketing_support_teams
                WHERE project_id = ?
                ORDER BY
                    sort_order,
                    title,
                    id
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function queues(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    q.*,
                    n.title AS node_title,
                    n.layer_id,
                    l.title AS layer_title,
                    l.rank_order
                FROM ticketing_support_queues q
                INNER JOIN ticketing_support_nodes n
                    ON n.id = q.node_id
                INNER JOIN ticketing_support_layers l
                    ON l.id = n.layer_id
                WHERE q.project_id = ?
                ORDER BY
                    l.rank_order,
                    n.sort_order,
                    q.sort_order,
                    q.title,
                    q.id
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function teamNodes(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    tn.*,
                    t.title AS team_title,
                    n.title AS node_title
                FROM ticketing_support_team_nodes tn
                INNER JOIN ticketing_support_teams t
                    ON t.id = tn.team_id
                INNER JOIN ticketing_support_nodes n
                    ON n.id = tn.node_id
                WHERE t.project_id = ?
                  AND n.project_id = ?
                ORDER BY
                    t.title,
                    n.title,
                    tn.id
            ");

        $statement->execute([
            $projectId,
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function teamQueues(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    tq.*,
                    t.title AS team_title,
                    q.title AS queue_title,
                    n.title AS node_title
                FROM ticketing_support_team_queues tq
                INNER JOIN ticketing_support_teams t
                    ON t.id = tq.team_id
                INNER JOIN ticketing_support_queues q
                    ON q.id = tq.queue_id
                INNER JOIN ticketing_support_nodes n
                    ON n.id = q.node_id
                WHERE t.project_id = ?
                  AND q.project_id = ?
                ORDER BY
                    t.title,
                    q.title,
                    tq.id
            ");

        $statement->execute([
            $projectId,
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function teamMembers(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    tm.*,
                    t.title AS team_title,

                    pm.display_name_snapshot
                        AS member_name,

                    pm.role_code
                        AS project_role_code,

                    pm.user_reference
                        AS user_reference

                FROM
                    ticketing_support_team_members tm

                INNER JOIN
                    ticketing_support_teams t
                    ON t.id = tm.team_id

                INNER JOIN
                    ticketing_support_project_members pm
                    ON pm.id = tm.project_member_id

                WHERE t.project_id = ?
                  AND pm.project_id = ?

                ORDER BY
                    t.title,
                    tm.staff_role_code,
                    pm.display_name_snapshot,
                    tm.id
            ");

        $statement->execute([
            $projectId,
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function staffCandidates(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    participant_id,
                    user_reference,
                    display_name_snapshot,
                    role_code,
                    organization_title_snapshot
                FROM ticketing_support_project_members
                WHERE project_id = ?
                  AND left_at IS NULL
                  AND user_reference IS NOT NULL
                  AND user_reference <> ''
                ORDER BY
                    display_name_snapshot,
                    id
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function layer(
        int $projectId,
        int $layerId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM ticketing_support_layers
                WHERE id = ?
                  AND project_id = ?
                LIMIT 1
            ");

        $statement->execute([
            $layerId,
            $projectId,
        ]);

        return $this->one($statement);
    }


    public function node(
        int $projectId,
        int $nodeId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    n.*,
                    l.rank_order,
                    l.title AS layer_title
                FROM ticketing_support_nodes n
                INNER JOIN ticketing_support_layers l
                    ON l.id = n.layer_id
                WHERE n.id = ?
                  AND n.project_id = ?
                LIMIT 1
            ");

        $statement->execute([
            $nodeId,
            $projectId,
        ]);

        return $this->one($statement);
    }


    public function team(
        int $projectId,
        int $teamId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM ticketing_support_teams
                WHERE id = ?
                  AND project_id = ?
                LIMIT 1
            ");

        $statement->execute([
            $teamId,
            $projectId,
        ]);

        return $this->one($statement);
    }


    public function queue(
        int $projectId,
        int $queueId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM ticketing_support_queues
                WHERE id = ?
                  AND project_id = ?
                LIMIT 1
            ");

        $statement->execute([
            $queueId,
            $projectId,
        ]);

        return $this->one($statement);
    }


    public function projectMember(
        int $projectId,
        int $memberId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM ticketing_support_project_members
                WHERE id = ?
                  AND project_id = ?
                  AND left_at IS NULL
                LIMIT 1
            ");

        $statement->execute([
            $memberId,
            $projectId,
        ]);

        return $this->one($statement);
    }


    public function layerCodeExists(
        int $projectId,
        string $code
    ): bool {
        return $this->valueExists(
            'ticketing_support_layers',
            'code',
            $projectId,
            $code
        );
    }


    public function layerRankExists(
        int $projectId,
        int $rank
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM ticketing_support_layers
                WHERE project_id = ?
                  AND rank_order = ?
            ");

        $statement->execute([
            $projectId,
            $rank,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function nodeCodeExists(
        int $projectId,
        string $code
    ): bool {
        return $this->valueExists(
            'ticketing_support_nodes',
            'code',
            $projectId,
            $code
        );
    }


    public function teamCodeExists(
        int $projectId,
        string $code
    ): bool {
        return $this->valueExists(
            'ticketing_support_teams',
            'code',
            $projectId,
            $code
        );
    }


    public function queueCodeExists(
        int $projectId,
        string $code
    ): bool {
        return $this->valueExists(
            'ticketing_support_queues',
            'code',
            $projectId,
            $code
        );
    }


    public function relationExists(
        int $projectId,
        int $parentId,
        int $childId
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM ticketing_support_node_relations
                WHERE project_id = ?
                  AND parent_node_id = ?
                  AND child_node_id = ?
                  AND relation_type_code = 'hierarchy'
                  AND status = 'active'
            ");

        $statement->execute([
            $projectId,
            $parentId,
            $childId,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function wouldCreateCycle(
        int $projectId,
        int $parentId,
        int $childId
    ): bool {
        if ($parentId === $childId) {
            return true;
        }

        $frontier = [
            $childId,
        ];

        $visited = [];

        while ($frontier !== []) {

            $current = [];

            foreach ($frontier as $nodeId) {

                $nodeId =
                    (int) $nodeId;

                if (
                    $nodeId < 1
                    || isset(
                        $visited[$nodeId]
                    )
                ) {
                    continue;
                }

                if ($nodeId === $parentId) {
                    return true;
                }

                $visited[$nodeId] = true;

                $current[] = $nodeId;
            }

            if ($current === []) {
                break;
            }

            $placeholders =
                implode(
                    ',',
                    array_fill(
                        0,
                        count($current),
                        '?'
                    )
                );

            $statement =
                $this->db->prepare("
                    SELECT child_node_id
                    FROM ticketing_support_node_relations
                    WHERE project_id = ?
                      AND relation_type_code = 'hierarchy'
                      AND status = 'active'
                      AND parent_node_id IN
                          ({$placeholders})
                ");

            $statement->execute(
                array_merge(
                    [$projectId],
                    $current
                )
            );

            $frontier =
                array_map(
                    'intval',
                    $statement->fetchAll(
                        PDO::FETCH_COLUMN
                    ) ?: []
                );
        }

        return false;
    }


    public function teamNodeBindingExists(
        int $teamId,
        int $nodeId
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM ticketing_support_team_nodes
                WHERE team_id = ?
                  AND node_id = ?
                  AND status = 'active'
            ");

        $statement->execute([
            $teamId,
            $nodeId,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function createLayer(
        array $data
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_support_layers
                (
                    public_reference,
                    project_id,
                    code,
                    title,
                    description,
                    rank_order,
                    can_observe_descendants,
                    can_assist_descendants,
                    can_takeover_descendants,
                    can_transfer_downward,
                    is_entry_layer,
                    is_terminal_layer,
                    status,
                    sort_order,
                    metadata_json
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'active',
                    ?,
                    NULL
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['project_id'],
            $data['code'],
            $data['title'],
            $data['description'],
            $data['rank_order'],
            $data['can_observe_descendants'],
            $data['can_assist_descendants'],
            $data['can_takeover_descendants'],
            $data['can_transfer_downward'],
            $data['is_entry_layer'],
            $data['is_terminal_layer'],
            $data['sort_order'],
        ]);
    }


    public function createNode(
        array $data
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_support_nodes
                (
                    public_reference,
                    project_id,
                    layer_id,
                    code,
                    title,
                    description,
                    node_kind_code,
                    core_organization_reference,
                    scope_type_code,
                    scope_reference,
                    is_intake_node,
                    status,
                    sort_order,
                    metadata_json
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, 'support',
                    ?, ?, ?, ?,
                    'active',
                    ?,
                    NULL
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['project_id'],
            $data['layer_id'],
            $data['code'],
            $data['title'],
            $data['description'],
            $data['core_organization_reference'],
            $data['scope_type_code'],
            $data['scope_reference'],
            $data['is_intake_node'],
            $data['sort_order'],
        ]);
    }


    public function createRelation(
        array $data
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO
                    ticketing_support_node_relations
                (
                    public_reference,
                    project_id,
                    parent_node_id,
                    child_node_id,
                    relation_type_code,
                    is_primary_path,
                    allow_escalation,
                    allow_downward_transfer,
                    status,
                    sort_order,
                    metadata_json
                )
                VALUES
                (
                    ?, ?, ?, ?,
                    'hierarchy',
                    ?, ?, ?,
                    'active',
                    0,
                    NULL
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['project_id'],
            $data['parent_node_id'],
            $data['child_node_id'],
            $data['is_primary_path'],
            $data['allow_escalation'],
            $data['allow_downward_transfer'],
        ]);
    }


    public function createTeam(
        array $data
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_support_teams
                (
                    public_reference,
                    project_id,
                    code,
                    title,
                    description,
                    status,
                    sort_order,
                    metadata_json
                )
                VALUES
                (
                    ?, ?, ?, ?, ?,
                    'active',
                    ?,
                    NULL
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['project_id'],
            $data['code'],
            $data['title'],
            $data['description'],
            $data['sort_order'],
        ]);
    }


    public function createQueue(
        array $data
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_support_queues
                (
                    public_reference,
                    project_id,
                    node_id,
                    code,
                    title,
                    description,
                    queue_type_code,
                    assignment_mode_code,
                    max_open_per_agent,
                    is_default,
                    status,
                    sort_order,
                    metadata_json
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?,
                    'work',
                    ?,
                    ?,
                    ?,
                    'active',
                    ?,
                    NULL
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['project_id'],
            $data['node_id'],
            $data['code'],
            $data['title'],
            $data['description'],
            $data['assignment_mode_code'],
            $data['max_open_per_agent'],
            $data['is_default'],
            $data['sort_order'],
        ]);
    }


    public function bindTeamNode(
        int $teamId,
        int $nodeId
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_support_team_nodes
                (
                    team_id,
                    node_id,
                    service_role_code,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    'primary',
                    'active'
                )
                ON DUPLICATE KEY UPDATE
                    status = 'active',
                    updated_at = CURRENT_TIMESTAMP
            ");

        $statement->execute([
            $teamId,
            $nodeId,
        ]);
    }


    public function bindTeamQueue(
        int $teamId,
        int $queueId
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_support_team_queues
                (
                    team_id,
                    queue_id,
                    service_role_code,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    'owner',
                    'active'
                )
                ON DUPLICATE KEY UPDATE
                    status = 'active',
                    updated_at = CURRENT_TIMESTAMP
            ");

        $statement->execute([
            $teamId,
            $queueId,
        ]);
    }


    public function addTeamMember(
        array $data
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO
                    ticketing_support_team_members
                (
                    team_id,
                    project_member_id,
                    staff_role_code,
                    workload_weight,
                    can_assign,
                    can_observe,
                    can_assist,
                    can_takeover,
                    can_transfer,
                    status,
                    joined_at,
                    left_at,
                    metadata_json
                )
                VALUES
                (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    'active',
                    CURRENT_TIMESTAMP,
                    NULL,
                    NULL
                )
                ON DUPLICATE KEY UPDATE
                    staff_role_code =
                        VALUES(staff_role_code),

                    workload_weight =
                        VALUES(workload_weight),

                    can_assign =
                        VALUES(can_assign),

                    can_observe =
                        VALUES(can_observe),

                    can_assist =
                        VALUES(can_assist),

                    can_takeover =
                        VALUES(can_takeover),

                    can_transfer =
                        VALUES(can_transfer),

                    status = 'active',

                    left_at = NULL,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $statement->execute([
            $data['team_id'],
            $data['project_member_id'],
            $data['staff_role_code'],
            $data['workload_weight'],
            $data['can_assign'],
            $data['can_observe'],
            $data['can_assist'],
            $data['can_takeover'],
            $data['can_transfer'],
        ]);
    }


    private function valueExists(
        string $table,
        string $column,
        int $projectId,
        string $value
    ): bool {
        $allowed = [
            'ticketing_support_layers' => [
                'code',
            ],
            'ticketing_support_nodes' => [
                'code',
            ],
            'ticketing_support_teams' => [
                'code',
            ],
            'ticketing_support_queues' => [
                'code',
            ],
        ];

        if (
            !isset($allowed[$table])
            || !in_array(
                $column,
                $allowed[$table],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'Invalid topology lookup.'
            );
        }

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM {$table}
                WHERE project_id = ?
                  AND {$column} = ?
            ");

        $statement->execute([
            $projectId,
            $value,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    private function one(
        \PDOStatement $statement
    ): ?array {
        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }
}

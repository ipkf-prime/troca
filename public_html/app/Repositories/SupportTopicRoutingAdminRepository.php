<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class SupportTopicRoutingAdminRepository
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
                    is_active,
                    archived_at
                FROM ticketing_support_projects
                WHERE public_reference = ?
                LIMIT 1
            ");

        $statement->execute([
            trim($reference),
        ]);

        return $this->one($statement);
    }


    public function pageData(
        int $projectId
    ): array {
        return [
            'services' =>
                $this->services(
                    $projectId
                ),

            'topics' =>
                $this->topics(
                    $projectId
                ),

            'rules' =>
                $this->rules(
                    $projectId
                ),

            'layers' =>
                $this->layers(
                    $projectId
                ),

            'nodes' =>
                $this->nodes(
                    $projectId
                ),

            'queues' =>
                $this->queues(
                    $projectId
                ),

            'teams' =>
                $this->teams(
                    $projectId
                ),

            'staff' =>
                $this->staff(
                    $projectId
                ),
        ];
    }


    public function services(
        int $projectId
    ): array {
        return $this->all("
            SELECT
                id,
                code,
                title,
                is_default,
                is_active
            FROM ticketing_support_services
            WHERE project_id = {$projectId}
            ORDER BY
                is_default DESC,
                sort_order,
                title,
                id
        ");
    }


    /*
     * TICKETING_SUPPORT_TOPIC_GOVERNANCE_V1
     *
     * Topic/category rows are enriched with dependency counters.
     * These counters are read-only and are used by the management
     * UI and validation layer before structural changes.
     */
    public function topics(
        int $projectId
    ): array {
        if ($projectId < 1) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    t.*,

                    s.title
                        AS service_title,

                    p.title
                        AS parent_title,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_topics child
                        WHERE child.parent_topic_id = t.id
                    ) AS child_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_topics child
                        WHERE child.parent_topic_id = t.id
                          AND child.status = 'active'
                    ) AS active_child_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_routing_rules rr
                        WHERE rr.topic_id = t.id
                    ) AS routing_rule_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_routing_rules rr
                        WHERE rr.topic_id = t.id
                          AND rr.status = 'active'
                    ) AS active_routing_rule_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_tickets ticket
                        WHERE ticket.support_topic_id = t.id
                    ) AS ticket_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_tickets ticket
                        INNER JOIN ticketing_statuses status_row
                            ON status_row.code =
                                ticket.status_code
                        WHERE ticket.support_topic_id = t.id
                          AND ticket.archived_at IS NULL
                          AND status_row.is_closed = 0
                    ) AS open_ticket_count

                FROM ticketing_support_topics t

                LEFT JOIN ticketing_support_services s
                    ON s.id = t.service_id

                LEFT JOIN ticketing_support_topics p
                    ON p.id = t.parent_topic_id

                WHERE t.project_id = ?

                ORDER BY
                    t.sort_order,
                    t.title,
                    t.id
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    public function rules(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    r.*,

                    s.title AS service_title,
                    tp.title AS topic_title,

                    l.title AS layer_title,
                    n.title AS node_title,
                    q.title AS queue_title,
                    tm.title AS team_title,

                    pm.display_name_snapshot
                        AS fixed_member_name

                FROM
                    ticketing_support_routing_rules r

                LEFT JOIN
                    ticketing_support_services s
                    ON s.id = r.service_id

                LEFT JOIN
                    ticketing_support_topics tp
                    ON tp.id = r.topic_id

                INNER JOIN
                    ticketing_support_layers l
                    ON l.id =
                        r.target_layer_id

                INNER JOIN
                    ticketing_support_nodes n
                    ON n.id =
                        r.target_node_id

                INNER JOIN
                    ticketing_support_queues q
                    ON q.id =
                        r.target_queue_id

                INNER JOIN
                    ticketing_support_teams tm
                    ON tm.id =
                        r.target_team_id

                LEFT JOIN
                    ticketing_support_project_members pm
                    ON pm.id =
                        r.fixed_project_member_id

                WHERE r.project_id = ?

                ORDER BY
                    r.priority DESC,
                    r.sort_order,
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


    public function layers(
        int $projectId
    ): array {
        return $this->projectRows(
            'ticketing_support_layers',
            $projectId,
            'rank_order, sort_order, id'
        );
    }


    public function nodes(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    n.*,
                    l.title AS layer_title,
                    l.rank_order
                FROM ticketing_support_nodes n
                INNER JOIN ticketing_support_layers l
                    ON l.id = n.layer_id
                WHERE n.project_id = ?
                  AND n.status = 'active'
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


    public function queues(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    q.*,
                    n.title AS node_title
                FROM ticketing_support_queues q
                INNER JOIN ticketing_support_nodes n
                    ON n.id = q.node_id
                WHERE q.project_id = ?
                  AND q.status = 'active'
                ORDER BY
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


    public function teams(
        int $projectId
    ): array {
        return $this->projectRows(
            'ticketing_support_teams',
            $projectId,
            'sort_order, title, id',
            true
        );
    }


    public function staff(
        int $projectId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    pm.id,
                    pm.user_reference,
                    pm.display_name_snapshot
                FROM
                    ticketing_support_project_members pm
                WHERE pm.project_id = ?
                  AND pm.left_at IS NULL
                  AND pm.user_reference
                        IS NOT NULL
                  AND pm.user_reference <> ''
                ORDER BY
                    pm.display_name_snapshot,
                    pm.id
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function service(
        int $projectId,
        int $serviceId
    ): ?array {
        return $this->ownedRow(
            'ticketing_support_services',
            $projectId,
            $serviceId
        );
    }


    public function topic(
        int $projectId,
        int $topicId
    ): ?array {
        return $this->ownedRow(
            'ticketing_support_topics',
            $projectId,
            $topicId
        );
    }


    public function layer(
        int $projectId,
        int $id
    ): ?array {
        return $this->ownedRow(
            'ticketing_support_layers',
            $projectId,
            $id
        );
    }


    public function node(
        int $projectId,
        int $id
    ): ?array {
        return $this->ownedRow(
            'ticketing_support_nodes',
            $projectId,
            $id
        );
    }


    public function queue(
        int $projectId,
        int $id
    ): ?array {
        return $this->ownedRow(
            'ticketing_support_queues',
            $projectId,
            $id
        );
    }


    public function team(
        int $projectId,
        int $id
    ): ?array {
        return $this->ownedRow(
            'ticketing_support_teams',
            $projectId,
            $id
        );
    }


    public function member(
        int $projectId,
        int $id
    ): ?array {
        return $this->ownedRow(
            'ticketing_support_project_members',
            $projectId,
            $id
        );
    }


    public function topicCodeExists(
        int $projectId,
        string $code
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM ticketing_support_topics
                WHERE project_id = ?
                  AND code = ?
            ");

        $statement->execute([
            $projectId,
            $code,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function topicImpact(
        int $projectId,
        int $topicId
    ): ?array {
        if (
            $projectId < 1
            || $topicId < 1
        ) {
            return null;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    t.id,
                    t.project_id,
                    t.service_id,
                    t.parent_topic_id,
                    t.code,
                    t.title,
                    t.is_selectable,
                    t.is_default,
                    t.status,
                    t.sort_order,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_topics child
                        WHERE child.parent_topic_id = t.id
                    ) AS child_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_topics child
                        WHERE child.parent_topic_id = t.id
                          AND child.status = 'active'
                    ) AS active_child_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_routing_rules rr
                        WHERE rr.topic_id = t.id
                    ) AS routing_rule_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_routing_rules rr
                        WHERE rr.topic_id = t.id
                          AND rr.status = 'active'
                    ) AS active_routing_rule_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_tickets ticket
                        WHERE ticket.support_topic_id = t.id
                    ) AS ticket_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_tickets ticket
                        INNER JOIN ticketing_statuses status_row
                            ON status_row.code =
                                ticket.status_code
                        WHERE ticket.support_topic_id = t.id
                          AND ticket.archived_at IS NULL
                          AND status_row.is_closed = 0
                    ) AS open_ticket_count

                FROM ticketing_support_topics t

                WHERE t.id = ?
                  AND t.project_id = ?

                LIMIT 1
            ");

        $statement->execute([
            $topicId,
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


    public function topicChildren(
        int $projectId,
        int $topicId
    ): array {
        if (
            $projectId < 1
            || $topicId < 1
        ) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    project_id,
                    service_id,
                    parent_topic_id,
                    title,
                    status
                FROM ticketing_support_topics
                WHERE project_id = ?
                  AND parent_topic_id = ?
                ORDER BY sort_order, id
            ");

        $statement->execute([
            $projectId,
            $topicId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function topicWouldCreateCycle(
        int $projectId,
        int $topicId,
        ?int $candidateParentId
    ): bool {
        if (
            $projectId < 1
            || $topicId < 1
            || $candidateParentId === null
            || $candidateParentId < 1
        ) {
            return false;
        }

        $current =
            $candidateParentId;

        $visited = [];

        for ($guard = 0; $guard < 100; $guard++) {

            if ($current === $topicId) {
                return true;
            }

            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;

            $parent =
                $this->topic(
                    $projectId,
                    $current
                );

            if ($parent === null) {
                return false;
            }

            $next =
                isset($parent['parent_topic_id'])
                && $parent['parent_topic_id'] !== null
                    ? (int) $parent['parent_topic_id']
                    : 0;

            if ($next < 1) {
                return false;
            }

            $current = $next;
        }

        return true;
    }


    public function createTopicGoverned(
        array $data
    ): void {
        $projectId =
            (int) (
                $data['project_id']
                ?? 0
            );

        if ($projectId < 1) {
            throw new \RuntimeException(
                'topic_governance_invalid_project'
            );
        }

        $this->db->beginTransaction();

        try {
            if (
                (int) (
                    $data['is_default']
                    ?? 0
                ) === 1
            ) {
                $this->lockTopicDefaultScope(
                    $projectId,
                    $data['service_id']
                        ?? null
                );

                $this->clearTopicDefaultScope(
                    $projectId,
                    $data['service_id']
                        ?? null,
                    null
                );
            }

            $this->createTopic(
                $data
            );

            $this->db->commit();

        } catch (\Throwable $exception) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function updateTopicGoverned(
        array $data
    ): void {
        $topicId =
            (int) (
                $data['id']
                ?? 0
            );

        $projectId =
            (int) (
                $data['project_id']
                ?? 0
            );

        if (
            $topicId < 1
            || $projectId < 1
        ) {
            throw new \RuntimeException(
                'topic_governance_invalid_identity'
            );
        }

        $this->db->beginTransaction();

        try {
            $lockTopic =
                $this->db->prepare("
                    SELECT id
                    FROM ticketing_support_topics
                    WHERE id = ?
                      AND project_id = ?
                    FOR UPDATE
                ");

            $lockTopic->execute([
                $topicId,
                $projectId,
            ]);

            if (
                (int) (
                    $lockTopic->fetchColumn()
                    ?: 0
                ) !== $topicId
            ) {
                throw new \RuntimeException(
                    'topic_governance_topic_not_found'
                );
            }

            if (
                (int) (
                    $data['is_default']
                    ?? 0
                ) === 1
            ) {
                $this->lockTopicDefaultScope(
                    $projectId,
                    $data['service_id']
                        ?? null
                );

                $this->clearTopicDefaultScope(
                    $projectId,
                    $data['service_id']
                        ?? null,
                    $topicId
                );
            }

            $statement =
                $this->db->prepare("
                    UPDATE ticketing_support_topics
                    SET
                        service_id = ?,
                        parent_topic_id = ?,
                        title = ?,
                        description = ?,
                        is_selectable = ?,
                        is_default = ?,
                        status = ?,
                        sort_order = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                      AND project_id = ?
                ");

            $statement->execute([
                $data['service_id']
                    ?? null,

                $data['parent_topic_id']
                    ?? null,

                $data['title'],

                $data['description']
                    ?? null,

                (int) $data['is_selectable'],

                (int) $data['is_default'],

                $data['status'],

                (int) $data['sort_order'],

                $topicId,
                $projectId,
            ]);

            if ($statement->rowCount() > 1) {
                throw new \RuntimeException(
                    'topic_governance_update_conflict'
                );
            }

            $this->db->commit();

        } catch (\Throwable $exception) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    private function lockTopicDefaultScope(
        int $projectId,
        ?int $serviceId
    ): void {
        /*
         * Lock the project row first.  This provides a stable
         * serialization point even when the target default scope
         * currently contains zero topic rows.
         */
        $projectLock =
            $this->db->prepare("
                SELECT id
                FROM ticketing_support_projects
                WHERE id = ?
                FOR UPDATE
            ");

        $projectLock->execute([
            $projectId,
        ]);

        if (
            (int) (
                $projectLock->fetchColumn()
                ?: 0
            ) !== $projectId
        ) {
            throw new \RuntimeException(
                'topic_governance_project_not_found'
            );
        }

        $scopeLock =
            $this->db->prepare("
                SELECT id
                FROM ticketing_support_topics
                WHERE project_id = ?
                  AND service_id <=> ?
                ORDER BY id
                FOR UPDATE
            ");

        $scopeLock->execute([
            $projectId,
            $serviceId,
        ]);

        $scopeLock->fetchAll(
            PDO::FETCH_COLUMN
        );
    }


    private function clearTopicDefaultScope(
        int $projectId,
        ?int $serviceId,
        ?int $exceptTopicId
    ): void {
        $sql = "
            UPDATE ticketing_support_topics
            SET
                is_default = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE project_id = ?
              AND service_id <=> ?
              AND is_default = 1
        ";

        $parameters = [
            $projectId,
            $serviceId,
        ];

        if (
            $exceptTopicId !== null
            && $exceptTopicId > 0
        ) {
            $sql .= "
              AND id <> ?
            ";

            $parameters[] =
                $exceptTopicId;
        }

        $statement =
            $this->db->prepare(
                $sql
            );

        $statement->execute(
            $parameters
        );
    }


    public function createTopic(
        array $data
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_support_topics
                (
                    public_reference,
                    project_id,
                    service_id,
                    parent_topic_id,
                    code,
                    title,
                    description,
                    is_selectable,
                    is_default,
                    status,
                    sort_order,
                    metadata_json
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?,
                    'active',
                    ?,
                    NULL
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['project_id'],
            $data['service_id'],
            $data['parent_topic_id'],
            $data['code'],
            $data['title'],
            $data['description'],
            $data['is_selectable'],
            $data['is_default'],
            $data['sort_order'],
        ]);
    }


    public function createRule(
        array $data
    ): void {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_support_routing_rules
                (
                    public_reference,
                    project_id,
                    service_id,
                    topic_id,
                    title,
                    description,
                    scope_type_code,
                    scope_reference,
                    target_layer_id,
                    target_node_id,
                    target_queue_id,
                    target_team_id,
                    fixed_project_member_id,
                    assignment_mode_code,
                    priority,
                    stop_processing,
                    status,
                    sort_order,
                    matcher_json,
                    metadata_json
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?,
                    1,
                    'active',
                    ?,
                    NULL,
                    NULL
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['project_id'],
            $data['service_id'],
            $data['topic_id'],
            $data['title'],
            $data['description'],
            $data['scope_type_code'],
            $data['scope_reference'],
            $data['target_layer_id'],
            $data['target_node_id'],
            $data['target_queue_id'],
            $data['target_team_id'],
            $data['fixed_project_member_id'],
            $data['assignment_mode_code'],
            $data['priority'],
            $data['sort_order'],
        ]);
    }


    public function teamOwnsNodeAndQueue(
        int $teamId,
        int $nodeId,
        int $queueId
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM ticketing_support_team_nodes tn

                INNER JOIN
                    ticketing_support_team_queues tq
                    ON tq.team_id = tn.team_id

                INNER JOIN
                    ticketing_support_queues q
                    ON q.id = tq.queue_id

                WHERE tn.team_id = ?
                  AND tn.node_id = ?
                  AND tq.queue_id = ?
                  AND q.node_id = ?
                  AND tn.status = 'active'
                  AND tq.status = 'active'
                  AND q.status = 'active'
            ");

        $statement->execute([
            $teamId,
            $nodeId,
            $queueId,
            $nodeId,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function memberBelongsToTeam(
        int $teamId,
        int $memberId
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM ticketing_support_team_members
                WHERE team_id = ?
                  AND project_member_id = ?
                  AND status = 'active'
                  AND left_at IS NULL
            ");

        $statement->execute([
            $teamId,
            $memberId,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    private function ownedRow(
        string $table,
        int $projectId,
        int $id
    ): ?array {
        $allowed = [
            'ticketing_support_services',
            'ticketing_support_topics',
            'ticketing_support_layers',
            'ticketing_support_nodes',
            'ticketing_support_queues',
            'ticketing_support_teams',
            'ticketing_support_project_members',
        ];

        if (!in_array(
            $table,
            $allowed,
            true
        )) {
            throw new \InvalidArgumentException(
                'Invalid routing lookup.'
            );
        }

        $statement =
            $this->db->prepare("
                SELECT *
                FROM {$table}
                WHERE id = ?
                  AND project_id = ?
                LIMIT 1
            ");

        $statement->execute([
            $id,
            $projectId,
        ]);

        return $this->one($statement);
    }


    private function projectRows(
        string $table,
        int $projectId,
        string $order,
        bool $activeOnly = false
    ): array {
        $status =
            $activeOnly
                ? " AND status = 'active'"
                : '';

        $statement =
            $this->db->prepare("
                SELECT *
                FROM {$table}
                WHERE project_id = ?
                {$status}
                ORDER BY {$order}
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    private function all(
        string $sql
    ): array {
        return
            $this->db->query($sql)
                ->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];
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

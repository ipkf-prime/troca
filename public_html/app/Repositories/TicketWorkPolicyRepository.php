<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class TicketWorkPolicyRepository
{
    private PDO $ticketing;
    private PDO $work;

    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $resolver =
            $connections
            ?? new ConnectionResolver();

        $this->ticketing =
            $resolver->resolve(
                'ticketing.primary'
            );

        $this->work =
            $resolver->resolve(
                'work.primary'
            );
    }

    public function ticketContext(
        string $ticketReference,
        string $actorUserReference
    ): ?array {
        $statement =
            $this->ticketing->prepare(
                "SELECT
                    t.id AS ticket_id,
                    t.public_reference AS ticket_reference,
                    t.ticket_number,
                    t.subject,
                    t.status_code,
                    t.current_assignee_project_member_id,

                    p.id AS project_id,
                    p.public_reference AS project_reference,
                    p.code AS project_code,

                    sd.public_reference AS subdomain_reference,
                    sd.code AS subdomain_code,

                    s.public_reference AS service_reference,
                    s.code AS service_code,

                    tp.public_reference AS topic_reference,
                    tp.code AS topic_code,

                    l.public_reference AS layer_reference,
                    l.code AS layer_code,

                    n.public_reference AS node_reference,
                    n.code AS node_code,

                    q.public_reference AS queue_reference,
                    q.code AS queue_code,

                    st.public_reference AS team_reference,
                    st.code AS team_code,

                    pm.id AS actor_project_member_id,
                    pm.role_code AS actor_project_role_code,

                    tm.id AS actor_team_member_id,
                    tm.staff_role_code AS actor_staff_role_code

                 FROM ticketing_tickets t

                 INNER JOIN ticketing_support_projects p
                    ON p.id = t.support_project_id

                 LEFT JOIN ticketing_support_services s
                    ON s.id = t.support_service_id

                 LEFT JOIN ticketing_support_subdomains sd
                    ON sd.id = s.subdomain_id

                 LEFT JOIN ticketing_support_topics tp
                    ON tp.id = t.support_topic_id

                 LEFT JOIN ticketing_support_layers l
                    ON l.id = t.current_support_layer_id

                 LEFT JOIN ticketing_support_nodes n
                    ON n.id = t.current_support_node_id

                 LEFT JOIN ticketing_support_queues q
                    ON q.id = t.current_support_queue_id

                 LEFT JOIN ticketing_support_teams st
                    ON st.id = t.current_support_team_id

                 LEFT JOIN ticketing_support_project_members pm
                    ON pm.project_id = t.support_project_id
                   AND pm.user_reference = ?
                   AND pm.left_at IS NULL

                 LEFT JOIN ticketing_support_team_members tm
                    ON tm.project_member_id = pm.id
                   AND tm.team_id = t.current_support_team_id
                   AND tm.status = 'active'
                   AND tm.left_at IS NULL

                 WHERE t.public_reference = ?
                   AND t.archived_at IS NULL
                   AND p.archived_at IS NULL
                   AND p.is_active = 1
                 LIMIT 1"
            );

        $statement->execute([
            $actorUserReference,
            $ticketReference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return null;
        }

        $row['actor_user_reference'] =
            $actorUserReference;

        $actorProjectMemberId =
            (int) (
                $row[
                    'actor_project_member_id'
                ]
                ?? 0
            );

        $currentAssigneeProjectMemberId =
            (int) (
                $row[
                    'current_assignee_project_member_id'
                ]
                ?? 0
            );

        $row['actor_is_current_assignee'] =
            $actorProjectMemberId > 0
            && $currentAssigneeProjectMemberId > 0
            && $actorProjectMemberId
                === $currentAssigneeProjectMemberId;

        return $row;
    }

    public function destinationCandidates(
        array $context
    ): array {
        $statement =
            $this->work->prepare(
                "SELECT
                    r.*,
                    wp.public_reference
                        AS work_project_reference,
                    wp.code
                        AS work_project_code,
                    wp.title
                        AS work_project_title,
                    ws.code
                        AS default_status_code,

                    (
                        1
                        + (r.ticketing_subdomain_reference IS NOT NULL)
                        + (r.ticketing_service_reference IS NOT NULL)
                        + (r.ticketing_topic_reference IS NOT NULL)
                        + (r.ticketing_layer_reference IS NOT NULL)
                        + (r.ticketing_node_reference IS NOT NULL)
                        + (r.ticketing_queue_reference IS NOT NULL)
                        + (r.ticketing_team_reference IS NOT NULL)
                    ) AS specificity

                 FROM work_ticket_destination_rules r

                 INNER JOIN work_projects wp
                    ON wp.id = r.work_project_id

                 LEFT JOIN work_statuses ws
                    ON ws.id = r.default_status_id

                 WHERE r.is_active = 1
                   AND wp.archived_at IS NULL
                   AND wp.status_code = 'active'
                   AND r.ticketing_project_reference = ?

                   AND (
                        r.ticketing_subdomain_reference IS NULL
                        OR r.ticketing_subdomain_reference = ?
                   )
                   AND (
                        r.ticketing_service_reference IS NULL
                        OR r.ticketing_service_reference = ?
                   )
                   AND (
                        r.ticketing_topic_reference IS NULL
                        OR r.ticketing_topic_reference = ?
                   )
                   AND (
                        r.ticketing_layer_reference IS NULL
                        OR r.ticketing_layer_reference = ?
                   )
                   AND (
                        r.ticketing_node_reference IS NULL
                        OR r.ticketing_node_reference = ?
                   )
                   AND (
                        r.ticketing_queue_reference IS NULL
                        OR r.ticketing_queue_reference = ?
                   )
                   AND (
                        r.ticketing_team_reference IS NULL
                        OR r.ticketing_team_reference = ?
                   )

                 ORDER BY
                    r.rule_priority DESC,
                    specificity DESC,
                    r.id ASC"
            );

        $statement->execute(
            $this->contextParams(
                $context
            )
        );

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );

        return is_array($rows)
            ? $rows
            : [];
    }

    public function accessCandidates(
        array $context,
        ?int $workProjectId
    ): array {
        $statement =
            $this->work->prepare(
                "SELECT
                    r.*,

                    (
                        1
                        + (r.ticketing_subdomain_reference IS NOT NULL)
                        + (r.ticketing_service_reference IS NOT NULL)
                        + (r.ticketing_topic_reference IS NOT NULL)
                        + (r.ticketing_layer_reference IS NOT NULL)
                        + (r.ticketing_node_reference IS NOT NULL)
                        + (r.ticketing_queue_reference IS NOT NULL)
                        + (r.ticketing_team_reference IS NOT NULL)
                        + (r.work_project_id IS NOT NULL)
                    ) AS specificity,

                    CASE r.principal_type_code
                        WHEN 'user' THEN 60
                        WHEN 'current_assignee' THEN 50
                        WHEN 'team_role' THEN 40
                        WHEN 'project_role' THEN 30
                        WHEN 'team_member' THEN 20
                        WHEN 'project_member' THEN 10
                        WHEN 'any_staff' THEN 0
                        ELSE -1
                    END AS principal_specificity

                 FROM work_ticket_access_rules r

                 WHERE r.is_active = 1
                   AND r.ticketing_project_reference = ?

                   AND (
                        r.ticketing_subdomain_reference IS NULL
                        OR r.ticketing_subdomain_reference = ?
                   )
                   AND (
                        r.ticketing_service_reference IS NULL
                        OR r.ticketing_service_reference = ?
                   )
                   AND (
                        r.ticketing_topic_reference IS NULL
                        OR r.ticketing_topic_reference = ?
                   )
                   AND (
                        r.ticketing_layer_reference IS NULL
                        OR r.ticketing_layer_reference = ?
                   )
                   AND (
                        r.ticketing_node_reference IS NULL
                        OR r.ticketing_node_reference = ?
                   )
                   AND (
                        r.ticketing_queue_reference IS NULL
                        OR r.ticketing_queue_reference = ?
                   )
                   AND (
                        r.ticketing_team_reference IS NULL
                        OR r.ticketing_team_reference = ?
                   )
                   AND (
                        r.work_project_id IS NULL
                        OR r.work_project_id = ?
                   )

                 ORDER BY
                    r.rule_priority DESC,
                    specificity DESC,
                    principal_specificity DESC,
                    r.id ASC"
            );

        $params =
            $this->contextParams(
                $context
            );

        $params[] =
            $workProjectId;

        $statement->execute(
            $params
        );

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );

        return is_array($rows)
            ? $rows
            : [];
    }

    private function contextParams(
        array $context
    ): array {
        return [
            (string) (
                $context[
                    'project_reference'
                ]
                ?? ''
            ),
            $this->nullable(
                $context[
                    'subdomain_reference'
                ]
                ?? null
            ),
            $this->nullable(
                $context[
                    'service_reference'
                ]
                ?? null
            ),
            $this->nullable(
                $context[
                    'topic_reference'
                ]
                ?? null
            ),
            $this->nullable(
                $context[
                    'layer_reference'
                ]
                ?? null
            ),
            $this->nullable(
                $context[
                    'node_reference'
                ]
                ?? null
            ),
            $this->nullable(
                $context[
                    'queue_reference'
                ]
                ?? null
            ),
            $this->nullable(
                $context[
                    'team_reference'
                ]
                ?? null
            ),
        ];
    }

    private function nullable(
        mixed $value
    ): ?string {
        $value =
            trim(
                (string) $value
            );

        return $value === ''
            ? null
            : $value;
    }
}

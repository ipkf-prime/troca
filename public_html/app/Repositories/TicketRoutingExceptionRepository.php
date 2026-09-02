<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class TicketRoutingExceptionRepository
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

    public function ticketByReference(
        string $publicReference
    ): ?array {
        $publicReference = trim($publicReference);

        if ($publicReference === '') {
            return null;
        }

        $statement = $this->db->prepare("
            SELECT
                t.id,
                t.ticket_number,
                t.public_reference,
                t.subject,
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
                t.status_code,
                t.priority_code,
                t.archived_at,
                t.created_at,
                t.last_activity_at,
                t.updated_at,
                s.is_closed AS status_is_closed,
                rr.assignment_mode_code AS rule_assignment_mode_code,
                q.assignment_mode_code AS queue_assignment_mode_code
            FROM ticketing_tickets t
            INNER JOIN ticketing_statuses s
                ON s.code = t.status_code
            LEFT JOIN ticketing_support_routing_rules rr
                ON rr.id = t.matched_routing_rule_id
            LEFT JOIN ticketing_support_queues q
                ON q.id = t.current_support_queue_id
            WHERE t.public_reference = ?
            LIMIT 1
        ");

        $statement->execute([$publicReference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function activeTickets(
        ?int $projectId = null,
        int $limit = 500
    ): array {
        $limit = max(1, min(2000, $limit));

        $where = [
            't.archived_at IS NULL',
            's.is_closed = 0',
        ];

        $parameters = [];

        if ($projectId !== null && $projectId > 0) {
            $where[] = 't.support_project_id = ?';
            $parameters[] = $projectId;
        }

        $sql = "
            SELECT
                t.id,
                t.ticket_number,
                t.public_reference,
                t.subject,
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
                t.status_code,
                t.priority_code,
                t.archived_at,
                t.created_at,
                t.last_activity_at,
                t.updated_at,
                s.is_closed AS status_is_closed,
                rr.assignment_mode_code AS rule_assignment_mode_code,
                q.assignment_mode_code AS queue_assignment_mode_code
            FROM ticketing_tickets t
            INNER JOIN ticketing_statuses s
                ON s.code = t.status_code
            LEFT JOIN ticketing_support_routing_rules rr
                ON rr.id = t.matched_routing_rule_id
            LEFT JOIN ticketing_support_queues q
                ON q.id = t.current_support_queue_id
            WHERE "
            . implode("\n                AND ", $where)
            . "
            ORDER BY
                t.last_activity_at DESC,
                t.id DESC
            LIMIT "
            . $limit;

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function selectableTopics(
        int $projectId,
        ?int $serviceId
    ): array {
        if ($projectId <= 0) {
            return [];
        }

        $parameters = [$projectId];
        $serviceWhere = 'service_id IS NULL';

        if ($serviceId !== null && $serviceId > 0) {
            $serviceWhere = '(service_id = ? OR service_id IS NULL)';
            $parameters[] = $serviceId;
        }

        $statement = $this->db->prepare(
            "
            SELECT
                id,
                public_reference,
                project_id,
                service_id,
                parent_topic_id,
                code,
                title,
                is_default,
                sort_order
            FROM ticketing_support_topics
            WHERE project_id = ?
              AND status = 'active'
              AND is_selectable = 1
              AND {$serviceWhere}
            ORDER BY
                CASE
                    WHEN is_default = 1 THEN 0
                    ELSE 1
                END,
                sort_order,
                id
            "
        );

        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

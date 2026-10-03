<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class WorkTicketLifecycleSyncRepository
{
    private PDO $work;

    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $resolver =
            $connections
            ?? new ConnectionResolver();

        $this->work =
            $resolver->resolve(
                'work.primary'
            );
    }

    /**
     * Return active mappings for the resulting Work status.
     *
     * No status/action mapping exists in application source. The rows in
     * work_ticket_lifecycle_sync_rules are the sole mapping source.
     */
    public function activeRulesForStatus(
        int $workProjectId,
        string $resultingStatusCode
    ): array {
        $statement =
            $this->work->prepare(
                "SELECT
                    r.id,
                    r.public_reference,
                    r.work_project_id,
                    r.work_status_id,
                    r.relation_type_code,
                    r.ticket_action_code,
                    r.rule_priority,

                    ws.code AS work_status_code,
                    ws.title AS work_status_title,
                    ws.category AS work_status_category,
                    ws.is_closed AS work_status_is_closed,

                    CASE
                        WHEN r.work_project_id IS NULL
                            THEN 0
                        ELSE 1
                    END AS project_specificity

                 FROM work_ticket_lifecycle_sync_rules r

                 INNER JOIN work_statuses ws
                    ON ws.id = r.work_status_id

                 WHERE r.is_active = 1
                   AND ws.is_active = 1
                   AND ws.code = ?
                   AND (
                        r.work_project_id IS NULL
                        OR r.work_project_id = ?
                   )

                 ORDER BY
                    r.rule_priority DESC,
                    project_specificity DESC,
                    r.id ASC"
            );

        $statement->execute([
            trim($resultingStatusCode),
            $workProjectId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Source links eligible for Work -> Ticket lifecycle planning.
     *
     * The bridge identity is generic at storage level. This C3 component is
     * specifically the Ticket lifecycle adapter, therefore it selects only
     * Ticket source links and does not encode any customer/project identity.
     */
    public function ticketSourceLinksForItem(
        int $workItemId
    ): array {
        $statement =
            $this->work->prepare(
                "SELECT
                    id,
                    public_reference,
                    work_item_id,
                    source_module_code,
                    source_resource_type,
                    source_reference,
                    relation_type_code,
                    metadata_json,
                    created_by_user_reference,
                    created_at,
                    updated_at
                 FROM work_item_source_links
                 WHERE work_item_id = ?
                   AND source_module_code = 'ticketing'
                   AND source_resource_type = 'ticket'
                 ORDER BY id ASC"
            );

        $statement->execute([
            $workItemId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );

        return is_array($rows)
            ? $rows
            : [];
    }

    public function attemptByIdempotencyKey(
        string $idempotencyKey
    ): ?array {
        $statement =
            $this->work->prepare(
                "SELECT *
                 FROM work_ticket_lifecycle_sync_attempts
                 WHERE idempotency_key = ?
                 LIMIT 1"
            );

        $statement->execute([
            trim($idempotencyKey),
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * Persist one pending attempt.
     *
     * Retry/executor logic is intentionally not wired in C3-A1.
     * The unique idempotency key is the final race-condition guard.
     */
    public function createAttempt(
        array $data
    ): int {
        $statement =
            $this->work->prepare(
                "INSERT INTO work_ticket_lifecycle_sync_attempts (
                    public_reference,
                    idempotency_key,
                    correlation_reference,
                    rule_id,
                    work_item_id,
                    work_activity_event_id,
                    source_link_id,
                    source_link_reference,
                    ticket_reference,
                    previous_work_status_code,
                    resulting_work_status_code,
                    ticket_action_code,
                    result_code,
                    attempt_count,
                    actor_user_reference,
                    ticket_previous_status_code,
                    ticket_resulting_status_code,
                    ticket_event_reference,
                    error_code,
                    metadata_json,
                    first_attempted_at,
                    last_attempted_at,
                    completed_at,
                    created_at,
                    updated_at
                 ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    'pending',
                    0,
                    ?,
                    NULL, NULL, NULL,
                    NULL,
                    ?,
                    NULL, NULL, NULL,
                    NOW(), NOW()
                 )"
            );

        $statement->execute([
            (string) $data['public_reference'],
            (string) $data['idempotency_key'],
            (string) $data['correlation_reference'],
            (int) $data['rule_id'],
            (int) $data['work_item_id'],
            (int) $data['work_activity_event_id'],
            (int) $data['source_link_id'],
            (string) $data['source_link_reference'],
            (string) $data['ticket_reference'],
            (string) $data['previous_work_status_code'],
            (string) $data['resulting_work_status_code'],
            (string) $data['ticket_action_code'],
            (string) $data['actor_user_reference'],
            $data['metadata_json'] ?? null,
        ]);

        return (int) $this->work->lastInsertId();
    }

    /**
     * Complete/update one durable attempt after native Ticket lifecycle
     * execution. A later C3 stage owns the executor.
     */
    public function completeAttempt(
        int $attemptId,
        array $result
    ): void {
        $statement =
            $this->work->prepare(
                "UPDATE work_ticket_lifecycle_sync_attempts
                 SET result_code = ?,
                     attempt_count = attempt_count + 1,
                     ticket_previous_status_code = ?,
                     ticket_resulting_status_code = ?,
                     ticket_event_reference = ?,
                     error_code = ?,
                     metadata_json = ?,
                     first_attempted_at = COALESCE(
                         first_attempted_at,
                         NOW()
                     ),
                     last_attempted_at = NOW(),
                     completed_at = ?,
                     updated_at = NOW()
                 WHERE id = ?"
            );

        $statement->execute([
            (string) $result['result_code'],
            $result['ticket_previous_status_code'] ?? null,
            $result['ticket_resulting_status_code'] ?? null,
            $result['ticket_event_reference'] ?? null,
            $result['error_code'] ?? null,
            $result['metadata_json'] ?? null,
            $result['completed_at'] ?? null,
            $attemptId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException(
                'work_ticket_lifecycle_sync_attempt_not_updated'
            );
        }
    }
}

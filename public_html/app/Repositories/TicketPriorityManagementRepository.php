<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

class TicketPriorityManagementRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db
            ?? (new ConnectionResolver())->resolve(
                'ticketing.primary'
            );
    }

    public function panel(
        string $publicReference,
        int $userId
    ): array {
        $publicReference = trim($publicReference);

        if ($publicReference === '' || $userId < 1) {
            return [
                'found' => false,
                'ticket' => [],
                'priorities' => [],
                'operational' => false,
                'actor_display_name' => '',
                'history' => [],
            ];
        }

        $ticket = $this->ticket($publicReference, false);

        if (!is_array($ticket)) {
            return [
                'found' => false,
                'ticket' => [],
                'priorities' => [],
                'operational' => false,
                'actor_display_name' => '',
                'history' => [],
            ];
        }

        $actor = $this->operationalActor(
            (int) $ticket['support_project_id'],
            $userId
        );

        return [
            'found' => true,
            'ticket' => $ticket,
            'priorities' => $this->priorities(),
            'operational' => is_array($actor),
            'actor_display_name' => is_array($actor)
                ? trim((string) (
                    $actor['display_name_snapshot'] ?? ''
                ))
                : '',
            'history' => $this->history(
                (int) $ticket['id']
            ),
        ];
    }

    public function change(
        string $publicReference,
        int $userId,
        string $newPriorityCode,
        string $reason
    ): array {
        $publicReference = trim($publicReference);
        $newPriorityCode = trim($newPriorityCode);
        $reason = trim($reason);

        if (
            $publicReference === ''
            || $userId < 1
            || $newPriorityCode === ''
        ) {
            throw new RuntimeException('priority_invalid');
        }

        $reasonLength = function_exists('mb_strlen')
            ? mb_strlen($reason, 'UTF-8')
            : strlen($reason);

        if (
            $reasonLength < 3
            || $reasonLength > 1000
        ) {
            throw new RuntimeException(
                'priority_reason_invalid'
            );
        }

        $actorUserReference = 'user:' . $userId;

        $this->db->beginTransaction();

        try {
            $ticket = $this->ticket(
                $publicReference,
                true
            );

            if (!is_array($ticket)) {
                throw new RuntimeException(
                    'ticket_not_found'
                );
            }

            $actor = $this->operationalActor(
                (int) $ticket['support_project_id'],
                $userId
            );

            if (!is_array($actor)) {
                throw new RuntimeException(
                    'priority_change_forbidden'
                );
            }

            $actorDisplayName = trim(
                (string) (
                    $actor['display_name_snapshot']
                    ?? ''
                )
            );

            if ($actorDisplayName === '') {
                $actorDisplayName =
                    $actorUserReference;
            }

            $priorityStatement =
                $this->db->prepare("
                    SELECT
                        code,
                        title,
                        severity,
                        color,
                        sort_order
                    FROM ticketing_priorities
                    WHERE code = ?
                      AND is_active = 1
                    LIMIT 1
                ");

            $priorityStatement->execute([
                $newPriorityCode,
            ]);

            $newPriority =
                $priorityStatement->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($newPriority)) {
                throw new RuntimeException(
                    'priority_invalid'
                );
            }

            $oldPriorityCode = trim(
                (string) (
                    $ticket['priority_code'] ?? ''
                )
            );

            if (hash_equals(
                $oldPriorityCode,
                $newPriorityCode
            )) {
                $this->db->commit();

                return [
                    'changed' => false,
                    'ticket_id' => (int) $ticket['id'],
                    'public_reference' =>
                        (string) $ticket['public_reference'],
                    'old_priority_code' =>
                        $oldPriorityCode,
                    'new_priority_code' =>
                        $newPriorityCode,
                    'event_reference' => null,
                    'sla_recalculation_required' => false,
                ];
            }

            $slaStatement =
                $this->db->prepare("
                    SELECT priority_code_snapshot
                    FROM ticketing_ticket_sla_states
                    WHERE ticket_id = ?
                    LIMIT 1
                ");

            $slaStatement->execute([
                (int) $ticket['id'],
            ]);

            $slaSnapshot =
                $slaStatement->fetchColumn();

            $slaSnapshot =
                $slaSnapshot === false
                    ? null
                    : trim((string) $slaSnapshot);

            if ($slaSnapshot === '') {
                $slaSnapshot = null;
            }

            $slaRecalculationRequired =
                $slaSnapshot !== null
                && !hash_equals(
                    $slaSnapshot,
                    $newPriorityCode
                );

            /*
             * Priority correction deliberately does NOT touch:
             * lifecycle status, last_activity_at, routing,
             * assignment, resolved_at, closed_at, or SLA state.
             */
            $updateStatement =
                $this->db->prepare("
                    UPDATE ticketing_tickets
                    SET
                        priority_code = ?,
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()
                    WHERE id = ?
                ");

            $updateStatement->execute([
                $newPriorityCode,
                $actorUserReference,
                (int) $ticket['id'],
            ]);

            if ($updateStatement->rowCount() !== 1) {
                throw new RuntimeException(
                    'priority_update_failed'
                );
            }

            $oldPriorityTitle = trim(
                (string) (
                    $ticket['priority_title']
                    ?? $oldPriorityCode
                )
            );

            $payload = json_encode(
                [
                    'old_priority_code' =>
                        $oldPriorityCode,
                    'old_priority_title' =>
                        $oldPriorityTitle,
                    'old_priority_severity' =>
                        isset($ticket['priority_severity'])
                            ? (int) $ticket['priority_severity']
                            : null,
                    'new_priority_code' =>
                        (string) $newPriority['code'],
                    'new_priority_title' =>
                        (string) $newPriority['title'],
                    'new_priority_severity' =>
                        (int) $newPriority['severity'],
                    'reason' => $reason,
                    'lifecycle_preserved' => true,
                    'routing_preserved' => true,
                    'assignment_preserved' => true,
                    'last_activity_preserved' => true,
                    'sla_priority_snapshot' =>
                        $slaSnapshot,
                    'sla_recalculation_required' =>
                        $slaRecalculationRequired,
                    'sla_recalculation_performed' => false,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );

            $eventReference =
                'TEVT-'
                . strtoupper(
                    bin2hex(random_bytes(12))
                );

            $eventStatement =
                $this->db->prepare("
                    INSERT INTO ticketing_events (
                        public_reference,
                        ticket_id,
                        event_code,
                        actor_user_reference,
                        actor_display_name_snapshot,
                        previous_status_code,
                        resulting_status_code,
                        payload_json,
                        occurred_at
                    ) VALUES (
                        ?,
                        ?,
                        'ticket_priority_changed',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        UTC_TIMESTAMP()
                    )
                ");

            $eventStatement->execute([
                $eventReference,
                (int) $ticket['id'],
                $actorUserReference,
                $actorDisplayName,
                (string) $ticket['status_code'],
                (string) $ticket['status_code'],
                $payload,
            ]);

            $this->db->commit();

            return [
                'changed' => true,
                'ticket_id' => (int) $ticket['id'],
                'public_reference' =>
                    (string) $ticket['public_reference'],
                'old_priority_code' =>
                    $oldPriorityCode,
                'new_priority_code' =>
                    $newPriorityCode,
                'event_reference' =>
                    $eventReference,
                'sla_recalculation_required' =>
                    $slaRecalculationRequired,
            ];

        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function ticket(
        string $publicReference,
        bool $forUpdate
    ): ?array {
        $sql = "
            SELECT
                t.id,
                t.public_reference,
                t.ticket_number,
                t.subject,
                t.status_code,
                t.priority_code,
                t.support_project_id,
                t.requester_user_reference,
                p.title AS priority_title,
                p.severity AS priority_severity,
                p.color AS priority_color
            FROM ticketing_tickets t
            LEFT JOIN ticketing_priorities p
                ON p.code = t.priority_code
            WHERE t.public_reference = ?
              AND t.archived_at IS NULL
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

    private function operationalActor(
        int $projectId,
        int $userId
    ): ?array {
        if ($projectId < 1 || $userId < 1) {
            return null;
        }

        $statement = $this->db->prepare("
            SELECT DISTINCT
                pm.id,
                pm.display_name_snapshot,
                pm.role_code,
                tm.staff_role_code
            FROM ticketing_support_project_members pm
            INNER JOIN ticketing_support_team_members tm
                ON tm.project_member_id = pm.id
            INNER JOIN ticketing_support_teams team
                ON team.id = tm.team_id
               AND team.project_id = pm.project_id
            WHERE pm.project_id = ?
              AND pm.user_reference = ?
              AND pm.left_at IS NULL
              AND pm.role_code IN (
                    'member',
                    'manager'
              )
              AND tm.status = 'active'
              AND tm.left_at IS NULL
              AND tm.staff_role_code IN (
                    'agent',
                    'supervisor',
                    'manager'
              )
            ORDER BY
                FIELD(
                    tm.staff_role_code,
                    'manager',
                    'supervisor',
                    'agent'
                ),
                pm.id
            LIMIT 1
        ");

        $statement->execute([
            $projectId,
            'user:' . $userId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function priorities(): array
    {
        return $this->db->query("
            SELECT
                code,
                title,
                severity,
                color,
                sort_order
            FROM ticketing_priorities
            WHERE is_active = 1
            ORDER BY
                severity DESC,
                id ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function history(int $ticketId): array
    {
        if ($ticketId < 1) {
            return [];
        }

        $statement = $this->db->prepare("
            SELECT
                id,
                actor_user_reference,
                actor_display_name_snapshot,
                payload_json,
                occurred_at
            FROM ticketing_events
            WHERE ticket_id = ?
              AND event_code = 'ticket_priority_changed'
            ORDER BY
                occurred_at DESC,
                id DESC
            LIMIT 50
        ");

        $statement->execute([$ticketId]);

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

        $history = [];

        foreach ($rows as $row) {
            $payload = json_decode(
                (string) (
                    $row['payload_json'] ?? ''
                ),
                true
            );

            if (!is_array($payload)) {
                $payload = [];
            }

            $history[] = [
                'old_priority_code' =>
                    (string) (
                        $payload['old_priority_code'] ?? ''
                    ),
                'old_priority_title' =>
                    (string) (
                        $payload['old_priority_title'] ?? ''
                    ),
                'old_priority_severity' =>
                    $payload['old_priority_severity'] ?? null,
                'new_priority_code' =>
                    (string) (
                        $payload['new_priority_code'] ?? ''
                    ),
                'new_priority_title' =>
                    (string) (
                        $payload['new_priority_title'] ?? ''
                    ),
                'new_priority_severity' =>
                    $payload['new_priority_severity'] ?? null,
                'reason' =>
                    (string) (
                        $payload['reason'] ?? ''
                    ),
                'actor_user_reference' =>
                    (string) (
                        $row['actor_user_reference'] ?? ''
                    ),
                'actor_display_name' =>
                    (string) (
                        $row['actor_display_name_snapshot']
                        ?? ''
                    ),
                'occurred_at' =>
                    (string) (
                        $row['occurred_at'] ?? ''
                    ),
                'sla_recalculation_required' =>
                    !empty(
                        $payload[
                            'sla_recalculation_required'
                        ]
                    ),
            ];
        }

        return $history;
    }
}

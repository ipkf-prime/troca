<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkItemRepository
{
    private PDO $db;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->db = ($connections ?? new ConnectionResolver())->resolve('work.primary');
    }

    public function project(string $publicReference): ?array
    {
        $statement = $this->db->prepare("
            SELECT id, public_reference, code, title, archived_at
            FROM work_projects
            WHERE public_reference = ?
            LIMIT 1
        ");
        $statement->execute([$publicReference]);
        $project = $statement->fetch(PDO::FETCH_ASSOC);

        return $project ?: null;
    }

    public function statuses(): array
    {
        $statement = $this->db->query("
            SELECT id, code, title, category, color, sort_order, is_closed
            FROM work_statuses
            WHERE is_active = 1
            ORDER BY sort_order, id
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function members(int $projectId): array
    {
        $statement = $this->db->prepare("
            SELECT user_reference, display_name_snapshot, role_code
            FROM work_project_members
            WHERE project_id = ?
              AND left_at IS NULL
            ORDER BY FIELD(role_code, 'owner', 'manager', 'member', 'observer'),
                     display_name_snapshot
        ");
        $statement->execute([$projectId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function index(int $projectId, array $filters = []): array
    {
        $where = ['wi.project_id = ?', 'wi.archived_at IS NULL'];
        $parameters = [$projectId];
        $q = trim((string) ($filters['q'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if ($q !== '') {
            $where[] = '(wi.title LIKE ? OR wi.public_reference LIKE ?)';
            $needle = '%' . $q . '%';
            $parameters[] = $needle;
            $parameters[] = $needle;
        }

        if ($type !== '') {
            $where[] = 'wi.item_type = ?';
            $parameters[] = $type;
        }

        if ($status !== '') {
            $where[] = 'ws.code = ?';
            $parameters[] = $status;
        }

        $statement = $this->db->prepare("
            SELECT
                wi.id,
                wi.public_reference,
                wi.project_id,
                wi.parent_id,
                parent.public_reference AS parent_reference,
                parent.title AS parent_title,
                wi.item_type,
                wi.sequence_number,
                wi.title,
                wi.description,
                wi.priority_code,
                wi.progress_percent,
                wi.start_at,
                wi.due_at,
                wi.completed_at,
                wi.estimate_minutes,
                wi.sort_order,
                wi.created_at,
                wi.updated_at,
                ws.code AS status_code,
                ws.title AS status_title,
                ws.color AS status_color,
                ws.is_closed,
                assignee.user_reference AS assignee_reference,
                assignee.display_name_snapshot AS assignee_name
            FROM work_items wi
            INNER JOIN work_statuses ws ON ws.id = wi.status_id
            LEFT JOIN work_items parent ON parent.id = wi.parent_id
            LEFT JOIN work_item_assignees assignee
              ON assignee.id = (
                    SELECT a.id
                    FROM work_item_assignees a
                    WHERE a.work_item_id = wi.id
                      AND a.assignment_role = 'responsible'
                      AND a.unassigned_at IS NULL
                    ORDER BY a.id DESC
                    LIMIT 1
              )
            WHERE " . implode(' AND ', $where) . "
            ORDER BY wi.sequence_number, wi.id
            LIMIT 500
        ");
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByReference(int $projectId, string $publicReference): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                wi.*,
                parent.public_reference AS parent_reference,
                ws.code AS status_code,
                ws.title AS status_title,
                ws.is_closed,
                assignee.user_reference AS assignee_reference,
                assignee.display_name_snapshot AS assignee_name
            FROM work_items wi
            INNER JOIN work_statuses ws ON ws.id = wi.status_id
            LEFT JOIN work_items parent ON parent.id = wi.parent_id
            LEFT JOIN work_item_assignees assignee
              ON assignee.id = (
                    SELECT a.id
                    FROM work_item_assignees a
                    WHERE a.work_item_id = wi.id
                      AND a.assignment_role = 'responsible'
                      AND a.unassigned_at IS NULL
                    ORDER BY a.id DESC
                    LIMIT 1
              )
            WHERE wi.project_id = ?
              AND wi.public_reference = ?
            LIMIT 1
        ");
        $statement->execute([$projectId, $publicReference]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function findById(int $projectId, int $itemId): ?array
    {
        $statement = $this->db->prepare("
            SELECT id, public_reference, parent_id, item_type, title, archived_at
            FROM work_items
            WHERE project_id = ? AND id = ?
            LIMIT 1
        ");
        $statement->execute([$projectId, $itemId]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function parentCandidates(int $projectId, ?int $exceptId = null): array
    {
        $sql = "
            SELECT id, public_reference, parent_id, item_type, sequence_number, title
            FROM work_items
            WHERE project_id = ?
              AND archived_at IS NULL
        ";
        $parameters = [$projectId];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $parameters[] = $exceptId;
        }

        $sql .= ' ORDER BY sequence_number, id';
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(
        int $projectId,
        array $data,
        string $actorReference,
        string $actorDisplayName
    ): string {
        $this->db->beginTransaction();

        try {
            $sequenceStatement = $this->db->prepare("
                SELECT COALESCE(MAX(sequence_number), 0) + 1
                FROM work_items
                WHERE project_id = ?
                FOR UPDATE
            ");
            $sequenceStatement->execute([$projectId]);
            $sequence = (int) $sequenceStatement->fetchColumn();

            $statement = $this->db->prepare("
                INSERT INTO work_items
                    (public_reference, project_id, parent_id, status_id, item_type,
                     sequence_number, title, description, priority_code, progress_percent,
                     start_at, due_at, completed_at, estimate_minutes, sort_order,
                     created_by_user_reference, updated_by_user_reference,
                     created_at, updated_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ");
            $statement->execute([
                $data['public_reference'],
                $projectId,
                $data['parent_id'],
                $data['status_id'],
                $data['item_type'],
                $sequence,
                $data['title'],
                $data['description'],
                $data['priority_code'],
                $data['progress_percent'],
                $this->dateTimeStart($data['start_date']),
                $this->dateTimeEnd($data['due_date']),
                $data['completed_at'],
                $data['estimate_minutes'],
                $sequence * 10,
                $actorReference,
                $actorReference,
            ]);
            $itemId = (int) $this->db->lastInsertId();

            $this->syncAssignee(
                $itemId,
                $data['assignee_reference'],
                $data['assignee_name'],
                $actorReference
            );
            $this->recordActivity(
                $projectId,
                $itemId,
                'work_item_created',
                $actorReference,
                $actorDisplayName,
                [
                    'public_reference' => $data['public_reference'],
                    'item_type' => $data['item_type'],
                    'title' => $data['title'],
                ]
            );

            $this->db->commit();
            return (string) $data['public_reference'];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function update(
        int $projectId,
        int $itemId,
        array $data,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare("
                UPDATE work_items
                   SET parent_id = ?,
                       status_id = ?,
                       title = ?,
                       description = ?,
                       priority_code = ?,
                       progress_percent = ?,
                       start_at = ?,
                       due_at = ?,
                       completed_at = ?,
                       estimate_minutes = ?,
                       updated_by_user_reference = ?,
                       updated_at = UTC_TIMESTAMP()
                 WHERE id = ?
                   AND project_id = ?
                   AND archived_at IS NULL
            ");
            $statement->execute([
                $data['parent_id'],
                $data['status_id'],
                $data['title'],
                $data['description'],
                $data['priority_code'],
                $data['progress_percent'],
                $this->dateTimeStart($data['start_date']),
                $this->dateTimeEnd($data['due_date']),
                $data['completed_at'],
                $data['estimate_minutes'],
                $actorReference,
                $itemId,
                $projectId,
            ]);

            $this->syncAssignee(
                $itemId,
                $data['assignee_reference'],
                $data['assignee_name'],
                $actorReference
            );
            $this->recordActivity(
                $projectId,
                $itemId,
                'work_item_updated',
                $actorReference,
                $actorDisplayName,
                [
                    'status_code' => $data['status_code'],
                    'title' => $data['title'],
                    'progress_percent' => $data['progress_percent'],
                ]
            );

            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function archive(
        int $projectId,
        int $itemId,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $children = $this->db->prepare("
            SELECT COUNT(*)
            FROM work_items
            WHERE project_id = ?
              AND parent_id = ?
              AND archived_at IS NULL
        ");
        $children->execute([$projectId, $itemId]);
        if ((int) $children->fetchColumn() > 0) {
            return false;
        }

        $statement = $this->db->prepare("
            UPDATE work_items
               SET archived_at = UTC_TIMESTAMP(),
                   updated_by_user_reference = ?,
                   updated_at = UTC_TIMESTAMP()
             WHERE id = ?
               AND project_id = ?
               AND archived_at IS NULL
        ");
        $statement->execute([$actorReference, $itemId, $projectId]);

        if ($statement->rowCount() !== 1) {
            return false;
        }

        $this->recordActivity(
            $projectId,
            $itemId,
            'work_item_archived',
            $actorReference,
            $actorDisplayName,
            null
        );

        return true;
    }

    private function syncAssignee(
        int $itemId,
        ?string $userReference,
        ?string $displayName,
        string $actorReference
    ): void {
        $clear = $this->db->prepare("
            UPDATE work_item_assignees
               SET unassigned_at = UTC_TIMESTAMP()
             WHERE work_item_id = ?
               AND assignment_role = 'responsible'
               AND unassigned_at IS NULL
        ");
        $clear->execute([$itemId]);

        if ($userReference === null || $displayName === null) {
            return;
        }

        $statement = $this->db->prepare("
            INSERT INTO work_item_assignees
                (work_item_id, user_reference, person_reference, display_name_snapshot,
                 assignment_role, assigned_by_user_reference, assigned_at, unassigned_at)
            VALUES (?, ?, NULL, ?, 'responsible', ?, UTC_TIMESTAMP(), NULL)
            ON DUPLICATE KEY UPDATE
                display_name_snapshot = VALUES(display_name_snapshot),
                assigned_by_user_reference = VALUES(assigned_by_user_reference),
                assigned_at = UTC_TIMESTAMP(),
                unassigned_at = NULL
        ");
        $statement->execute([$itemId, $userReference, $displayName, $actorReference]);
    }

    private function recordActivity(
        int $projectId,
        int $itemId,
        string $eventType,
        string $actorReference,
        string $actorDisplayName,
        ?array $payload
    ): void {
        $statement = $this->db->prepare("
            INSERT INTO work_activity_events
                (project_id, work_item_id, event_type, actor_user_reference,
                 actor_display_name_snapshot, payload_json, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
        ");
        $statement->execute([
            $projectId,
            $itemId,
            $eventType,
            $actorReference,
            $actorDisplayName,
            $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function dateTimeStart(?string $date): ?string
    {
        return $date === null ? null : $date . ' 00:00:00';
    }

    private function dateTimeEnd(?string $date): ?string
    {
        return $date === null ? null : $date . ' 23:59:59';
    }
}
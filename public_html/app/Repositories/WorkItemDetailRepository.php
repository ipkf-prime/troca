<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkItemDetailRepository
{
    private PDO $db;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->db = ($connections ?? new ConnectionResolver())->resolve('work.primary');
    }

    public function detail(string $projectReference, string $itemReference): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                p.id AS project_id,
                p.public_reference AS project_reference,
                p.title AS project_title,
                p.archived_at AS project_archived_at,
                wi.id,
                wi.public_reference,
                wi.parent_id,
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
                wi.created_by_user_reference,
                wi.updated_by_user_reference,
                wi.archived_at,
                wi.created_at,
                wi.updated_at,
                parent.public_reference AS parent_reference,
                parent.title AS parent_title,
                ws.code AS status_code,
                ws.title AS status_title,
                ws.color AS status_color,
                ws.is_closed,
                assignee.user_reference AS assignee_reference,
                assignee.display_name_snapshot AS assignee_name
            FROM work_items wi
            INNER JOIN work_projects p ON p.id = wi.project_id
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
            WHERE p.public_reference = ?
              AND wi.public_reference = ?
            LIMIT 1
        ");
        $statement->execute([$projectReference, $itemReference]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function children(int $itemId): array
    {
        $statement = $this->db->prepare("
            SELECT
                child.public_reference,
                child.item_type,
                child.sequence_number,
                child.title,
                child.priority_code,
                child.progress_percent,
                child.due_at,
                ws.title AS status_title,
                ws.is_closed
            FROM work_items child
            INNER JOIN work_statuses ws ON ws.id = child.status_id
            WHERE child.parent_id = ?
              AND child.archived_at IS NULL
            ORDER BY child.sort_order, child.sequence_number, child.id
        ");
        $statement->execute([$itemId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function checklist(int $itemId): array
    {
        $statement = $this->db->prepare("
            SELECT id, title, is_completed, completed_by_user_reference,
                   completed_at, sort_order, created_at, updated_at
            FROM work_checklist_items
            WHERE work_item_id = ?
            ORDER BY sort_order, id
        ");
        $statement->execute([$itemId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function comments(int $itemId): array
    {
        $statement = $this->db->prepare("
            SELECT public_reference, body, author_user_reference,
                   author_display_name_snapshot, edited_at, created_at
            FROM work_comments
            WHERE work_item_id = ?
              AND deleted_at IS NULL
            ORDER BY created_at, id
        ");
        $statement->execute([$itemId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function attachments(int $itemId): array
    {
        $statement = $this->db->prepare("
            SELECT public_reference, original_name, mime_type, size_bytes,
                   scan_status, uploaded_by_user_reference, created_at
            FROM work_attachments
            WHERE work_item_id = ?
              AND deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
        ");
        $statement->execute([$itemId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activities(int $itemId, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));

        $statement = $this->db->prepare("
            SELECT event_type, actor_user_reference, actor_display_name_snapshot,
                   payload_json, occurred_at
            FROM work_activity_events
            WHERE work_item_id = ?
            ORDER BY occurred_at DESC, id DESC
            LIMIT {$limit}
        ");
        $statement->execute([$itemId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addComment(
        array $item,
        string $body,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $this->db->beginTransaction();

        try {
            $reference = 'WRK-CMT-' . strtoupper(bin2hex(random_bytes(9)));
            $statement = $this->db->prepare("
                INSERT INTO work_comments
                    (public_reference, work_item_id, parent_comment_id, body,
                     author_user_reference, author_display_name_snapshot, created_at)
                VALUES (?, ?, NULL, ?, ?, ?, UTC_TIMESTAMP())
            ");
            $statement->execute([
                $reference,
                (int) $item['id'],
                $body,
                $actorReference,
                $actorDisplayName,
            ]);

            $this->recordActivity(
                (int) $item['project_id'],
                (int) $item['id'],
                'work_comment_added',
                $actorReference,
                $actorDisplayName,
                ['comment_reference' => $reference]
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

    public function addChecklistItem(
        array $item,
        string $title,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $this->db->beginTransaction();

        try {
            $order = $this->db->prepare("
                SELECT COALESCE(MAX(sort_order), 0) + 10
                FROM work_checklist_items
                WHERE work_item_id = ?
                FOR UPDATE
            ");
            $order->execute([(int) $item['id']]);
            $sortOrder = (int) $order->fetchColumn();

            $statement = $this->db->prepare("
                INSERT INTO work_checklist_items
                    (work_item_id, title, is_completed, sort_order, created_at, updated_at)
                VALUES (?, ?, 0, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ");
            $statement->execute([(int) $item['id'], $title, $sortOrder]);
            $checklistId = (int) $this->db->lastInsertId();

            $this->recordActivity(
                (int) $item['project_id'],
                (int) $item['id'],
                'work_checklist_added',
                $actorReference,
                $actorDisplayName,
                ['checklist_id' => $checklistId, 'title' => $title]
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

    public function toggleChecklistItem(
        array $item,
        int $checklistId,
        bool $completed,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $this->db->beginTransaction();

        try {
            $find = $this->db->prepare("
                SELECT id, title
                FROM work_checklist_items
                WHERE id = ? AND work_item_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $find->execute([$checklistId, (int) $item['id']]);
            $checklist = $find->fetch(PDO::FETCH_ASSOC);

            if (!$checklist) {
                $this->db->rollBack();
                return false;
            }

            $statement = $this->db->prepare("
                UPDATE work_checklist_items
                   SET is_completed = ?,
                       completed_by_user_reference = ?,
                       completed_at = ?,
                       updated_at = UTC_TIMESTAMP()
                 WHERE id = ? AND work_item_id = ?
            ");
            $statement->execute([
                $completed ? 1 : 0,
                $completed ? $actorReference : null,
                $completed ? gmdate('Y-m-d H:i:s') : null,
                $checklistId,
                (int) $item['id'],
            ]);

            $this->recordActivity(
                (int) $item['project_id'],
                (int) $item['id'],
                'work_checklist_toggled',
                $actorReference,
                $actorDisplayName,
                [
                    'checklist_id' => $checklistId,
                    'title' => (string) $checklist['title'],
                    'completed' => $completed,
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

    public function addAttachment(
        array $item,
        array $attachment,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare("
                INSERT INTO work_attachments
                    (public_reference, work_item_id, storage_disk, storage_key,
                     original_name, mime_type, size_bytes, checksum_sha256,
                     scan_status, uploaded_by_user_reference, created_at)
                VALUES (?, ?, 'private', ?, ?, ?, ?, ?, 'pending', ?, UTC_TIMESTAMP())
            ");
            $statement->execute([
                $attachment['public_reference'],
                (int) $item['id'],
                $attachment['storage_key'],
                $attachment['original_name'],
                $attachment['mime_type'],
                $attachment['size_bytes'],
                $attachment['checksum_sha256'],
                $actorReference,
            ]);

            $this->recordActivity(
                (int) $item['project_id'],
                (int) $item['id'],
                'work_attachment_uploaded',
                $actorReference,
                $actorDisplayName,
                [
                    'attachment_reference' => $attachment['public_reference'],
                    'original_name' => $attachment['original_name'],
                    'size_bytes' => $attachment['size_bytes'],
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

    public function attachment(
        string $projectReference,
        string $itemReference,
        string $attachmentReference
    ): ?array {
        $statement = $this->db->prepare("
            SELECT
                wa.public_reference,
                wa.storage_key,
                wa.original_name,
                wa.mime_type,
                wa.size_bytes,
                wa.checksum_sha256,
                wa.scan_status
            FROM work_attachments wa
            INNER JOIN work_items wi ON wi.id = wa.work_item_id
            INNER JOIN work_projects p ON p.id = wi.project_id
            WHERE p.public_reference = ?
              AND wi.public_reference = ?
              AND wa.public_reference = ?
              AND wa.deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute([
            $projectReference,
            $itemReference,
            $attachmentReference,
        ]);
        $attachment = $statement->fetch(PDO::FETCH_ASSOC);

        return $attachment ?: null;
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
            $payload === null
                ? null
                : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}

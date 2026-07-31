<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkSettingsRepository
{
    private PDO $db;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->db = ($connections ?? new ConnectionResolver())->resolve('work.primary');
    }

    public function referenceGroups(): array
    {
        $statement = $this->db->query("
            SELECT g.id, g.code, g.title, g.description, g.management_mode,
                   g.sort_order, g.is_active,
                   COUNT(i.id) AS item_count
            FROM module_reference_groups g
            LEFT JOIN module_reference_items i ON i.group_id = g.id
            WHERE g.module_code = 'work'
            GROUP BY g.id
            ORDER BY g.sort_order, g.id
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function referenceGroup(string $groupCode): ?array
    {
        $statement = $this->db->prepare("
            SELECT id, code, title, description, management_mode,
                   sort_order, is_active
            FROM module_reference_groups
            WHERE module_code = 'work' AND code = ?
            LIMIT 1
        ");
        $statement->execute([$groupCode]);
        $group = $statement->fetch(PDO::FETCH_ASSOC);

        return $group ?: null;
    }

    public function referenceItems(int $groupId): array
    {
        $statement = $this->db->prepare("
            SELECT id, code, title_fa, title_en, color, sort_order,
                   is_active, is_system, is_locked, metadata_json,
                   created_at, updated_at
            FROM module_reference_items
            WHERE group_id = ?
            ORDER BY sort_order, id
        ");
        $statement->execute([$groupId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function referenceItem(int $groupId, int $itemId): ?array
    {
        $statement = $this->db->prepare("
            SELECT id, group_id, code, title_fa, title_en, color, sort_order,
                   is_active, is_system, is_locked, metadata_json,
                   created_at, updated_at
            FROM module_reference_items
            WHERE group_id = ? AND id = ?
            LIMIT 1
        ");
        $statement->execute([$groupId, $itemId]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function createReferenceItem(
        array $group,
        array $data,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare("
                INSERT INTO module_reference_items
                    (group_id, code, title_fa, title_en, color, sort_order,
                     is_active, is_system, is_locked, metadata_json,
                     created_by_user_reference, updated_by_user_reference,
                     created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NULL, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ");
            $statement->execute([
                (int) $group['id'],
                $data['code'],
                $data['title_fa'],
                $data['title_en'],
                $data['color'],
                $data['sort_order'],
                $data['is_active'],
                $actorReference,
                $actorReference,
            ]);

            $after = $this->referenceItem((int) $group['id'], (int) $this->db->lastInsertId());
            $this->audit(
                (string) $group['code'],
                (string) $data['code'],
                'create',
                $actorReference,
                $actorDisplayName,
                null,
                $after
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

    public function updateReferenceItem(
        array $group,
        int $itemId,
        array $data,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $this->db->beginTransaction();

        try {
            $before = $this->referenceItem((int) $group['id'], $itemId);
            if ($before === null) {
                $this->db->rollBack();
                return false;
            }

            $statement = $this->db->prepare("
                UPDATE module_reference_items
                   SET title_fa = ?,
                       title_en = ?,
                       color = ?,
                       sort_order = ?,
                       is_active = ?,
                       updated_by_user_reference = ?,
                       updated_at = UTC_TIMESTAMP()
                 WHERE group_id = ? AND id = ?
            ");
            $statement->execute([
                $data['title_fa'],
                $data['title_en'],
                $data['color'],
                $data['sort_order'],
                $data['is_active'],
                $actorReference,
                (int) $group['id'],
                $itemId,
            ]);

            $after = $this->referenceItem((int) $group['id'], $itemId);
            $this->audit(
                (string) $group['code'],
                (string) $before['code'],
                'update',
                $actorReference,
                $actorDisplayName,
                $before,
                $after
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

    public function workStatuses(): array
    {
        $statement = $this->db->query("
            SELECT
                ws.id, ws.code, ws.title, ws.category, ws.color,
                ws.sort_order, ws.is_closed, ws.is_system, ws.is_active,
                ws.created_at, ws.updated_at,
                (
                    SELECT COUNT(*)
                    FROM work_items wi
                    WHERE wi.status_id = ws.id
                ) AS usage_count
            FROM work_statuses ws
            ORDER BY ws.sort_order, ws.id
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function workStatus(int $statusId): ?array
    {
        $statement = $this->db->prepare("
            SELECT id, code, title, category, color, sort_order,
                   is_closed, is_system, is_active, created_at, updated_at
            FROM work_statuses
            WHERE id = ?
            LIMIT 1
        ");
        $statement->execute([$statusId]);
        $status = $statement->fetch(PDO::FETCH_ASSOC);

        return $status ?: null;
    }

    public function createWorkStatus(
        array $data,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare("
                INSERT INTO work_statuses
                    (code, title, category, color, sort_order,
                     is_closed, is_system, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ");
            $statement->execute([
                $data['code'],
                $data['title'],
                $data['category'],
                $data['color'],
                $data['sort_order'],
                $data['is_closed'],
                $data['is_active'],
            ]);

            $after = $this->workStatus((int) $this->db->lastInsertId());
            $this->audit(
                'item_status',
                (string) $data['code'],
                'create',
                $actorReference,
                $actorDisplayName,
                null,
                $after
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

    public function updateWorkStatus(
        int $statusId,
        array $data,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $this->db->beginTransaction();

        try {
            $before = $this->workStatus($statusId);
            if ($before === null) {
                $this->db->rollBack();
                return false;
            }

            $statement = $this->db->prepare("
                UPDATE work_statuses
                   SET title = ?,
                       category = ?,
                       color = ?,
                       sort_order = ?,
                       is_closed = ?,
                       is_active = ?,
                       updated_at = UTC_TIMESTAMP()
                 WHERE id = ?
            ");
            $statement->execute([
                $data['title'],
                $data['category'],
                $data['color'],
                $data['sort_order'],
                $data['is_closed'],
                $data['is_active'],
                $statusId,
            ]);

            $after = $this->workStatus($statusId);
            $this->audit(
                'item_status',
                (string) $before['code'],
                'update',
                $actorReference,
                $actorDisplayName,
                $before,
                $after
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

    public function workStatusUsageCount(int $statusId): int
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM work_items
            WHERE status_id = ?
        ");
        $statement->execute([$statusId]);

        return (int) $statement->fetchColumn();
    }

    public function referenceUsageCount(string $groupCode, string $itemCode): int
    {
        [$table, $column] = match ($groupCode) {
            'project_status' => ['work_projects', 'status_code'],
            'project_visibility' => ['work_projects', 'visibility_code'],
            'item_priority' => ['work_items', 'priority_code'],
            'item_type' => ['work_items', 'item_type'],
            default => [null, null],
        };

        if ($table === null || $column === null) {
            return 0;
        }

        $statement = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
        $statement->execute([$itemCode]);

        return (int) $statement->fetchColumn();
    }

    private function audit(
        string $groupCode,
        ?string $itemCode,
        string $actionCode,
        string $actorReference,
        string $actorDisplayName,
        ?array $before,
        ?array $after
    ): void {
        $statement = $this->db->prepare("
            INSERT INTO module_reference_audit_events
                (module_code, group_code, item_code, action_code,
                 actor_user_reference, actor_display_name_snapshot,
                 before_json, after_json, occurred_at)
            VALUES ('work', ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
        ");
        $statement->execute([
            $groupCode,
            $itemCode,
            $actionCode,
            $actorReference,
            $actorDisplayName,
            $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}

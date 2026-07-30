<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkProjectRepository
{
    private PDO $db;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $this->db = ($connections ?? new ConnectionResolver())->resolve('work.primary');
    }

    public function index(array $filters = []): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $where = [];
        $parameters = [];

        if ($status === 'archived') {
            $where[] = 'p.archived_at IS NOT NULL';
        } else {
            $where[] = 'p.archived_at IS NULL';

            if ($status !== '') {
                $where[] = 'p.status_code = ?';
                $parameters[] = $status;
            }
        }

        if ($q !== '') {
            $where[] = '(
                p.title LIKE ?
                OR p.code LIKE ?
                OR p.public_reference LIKE ?
                OR p.organization_snapshot LIKE ?
            )';
            $needle = '%' . $q . '%';
            array_push($parameters, $needle, $needle, $needle, $needle);
        }

        $statement = $this->db->prepare("
            SELECT
                p.id,
                p.public_reference,
                p.code,
                p.title,
                p.description,
                p.owner_user_reference,
                MAX(CASE WHEN pm.user_reference = p.owner_user_reference AND pm.left_at IS NULL
                    THEN pm.display_name_snapshot
                END) AS owner_display_name,
                p.organization_reference,
                p.organization_snapshot,
                p.start_date,
                p.target_date,
                p.status_code,
                p.visibility_code,
                p.archived_at,
                p.created_at,
                p.updated_at,
                COUNT(DISTINCT CASE WHEN pm.left_at IS NULL THEN pm.id END) AS member_count,
                COUNT(DISTINCT CASE WHEN wi.archived_at IS NULL THEN wi.id END) AS item_count,
                COUNT(DISTINCT CASE
                    WHEN wi.archived_at IS NULL AND ws.is_closed = 0 THEN wi.id
                END) AS open_item_count
            FROM work_projects p
            LEFT JOIN work_project_members pm
              ON pm.project_id = p.id
            LEFT JOIN work_items wi
              ON wi.project_id = p.id
            LEFT JOIN work_statuses ws
              ON ws.id = wi.status_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY
                p.id, p.public_reference, p.code, p.title, p.description,
                p.owner_user_reference, p.organization_reference, p.organization_snapshot,
                p.start_date, p.target_date, p.status_code, p.visibility_code,
                p.archived_at, p.created_at, p.updated_at
            ORDER BY p.updated_at DESC, p.id DESC
            LIMIT 100
        ");
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByReference(string $publicReference): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                p.*,
                (SELECT pm_owner.display_name_snapshot
                   FROM work_project_members pm_owner
                  WHERE pm_owner.project_id = p.id
                    AND pm_owner.user_reference = p.owner_user_reference
                    AND pm_owner.left_at IS NULL
                  LIMIT 1) AS owner_display_name,
                (SELECT COUNT(*)
                   FROM work_project_members pm
                  WHERE pm.project_id = p.id
                    AND pm.left_at IS NULL) AS member_count,
                (SELECT COUNT(*)
                   FROM work_items wi
                  WHERE wi.project_id = p.id
                    AND wi.archived_at IS NULL) AS item_count,
                (SELECT COUNT(*)
                   FROM work_items wi
                   INNER JOIN work_statuses ws ON ws.id = wi.status_id
                  WHERE wi.project_id = p.id
                    AND wi.archived_at IS NULL
                    AND ws.is_closed = 0) AS open_item_count
            FROM work_projects p
            WHERE p.public_reference = ?
            LIMIT 1
        ");
        $statement->execute([$publicReference]);
        $project = $statement->fetch(PDO::FETCH_ASSOC);

        return $project ?: null;
    }

    public function codeExists(string $code, ?string $exceptReference = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM work_projects WHERE code = ?';
        $parameters = [$code];

        if ($exceptReference !== null) {
            $sql .= ' AND public_reference <> ?';
            $parameters[] = $exceptReference;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(array $data, string $actorReference, string $actorDisplayName): string
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare("
                INSERT INTO work_projects
                    (public_reference, code, title, description, owner_user_reference,
                     organization_reference, organization_snapshot, start_date, target_date,
                     status_code, visibility_code, created_by_user_reference,
                     created_at, updated_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ");
            $statement->execute([
                $data['public_reference'],
                $data['code'],
                $data['title'],
                $data['description'],
                $data['owner_user_reference'],
                $data['organization_reference'],
                $data['organization_snapshot'],
                $data['start_date'],
                $data['target_date'],
                $data['status_code'],
                $data['visibility_code'],
                $actorReference,
            ]);

            $projectId = (int) $this->db->lastInsertId();
            $member = $this->db->prepare("
                INSERT INTO work_project_members
                    (project_id, user_reference, person_reference, display_name_snapshot,
                     role_code, joined_at, left_at)
                VALUES (?, ?, NULL, ?, 'owner', UTC_TIMESTAMP(), NULL)
            ");
            $member->execute([$projectId, $data['owner_user_reference'], $actorDisplayName]);

            $this->recordActivity(
                $projectId,
                'project_created',
                $actorReference,
                $actorDisplayName,
                ['code' => $data['code'], 'title' => $data['title']]
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
        string $publicReference,
        array $data,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $project = $this->findByReference($publicReference);
        if ($project === null || !empty($project['archived_at'])) {
            return false;
        }

        $statement = $this->db->prepare("
            UPDATE work_projects
               SET code = ?,
                   title = ?,
                   description = ?,
                   organization_reference = ?,
                   organization_snapshot = ?,
                   start_date = ?,
                   target_date = ?,
                   status_code = ?,
                   visibility_code = ?,
                   updated_at = UTC_TIMESTAMP()
             WHERE public_reference = ?
               AND archived_at IS NULL
        ");
        $statement->execute([
            $data['code'],
            $data['title'],
            $data['description'],
            $data['organization_reference'],
            $data['organization_snapshot'],
            $data['start_date'],
            $data['target_date'],
            $data['status_code'],
            $data['visibility_code'],
            $publicReference,
        ]);

        $this->recordActivity(
            (int) $project['id'],
            'project_updated',
            $actorReference,
            $actorDisplayName,
            ['code' => $data['code'], 'title' => $data['title']]
        );

        return true;
    }

    public function archive(string $publicReference, string $actorReference, string $actorDisplayName): bool
    {
        $project = $this->findByReference($publicReference);
        if ($project === null || !empty($project['archived_at'])) {
            return false;
        }

        $statement = $this->db->prepare("
            UPDATE work_projects
               SET archived_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE public_reference = ? AND archived_at IS NULL
        ");
        $statement->execute([$publicReference]);

        $this->recordActivity(
            (int) $project['id'],
            'project_archived',
            $actorReference,
            $actorDisplayName,
            null
        );

        return $statement->rowCount() === 1;
    }

    public function restore(string $publicReference, string $actorReference, string $actorDisplayName): bool
    {
        $project = $this->findByReference($publicReference);
        if ($project === null || empty($project['archived_at'])) {
            return false;
        }

        $statement = $this->db->prepare("
            UPDATE work_projects
               SET archived_at = NULL, updated_at = UTC_TIMESTAMP()
             WHERE public_reference = ? AND archived_at IS NOT NULL
        ");
        $statement->execute([$publicReference]);

        $this->recordActivity(
            (int) $project['id'],
            'project_restored',
            $actorReference,
            $actorDisplayName,
            null
        );

        return $statement->rowCount() === 1;
    }

    private function recordActivity(
        int $projectId,
        string $eventType,
        string $actorReference,
        string $actorDisplayName,
        ?array $payload
    ): void {
        $statement = $this->db->prepare("
            INSERT INTO work_activity_events
                (project_id, work_item_id, event_type, actor_user_reference,
                 actor_display_name_snapshot, payload_json, occurred_at)
            VALUES (?, NULL, ?, ?, ?, ?, UTC_TIMESTAMP())
        ");
        $statement->execute([
            $projectId,
            $eventType,
            $actorReference,
            $actorDisplayName,
            $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}

<?php

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

class WorkProjectMemberRepository
{
    private PDO $work;
    private PDO $core;

    public function __construct(?ConnectionResolver $connections = null)
    {
        $resolver = $connections ?? new ConnectionResolver();
        $this->work = $resolver->resolve('work.primary');
        $this->core = $resolver->resolve('core.primary');
    }

    public function project(string $publicReference): ?array
    {
        $statement = $this->work->prepare("\n            SELECT id, public_reference, code, title, owner_user_reference, archived_at\n            FROM work_projects\n            WHERE public_reference = ?\n            LIMIT 1\n        ");
        $statement->execute([$publicReference]);
        $project = $statement->fetch(PDO::FETCH_ASSOC);

        return $project ?: null;
    }

    public function members(int $projectId): array
    {
        $statement = $this->work->prepare("\n            SELECT id, user_reference, person_reference, display_name_snapshot,\n                   role_code, joined_at, left_at\n            FROM work_project_members\n            WHERE project_id = ?\n              AND left_at IS NULL\n            ORDER BY FIELD(role_code, 'owner', 'manager', 'member', 'observer'),\n                     display_name_snapshot, id\n        ");
        $statement->execute([$projectId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeUsers(): array
    {
        $statement = $this->core->query("\n            SELECT users.id, users.username, users.email, users.mobile, persons.full_name\n            FROM users\n            LEFT JOIN persons ON persons.id = users.person_id\n            WHERE users.status = 'active'\n            ORDER BY COALESCE(NULLIF(persons.full_name, ''), users.username, users.email), users.id\n            LIMIT 500\n        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeUser(int $userId): ?array
    {
        $statement = $this->core->prepare("\n            SELECT users.id, users.username, users.email, users.mobile, persons.full_name\n            FROM users\n            LEFT JOIN persons ON persons.id = users.person_id\n            WHERE users.id = ?\n              AND users.status = 'active'\n            LIMIT 1\n        ");
        $statement->execute([$userId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function saveMember(
        int $projectId,
        int $userId,
        string $displayName,
        string $roleCode,
        string $actorReference,
        string $actorDisplayName
    ): void {
        $userReference = 'user:' . $userId;
        $statement = $this->work->prepare("\n            SELECT id, role_code, left_at\n            FROM work_project_members\n            WHERE project_id = ? AND user_reference = ?\n            ORDER BY id DESC\n            LIMIT 1\n        ");
        $statement->execute([$projectId, $userReference]);
        $existing = $statement->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ((string) $existing['role_code'] === 'owner') {
                return;
            }

            $update = $this->work->prepare("\n                UPDATE work_project_members\n                SET display_name_snapshot = ?, role_code = ?,\n                    joined_at = CASE WHEN left_at IS NULL THEN joined_at ELSE UTC_TIMESTAMP() END,\n                    left_at = NULL\n                WHERE id = ?\n            ");
            $update->execute([$displayName, $roleCode, (int) $existing['id']]);
        } else {
            $insert = $this->work->prepare("\n                INSERT INTO work_project_members\n                    (project_id, user_reference, person_reference, display_name_snapshot,\n                     role_code, joined_at, left_at)\n                VALUES (?, ?, NULL, ?, ?, UTC_TIMESTAMP(), NULL)\n            ");
            $insert->execute([$projectId, $userReference, $displayName, $roleCode]);
        }

        $this->recordActivity($projectId, 'project_member_saved', $actorReference, $actorDisplayName, [
            'user_reference' => $userReference,
            'display_name' => $displayName,
            'role_code' => $roleCode,
        ]);
    }

    public function updateRole(
        int $projectId,
        int $memberId,
        string $roleCode,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $member = $this->member($projectId, $memberId);
        if ($member === null || (string) $member['role_code'] === 'owner') {
            return false;
        }
        if ((string) $member['role_code'] === $roleCode) {
            return true;
        }

        $statement = $this->work->prepare("\n            UPDATE work_project_members\n            SET role_code = ?\n            WHERE id = ? AND project_id = ? AND left_at IS NULL AND role_code <> 'owner'\n        ");
        $statement->execute([$roleCode, $memberId, $projectId]);

        if ($statement->rowCount() !== 1) {
            return false;
        }

        $this->recordActivity($projectId, 'project_member_role_changed', $actorReference, $actorDisplayName, [
            'member_id' => $memberId,
            'user_reference' => $member['user_reference'],
            'role_code' => $roleCode,
        ]);

        return true;
    }

    public function removeMember(
        int $projectId,
        int $memberId,
        string $actorReference,
        string $actorDisplayName
    ): bool {
        $member = $this->member($projectId, $memberId);
        if ($member === null || (string) $member['role_code'] === 'owner') {
            return false;
        }

        $statement = $this->work->prepare("\n            UPDATE work_project_members\n            SET left_at = UTC_TIMESTAMP()\n            WHERE id = ? AND project_id = ? AND left_at IS NULL AND role_code <> 'owner'\n        ");
        $statement->execute([$memberId, $projectId]);

        if ($statement->rowCount() !== 1) {
            return false;
        }

        $this->recordActivity($projectId, 'project_member_removed', $actorReference, $actorDisplayName, [
            'member_id' => $memberId,
            'user_reference' => $member['user_reference'],
            'display_name' => $member['display_name_snapshot'],
        ]);

        return true;
    }

    private function member(int $projectId, int $memberId): ?array
    {
        $statement = $this->work->prepare("\n            SELECT id, user_reference, display_name_snapshot, role_code, left_at\n            FROM work_project_members\n            WHERE id = ? AND project_id = ? AND left_at IS NULL\n            LIMIT 1\n        ");
        $statement->execute([$memberId, $projectId]);
        $member = $statement->fetch(PDO::FETCH_ASSOC);

        return $member ?: null;
    }

    private function recordActivity(
        int $projectId,
        string $eventType,
        string $actorReference,
        string $actorDisplayName,
        array $payload
    ): void {
        $statement = $this->work->prepare("\n            INSERT INTO work_activity_events\n                (project_id, work_item_id, event_type, actor_user_reference,\n                 actor_display_name_snapshot, payload_json, occurred_at)\n            VALUES (?, NULL, ?, ?, ?, ?, UTC_TIMESTAMP())\n        ");
        $statement->execute([
            $projectId,
            $eventType,
            $actorReference,
            $actorDisplayName,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}

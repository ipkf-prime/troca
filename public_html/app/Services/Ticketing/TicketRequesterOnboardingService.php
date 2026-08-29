<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Support\ApplicationUrlRegistry;
use PDO;
use Throwable;

final class TicketRequesterOnboardingService
{
    private PDO $core;
    private PDO $ticketing;


    public function __construct(
        ?ConnectionResolver $resolver = null
    ) {
        $resolver ??= new ConnectionResolver();

        $this->core =
            $resolver->resolve('core.primary');

        $this->ticketing =
            $resolver->resolve('ticketing.primary');
    }


    public function page(int $userId): array
    {
        $reference = 'user:' . $userId;

        $memberships =
            $this->memberships($reference);

        $memberIds =
            array_map(
                static fn (array $row): int =>
                    (int) $row['id'],
                $memberships
            );

        $urls =
            new ApplicationUrlRegistry();

        return [
            'memberships' =>
                $memberships,

            'open_projects' =>
                $this->openProjects(
                    $memberIds
                ),

            'invite_enabled' =>
                $this->inviteAvailable(),

            'my_tickets_url' =>
                $urls->ticketing(
                    '/admin/ticketing/tickets'
                ),

            'create_ticket_url' =>
                $urls->ticketing(
                    '/admin/ticketing/tickets/create'
                ),
        ];
    }


    public function hasMembership(
        int $userId
    ): bool {
        if ($userId < 1) {
            return false;
        }

        $q =
            $this->ticketing->prepare("
                SELECT COUNT(*)
                FROM ticketing_support_project_members AS members
                INNER JOIN ticketing_support_projects AS projects
                  ON projects.id = members.project_id
                WHERE members.user_reference = ?
                  AND members.left_at IS NULL
                  AND projects.is_active = 1
                  AND projects.archived_at IS NULL
            ");

        $q->execute([
            'user:' . $userId,
        ]);

        return
            (int) $q->fetchColumn() > 0;
    }


    public function joinOpen(
        string $projectReference,
        int $userId
    ): array {
        return
            $this->joinProject(
                $projectReference,
                $userId,
                true
            );
    }


    public function joinWithCode(
        string $code,
        int $userId
    ): array {
        $normalized =
            $this->normalizeCode(
                $code
            );

        if (strlen($normalized) < 10) {
            return [
                'ok' => false,
                'error' =>
                    'requester_invite_invalid',
            ];
        }

        $this->ticketing->beginTransaction();

        try {
            $q =
                $this->ticketing->prepare("
                    SELECT
                        invites.id AS invite_id,
                        invites.project_id,
                        invites.status_code,
                        invites.max_uses,
                        invites.use_count,
                        invites.valid_from,
                        invites.valid_until,

                        projects.id,
                        projects.public_reference,
                        projects.code,
                        projects.title,

                        access.invite_join_enabled

                    FROM ticketing_support_project_invites AS invites

                    INNER JOIN ticketing_support_projects AS projects
                      ON projects.id = invites.project_id

                    INNER JOIN ticketing_support_project_requester_access AS access
                      ON access.project_id = projects.id

                    WHERE invites.code_hash = ?
                      AND projects.is_active = 1
                      AND projects.archived_at IS NULL

                    LIMIT 1
                    FOR UPDATE
                ");

            $q->execute([
                hash(
                    'sha256',
                    $normalized
                ),
            ]);

            $invite =
                $q->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($invite)) {
                throw new \RuntimeException(
                    'requester_invite_invalid'
                );
            }

            if (
                (string) $invite['status_code']
                    !== 'active'
                ||
                (int) $invite['invite_join_enabled']
                    !== 1
            ) {
                throw new \RuntimeException(
                    'requester_invite_inactive'
                );
            }

            $now = time();

            if (
                !empty($invite['valid_from'])
                &&
                strtotime(
                    (string) $invite['valid_from']
                ) > $now
            ) {
                throw new \RuntimeException(
                    'requester_invite_not_started'
                );
            }

            if (
                !empty($invite['valid_until'])
                &&
                strtotime(
                    (string) $invite['valid_until']
                ) < $now
            ) {
                throw new \RuntimeException(
                    'requester_invite_expired'
                );
            }

            if (
                $invite['max_uses'] !== null
                &&
                (int) $invite['use_count']
                    >=
                (int) $invite['max_uses']
            ) {
                throw new \RuntimeException(
                    'requester_invite_exhausted'
                );
            }

            $membership =
                $this->ensureMembership(
                    (int) $invite['project_id'],
                    $userId
                );

            $userReference =
                'user:' . $userId;

            $insertUse =
                $this->ticketing->prepare("
                    INSERT IGNORE INTO
                        ticketing_support_project_invite_uses
                    (
                        invite_id,
                        project_member_id,
                        user_reference,
                        used_at,
                        created_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        UTC_TIMESTAMP(),
                        UTC_TIMESTAMP()
                    )
                ");

            $insertUse->execute([
                (int) $invite['invite_id'],
                (int) $membership['id'],
                $userReference,
            ]);

            if ($insertUse->rowCount() === 1) {
                $update =
                    $this->ticketing->prepare("
                        UPDATE ticketing_support_project_invites
                        SET
                            use_count = use_count + 1,
                            updated_at = UTC_TIMESTAMP()
                        WHERE id = ?
                    ");

                $update->execute([
                    (int) $invite['invite_id'],
                ]);
            }

            $this->ticketing->commit();

            return [
                'ok' => true,
                'membership' =>
                    $membership,
            ];

        } catch (Throwable $exception) {

            if ($this->ticketing->inTransaction()) {
                $this->ticketing->rollBack();
            }

            return [
                'ok' => false,
                'error' =>
                    $exception->getMessage(),
            ];
        }
    }


    public function createInvite(
        string $projectReference,
        int $actorUserId,
        ?int $maxUses = null,
        ?string $validUntil = null
    ): array {
        $q =
            $this->ticketing->prepare("
                SELECT
                    projects.id,
                    projects.code,
                    projects.title
                FROM ticketing_support_projects AS projects
                INNER JOIN ticketing_support_project_requester_access AS access
                  ON access.project_id = projects.id
                WHERE projects.public_reference = ?
                  AND projects.is_active = 1
                  AND projects.archived_at IS NULL
                  AND access.invite_join_enabled = 1
                LIMIT 1
            ");

        $q->execute([
            trim($projectReference),
        ]);

        $project =
            $q->fetch(PDO::FETCH_ASSOC);

        if (!is_array($project)) {
            throw new \RuntimeException(
                'requester_project_not_found'
            );
        }

        $prefix =
            strtoupper(
                preg_replace(
                    '/[^A-Za-z0-9]/',
                    '',
                    (string) $project['code']
                )
                ?: 'TKT'
            );

        $raw =
            strtoupper(
                bin2hex(
                    random_bytes(8)
                )
            );

        $code =
            substr($prefix, 0, 8)
            . '-'
            . implode(
                '-',
                str_split($raw, 4)
            );

        $reference =
            'TINV-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );

        $q =
            $this->ticketing->prepare("
                INSERT INTO ticketing_support_project_invites
                (
                    public_reference,
                    project_id,
                    code_hash,
                    code_preview,
                    status_code,
                    max_uses,
                    use_count,
                    valid_from,
                    valid_until,
                    created_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'active',
                    ?,
                    0,
                    UTC_TIMESTAMP(),
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $q->execute([
            $reference,
            (int) $project['id'],
            hash(
                'sha256',
                $this->normalizeCode($code)
            ),
            substr($code, 0, 10) . '…',
            $maxUses,
            $validUntil,
            'user:' . $actorUserId,
        ]);

        return [
            'public_reference' =>
                $reference,

            'project_title' =>
                (string) $project['title'],

            'code' =>
                $code,

            'max_uses' =>
                $maxUses,

            'valid_until' =>
                $validUntil,
        ];
    }


    private function joinProject(
        string $reference,
        int $userId,
        bool $requireOpenJoin
    ): array {
        $this->ticketing->beginTransaction();

        try {
            $q =
                $this->ticketing->prepare("
                    SELECT
                        projects.id,
                        access.self_join_enabled
                    FROM ticketing_support_projects AS projects
                    INNER JOIN ticketing_support_project_requester_access AS access
                      ON access.project_id = projects.id
                    WHERE projects.public_reference = ?
                      AND projects.is_active = 1
                      AND projects.archived_at IS NULL
                    LIMIT 1
                    FOR UPDATE
                ");

            $q->execute([
                trim($reference),
            ]);

            $project =
                $q->fetch(PDO::FETCH_ASSOC);

            if (!is_array($project)) {
                throw new \RuntimeException(
                    'requester_project_not_found'
                );
            }

            if (
                $requireOpenJoin
                &&
                (int) $project['self_join_enabled']
                    !== 1
            ) {
                throw new \RuntimeException(
                    'requester_open_join_disabled'
                );
            }

            $membership =
                $this->ensureMembership(
                    (int) $project['id'],
                    $userId
                );

            $this->ticketing->commit();

            return [
                'ok' => true,
                'membership' =>
                    $membership,
            ];

        } catch (Throwable $exception) {

            if ($this->ticketing->inTransaction()) {
                $this->ticketing->rollBack();
            }

            return [
                'ok' => false,
                'error' =>
                    $exception->getMessage(),
            ];
        }
    }


    private function ensureMembership(
        int $projectId,
        int $userId
    ): array {
        $user =
            $this->coreUser($userId);

        if ($user === null) {
            throw new \RuntimeException(
                'requester_user_not_found'
            );
        }

        $reference =
            'user:' . $userId;

        $q =
            $this->ticketing->prepare("
                SELECT id, role_code, left_at
                FROM ticketing_support_project_members
                WHERE project_id = ?
                  AND user_reference = ?
                LIMIT 1
                FOR UPDATE
            ");

        $q->execute([
            $projectId,
            $reference,
        ]);

        $member =
            $q->fetch(PDO::FETCH_ASSOC);

        if (is_array($member)) {

            if (empty($member['left_at'])) {
                return [
                    'id' =>
                        (int) $member['id'],

                    'state' =>
                        'already_active',

                    'role_code' =>
                        (string) $member['role_code'],
                ];
            }

            $role =
                in_array(
                    (string) $member['role_code'],
                    ['manager', 'member'],
                    true
                )
                    ? (string) $member['role_code']
                    : 'requester';

            $update =
                $this->ticketing->prepare("
                    UPDATE ticketing_support_project_members
                    SET
                        display_name_snapshot = ?,
                        role_code = ?,
                        joined_at = UTC_TIMESTAMP(),
                        left_at = NULL,
                        updated_by_user_reference = ?,
                        updated_at = UTC_TIMESTAMP()
                    WHERE id = ?
                ");

            $update->execute([
                (string) $user['display_name'],
                $role,
                $reference,
                (int) $member['id'],
            ]);

            return [
                'id' =>
                    (int) $member['id'],

                'state' =>
                    'reactivated',

                'role_code' =>
                    $role,
            ];
        }

        $insert =
            $this->ticketing->prepare("
                INSERT INTO ticketing_support_project_members
                (
                    project_id,
                    participant_id,
                    user_reference,
                    person_reference,
                    display_name_snapshot,
                    role_code,
                    joined_at,
                    left_at,
                    created_by_user_reference,
                    updated_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    NULL,
                    ?,
                    NULL,
                    ?,
                    'requester',
                    UTC_TIMESTAMP(),
                    NULL,
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $insert->execute([
            $projectId,
            $reference,
            (string) $user['display_name'],
            $reference,
            $reference,
        ]);

        return [
            'id' =>
                (int) $this->ticketing->lastInsertId(),

            'state' =>
                'created',

            'role_code' =>
                'requester',
        ];
    }


    private function coreUser(
        int $userId
    ): ?array {
        $q =
            $this->core->prepare("
                SELECT
                    users.id,
                    COALESCE(
                        NULLIF(persons.full_name, ''),
                        NULLIF(users.username, ''),
                        NULLIF(users.email, ''),
                        CONCAT('کاربر ', users.id)
                    ) AS display_name
                FROM users
                LEFT JOIN persons
                  ON persons.id = users.person_id
                WHERE users.id = ?
                LIMIT 1
            ");

        $q->execute([$userId]);

        $row =
            $q->fetch(PDO::FETCH_ASSOC);

        return
            is_array($row)
                ? $row
                : null;
    }


    private function memberships(
        string $reference
    ): array {
        $q =
            $this->ticketing->prepare("
                SELECT
                    projects.id,
                    projects.public_reference,
                    projects.code,
                    projects.title,
                    projects.description,
                    members.role_code,
                    members.joined_at
                FROM ticketing_support_project_members AS members
                INNER JOIN ticketing_support_projects AS projects
                  ON projects.id = members.project_id
                WHERE members.user_reference = ?
                  AND members.left_at IS NULL
                  AND projects.is_active = 1
                  AND projects.archived_at IS NULL
                ORDER BY projects.sort_order, projects.title, projects.id
            ");

        $q->execute([$reference]);

        return
            $q->fetchAll(PDO::FETCH_ASSOC)
            ?: [];
    }


    private function openProjects(
        array $excludedIds
    ): array {
        $rows =
            $this->ticketing->query("
                SELECT
                    projects.id,
                    projects.public_reference,
                    projects.code,
                    projects.title,
                    projects.description
                FROM ticketing_support_projects AS projects
                INNER JOIN ticketing_support_project_requester_access AS access
                  ON access.project_id = projects.id
                WHERE projects.is_active = 1
                  AND projects.archived_at IS NULL
                  AND access.self_join_enabled = 1
                ORDER BY projects.sort_order, projects.title, projects.id
            ")->fetchAll(PDO::FETCH_ASSOC)
            ?: [];

        if ($excludedIds === []) {
            return $rows;
        }

        return
            array_values(
                array_filter(
                    $rows,
                    static fn (array $row): bool =>
                        !in_array(
                            (int) $row['id'],
                            $excludedIds,
                            true
                        )
                )
            );
    }


    private function inviteAvailable(): bool
    {
        return
            (int) $this->ticketing->query("
                SELECT COUNT(*)
                FROM ticketing_support_project_requester_access AS access
                INNER JOIN ticketing_support_projects AS projects
                  ON projects.id = access.project_id
                WHERE access.invite_join_enabled = 1
                  AND projects.is_active = 1
                  AND projects.archived_at IS NULL
            ")->fetchColumn()
            > 0;
    }


    private function normalizeCode(
        string $code
    ): string {
        $value =
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                trim($code)
            );

        return
            strtoupper(
                is_string($value)
                    ? $value
                    : ''
            );
    }
}

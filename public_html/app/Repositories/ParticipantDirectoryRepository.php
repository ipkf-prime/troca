<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class ParticipantDirectoryRepository
{
    private PDO $ticketing;
    private PDO $core;


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

        $this->core =
            $resolver->resolve(
                'core.primary'
            );
    }


    public function index(
        array $filters = []
    ): array {
        $where = [
            'p.archived_at IS NULL',
        ];

        $parameters = [];

        $q =
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            );

        $origin =
            trim(
                (string) (
                    $filters['origin']
                    ?? ''
                )
            );

        $state =
            trim(
                (string) (
                    $filters['state']
                    ?? ''
                )
            );


        if ($q !== '') {

            $where[] = "(
                p.public_reference LIKE ?
                OR p.full_name LIKE ?
                OR p.email LIKE ?
                OR p.mobile LIKE ?
                OR p.organization_name LIKE ?
                OR p.external_reference LIKE ?
                OR p.core_user_reference LIKE ?
            )";

            $needle =
                '%' . $q . '%';

            for ($i = 0; $i < 7; $i++) {
                $parameters[] =
                    $needle;
            }
        }


        if (
            in_array(
                $origin,
                [
                    'core',
                    'manual',
                    'import',
                ],
                true
            )
        ) {
            $where[] =
                'p.origin_code = ?';

            $parameters[] =
                $origin;
        }


        if (
            in_array(
                $state,
                [
                    'contact',
                    'invited',
                    'linked',
                    'disabled',
                ],
                true
            )
        ) {
            $where[] =
                'p.account_state = ?';

            $parameters[] =
                $state;
        }


        $statement =
            $this->ticketing->prepare("
                SELECT
                    p.id,
                    p.public_reference,
                    p.origin_code,
                    p.core_user_reference,
                    p.core_person_reference,
                    p.full_name,
                    p.email,
                    p.mobile,
                    p.organization_name,
                    p.external_reference,
                    p.account_state,
                    p.linked_at,
                    p.disabled_at,
                    p.created_at,
                    p.updated_at,

                    (
                        SELECT COUNT(*)
                        FROM
                            ticketing_support_project_members m
                        WHERE
                            m.participant_id = p.id
                            AND m.left_at IS NULL
                    ) AS project_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_tickets t
                        WHERE
                            t.requester_participant_id = p.id
                            AND t.archived_at IS NULL
                    ) AS ticket_count

                FROM ticketing_participants p

                WHERE
                    " . implode(
                        ' AND ',
                        $where
                    ) . "

                ORDER BY
                    CASE p.account_state
                        WHEN 'linked' THEN 10
                        WHEN 'contact' THEN 20
                        WHEN 'invited' THEN 30
                        WHEN 'disabled' THEN 40
                        ELSE 90
                    END,
                    p.full_name,
                    p.id

                LIMIT 500
            ");

        $statement->execute(
            $parameters
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function linkedCoreReferences(): array
    {
        $rows =
            $this->ticketing->query("
                SELECT core_user_reference
                FROM ticketing_participants
                WHERE core_user_reference
                    IS NOT NULL
                  AND core_user_reference <> ''
                  AND archived_at IS NULL
            ")->fetchAll(
                PDO::FETCH_COLUMN
            ) ?: [];

        return
            array_values(
                array_unique(
                    array_map(
                        'strval',
                        $rows
                    )
                )
            );
    }


    public function activeCoreUsers(
        string $q = ''
    ): array {
        $where = [
            "u.status = 'active'",
            'u.deleted_at IS NULL',
        ];

        $parameters = [];

        $q =
            trim($q);

        if ($q !== '') {

            $where[] = "(
                p.full_name LIKE ?
                OR u.username LIKE ?
                OR u.email LIKE ?
                OR u.mobile LIKE ?
            )";

            $needle =
                '%' . $q . '%';

            for ($i = 0; $i < 4; $i++) {
                $parameters[] =
                    $needle;
            }
        }

        $statement =
            $this->core->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.email,
                    u.mobile,
                    u.person_id,

                    p.public_reference
                        AS person_public_reference,

                    p.full_name

                FROM users u

                LEFT JOIN persons p
                    ON p.id = u.person_id

                WHERE
                    " . implode(
                        ' AND ',
                        $where
                    ) . "

                ORDER BY
                    COALESCE(
                        NULLIF(
                            p.full_name,
                            ''
                        ),
                        NULLIF(
                            u.username,
                            ''
                        ),
                        NULLIF(
                            u.email,
                            ''
                        )
                    ),
                    u.id

                LIMIT 200
            ");

        $statement->execute(
            $parameters
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function coreProfilesByUserIds(
        array $userIds
    ): array {
        $ids = [];

        foreach ($userIds as $userId) {

            $userId =
                (int) $userId;

            if ($userId > 0) {
                $ids[$userId] =
                    $userId;
            }
        }

        $ids =
            array_values($ids);

        if ($ids === []) {
            return [];
        }

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($ids),
                    '?'
                )
            );

        $statement =
            $this->core->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.email,
                    u.mobile,
                    u.person_id,
                    u.status,

                    p.public_reference
                        AS person_public_reference,

                    p.full_name

                FROM users u

                LEFT JOIN persons p
                    ON p.id = u.person_id

                WHERE u.id IN (
                    " . $placeholders . "
                )
                  AND u.deleted_at IS NULL
            ");

        $statement->execute(
            $ids
        );

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $result = [];

        foreach ($rows as $row) {
            $result[
                (int) $row['id']
            ] = $row;
        }

        return $result;
    }


    public function activeCoreUser(
        int $userId
    ): ?array {
        $statement =
            $this->core->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.email,
                    u.mobile,
                    u.person_id,

                    p.public_reference
                        AS person_public_reference,

                    p.full_name

                FROM users u

                LEFT JOIN persons p
                    ON p.id = u.person_id

                WHERE u.id = ?
                  AND u.status = 'active'
                  AND u.deleted_at IS NULL

                LIMIT 1
            ");

        $statement->execute([
            $userId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }


    public function findByCoreReference(
        string $reference
    ): ?array {
        $statement =
            $this->ticketing->prepare("
                SELECT *
                FROM ticketing_participants
                WHERE core_user_reference = ?
                LIMIT 1
            ");

        $statement->execute([
            trim($reference),
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }


    public function duplicateContact(
        ?string $emailNormalized,
        ?string $mobileNormalized
    ): ?array {
        $conditions = [];
        $parameters = [];

        if (
            $emailNormalized !== null
            && $emailNormalized !== ''
        ) {
            $conditions[] =
                'email_normalized = ?';

            $parameters[] =
                $emailNormalized;
        }

        if (
            $mobileNormalized !== null
            && $mobileNormalized !== ''
        ) {
            $conditions[] =
                'mobile_normalized = ?';

            $parameters[] =
                $mobileNormalized;
        }

        if ($conditions === []) {
            return null;
        }

        $statement =
            $this->ticketing->prepare("
                SELECT
                    id,
                    public_reference,
                    full_name,
                    email,
                    mobile,
                    origin_code,
                    account_state

                FROM ticketing_participants

                WHERE archived_at IS NULL
                  AND (
                    " . implode(
                        ' OR ',
                        $conditions
                    ) . "
                  )

                ORDER BY id
                LIMIT 1
            ");

        $statement->execute(
            $parameters
        );

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return
            is_array($row)
                ? $row
                : null;
    }


    public function createCoreParticipant(
        array $data
    ): array {
        $statement =
            $this->ticketing->prepare("
                INSERT INTO ticketing_participants
                (
                    public_reference,
                    origin_code,
                    core_user_reference,
                    core_person_reference,
                    full_name,
                    email,
                    email_normalized,
                    mobile,
                    mobile_normalized,
                    organization_name,
                    external_reference,
                    account_state,
                    imported_batch_reference,
                    linked_at,
                    disabled_at,
                    archived_at,
                    created_by_user_reference,
                    updated_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'core',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NULL,
                    NULL,
                    'linked',
                    NULL,
                    UTC_TIMESTAMP(),
                    NULL,
                    NULL,
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['core_user_reference'],
            $data['core_person_reference'],
            $data['full_name'],
            $data['email'],
            $data['email_normalized'],
            $data['mobile'],
            $data['mobile_normalized'],
            $data['actor_reference'],
            $data['actor_reference'],
        ]);

        return [
            'id' =>
                (int) $this->ticketing
                    ->lastInsertId(),

            'public_reference' =>
                (string) $data[
                    'public_reference'
                ],
        ];
    }


    public function createManualParticipant(
        array $data
    ): array {
        $statement =
            $this->ticketing->prepare("
                INSERT INTO ticketing_participants
                (
                    public_reference,
                    origin_code,
                    core_user_reference,
                    core_person_reference,
                    full_name,
                    email,
                    email_normalized,
                    mobile,
                    mobile_normalized,
                    organization_name,
                    external_reference,
                    account_state,
                    imported_batch_reference,
                    linked_at,
                    disabled_at,
                    archived_at,
                    created_by_user_reference,
                    updated_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'manual',
                    NULL,
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'contact',
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    ?,
                    ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $statement->execute([
            $data['public_reference'],
            $data['full_name'],
            $data['email'],
            $data['email_normalized'],
            $data['mobile'],
            $data['mobile_normalized'],
            $data['organization_name'],
            $data['external_reference'],
            $data['actor_reference'],
            $data['actor_reference'],
        ]);

        return [
            'id' =>
                (int) $this->ticketing
                    ->lastInsertId(),

            'public_reference' =>
                (string) $data[
                    'public_reference'
                ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class SupportProjectRepository
{
    private PDO $db;


    public function __construct(
        ?ConnectionResolver $connections = null
    ) {
        $this->db =
            (
                $connections
                ?? new ConnectionResolver()
            )->resolve('ticketing.primary');
    }


    /**
     * Security boundary:
     * only active project memberships are returned.
     *
     * There is intentionally no "public project"
     * bypass in Ticketing.
     */
    public function forUser(
        string $userReference
    ): array {
        $userReference =
            trim($userReference);

        if ($userReference === '') {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    p.id,
                    p.public_reference,
                    p.code,
                    p.title,
                    p.description,
                    p.color_code,
                    p.icon_code,
                    p.sort_order,

                    m.role_code
                        AS my_role_code,

                    m.joined_at
                        AS membership_joined_at,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_services s
                        WHERE s.project_id = p.id
                          AND s.is_active = 1
                    ) AS service_count

                FROM ticketing_support_projects p

                INNER JOIN
                    ticketing_support_project_members m
                    ON m.project_id = p.id
                   AND m.user_reference = ?
                   AND m.left_at IS NULL

                WHERE p.is_active = 1
                  AND p.archived_at IS NULL

                ORDER BY
                    p.sort_order,
                    p.title,
                    p.id
            ");

        $statement->execute([
            $userReference,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function projectForUser(
        string $publicReference,
        string $userReference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    p.id,
                    p.public_reference,
                    p.code,
                    p.title,
                    p.description,
                    p.color_code,
                    p.icon_code,
                    p.sort_order,

                    m.role_code
                        AS my_role_code,

                    m.joined_at
                        AS membership_joined_at

                FROM ticketing_support_projects p

                INNER JOIN
                    ticketing_support_project_members m
                    ON m.project_id = p.id
                   AND m.user_reference = ?
                   AND m.left_at IS NULL

                WHERE p.public_reference = ?
                  AND p.is_active = 1
                  AND p.archived_at IS NULL

                LIMIT 1
            ");

        $statement->execute([
            trim($userReference),
            trim($publicReference),
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


    public function servicesForProject(
        int $projectId
    ): array {
        if ($projectId < 1) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    public_reference,
                    project_id,
                    code,
                    title,
                    description,
                    sort_order,
                    is_default

                FROM ticketing_support_services

                WHERE project_id = ?
                  AND is_active = 1

                ORDER BY
                    sort_order,
                    title,
                    id
            ");

        $statement->execute([
            $projectId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function serviceForUser(
        string $projectReference,
        string $serviceReference,
        string $userReference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    s.id,
                    s.public_reference,
                    s.project_id,
                    s.code,
                    s.title,
                    s.description,
                    s.sort_order,
                    s.is_default,

                    p.public_reference
                        AS project_reference,

                    p.title
                        AS project_title,

                    m.role_code
                        AS my_role_code

                FROM ticketing_support_services s

                INNER JOIN
                    ticketing_support_projects p
                    ON p.id = s.project_id

                INNER JOIN
                    ticketing_support_project_members m
                    ON m.project_id = p.id
                   AND m.user_reference = ?
                   AND m.left_at IS NULL

                WHERE
                    p.public_reference = ?

                  AND
                    s.public_reference = ?

                  AND p.is_active = 1
                  AND p.archived_at IS NULL
                  AND s.is_active = 1

                LIMIT 1
            ");

        $statement->execute([
            trim($userReference),
            trim($projectReference),
            trim($serviceReference),
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
}

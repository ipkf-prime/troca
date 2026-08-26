<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class SupportProjectAdminRepository
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


    public function index(
        array $filters = []
    ): array {
        $q =
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            );

        $status =
            trim(
                (string) (
                    $filters['status']
                    ?? ''
                )
            );

        $where = [
            'p.archived_at IS NULL',
        ];

        $parameters = [];

        if ($status === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'p.is_active = 0';
        }

        if ($q !== '') {
            $where[] = "(
                p.title LIKE ?
                OR p.code LIKE ?
                OR p.public_reference LIKE ?
                OR p.description LIKE ?
            )";

            $needle =
                '%' . $q . '%';

            array_push(
                $parameters,
                $needle,
                $needle,
                $needle,
                $needle
            );
        }

        $whereSql =
            implode(
                ' AND ',
                $where
            );

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
                    p.is_active,
                    p.created_by_user_reference,
                    p.created_at,
                    p.updated_at,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_services s
                        WHERE s.project_id = p.id
                          AND s.is_active = 1
                    ) AS service_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_support_project_members m
                        WHERE m.project_id = p.id
                          AND m.left_at IS NULL
                    ) AS member_count,

                    (
                        SELECT COUNT(*)
                        FROM ticketing_tickets t
                        WHERE t.support_project_id = p.id
                    ) AS ticket_count

                FROM ticketing_support_projects p

                WHERE {$whereSql}

                ORDER BY
                    p.sort_order,
                    p.title,
                    p.id

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


    public function findByReference(
        string $publicReference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    public_reference,
                    code,
                    title,
                    description,
                    color_code,
                    icon_code,
                    sort_order,
                    is_active,
                    archived_at,
                    created_by_user_reference,
                    created_at,
                    updated_at
                FROM ticketing_support_projects
                WHERE public_reference = ?
                LIMIT 1
            ");

        $statement->execute([
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


    public function codeExists(
        string $code,
        ?int $excludeId = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM ticketing_support_projects
            WHERE code = ?
        ";

        $parameters = [
            trim($code),
        ];

        if (
            $excludeId !== null
            && $excludeId > 0
        ) {
            $sql .= ' AND id <> ?';

            $parameters[] =
                $excludeId;
        }

        $statement =
            $this->db->prepare($sql);

        $statement->execute(
            $parameters
        );

        return
            (int) $statement->fetchColumn()
            > 0;
    }


    public function create(
        array $data
    ): array {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_support_projects
                (
                    public_reference,
                    code,
                    title,
                    description,
                    color_code,
                    icon_code,
                    sort_order,
                    is_active,
                    archived_at,
                    created_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :public_reference,
                    :code,
                    :title,
                    :description,
                    :color_code,
                    :icon_code,
                    :sort_order,
                    :is_active,
                    NULL,
                    :actor_reference,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

        $statement->execute([
            'public_reference' =>
                $data['public_reference'],

            'code' =>
                $data['code'],

            'title' =>
                $data['title'],

            'description' =>
                $data['description'],

            'color_code' =>
                $data['color_code'],

            'icon_code' =>
                $data['icon_code'],

            'sort_order' =>
                $data['sort_order'],

            'is_active' =>
                $data['is_active'],

            'actor_reference' =>
                $data['actor_reference'],
        ]);

        $project =
            $this->findByReference(
                (string) $data[
                    'public_reference'
                ]
            );

        if ($project === null) {
            throw new \RuntimeException(
                'Created support project could not be reloaded.'
            );
        }

        return $project;
    }


    public function update(
        int $id,
        array $data
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE ticketing_support_projects
                SET
                    title = :title,
                    description = :description,
                    color_code = :color_code,
                    icon_code = :icon_code,
                    sort_order = :sort_order,
                    is_active = :is_active,
                    updated_at = UTC_TIMESTAMP()
                WHERE id = :id
                  AND archived_at IS NULL
            ");

        $statement->execute([
            'title' =>
                $data['title'],

            'description' =>
                $data['description'],

            'color_code' =>
                $data['color_code'],

            'icon_code' =>
                $data['icon_code'],

            'sort_order' =>
                $data['sort_order'],

            'is_active' =>
                $data['is_active'],

            'id' =>
                $id,
        ]);

        return
            $statement->rowCount() <= 1;
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

final class TicketingSlaPolicyAdminRepository
{
    private PDO $db;


    public function __construct(
        ?PDO $db = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );
    }


    public function pageData(): array
    {
        return [
            'policies' =>
                $this->policies(),

            'projects' =>
                $this->projects(),

            'services' =>
                $this->services(),

            'topics' =>
                $this->topics(),

            'queues' =>
                $this->queues(),

            'priorities' =>
                $this->priorities(),

            'calendars' =>
                $this->calendars(),

            'statuses' =>
                $this->statuses(),
        ];
    }


    public function policies(): array
    {
        $statement =
            $this->db->query("
                SELECT
                    p.*,

                    project.title
                        AS project_title,

                    service.title
                        AS service_title,

                    topic.title
                        AS topic_title,

                    queue.title
                        AS queue_title,

                    priority.title
                        AS priority_title,

                    calendar.title
                        AS calendar_title

                FROM
                    ticketing_sla_policies p

                LEFT JOIN
                    ticketing_support_projects project
                    ON project.id =
                        p.project_id

                LEFT JOIN
                    ticketing_support_services service
                    ON service.id =
                        p.service_id

                LEFT JOIN
                    ticketing_support_topics topic
                    ON topic.id =
                        p.topic_id

                LEFT JOIN
                    ticketing_support_queues queue
                    ON queue.id =
                        p.queue_id

                LEFT JOIN
                    ticketing_priorities priority
                    ON priority.code =
                        p.priority_code

                LEFT JOIN
                    ticketing_business_calendars calendar
                    ON calendar.id =
                        p.calendar_id

                ORDER BY
                    CASE
                        WHEN p.status = 'active'
                            THEN 0
                        ELSE 1
                    END,

                    p.sort_order DESC,
                    p.id DESC
            ");

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }


    public function policyByReference(
        string $reference
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM ticketing_sla_policies
                WHERE public_reference = ?
                LIMIT 1
            ");

        $statement->execute([
            trim($reference),
        ]);

        return
            $this->one(
                $statement
            );
    }


    public function projects(): array
    {
        return
            $this->all("
                SELECT
                    id,
                    public_reference,
                    code,
                    title

                FROM ticketing_support_projects

                WHERE archived_at IS NULL
                  AND is_active = 1

                ORDER BY
                    sort_order,
                    title,
                    id
            ");
    }


    public function services(): array
    {
        return
            $this->all("
                SELECT
                    service.id,
                    service.project_id,
                    service.code,
                    service.title,

                    project.title
                        AS project_title

                FROM ticketing_support_services service

                INNER JOIN ticketing_support_projects project
                    ON project.id =
                        service.project_id

                WHERE service.is_active = 1
                  AND project.archived_at IS NULL
                  AND project.is_active = 1

                ORDER BY
                    project.sort_order,
                    project.title,
                    service.sort_order,
                    service.title,
                    service.id
            ");
    }


    public function topics(): array
    {
        return
            $this->all("
                SELECT
                    topic.id,
                    topic.project_id,
                    topic.service_id,
                    topic.code,
                    topic.title,

                    project.title
                        AS project_title,

                    service.title
                        AS service_title

                FROM ticketing_support_topics topic

                INNER JOIN ticketing_support_projects project
                    ON project.id =
                        topic.project_id

                LEFT JOIN ticketing_support_services service
                    ON service.id =
                        topic.service_id

                WHERE topic.status = 'active'
                  AND project.archived_at IS NULL
                  AND project.is_active = 1

                ORDER BY
                    project.sort_order,
                    project.title,
                    service.sort_order,
                    service.title,
                    topic.sort_order,
                    topic.title,
                    topic.id
            ");
    }


    public function queues(): array
    {
        return
            $this->all("
                SELECT
                    queue.id,
                    queue.project_id,
                    queue.node_id,
                    queue.code,
                    queue.title,

                    project.title
                        AS project_title,

                    node.title
                        AS node_title

                FROM ticketing_support_queues queue

                INNER JOIN ticketing_support_projects project
                    ON project.id =
                        queue.project_id

                LEFT JOIN ticketing_support_nodes node
                    ON node.id =
                        queue.node_id

                WHERE queue.status = 'active'
                  AND project.archived_at IS NULL
                  AND project.is_active = 1

                ORDER BY
                    project.sort_order,
                    project.title,
                    queue.sort_order,
                    queue.title,
                    queue.id
            ");
    }


    public function priorities(): array
    {
        return
            $this->all("
                SELECT
                    code,
                    title,
                    severity,
                    sort_order

                FROM ticketing_priorities

                WHERE is_active = 1

                ORDER BY
                    sort_order,
                    severity,
                    code
            ");
    }


    public function calendars(): array
    {
        return
            $this->all("
                SELECT
                    id,
                    project_id,
                    code,
                    title,
                    timezone,
                    is_default

                FROM ticketing_business_calendars

                WHERE status = 'active'

                ORDER BY
                    is_default DESC,
                    project_id,
                    title,
                    id
            ");
    }


    public function statuses(): array
    {
        return
            $this->all("
                SELECT
                    code,
                    title,
                    is_closed,
                    sort_order

                FROM ticketing_statuses

                WHERE is_active = 1

                ORDER BY
                    sort_order,
                    title,
                    code
            ");
    }


    public function project(
        int $id
    ): ?array {
        return
            $this->owned(
                "
                    SELECT
                        id,
                        code,
                        title

                    FROM ticketing_support_projects

                    WHERE id = ?
                      AND archived_at IS NULL
                      AND is_active = 1

                    LIMIT 1
                ",
                $id
            );
    }


    public function service(
        int $id
    ): ?array {
        return
            $this->owned(
                "
                    SELECT
                        id,
                        project_id,
                        code,
                        title

                    FROM ticketing_support_services

                    WHERE id = ?
                      AND is_active = 1

                    LIMIT 1
                ",
                $id
            );
    }


    public function topic(
        int $id
    ): ?array {
        return
            $this->owned(
                "
                    SELECT
                        id,
                        project_id,
                        service_id,
                        code,
                        title

                    FROM ticketing_support_topics

                    WHERE id = ?
                      AND status = 'active'

                    LIMIT 1
                ",
                $id
            );
    }


    public function queue(
        int $id
    ): ?array {
        return
            $this->owned(
                "
                    SELECT
                        id,
                        project_id,
                        node_id,
                        code,
                        title

                    FROM ticketing_support_queues

                    WHERE id = ?
                      AND status = 'active'

                    LIMIT 1
                ",
                $id
            );
    }


    public function priority(
        string $code
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    code,
                    title,
                    severity

                FROM ticketing_priorities

                WHERE code = ?
                  AND is_active = 1

                LIMIT 1
            ");

        $statement->execute([
            trim($code),
        ]);

        return
            $this->one(
                $statement
            );
    }


    public function calendar(
        int $id
    ): ?array {
        return
            $this->owned(
                "
                    SELECT
                        id,
                        project_id,
                        code,
                        title,
                        timezone

                    FROM ticketing_business_calendars

                    WHERE id = ?
                      AND status = 'active'

                    LIMIT 1
                ",
                $id
            );
    }


    /*
     * TICKETING_SLA_VERSIONED_POLICY_WRITE_V1
     *
     * Existing enrolled tickets retain their original
     * policy_id. New tickets receive the successor policy.
     *
     * Never rewrite historical policy values in place.
     */
    public function createVersion(
        array $data
    ): array {
        $this->db->beginTransaction();

        try {

            $expire =
                $this->db->prepare("
                    UPDATE
                        ticketing_sla_policies

                    SET
                        status = 'inactive',

                        effective_to_at =
                            COALESCE(
                                effective_to_at,
                                UTC_TIMESTAMP()
                            ),

                        updated_at =
                            CURRENT_TIMESTAMP

                    WHERE status = 'active'

                      AND priority_code = ?

                      AND project_id <=> ?
                      AND service_id <=> ?
                      AND topic_id <=> ?
                      AND queue_id <=> ?
                ");

            $expire->execute([
                $data['priority_code'],
                $data['project_id'],
                $data['service_id'],
                $data['topic_id'],
                $data['queue_id'],
            ]);


            $insert =
                $this->db->prepare("
                    INSERT INTO
                        ticketing_sla_policies
                    (
                        public_reference,
                        scope_key,

                        project_id,
                        service_id,
                        topic_id,
                        queue_id,

                        priority_code,
                        calendar_id,

                        title,

                        response_minutes,
                        resolution_minutes,

                        pause_statuses_json,

                        breach_action_code,
                        max_auto_escalations,
                        escalation_repeat_minutes,

                        effective_from_at,
                        effective_to_at,

                        status,
                        sort_order,

                        metadata_json
                    )
                    VALUES
                    (
                        ?,
                        ?,

                        ?,
                        ?,
                        ?,
                        ?,

                        ?,
                        ?,

                        ?,

                        ?,
                        ?,

                        ?,

                        ?,
                        ?,
                        ?,

                        UTC_TIMESTAMP(),
                        NULL,

                        'active',
                        ?,

                        ?
                    )
                ");

            $insert->execute([
                $data[
                    'public_reference'
                ],

                $data[
                    'scope_key'
                ],

                $data[
                    'project_id'
                ],

                $data[
                    'service_id'
                ],

                $data[
                    'topic_id'
                ],

                $data[
                    'queue_id'
                ],

                $data[
                    'priority_code'
                ],

                $data[
                    'calendar_id'
                ],

                $data[
                    'title'
                ],

                $data[
                    'response_minutes'
                ],

                $data[
                    'resolution_minutes'
                ],

                $data[
                    'pause_statuses_json'
                ],

                $data[
                    'breach_action_code'
                ],

                $data[
                    'max_auto_escalations'
                ],

                $data[
                    'escalation_repeat_minutes'
                ],

                $data[
                    'sort_order'
                ],

                $data[
                    'metadata_json'
                ],
            ]);


            if (
                $insert->rowCount()
                !== 1
            ) {
                throw new RuntimeException(
                    'sla_policy_insert_failed'
                );
            }


            $id =
                (int) $this->db
                    ->lastInsertId();


            $this->db->commit();


            return [
                'id' => $id,

                'public_reference' =>
                    $data[
                        'public_reference'
                    ],
            ];

        } catch (Throwable $exception) {

            if (
                $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        }
    }


    public function disable(
        string $reference
    ): bool {
        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_sla_policies

                SET
                    status = 'inactive',

                    effective_to_at =
                        COALESCE(
                            effective_to_at,
                            UTC_TIMESTAMP()
                        ),

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE public_reference = ?
                  AND status = 'active'
            ");

        $statement->execute([
            trim($reference),
        ]);

        return
            $statement->rowCount()
            === 1;
    }


    private function all(
        string $sql
    ): array {
        return
            $this->db
                ->query($sql)
                ->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];
    }


    private function owned(
        string $sql,
        int $id
    ): ?array {
        if ($id < 1) {
            return null;
        }

        $statement =
            $this->db->prepare(
                $sql
            );

        $statement->execute([
            $id,
        ]);

        return
            $this->one(
                $statement
            );
    }


    private function one(
        \PDOStatement $statement
    ): ?array {
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

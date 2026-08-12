<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceRepository
{
    public function __construct(
        private ?AutomationOperationalRuntime $runtime = null
    ) {
        $this->runtime ??=
            new AutomationOperationalRuntime();
    }

    public function insert(
        array $data
    ): int {
        $statement =
            $this->connection()->prepare("
                INSERT INTO correspondences (
                    public_reference,

                    root_organization_id,
                    root_organization_public_reference,

                    organization_id,
                    organization_public_reference,

                    org_unit_id,
                    org_unit_public_reference,

                    secretariat_desk_id,

                    fiscal_year_id,

                    direction_code,
                    status_code,

                    subject,
                    summary,

                    document_template_version_id,

                    priority_code,
                    confidentiality_code,
                    channel_code,

                    external_number,
                    external_date,

                    received_at,
                    dispatched_at,

                    created_by_user_id,
                    creating_appointment_reference,
                    organizational_context_snapshot_json,

                    updated_by_user_id,

                    lock_version,

                    created_at,
                    updated_at
                )
                VALUES (
                    :public_reference,

                    :root_organization_id,
                    :root_organization_public_reference,

                    :organization_id,
                    :organization_public_reference,

                    :org_unit_id,
                    :org_unit_public_reference,

                    :secretariat_desk_id,

                    NULL,

                    :direction_code,
                    :status_code,

                    :subject,
                    :summary,

                    :document_template_version_id,

                    :priority_code,
                    :confidentiality_code,
                    :channel_code,

                    :external_number,
                    :external_date,

                    :received_at,
                    :dispatched_at,

                    :created_by_user_id,
                    :creating_appointment_reference,
                    :organizational_context_snapshot_json,

                    :updated_by_user_id,

                    0,

                    :created_at,
                    :updated_at
                )
            ");

        $statement->execute(
            $data
        );

        return
            (int) $this
                ->connection()
                ->lastInsertId();
    }

    public function updateCurrentVersion(
        int $id,
        int $versionId,
        int $versionNumber,
        int $userId,
        string $now,
        bool $incrementLock = true
    ): void {
        $lockSql =
            $incrementLock
                ? ', lock_version = lock_version + 1'
                : '';

        $statement =
            $this->connection()->prepare("
                UPDATE correspondences

                SET current_version_id = ?,
                    current_version_number = ?,
                    updated_by_user_id = ?,
                    updated_at = ?
                    {$lockSql}

                WHERE id = ?
            ");

        $statement->execute([
            $versionId,
            $versionNumber,
            $userId,
            $now,
            $id,
        ]);
    }

    public function updateDraft(
        int $id,
        array $data,
        int $expectedLockVersion
    ): bool {
        $statement =
            $this->connection()->prepare("
                UPDATE correspondences

                SET direction_code =
                        :direction_code,

                    subject =
                        :subject,

                    summary =
                        :summary,

                    document_template_version_id =
                        :document_template_version_id,

                    priority_code =
                        :priority_code,

                    confidentiality_code =
                        :confidentiality_code,

                    channel_code =
                        :channel_code,

                    external_number =
                        :external_number,

                    external_date =
                        :external_date,

                    received_at =
                        :received_at,

                    dispatched_at =
                        :dispatched_at,

                    updated_by_user_id =
                        :updated_by_user_id,

                    updated_at =
                        :updated_at,

                    lock_version =
                        lock_version + 1

                WHERE id = :id
                  AND status_code =
                        :status_code
                  AND lock_version =
                        :lock_version
            ");

        $statement->execute(
            $data
            + [
                'id' => $id,
                'status_code' => 'draft',
                'lock_version' =>
                    $expectedLockVersion,
            ]
        );

        return
            $statement->rowCount() === 1;
    }

    public function findByPublicReference(
        string $publicReference
    ): ?array {
        return $this->findOne(
            $publicReference,
            null
        );
    }

    public function findByPublicReferenceScoped(
        string $publicReference,
        array $scope
    ): ?array {
        return $this->findOne(
            $publicReference,
            $scope
        );
    }

    public function findByPublicReferenceForUpdate(
        string $publicReference
    ): ?array {
        $statement =
            $this->connection()->prepare("
                SELECT *
                FROM correspondences
                WHERE public_reference = ?
                LIMIT 1
                FOR UPDATE
            ");

        $statement->execute([
            $publicReference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    public function paginate(
        array $filters,
        int $page,
        int $perPage,
        ?array $scope = null
    ): array {
        [$where, $params] =
            $this->where(
                $filters,
                $scope
            );

        $offset =
            max(
                0,
                ($page - 1) * $perPage
            );

        $count =
            $this->connection()->prepare("
                SELECT COUNT(*)
                FROM correspondences c
                {$where}
            ");

        $count->execute(
            $params
        );

        $statement =
            $this->connection()->prepare("
                SELECT
                    c.*,
                    v.content_snapshot,
                    v.created_at
                        AS version_created_at,

                    cp_main.target_kind_code
                        AS correspondent_target_kind_code,

                    cp_main.person_id
                        AS correspondent_person_id,

                    cp_main.organization_id
                        AS correspondent_organization_id,

                    cp_main.org_unit_id
                        AS correspondent_org_unit_id,

                    cp_main.external_display_name
                        AS correspondent_external_display_name,

                    cp_main.external_organization_name
                        AS correspondent_external_organization_name,

                    cp_main.external_contact_or_address
                        AS correspondent_external_contact_or_address

                FROM correspondences c

                LEFT JOIN correspondence_versions v
                    ON v.id =
                        c.current_version_id

                LEFT JOIN correspondence_parties cp_main
                    ON cp_main.id = (
                        SELECT cp_pick.id

                        FROM correspondence_parties cp_pick

                        WHERE cp_pick.correspondence_id =
                                c.id

                          AND cp_pick.party_role_code =
                                CASE
                                    WHEN c.direction_code =
                                        'incoming'
                                    THEN 'sender'

                                    ELSE 'primary_recipient'
                                END

                        ORDER BY
                            cp_pick.sort_order ASC,
                            cp_pick.id ASC

                        LIMIT 1
                    )

                {$where}

                ORDER BY
                    c.updated_at DESC,
                    c.id DESC

                LIMIT {$perPage}
                OFFSET {$offset}
            ");

        $statement->execute(
            $params
        );

        return [
            'total' =>
                (int) $count
                    ->fetchColumn(),

            'items' =>
                $statement->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [],
        ];
    }

    public function dashboardCounts(
        ?array $scope = null
    ): array {
        $counts = [
            'all' => 0,
            'drafts' => 0,
            'incoming' => 0,
            'outgoing' => 0,
            'internal' => 0,
            'recent' => 0,
        ];

        [$scopeSql, $params] =
            $this->scopeClause(
                $scope,
                'c'
            );

        $where =
            $scopeSql !== ''
                ? 'WHERE ' . $scopeSql
                : '';

        $counts['all'] =
            $this->countFor(
                $where,
                $params
            );

        $counts['drafts'] =
            $this->countFor(
                $this->appendCondition(
                    $where,
                    "c.status_code = 'draft'"
                ),
                $params
            );

        $counts['incoming'] =
            $this->countFor(
                $this->appendCondition(
                    $where,
                    "c.direction_code = 'incoming'"
                ),
                $params
            );

        $counts['outgoing'] =
            $this->countFor(
                $this->appendCondition(
                    $where,
                    "c.direction_code = 'outgoing'"
                ),
                $params
            );

        $counts['internal'] =
            $this->countFor(
                $this->appendCondition(
                    $where,
                    "c.direction_code = 'internal'"
                ),
                $params
            );

        $counts['recent'] =
            $this->countFor(
                $this->appendCondition(
                    $where,
                    "c.updated_at >= DATE_SUB(
                        UTC_TIMESTAMP(),
                        INTERVAL 7 DAY
                    )"
                ),
                $params
            );

        return $counts;
    }

    private function findOne(
        string $publicReference,
        ?array $scope
    ): ?array {
        [$scopeSql, $scopeParams] =
            $this->scopeClause(
                $scope,
                'c'
            );

        $sql = "
            SELECT
                c.*,

                (
                    SELECT
                        cr.formatted_number

                    FROM correspondence_registrations cr

                    WHERE cr.correspondence_id =
                            c.id
                      AND cr.registration_role_code =
                            'official'
                      AND cr.status_code =
                            'active'
                      AND cr.cancelled_at IS NULL

                    ORDER BY cr.id DESC

                    LIMIT 1
                ) AS official_registration_number,

                (
                    SELECT
                        cr.registered_at

                    FROM correspondence_registrations cr

                    WHERE cr.correspondence_id =
                            c.id
                      AND cr.registration_role_code =
                            'official'
                      AND cr.status_code =
                            'active'
                      AND cr.cancelled_at IS NULL

                    ORDER BY cr.id DESC

                    LIMIT 1
                ) AS official_registered_at,

                t.public_reference
                    AS document_template_reference,
                t.title_fa
                    AS document_template_title

            FROM correspondences c

            LEFT JOIN
                correspondence_document_template_versions tv
                ON tv.id =
                    c.document_template_version_id

            LEFT JOIN
                correspondence_document_templates t
                ON t.id =
                    tv.template_id

            WHERE c.public_reference = ?
        ";

        $params = [
            $publicReference,
        ];

        if ($scopeSql !== '') {
            $sql .=
                " AND ({$scopeSql})";

            $params =
                array_merge(
                    $params,
                    $scopeParams
                );
        }

        $sql .= ' LIMIT 1';

        $statement =
            $this->connection()->prepare(
                $sql
            );

        $statement->execute(
            $params
        );

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function where(
        array $filters,
        ?array $scope
    ): array {
        $clauses = [];
        $params = [];

        [$scopeSql, $scopeParams] =
            $this->scopeClause(
                $scope,
                'c'
            );

        if ($scopeSql !== '') {
            $clauses[] =
                '(' . $scopeSql . ')';

            $params =
                array_merge(
                    $params,
                    $scopeParams
                );
        }

        if (
            ($filters['q'] ?? '') !== ''
        ) {
            $clauses[] = "
                (
                    c.subject LIKE ?
                    OR c.public_reference LIKE ?
                    OR c.external_number LIKE ?
                )
            ";

            $like =
                '%'
                . $filters['q']
                . '%';

            array_push(
                $params,
                $like,
                $like,
                $like
            );
        }

        foreach (
            [
                'status' => 'status_code',
                'direction' => 'direction_code',
                'priority' => 'priority_code',
            ]
            as $filter => $column
        ) {
            if (
                ($filters[$filter] ?? '')
                !== ''
            ) {
                $clauses[] =
                    "c.{$column} = ?";

                $params[] =
                    $filters[$filter];
            }
        }

        if (
            ($filters['date_from'] ?? '')
            !== ''
        ) {
            $clauses[] =
                'DATE(c.updated_at) >= ?';

            $params[] =
                $filters['date_from'];
        }

        if (
            ($filters['date_to'] ?? '')
            !== ''
        ) {
            $clauses[] =
                'DATE(c.updated_at) <= ?';

            $params[] =
                $filters['date_to'];
        }

        return [
            $clauses === []
                ? ''
                : 'WHERE '
                    . implode(
                        ' AND ',
                        $clauses
                    ),

            $params,
        ];
    }

    private function scopeClause(
        ?array $scope,
        string $alias
    ): array {
        if ($scope === null) {
            return ['', []];
        }

        $rootId =
            (int) (
                $scope[
                    'root_organization_id'
                ] ?? 0
            );

        $organizationIds =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            $scope[
                                'organization_ids'
                            ] ?? []
                        ),
                        static fn (
                            int $id
                        ): bool => $id > 0
                    )
                )
            );

        if (
            $rootId < 1
            || $organizationIds === []
        ) {
            return [
                '1 = 0',
                [],
            ];
        }

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count(
                        $organizationIds
                    ),
                    '?'
                )
            );

        /*
         * The second branch keeps only pre-enterprise
         * legacy rows visible to their owning organization.
         */
        $sql = "
            (
                (
                    {$alias}.root_organization_id = ?
                    AND {$alias}.organization_id
                        IN ({$placeholders})
                )
                OR
                (
                    {$alias}.root_organization_id IS NULL
                    AND {$alias}.organization_id
                        IN ({$placeholders})
                )
            )
        ";

        return [
            $sql,

            array_merge(
                [$rootId],
                $organizationIds,
                $organizationIds
            ),
        ];
    }

    private function countFor(
        string $where,
        array $params
    ): int {
        $statement =
            $this->connection()->prepare("
                SELECT COUNT(*)
                FROM correspondences c
                {$where}
            ");

        $statement->execute(
            $params
        );

        return
            (int) $statement
                ->fetchColumn();
    }

    private function appendCondition(
        string $where,
        string $condition
    ): string {
        return
            trim($where) === ''
                ? 'WHERE ' . $condition
                : $where
                    . ' AND '
                    . $condition;
    }

    private function connection(): PDO
    {
        return
            $this->runtime
                ->connection();
    }
}

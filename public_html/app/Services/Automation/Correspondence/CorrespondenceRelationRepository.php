<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceRelationRepository
{
    public function __construct(
        private ?AutomationOperationalRuntime $runtime = null
    ) {
        $this->runtime ??=
            new AutomationOperationalRuntime();
    }

    public function replaceForDraft(
        int $sourceId,
        array $relations,
        int $userId,
        string $createdAt
    ): void {
        $pdo =
            $this->runtime
                ->connection();

        $pdo->prepare("
            DELETE FROM correspondence_relations
            WHERE source_correspondence_id = ?
        ")->execute([
            $sourceId,
        ]);

        $statement =
            $pdo->prepare("
                INSERT INTO correspondence_relations (
                    source_correspondence_id,
                    target_correspondence_id,
                    relation_type_code,
                    note,
                    created_by_user_id,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

        foreach (
            $relations
            as $relation
        ) {
            $statement->execute([
                $sourceId,
                $relation[
                    'target_correspondence_id'
                ],
                $relation[
                    'relation_type_code'
                ],
                $relation['note'],
                $userId,
                $createdAt,
            ]);
        }
    }

    public function listFor(
        int $sourceId,
        ?array $scope = null
    ): array {
        [$scopeSql, $scopeParams] =
            $this->scopeClause(
                $scope
            );

        $sql = "
            SELECT
                r.*,

                c.public_reference
                    AS target_public_reference,

                c.subject
                    AS target_subject,

                c.external_number
                    AS target_external_number,

                c.external_date
                    AS target_external_date

            FROM correspondence_relations r

            INNER JOIN correspondences c
                ON c.id =
                    r.target_correspondence_id

            WHERE r.source_correspondence_id = ?
        ";

        $params = [
            $sourceId,
        ];

        if ($scopeSql !== '') {
            $sql .=
                ' AND '
                . $scopeSql;

            $params =
                array_merge(
                    $params,
                    $scopeParams
                );
        }

        $sql .= ' ORDER BY r.id';

        $statement =
            $this->runtime
                ->connection()
                ->prepare($sql);

        $statement->execute(
            $params
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    public function options(
        ?int $excludeId = null,
        ?array $scope = null
    ): array {
        [$scopeSql, $scopeParams] =
            $this->scopeClause(
                $scope
            );

        $clauses = [];
        $params = [];

        if ($scopeSql !== '') {
            $clauses[] =
                $scopeSql;

            $params =
                array_merge(
                    $params,
                    $scopeParams
                );
        }

        if ($excludeId !== null) {
            $clauses[] =
                'c.id <> ?';

            $params[] =
                $excludeId;
        }

        $sql = "
            SELECT
                c.id,
                c.public_reference,
                c.subject,
                c.external_number,
                c.external_date

            FROM correspondences c
        ";

        if ($clauses !== []) {
            $sql .=
                ' WHERE '
                . implode(
                    ' AND ',
                    $clauses
                );
        }

        $sql .= "
            ORDER BY
                c.updated_at DESC,
                c.id DESC

            LIMIT 200
        ";

        $statement =
            $this->runtime
                ->connection()
                ->prepare($sql);

        $statement->execute(
            $params
        );

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    public function targetId(
        string $publicReference,
        ?array $scope = null
    ): ?int {
        [$scopeSql, $scopeParams] =
            $this->scopeClause(
                $scope
            );

        $sql = "
            SELECT c.id
            FROM correspondences c
            WHERE c.public_reference = ?
        ";

        $params = [
            $publicReference,
        ];

        if ($scopeSql !== '') {
            $sql .=
                ' AND '
                . $scopeSql;

            $params =
                array_merge(
                    $params,
                    $scopeParams
                );
        }

        $sql .= ' LIMIT 1';

        $statement =
            $this->runtime
                ->connection()
                ->prepare($sql);

        $statement->execute(
            $params
        );

        $id =
            $statement->fetchColumn();

        return
            $id === false
                ? null
                : (int) $id;
    }

    private function scopeClause(
        ?array $scope
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

        $ids =
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
            || $ids === []
        ) {
            return [
                '1 = 0',
                [],
            ];
        }

        $in =
            implode(
                ',',
                array_fill(
                    0,
                    count($ids),
                    '?'
                )
            );

        return [
            "
                (
                    (
                        c.root_organization_id = ?
                        AND c.organization_id
                            IN ({$in})
                    )
                    OR
                    (
                        c.root_organization_id IS NULL
                        AND c.organization_id
                            IN ({$in})
                    )
                )
            ",

            array_merge(
                [$rootId],
                $ids,
                $ids
            ),
        ];
    }
}

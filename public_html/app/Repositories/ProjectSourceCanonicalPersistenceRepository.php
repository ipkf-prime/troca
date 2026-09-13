<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\ProjectSources\ProjectSourceCanonicalPersistenceRepositoryInterface;
use Closure;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

/**
 * PROJECT_SOURCE_CANONICAL_PERSISTENCE_REPOSITORY_V1
 *
 * MySQL/InnoDB persistence implementation.
 *
 * Security / consistency boundaries:
 * - source + run + dimension context is resolved from trusted DB rows;
 * - source/run row is locked FOR UPDATE before materialization;
 * - canonical Dimension values are upserted without deletion-by-omission;
 * - parent_value_id is resolved only from the same Dimension;
 * - closure paths are rebuilt only after all value/parent writes succeed;
 * - source snapshot success marker and run status are committed in the
 *   same transaction as canonical value/path changes.
 */
final class ProjectSourceCanonicalPersistenceRepository
    implements ProjectSourceCanonicalPersistenceRepositoryInterface
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


    public function transaction(
        Closure $operation
    ): mixed {
        if ($this->db->inTransaction()) {
            throw new RuntimeException(
                'Nested project-source apply transaction is forbidden.'
            );
        }

        $this->db->beginTransaction();

        try {
            $result =
                $operation();

            $this->db->commit();

            return $result;

        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function lockApplyContext(
        int $projectId,
        string $sourceCode,
        int $runId
    ): ?array {
        if (
            $projectId < 1
            || $runId < 1
            || trim($sourceCode) === ''
        ) {
            return null;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    s.id
                        AS source_id,

                    s.public_reference
                        AS source_public_reference,

                    s.project_id,

                    s.code
                        AS source_code,

                    s.driver_code,
                    s.catalog_code,
                    s.dimension_code,
                    s.status
                        AS source_status,

                    r.id
                        AS run_id,

                    r.public_reference
                        AS run_public_reference,

                    r.status
                        AS run_status,

                    r.source_snapshot_token,
                    r.item_count,
                    r.valid_count,
                    r.invalid_count,

                    d.id
                        AS dimension_id,

                    d.public_reference
                        AS dimension_public_reference,

                    d.project_id
                        AS dimension_project_id,

                    d.code
                        AS dimension_code_resolved,

                    d.status
                        AS dimension_status,

                    d.source_mode_code,
                    d.source_key,
                    d.value_kind_code,
                    d.hierarchy_mode_code,
                    d.supports_descendants

                FROM
                    ticketing_project_sources s

                INNER JOIN
                    ticketing_project_source_runs r

                    ON r.source_id =
                        s.id

                INNER JOIN
                    ticketing_scope_dimensions d

                    ON d.project_id =
                        s.project_id

                   AND d.code =
                        s.dimension_code

                WHERE
                    s.project_id = ?

                  AND s.code = ?

                  AND r.id = ?

                LIMIT 1

                FOR UPDATE
            ");

        $statement->execute([
            $projectId,
            trim($sourceCode),
            $runId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return null;
        }

        if (
            strtolower(
                trim(
                    (string) (
                        $row['source_status']
                        ?? ''
                    )
                )
            ) !== 'active'
        ) {
            throw new RuntimeException(
                'Project source is not active.'
            );
        }

        if (
            strtolower(
                trim(
                    (string) (
                        $row['run_status']
                        ?? ''
                    )
                )
            ) !== 'validated'
        ) {
            throw new RuntimeException(
                'Only a validated source run may be applied.'
            );
        }

        if (
            (int) (
                $row['invalid_count']
                ?? 0
            ) !== 0
        ) {
            throw new RuntimeException(
                'Validated source run contains invalid rows.'
            );
        }

        if (
            (int) (
                $row['project_id']
                ?? 0
            ) !==
            (int) (
                $row['dimension_project_id']
                ?? 0
            )
        ) {
            throw new RuntimeException(
                'Trusted project/dimension ownership mismatch.'
            );
        }

        return $row;
    }


    public function stagedBatch(
        array $context
    ): array {
        $runId =
            (int) (
                $context['run_id']
                ?? 0
            );

        if ($runId < 1) {
            throw new RuntimeException(
                'Trusted run id is required.'
            );
        }

        $statement =
            $this->db->prepare("
                SELECT
                    stage_row_number,
                    source_reference,
                    parent_source_reference,
                    title,
                    source_status,
                    attributes_json,
                    payload_hash,
                    validation_status,
                    validation_errors_json

                FROM
                    ticketing_project_source_stage_rows

                WHERE
                    run_id = ?

                ORDER BY
                    stage_row_number,
                    id
            ");

        $statement->execute([
            $runId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        $rows =
            array_map(
                static function (
                    array $row
                ): array {
                    $row['row_number'] =
                        (int) (
                            $row[
                                'stage_row_number'
                            ]
                            ?? 0
                        );

                    unset(
                        $row[
                            'stage_row_number'
                        ]
                    );

                    return $row;
                },
                $rows
            );

        $expectedCount =
            (int) (
                $context['item_count']
                ?? 0
            );

        if (
            count($rows) !==
                $expectedCount
        ) {
            throw new RuntimeException(
                'Staged row count does not match trusted run count.'
            );
        }

        foreach ($rows as $row) {
            if (
                strtolower(
                    trim(
                        (string) (
                            $row[
                                'validation_status'
                            ]
                            ?? ''
                        )
                    )
                ) !== 'valid'
            ) {
                throw new RuntimeException(
                    'Every staged row must be valid before apply.'
                );
            }

            if (
                trim(
                    (string) (
                        $row[
                            'validation_errors_json'
                        ]
                        ?? ''
                    )
                ) !== ''
            ) {
                throw new RuntimeException(
                    'Valid staged row contains validation errors.'
                );
            }
        }

        return [
            'source_code' =>
                (string) (
                    $context[
                        'source_code'
                    ]
                    ?? ''
                ),

            'driver_code' =>
                (string) (
                    $context[
                        'driver_code'
                    ]
                    ?? ''
                ),

            'catalog_code' =>
                (string) (
                    $context[
                        'catalog_code'
                    ]
                    ?? ''
                ),

            'dimension_code' =>
                (string) (
                    $context[
                        'dimension_code'
                    ]
                    ?? ''
                ),

            'snapshot_token' =>
                (string) (
                    $context[
                        'source_snapshot_token'
                    ]
                    ?? ''
                ),

            /*
             * A validated run is the trusted completeness boundary.
             */
            'next_cursor' =>
                null,

            'item_count' =>
                count($rows),

            'rows' =>
                $rows,
        ];
    }


    public function existingCanonical(
        int $dimensionId
    ): array {
        if ($dimensionId < 1) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    v.id,
                    v.public_reference,
                    v.value_reference,
                    v.source_reference,
                    v.title,
                    v.status,

                    parent.source_reference
                        AS parent_source_reference

                FROM
                    ticketing_scope_dimension_values v

                LEFT JOIN
                    ticketing_scope_dimension_values parent

                    ON parent.dimension_id =
                        v.dimension_id

                   AND parent.id =
                        v.parent_value_id

                WHERE
                    v.dimension_id = ?

                ORDER BY
                    v.id
            ");

        $statement->execute([
            $dimensionId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        $bySource = [];

        foreach ($rows as $row) {
            $sourceReference =
                trim(
                    (string) (
                        $row[
                            'source_reference'
                        ]
                        ?? ''
                    )
                );

            if ($sourceReference === '') {
                continue;
            }

            if (
                isset(
                    $bySource[
                        $sourceReference
                    ]
                )
            ) {
                throw new RuntimeException(
                    'Duplicate canonical source_reference in one Dimension.'
                );
            }

            $bySource[
                $sourceReference
            ] = $row;
        }

        return $bySource;
    }


    public function persistPlan(
        array $context,
        array $plan,
        string $actorReference
    ): array {
        $dimensionId =
            (int) (
                $context['dimension_id']
                ?? 0
            );

        if ($dimensionId < 1) {
            throw new RuntimeException(
                'Trusted Dimension id is required.'
            );
        }

        $actorReference =
            trim($actorReference);

        if ($actorReference === '') {
            $actorReference =
                'system:project-source';
        }

        $upsert =
            $this->db->prepare("
                INSERT INTO
                    ticketing_scope_dimension_values
                (
                    public_reference,
                    dimension_id,
                    parent_value_id,
                    value_reference,
                    code,
                    title,
                    source_reference,
                    status,
                    sort_order,
                    metadata_json,
                    created_by_user_reference,
                    updated_by_user_reference,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    NULL,
                    ?,
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )

                ON DUPLICATE KEY UPDATE
                    title =
                        VALUES(title),

                    source_reference =
                        VALUES(source_reference),

                    status =
                        VALUES(status),

                    sort_order =
                        VALUES(sort_order),

                    metadata_json =
                        VALUES(metadata_json),

                    updated_by_user_reference =
                        VALUES(updated_by_user_reference),

                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        $sortOrder = 0;

        foreach (
            $plan['upserts']
                ?? []
            as $row
        ) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Invalid canonical upsert row.'
                );
            }

            $valueReference =
                trim(
                    (string) (
                        $row[
                            'value_reference'
                        ]
                        ?? ''
                    )
                );

            $sourceReference =
                trim(
                    (string) (
                        $row[
                            'source_reference'
                        ]
                        ?? ''
                    )
                );

            if (
                $valueReference === ''
                || $sourceReference === ''
            ) {
                throw new RuntimeException(
                    'Canonical materialization identity is incomplete.'
                );
            }

            $sortOrder++;

            $publicReference =
                'SDV-'
                . substr(
                    hash(
                        'sha256',
                        $dimensionId
                        . "\x1F"
                        . $valueReference
                    ),
                    0,
                    32
                );

            $metadata =
                json_encode(
                    [
                        'project_source' => [
                            'source_code' =>
                                (string) (
                                    $plan[
                                        'source_code'
                                    ]
                                    ?? ''
                                ),

                            'snapshot_token' =>
                                (string) (
                                    $plan[
                                        'snapshot_token'
                                    ]
                                    ?? ''
                                ),

                            'payload_hash' =>
                                (string) (
                                    $row[
                                        'payload_hash'
                                    ]
                                    ?? ''
                                ),

                            'attributes_json' =>
                                $row[
                                    'attributes_json'
                                ]
                                ?? null,
                        ],
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );

            $upsert->execute([
                $publicReference,
                $dimensionId,
                $valueReference,
                (string) (
                    $row['title']
                    ?? ''
                ),
                $sourceReference,
                (string) (
                    $row['status']
                    ?? 'active'
                ),
                $sortOrder,
                $metadata,
                $actorReference,
                $actorReference,
            ]);
        }

        $canonical =
            $this->canonicalRows(
                $dimensionId
            );

        $bySource = [];

        foreach ($canonical as $row) {
            $sourceReference =
                trim(
                    (string) (
                        $row[
                            'source_reference'
                        ]
                        ?? ''
                    )
                );

            if ($sourceReference === '') {
                continue;
            }

            if (
                isset(
                    $bySource[
                        $sourceReference
                    ]
                )
            ) {
                throw new RuntimeException(
                    'Duplicate canonical source_reference after upsert.'
                );
            }

            $bySource[
                $sourceReference
            ] = $row;
        }

        $setParent =
            $this->db->prepare("
                UPDATE
                    ticketing_scope_dimension_values

                SET
                    parent_value_id = ?,
                    updated_by_user_reference = ?,
                    updated_at = CURRENT_TIMESTAMP

                WHERE
                    dimension_id = ?

                  AND id = ?
            ");

        foreach (
            $plan['upserts']
                ?? []
            as $row
        ) {
            $sourceReference =
                (string) $row[
                    'source_reference'
                ];

            if (
                !isset(
                    $bySource[
                        $sourceReference
                    ]
                )
            ) {
                throw new RuntimeException(
                    'Canonical source row missing after upsert.'
                );
            }

            $parentSourceReference =
                $row[
                    'parent_source_reference'
                ]
                ?? null;

            $parentId = null;

            if (
                $parentSourceReference !== null
                && trim(
                    (string) $parentSourceReference
                ) !== ''
            ) {
                $parentSourceReference =
                    trim(
                        (string) $parentSourceReference
                    );

                if (
                    !isset(
                        $bySource[
                            $parentSourceReference
                        ]
                    )
                ) {
                    throw new RuntimeException(
                        'Canonical parent source row is unresolved.'
                    );
                }

                $parentId =
                    (int) $bySource[
                        $parentSourceReference
                    ]['id'];
            }

            $setParent->execute([
                $parentId,
                $actorReference,
                $dimensionId,
                (int) $bySource[
                    $sourceReference
                ]['id'],
            ]);
        }

        if (
            !empty(
                $plan[
                    'path_rebuild_required'
                ]
            )
        ) {
            $this->rebuildPaths(
                $dimensionId
            );
        }

        return [
            'dimension_id' =>
                $dimensionId,

            'upserted_count' =>
                count(
                    $plan['upserts']
                    ?? []
                ),

            'deleted_count' =>
                0,

            'path_rebuilt' =>
                !empty(
                    $plan[
                        'path_rebuild_required'
                    ]
                ),
        ];
    }


    public function markRunApplied(
        int $runId,
        string $snapshotToken,
        int $itemCount
    ): void {
        if ($runId < 1) {
            throw new RuntimeException(
                'Run id is required.'
            );
        }

        $run =
            $this->db->prepare("
                UPDATE
                    ticketing_project_source_runs

                SET
                    status = 'applied',
                    item_count = ?,
                    valid_count = ?,
                    invalid_count = 0,
                    error_summary = NULL,
                    finished_at = CURRENT_TIMESTAMP

                WHERE
                    id = ?
            ");

        $run->execute([
            $itemCount,
            $itemCount,
            $runId,
        ]);

        $source =
            $this->db->prepare("
                UPDATE
                    ticketing_project_sources s

                INNER JOIN
                    ticketing_project_source_runs r

                    ON r.source_id =
                        s.id

                SET
                    s.last_successful_snapshot_token = ?,
                    s.updated_at = CURRENT_TIMESTAMP

                WHERE
                    r.id = ?
            ");

        $source->execute([
            $snapshotToken,
            $runId,
        ]);
    }


    public function recordApplyFailure(
        int $runId,
        string $message
    ): void {
        if ($runId < 1) {
            return;
        }

        $message =
            trim($message);

        if (
            function_exists(
                'mb_substr'
            )
        ) {
            $message =
                mb_substr(
                    $message,
                    0,
                    4000,
                    'UTF-8'
                );

        } else {
            $message =
                substr(
                    $message,
                    0,
                    4000
                );
        }

        $statement =
            $this->db->prepare("
                UPDATE
                    ticketing_project_source_runs

                SET
                    status = 'apply_failed',
                    error_summary = ?,
                    finished_at = CURRENT_TIMESTAMP

                WHERE
                    id = ?

                  AND status <> 'applied'
            ");

        $statement->execute([
            $message,
            $runId,
        ]);
    }


    private function canonicalRows(
        int $dimensionId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    dimension_id,
                    parent_value_id,
                    value_reference,
                    source_reference,
                    status

                FROM
                    ticketing_scope_dimension_values

                WHERE
                    dimension_id = ?

                ORDER BY
                    id
            ");

        $statement->execute([
            $dimensionId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    private function rebuildPaths(
        int $dimensionId
    ): void {
        $rows =
            $this->canonicalRows(
                $dimensionId
            );

        $parentById = [];

        foreach ($rows as $row) {
            $id =
                (int) (
                    $row['id']
                    ?? 0
                );

            if ($id < 1) {
                continue;
            }

            $parentId =
                $row[
                    'parent_value_id'
                ];

            $parentById[$id] =
                $parentId === null
                    ? null
                    : (int) $parentId;
        }

        $paths = [];

        foreach (
            array_keys(
                $parentById
            )
            as $descendantId
        ) {
            $seen = [];
            $current =
                $descendantId;
            $depth = 0;

            while ($current !== null) {
                if (
                    isset(
                        $seen[
                            $current
                        ]
                    )
                ) {
                    throw new RuntimeException(
                        'Canonical Dimension hierarchy contains a cycle.'
                    );
                }

                if (
                    !array_key_exists(
                        $current,
                        $parentById
                    )
                ) {
                    throw new RuntimeException(
                        'Canonical Dimension parent is outside target Dimension.'
                    );
                }

                $seen[$current] =
                    true;

                $paths[] = [
                    'ancestor_value_id' =>
                        $current,

                    'descendant_value_id' =>
                        $descendantId,

                    'depth' =>
                        $depth,
                ];

                $current =
                    $parentById[
                        $current
                    ];

                $depth++;
            }
        }

        $delete =
            $this->db->prepare("
                DELETE FROM
                    ticketing_scope_dimension_value_paths

                WHERE
                    dimension_id = ?
            ");

        $delete->execute([
            $dimensionId,
        ]);

        $insert =
            $this->db->prepare("
                INSERT INTO
                    ticketing_scope_dimension_value_paths
                (
                    dimension_id,
                    ancestor_value_id,
                    descendant_value_id,
                    depth
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

        foreach ($paths as $path) {
            $insert->execute([
                $dimensionId,
                $path[
                    'ancestor_value_id'
                ],
                $path[
                    'descendant_value_id'
                ],
                $path['depth'],
            ]);
        }
    }
}

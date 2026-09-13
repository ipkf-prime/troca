<?php

declare(strict_types=1);

namespace App\Services\ProjectSources;

use DomainException;

/**
 * PROJECT_SOURCE_CANONICAL_MATERIALIZATION_PLANNER_V1
 *
 * Pure deterministic planner.
 *
 * Produces canonical upsert intent only. It does NOT:
 * - write ticketing_scope_dimension_values;
 * - rebuild value-path rows;
 * - delete values omitted from a snapshot;
 * - call a source connector;
 * - resolve project/dimension ownership itself.
 *
 * Trusted ownership context must already be supplied to the validation
 * policy. Persistence is deliberately deferred to a later stage.
 */
final class ProjectSourceCanonicalMaterializationPlanner
{
    public function __construct(
        private ?ProjectSourceStageValidationPolicy
            $validation = null
    ) {
        $this->validation ??=
            new ProjectSourceStageValidationPolicy();
    }

    public function plan(
        array $sourceConfig,
        array $dimensionContext,
        array $batch,
        array $existingCanonical = []
    ): array {
        $validation =
            $this->validation->evaluate(
                $sourceConfig,
                $dimensionContext,
                $batch,
                $existingCanonical
            );

        if (
            ($validation['materializable']
                ?? false) !== true
        ) {
            throw new DomainException(
                'Staged source snapshot is not materializable: '
                . implode(
                    ',',
                    $validation['errors']
                    ?? []
                )
            );
        }

        $staged =
            $validation[
                'staged_by_reference'
            ];

        $parents =
            $validation[
                'effective_parent_map'
            ];

        $depthCache = [];

        $depthOf =
            function (
                string $reference
            ) use (
                &$depthOf,
                &$depthCache,
                $parents
            ): int {
                if (
                    array_key_exists(
                        $reference,
                        $depthCache
                    )
                ) {
                    return
                        $depthCache[$reference];
                }

                $parent =
                    $parents[$reference]
                    ?? null;

                if (
                    $parent === null
                    || !array_key_exists(
                        $parent,
                        $parents
                    )
                ) {
                    $depthCache[$reference] =
                        0;

                    return 0;
                }

                $depthCache[$reference] =
                    $depthOf($parent)
                    + 1;

                return
                    $depthCache[$reference];
            };

        $orderedReferences =
            array_keys($staged);

        usort(
            $orderedReferences,
            static function (
                string $left,
                string $right
            ) use (
                $depthOf
            ): int {
                $leftDepth =
                    $depthOf($left);

                $rightDepth =
                    $depthOf($right);

                if (
                    $leftDepth !==
                    $rightDepth
                ) {
                    return
                        $leftDepth
                        <=> $rightDepth;
                }

                return
                    strcmp(
                        $left,
                        $right
                    );
            }
        );

        $upserts = [];

        foreach (
            $orderedReferences
            as $reference
        ) {
            $row =
                $staged[$reference];

            $existingValue =
                $validation[
                    'existing_by_reference'
                ][$reference]
                ?? null;

            $existingValueReference =
                is_array(
                    $existingValue
                )
                    ? trim(
                        (string) (
                            $existingValue[
                                'value_reference'
                            ]
                            ?? ''
                        )
                    )
                    : '';

            $valueReference =
                $existingValueReference !== ''
                    ? $existingValueReference
                    : $this->canonicalValueReference(
                        (int) (
                            $sourceConfig[
                                'project_id'
                            ]
                            ?? 0
                        ),
                        (int) $validation[
                            'dimension_id'
                        ],
                        (string) (
                            $sourceConfig['code']
                            ?? ''
                        ),
                        $reference
                    );

            $upserts[] = [
                'source_reference' =>
                    $reference,

                'value_reference' =>
                    $valueReference,

                'parent_source_reference' =>
                    $row[
                        'parent_source_reference'
                    ],

                'title' =>
                    $row['title'],

                'status' =>
                    $row[
                        'source_status'
                    ],

                'attributes_json' =>
                    $row[
                        'attributes_json'
                    ],

                'payload_hash' =>
                    $row[
                        'payload_hash'
                    ],

                'depth' =>
                    $depthOf($reference),
            ];
        }

        return [
            'project_id' =>
                (int) (
                    $sourceConfig['project_id']
                    ?? 0
                ),

            'dimension_id' =>
                (int) $validation[
                    'dimension_id'
                ],

            'dimension_code' =>
                (string) $validation[
                    'dimension_code'
                ],

            'source_code' =>
                (string) (
                    $sourceConfig['code']
                    ?? ''
                ),

            'snapshot_token' =>
                (string) $validation[
                    'snapshot_token'
                ],

            'upserts' =>
                $upserts,

            /*
             * Omission is never interpreted as delete.
             */
            'deletes' =>
                [],

            /*
             * Persistence must rebuild paths only after all canonical
             * value upserts succeed atomically.
             */
            'path_rebuild_required' =>
                (
                    $validation[
                        'hierarchy_mode_code'
                    ]
                    === 'tree'
                ),

            'persistence_mode' =>
                'atomic_upsert_no_delete',

            'database_write_performed' =>
                false,
        ];
    }

    private function canonicalValueReference(
        int $projectId,
        int $dimensionId,
        string $sourceCode,
        string $sourceReference
    ): string {
        return
            'SRCV-'
            . hash(
                'sha256',
                implode(
                    "\x1F",
                    [
                        (string) $projectId,
                        (string) $dimensionId,
                        trim($sourceCode),
                        $sourceReference,
                    ]
                )
            );
    }

}

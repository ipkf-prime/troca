<?php

declare(strict_types=1);

namespace App\Services\ProjectSources;

/**
 * PROJECT_SOURCE_STAGE_VALIDATION_POLICY_V1
 *
 * Pure policy. No database write, no connector/network call.
 *
 * A complete staged snapshot page-set may be materialized only when:
 * - Project ownership matches the target Dimension.
 * - Source configuration and target Dimension codes match.
 * - The target Dimension is active and externally/reference managed.
 * - The staged snapshot is complete (no next cursor).
 * - References are unique.
 * - Parent references resolve against staged or existing canonical values.
 * - Flat dimensions contain no parent edges.
 * - The resulting parent graph is acyclic.
 * - Canonical statuses are from the generic supported set.
 *
 * Omission from a source snapshot NEVER means delete.
 */
final class ProjectSourceStageValidationPolicy
{
    private const MATERIALIZABLE_SOURCE_MODES = [
        'external',
        'reference',
    ];

    private const MATERIALIZABLE_STATUSES = [
        'active',
        'inactive',
        'archived',
    ];

    public function evaluate(
        array $sourceConfig,
        array $dimensionContext,
        array $batch,
        array $existingCanonical = []
    ): array {
        $errors = [];
        $rowErrors = [];

        $sourceProjectId =
            (int) (
                $sourceConfig['project_id']
                ?? 0
            );

        $dimensionProjectId =
            (int) (
                $dimensionContext['project_id']
                ?? 0
            );

        $dimensionId =
            (int) (
                $dimensionContext['id']
                ?? 0
            );

        $configuredDimensionCode =
            trim(
                (string) (
                    $sourceConfig['dimension_code']
                    ?? ''
                )
            );

        $dimensionCode =
            trim(
                (string) (
                    $dimensionContext['code']
                    ?? ''
                )
            );

        if (
            $sourceProjectId <= 0
            || $dimensionProjectId <= 0
            || $dimensionId <= 0
        ) {
            $errors[] =
                'trusted_project_dimension_context_required';
        }

        if (
            $sourceProjectId > 0
            && $dimensionProjectId > 0
            && $sourceProjectId !==
                $dimensionProjectId
        ) {
            $errors[] =
                'cross_project_dimension_forbidden';
        }

        if (
            $configuredDimensionCode === ''
            || $dimensionCode === ''
            || $configuredDimensionCode !==
                $dimensionCode
        ) {
            $errors[] =
                'dimension_code_mismatch';
        }

        $sourceCode =
            trim(
                (string) (
                    $sourceConfig['code']
                    ?? ''
                )
            );

        $dimensionSourceKey =
            trim(
                (string) (
                    $dimensionContext[
                        'source_key'
                    ]
                    ?? ''
                )
            );

        if (
            $sourceCode === ''
            || $dimensionSourceKey === ''
            || $sourceCode !==
                $dimensionSourceKey
        ) {
            $errors[] =
                'dimension_source_binding_mismatch';
        }

        if (
            strtolower(
                trim(
                    (string) (
                        $dimensionContext['status']
                        ?? ''
                    )
                )
            ) !== 'active'
        ) {
            $errors[] =
                'target_dimension_not_active';
        }

        $sourceMode =
            strtolower(
                trim(
                    (string) (
                        $dimensionContext[
                            'source_mode_code'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            !in_array(
                $sourceMode,
                self::MATERIALIZABLE_SOURCE_MODES,
                true
            )
        ) {
            $errors[] =
                'target_dimension_source_mode_not_materializable';
        }

        $valueKind =
            strtolower(
                trim(
                    (string) (
                        $dimensionContext[
                            'value_kind_code'
                        ]
                        ?? ''
                    )
                )
            );

        if ($valueKind !== 'reference') {
            $errors[] =
                'target_dimension_value_kind_must_be_reference';
        }

        $hierarchyMode =
            strtolower(
                trim(
                    (string) (
                        $dimensionContext[
                            'hierarchy_mode_code'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            !in_array(
                $hierarchyMode,
                [
                    'flat',
                    'tree',
                ],
                true
            )
        ) {
            $errors[] =
                'target_dimension_hierarchy_mode_invalid';
        }

        $batchSourceCode =
            trim(
                (string) (
                    $batch['source_code']
                    ?? ''
                )
            );

        $configSourceCode =
            trim(
                (string) (
                    $sourceConfig['code']
                    ?? ''
                )
            );

        if (
            $batchSourceCode === ''
            || $configSourceCode === ''
            || $batchSourceCode !==
                $configSourceCode
        ) {
            $errors[] =
                'source_code_mismatch';
        }

        if (
            trim(
                (string) (
                    $batch['dimension_code']
                    ?? ''
                )
            ) !== $configuredDimensionCode
        ) {
            $errors[] =
                'batch_dimension_code_mismatch';
        }

        $snapshotToken =
            trim(
                (string) (
                    $batch['snapshot_token']
                    ?? ''
                )
            );

        if ($snapshotToken === '') {
            $errors[] =
                'snapshot_token_required';
        }

        $nextCursor =
            $batch['next_cursor']
            ?? null;

        if (
            $nextCursor !== null
            && trim(
                (string) $nextCursor
            ) !== ''
        ) {
            $errors[] =
                'snapshot_not_complete';
        }

        $rows =
            $batch['rows']
            ?? null;

        if (!is_array($rows)) {
            $errors[] =
                'staged_rows_required';

            $rows = [];
        }

        if (
            isset($batch['item_count'])
            && (int) $batch['item_count'] !==
                count($rows)
        ) {
            $errors[] =
                'staged_item_count_mismatch';
        }

        if (count($rows) > 100000) {
            $errors[] =
                'staged_snapshot_too_large';
        }

        $existing =
            $this->normalizeExisting(
                $existingCanonical
            );

        $stagedByReference = [];
        $expectedRowNumber = 1;

        foreach ($rows as $index => $row) {
            $currentErrors = [];

            if (!is_array($row)) {
                $rowErrors[$index] = [
                    'staged_row_invalid',
                ];

                continue;
            }

            $rowNumber =
                (int) (
                    $row['row_number']
                    ?? 0
                );

            if (
                $rowNumber !==
                $expectedRowNumber
            ) {
                $currentErrors[] =
                    'row_number_sequence_invalid';
            }

            $expectedRowNumber++;

            $reference =
                trim(
                    (string) (
                        $row[
                            'source_reference'
                        ]
                        ?? ''
                    )
                );

            if (
                $reference === ''
                || $this->charLength(
                    $reference
                ) > 190
            ) {
                $currentErrors[] =
                    'source_reference_invalid';
            }

            if (
                $reference !== ''
                && array_key_exists(
                    $reference,
                    $stagedByReference
                )
            ) {
                $currentErrors[] =
                    'duplicate_source_reference';
            }

            $title =
                trim(
                    (string) (
                        $row['title']
                        ?? ''
                    )
                );

            if ($title === '') {
                $currentErrors[] =
                    'title_required';
            }

            if (
                $title !== ''
                && $this->charLength(
                    $title
                ) > 255
            ) {
                $currentErrors[] =
                    'title_too_long';
            }

            $parent =
                $this->nullableReference(
                    $row[
                        'parent_source_reference'
                    ]
                    ?? null
                );

            if (
                $reference !== ''
                && $parent === $reference
            ) {
                $currentErrors[] =
                    'self_parent_forbidden';
            }

            if (
                $parent !== null
                && $this->charLength(
                    $parent
                ) > 190
            ) {
                $currentErrors[] =
                    'parent_source_reference_invalid';
            }

            if (
                $hierarchyMode === 'flat'
                && $parent !== null
            ) {
                $currentErrors[] =
                    'flat_dimension_parent_forbidden';
            }

            $status =
                strtolower(
                    trim(
                        (string) (
                            $row['source_status']
                            ?? ''
                        )
                    )
                );

            if (
                !in_array(
                    $status,
                    self::MATERIALIZABLE_STATUSES,
                    true
                )
            ) {
                $currentErrors[] =
                    'source_status_not_materializable';
            }

            $payloadHash =
                strtolower(
                    trim(
                        (string) (
                            $row[
                                'payload_hash'
                            ]
                            ?? ''
                        )
                    )
                );

            if (
                preg_match(
                    '/^[a-f0-9]{64}$/',
                    $payloadHash
                ) !== 1
            ) {
                $currentErrors[] =
                    'payload_hash_invalid';
            }

            $attributesJson =
                $row[
                    'attributes_json'
                ]
                ?? null;

            if (
                !$this->validAttributesJson(
                    $attributesJson
                )
            ) {
                $currentErrors[] =
                    'attributes_json_invalid';
            }

            if (
                $reference !== ''
                && !array_key_exists(
                    $reference,
                    $stagedByReference
                )
            ) {
                $stagedByReference[
                    $reference
                ] = [
                    'row_index' =>
                        $index,

                    'row_number' =>
                        $rowNumber,

                    'source_reference' =>
                        $reference,

                    'parent_source_reference' =>
                        $parent,

                    'title' =>
                        $title,

                    'source_status' =>
                        $status,

                    'attributes_json' =>
                        $attributesJson,

                    'payload_hash' =>
                        $payloadHash,
                ];
            }

            if ($currentErrors !== []) {
                $rowErrors[$index] =
                    array_values(
                        array_unique(
                            $currentErrors
                        )
                    );
            }
        }

        $allReferences =
            array_fill_keys(
                array_merge(
                    array_keys($existing),
                    array_keys(
                        $stagedByReference
                    )
                ),
                true
            );

        foreach (
            $stagedByReference
            as $reference => $row
        ) {
            $parent =
                $row[
                    'parent_source_reference'
                ];

            if (
                $parent !== null
                && !isset(
                    $allReferences[$parent]
                )
            ) {
                $index =
                    $row['row_index'];

                $rowErrors[$index] =
                    array_values(
                        array_unique(
                            array_merge(
                                $rowErrors[$index]
                                ?? [],
                                [
                                    'parent_reference_unresolved',
                                ]
                            )
                        )
                    );
            }
        }

        $effectiveParents = [];

        foreach (
            $existing
            as $reference => $row
        ) {
            $effectiveParents[$reference] =
                $row[
                    'parent_source_reference'
                ];
        }

        foreach (
            $stagedByReference
            as $reference => $row
        ) {
            $effectiveParents[$reference] =
                $row[
                    'parent_source_reference'
                ];
        }

        $cycleMembers =
            $this->cycleMembers(
                $effectiveParents
            );

        foreach ($cycleMembers as $reference) {
            if (
                !isset(
                    $stagedByReference[$reference]
                )
            ) {
                continue;
            }

            $index =
                $stagedByReference[
                    $reference
                ]['row_index'];

            $rowErrors[$index] =
                array_values(
                    array_unique(
                        array_merge(
                            $rowErrors[$index]
                            ?? [],
                            [
                                'hierarchy_cycle_forbidden',
                            ]
                        )
                    )
                );
        }

        ksort($rowErrors);

        if ($rowErrors !== []) {
            $errors[] =
                'one_or_more_staged_rows_invalid';
        }

        $errors =
            array_values(
                array_unique(
                    $errors
                )
            );

        return [
            'materializable' =>
                $errors === [],

            'errors' =>
                $errors,

            'row_errors' =>
                $rowErrors,

            'snapshot_token' =>
                $snapshotToken,

            'dimension_id' =>
                $dimensionId,

            'dimension_code' =>
                $dimensionCode,

            'hierarchy_mode_code' =>
                $hierarchyMode,

            'staged_by_reference' =>
                $stagedByReference,

            'existing_by_reference' =>
                $existing,

            'effective_parent_map' =>
                $effectiveParents,
        ];
    }

    private function normalizeExisting(
        array $existingCanonical
    ): array {
        $normalized = [];

        foreach (
            $existingCanonical
            as $key => $row
        ) {
            if (!is_array($row)) {
                continue;
            }

            $reference =
                trim(
                    (string) (
                        $row[
                            'source_reference'
                        ]
                        ?? (
                            is_string($key)
                                ? $key
                                : ''
                        )
                    )
                );

            if ($reference === '') {
                continue;
            }

            $normalized[$reference] = [
                'source_reference' =>
                    $reference,

                'value_reference' =>
                    trim(
                        (string) (
                            $row[
                                'value_reference'
                            ]
                            ?? ''
                        )
                    ),

                'parent_source_reference' =>
                    $this->nullableReference(
                        $row[
                            'parent_source_reference'
                        ]
                        ?? null
                    ),
            ];
        }

        return $normalized;
    }

    private function nullableReference(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                (string) $value
            );

        return
            $value === ''
                ? null
                : $value;
    }

    private function validAttributesJson(
        mixed $raw
    ): bool {
        if ($raw === null) {
            return true;
        }

        if (!is_string($raw)) {
            return false;
        }

        $raw =
            trim($raw);

        if ($raw === '') {
            return true;
        }

        try {
            $decoded =
                json_decode(
                    $raw,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (\Throwable) {
            return false;
        }

        return
            is_array(
                $decoded
            );
    }


    private function charLength(
        string $value
    ): int {
        if (
            function_exists(
                'mb_strlen'
            )
        ) {
            return
                mb_strlen(
                    $value,
                    'UTF-8'
                );
        }

        if (
            preg_match_all(
                '/./us',
                $value,
                $matches
            ) === false
        ) {
            return strlen($value);
        }

        return
            count(
                $matches[0]
            );
    }


    private function cycleMembers(
        array $parents
    ): array {
        $cycleMembers = [];
        $done = [];

        foreach (
            array_keys($parents)
            as $start
        ) {
            if (isset($done[$start])) {
                continue;
            }

            $path = [];
            $position = [];
            $current = $start;

            while (
                $current !== null
                && array_key_exists(
                    $current,
                    $parents
                )
                && !isset($done[$current])
            ) {
                if (
                    array_key_exists(
                        $current,
                        $position
                    )
                ) {
                    $cycleStart =
                        $position[$current];

                    foreach (
                        array_slice(
                            $path,
                            $cycleStart
                        )
                        as $member
                    ) {
                        $cycleMembers[$member] =
                            true;
                    }

                    break;
                }

                $position[$current] =
                    count($path);

                $path[] =
                    $current;

                $current =
                    $parents[$current];
            }

            foreach ($path as $member) {
                $done[$member] = true;
            }
        }

        $members =
            array_keys(
                $cycleMembers
            );

        sort(
            $members,
            SORT_STRING
        );

        return $members;
    }
}

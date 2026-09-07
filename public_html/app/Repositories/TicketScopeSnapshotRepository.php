<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

/**
 * TICKETING_TICKET_CREATED_SCOPE_CAPTURE_REPOSITORY_V1
 *
 * Captures immutable Ticketing data-scope state inside the
 * caller's existing ticket-creation transaction.
 *
 * This repository deliberately does NOT:
 *
 * - begin a transaction;
 * - commit a transaction;
 * - roll back a transaction;
 * - evaluate staff access grants;
 * - change cartable/detail authorization.
 *
 * The caller owns transaction atomicity.
 */
final class TicketScopeSnapshotRepository
{
    public function __construct(
        private PDO $db
    ) {
    }


    public function captureCreatedTicket(
        int $ticketId,
        string $actorUserReference
    ): array {

        if (!$this->db->inTransaction()) {
            throw new RuntimeException(
                'ticket_scope_capture_requires_active_transaction'
            );
        }

        if ($ticketId <= 0) {
            throw new RuntimeException(
                'ticket_scope_capture_invalid_ticket'
            );
        }


        /*
         * Canonical subject/project context is derived from the
         * persisted Ticket row, not from untrusted form input.
         */
        $ticketStatement =
            $this->db->prepare("
                SELECT
                    t.id,
                    t.support_project_id,
                    t.requester_participant_id,
                    t.requester_organization_reference

                FROM ticketing_tickets t

                WHERE t.id = ?

                LIMIT 1

                FOR UPDATE
            ");

        $ticketStatement->execute([
            $ticketId,
        ]);

        $ticket =
            $ticketStatement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($ticket)) {
            throw new RuntimeException(
                'ticket_scope_capture_ticket_not_found'
            );
        }

        $projectId =
            (int) (
                $ticket[
                    'support_project_id'
                ]
                ?? 0
            );

        if ($projectId <= 0) {
            throw new RuntimeException(
                'ticket_scope_capture_project_missing'
            );
        }


        /*
         * Initial capture is exactly version 1.
         *
         * Future explicit re-scope is a separate operation and must
         * create a new version intentionally.
         */
        $existing =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM ticketing_ticket_scope_snapshots

                WHERE ticket_id = ?
            ");

        $existing->execute([
            $ticketId,
        ]);

        if (
            (int) $existing->fetchColumn()
            !== 0
        ) {
            throw new RuntimeException(
                'ticket_scope_snapshot_already_exists'
            );
        }


        /*
         * Resolve canonical stable subjects.
         *
         * Organization is included only when Ticket creation already
         * persisted an explicit authoritative organization reference.
         * Display labels are never treated as organization identity.
         */
        $subjects = [];

        $participantId =
            (int) (
                $ticket[
                    'requester_participant_id'
                ]
                ?? 0
            );

        if ($participantId > 0) {

            $participantStatement =
                $this->db->prepare("
                    SELECT
                        public_reference

                    FROM ticketing_participants

                    WHERE id = ?

                    LIMIT 1
                ");

            $participantStatement->execute([
                $participantId,
            ]);

            $participantReference =
                trim(
                    (string) (
                        $participantStatement
                            ->fetchColumn()
                        ?: ''
                    )
                );

            if ($participantReference === '') {
                throw new RuntimeException(
                    'ticket_scope_participant_reference_missing'
                );
            }

            $subjects[] = [
                'type' =>
                    'participant',

                'reference' =>
                    $participantReference,
            ];
        }


        $organizationReference =
            trim(
                (string) (
                    $ticket[
                        'requester_organization_reference'
                    ]
                    ?? ''
                )
            );

        if ($organizationReference !== '') {

            $subjects[] = [
                'type' =>
                    'organization',

                'reference' =>
                    $organizationReference,
            ];
        }


        /*
         * Capture the active dimension definition set even when
         * the requester has zero effective values in a dimension.
         */
        $dimensionStatement =
            $this->db->prepare("
                SELECT
                    d.id,
                    d.public_reference,
                    d.code,
                    d.title,
                    d.cardinality_code,
                    d.hierarchy_mode_code,
                    d.source_mode_code,
                    d.source_key,
                    d.supports_descendants

                FROM ticketing_scope_dimensions d

                WHERE d.project_id = ?
                  AND d.status = 'active'

                ORDER BY
                    d.sort_order,
                    d.id
            ");

        $dimensionStatement->execute([
            $projectId,
        ]);

        $dimensions =
            $dimensionStatement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $dimensionMap = [];

        foreach ($dimensions as $dimension) {

            $dimensionId =
                (int) (
                    $dimension['id']
                    ?? 0
                );

            if ($dimensionId <= 0) {
                throw new RuntimeException(
                    'ticket_scope_dimension_identity_invalid'
                );
            }

            $dimensionMap[
                $dimensionId
            ] = $dimension;
        }


        /*
         * Effective facts from all stable requester subjects are
         * unioned and deduplicated by:
         *
         *   dimension_id + dimension_value_id
         *
         * valid_until is exclusive.
         */
        $factsByDimension = [];
        $factReferences = [];

        $factStatement =
            $this->db->prepare("
                SELECT
                    f.public_reference
                        AS fact_reference,

                    f.dimension_id,
                    f.dimension_value_id,
                    f.is_primary,

                    v.value_reference,
                    v.code
                        AS value_code,
                    v.title
                        AS value_title,
                    v.source_reference

                FROM
                    ticketing_scope_subject_facts f

                INNER JOIN
                    ticketing_scope_dimensions d

                    ON d.id =
                        f.dimension_id

                   AND d.project_id = ?
                   AND d.status = 'active'

                INNER JOIN
                    ticketing_scope_dimension_values v

                    ON v.dimension_id =
                        f.dimension_id

                   AND v.id =
                        f.dimension_value_id

                   AND v.status = 'active'

                WHERE
                    f.subject_type_code = ?

                  AND f.subject_reference = ?

                  AND f.status = 'active'

                  AND
                  (
                        f.valid_from IS NULL
                        OR f.valid_from <= UTC_TIMESTAMP()
                  )

                  AND
                  (
                        f.valid_until IS NULL
                        OR f.valid_until > UTC_TIMESTAMP()
                  )

                ORDER BY
                    f.dimension_id,
                    f.dimension_value_id,
                    f.id
            ");


        foreach ($subjects as $subject) {

            $factStatement->execute([
                $projectId,
                (string) $subject['type'],
                (string) $subject['reference'],
            ]);

            $rows =
                $factStatement->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];

            foreach ($rows as $row) {

                $dimensionId =
                    (int) (
                        $row['dimension_id']
                        ?? 0
                    );

                $valueId =
                    (int) (
                        $row[
                            'dimension_value_id'
                        ]
                        ?? 0
                    );

                if (
                    $dimensionId <= 0
                    || $valueId <= 0
                    || !isset(
                        $dimensionMap[
                            $dimensionId
                        ]
                    )
                ) {
                    throw new RuntimeException(
                        'ticket_scope_fact_dimension_mismatch'
                    );
                }


                if (
                    !isset(
                        $factsByDimension[
                            $dimensionId
                        ][
                            $valueId
                        ]
                    )
                ) {
                    $factsByDimension[
                        $dimensionId
                    ][
                        $valueId
                    ] = [
                        'dimension_value_id' =>
                            $valueId,

                        'value_reference' =>
                            (string) (
                                $row[
                                    'value_reference'
                                ]
                                ?? ''
                            ),

                        'value_code' =>
                            $row[
                                'value_code'
                            ]
                            !== null
                                ? (string) $row[
                                    'value_code'
                                ]
                                : null,

                        'value_title' =>
                            (string) (
                                $row[
                                    'value_title'
                                ]
                                ?? ''
                            ),

                        'source_reference' =>
                            $row[
                                'source_reference'
                            ]
                            !== null
                                ? (string) $row[
                                    'source_reference'
                                ]
                                : null,

                        'is_primary' =>
                            !empty(
                                $row[
                                    'is_primary'
                                ]
                            )
                                ? 1
                                : 0,
                    ];

                } elseif (
                    !empty(
                        $row[
                            'is_primary'
                        ]
                    )
                ) {
                    /*
                     * Duplicate facts from participant/organization
                     * do not duplicate snapshot values.
                     */
                    $factsByDimension[
                        $dimensionId
                    ][
                        $valueId
                    ][
                        'is_primary'
                    ] = 1;
                }


                $factReference =
                    trim(
                        (string) (
                            $row[
                                'fact_reference'
                            ]
                            ?? ''
                        )
                    );

                if ($factReference !== '') {
                    $factReferences[
                        $factReference
                    ] = true;
                }
            }
        }


        /*
         * Fail closed on inconsistent single-cardinality master data.
         */
        foreach (
            $dimensionMap
            as $dimensionId => $dimension
        ) {
            $cardinality =
                strtolower(
                    trim(
                        (string) (
                            $dimension[
                                'cardinality_code'
                            ]
                            ?? ''
                        )
                    )
                );

            $values =
                $factsByDimension[
                    $dimensionId
                ]
                ?? [];

            if (
                $cardinality === 'single'
                && count($values) > 1
            ) {
                throw new RuntimeException(
                    'ticket_scope_single_cardinality_conflict:'
                    . $dimensionId
                );
            }
        }


        $actorUserReference =
            trim(
                $actorUserReference
            );

        $metadata = [
            'contract' =>
                'TICKETING_TICKET_CREATED_SCOPE_CAPTURE_V1',

            'subjects' =>
                $subjects,

            'fact_references' =>
                array_values(
                    array_keys(
                        $factReferences
                    )
                ),
        ];

        $metadataJson =
            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );


        $snapshotReference =
            $this->snapshotReference();

        $snapshotStatement =
            $this->db->prepare("
                INSERT INTO
                    ticketing_ticket_scope_snapshots
                (
                    public_reference,
                    ticket_id,
                    version_no,
                    capture_reason_code,
                    captured_by_user_reference,
                    captured_at,
                    metadata_json,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    1,
                    'ticket_created',
                    ?,
                    UTC_TIMESTAMP(),
                    ?,
                    UTC_TIMESTAMP()
                )
            ");

        $snapshotStatement->execute([
            $snapshotReference,
            $ticketId,
            $actorUserReference !== ''
                ? $actorUserReference
                : null,
            $metadataJson,
        ]);

        $snapshotId =
            (int) $this->db->lastInsertId();

        if ($snapshotId <= 0) {
            throw new RuntimeException(
                'ticket_scope_snapshot_insert_failed'
            );
        }


        $dimensionInsert =
            $this->db->prepare("
                INSERT INTO
                    ticketing_ticket_scope_snapshot_dimensions
                (
                    snapshot_id,
                    dimension_id,
                    dimension_reference_snapshot,
                    dimension_code_snapshot,
                    dimension_title_snapshot,
                    cardinality_code_snapshot,
                    hierarchy_mode_code_snapshot,
                    source_mode_code_snapshot,
                    source_key_snapshot,
                    supports_descendants_snapshot,
                    created_at
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
                    UTC_TIMESTAMP()
                )
            ");


        $valueInsert =
            $this->db->prepare("
                INSERT INTO
                    ticketing_ticket_scope_snapshot_values
                (
                    snapshot_id,
                    dimension_id,
                    dimension_value_id,
                    value_reference_snapshot,
                    value_code_snapshot,
                    value_title_snapshot,
                    source_reference_snapshot,
                    is_primary,
                    created_at
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
                    UTC_TIMESTAMP()
                )
            ");


        $dimensionCount = 0;
        $valueCount = 0;

        foreach (
            $dimensionMap
            as $dimensionId => $dimension
        ) {

            $dimensionInsert->execute([
                $snapshotId,
                $dimensionId,

                (string) $dimension[
                    'public_reference'
                ],

                (string) $dimension[
                    'code'
                ],

                (string) $dimension[
                    'title'
                ],

                (string) $dimension[
                    'cardinality_code'
                ],

                (string) $dimension[
                    'hierarchy_mode_code'
                ],

                (string) $dimension[
                    'source_mode_code'
                ],

                $dimension[
                    'source_key'
                ]
                !== null
                    ? (string) $dimension[
                        'source_key'
                    ]
                    : null,

                !empty(
                    $dimension[
                        'supports_descendants'
                    ]
                )
                    ? 1
                    : 0,
            ]);

            $dimensionCount++;


            $values =
                $factsByDimension[
                    $dimensionId
                ]
                ?? [];

            ksort(
                $values,
                SORT_NUMERIC
            );

            foreach ($values as $value) {

                $valueInsert->execute([
                    $snapshotId,
                    $dimensionId,

                    (int) $value[
                        'dimension_value_id'
                    ],

                    (string) $value[
                        'value_reference'
                    ],

                    $value[
                        'value_code'
                    ],

                    (string) $value[
                        'value_title'
                    ],

                    $value[
                        'source_reference'
                    ],

                    !empty(
                        $value[
                            'is_primary'
                        ]
                    )
                        ? 1
                        : 0,
                ]);

                $valueCount++;
            }
        }


        $stateStatement =
            $this->db->prepare("
                INSERT INTO
                    ticketing_ticket_scope_states
                (
                    ticket_id,
                    current_snapshot_id,
                    current_version_no,
                    updated_by_user_reference,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    1,
                    ?,
                    UTC_TIMESTAMP()
                )
            ");

        $stateStatement->execute([
            $ticketId,
            $snapshotId,
            $actorUserReference !== ''
                ? $actorUserReference
                : null,
        ]);


        return [
            'snapshot_id' =>
                $snapshotId,

            'snapshot_reference' =>
                $snapshotReference,

            'version_no' =>
                1,

            'dimension_count' =>
                $dimensionCount,

            'value_count' =>
                $valueCount,

            'subject_count' =>
                count(
                    $subjects
                ),

            'fact_count' =>
                count(
                    $factReferences
                ),
        ];
    }


    private function snapshotReference(): string
    {
        return
            'TSNP-'
            . strtoupper(
                substr(
                    bin2hex(
                        random_bytes(16)
                    ),
                    0,
                    28
                )
            );
    }
}

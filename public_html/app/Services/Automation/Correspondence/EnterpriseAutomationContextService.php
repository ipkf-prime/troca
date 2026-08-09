<?php

namespace App\Services\Automation\Correspondence;

use App\Services\Organization\UserOrganizationalContextResolver;
use IPKF\Database\Database;
use PDO;
use RuntimeException;

final class EnterpriseAutomationContextService
{
    private PDO $core;

    private UserOrganizationalContextResolver $resolver;

    public function __construct(
        ?PDO $core = null,
        ?UserOrganizationalContextResolver $resolver = null
    ) {
        $this->core = $core ?? Database::connect();

        $this->resolver =
            $resolver
            ?? new UserOrganizationalContextResolver(
                $this->core
            );
    }

    public function forUser(int $userId): array
    {
        if ($userId < 1) {
            throw new RuntimeException(
                'automation_user_context_required'
            );
        }

        $selected =
            $this->resolver->current(
                $userId
            );

        if ($selected === null) {
            throw new RuntimeException(
                'automation_organizational_context_required'
            );
        }

        $appointmentReference = trim(
            (string) (
                $selected[
                    'appointment_reference'
                ] ?? ''
            )
        );

        if ($appointmentReference === '') {
            throw new RuntimeException(
                'automation_appointment_context_required'
            );
        }

        $statement = $this->core->prepare("
            SELECT
                a.id AS appointment_id,
                a.public_reference AS appointment_reference,
                a.organization_id,
                a.appointment_kind,

                p.public_reference AS person_reference,
                COALESCE(
                    NULLIF(p.display_name_fa, ''),
                    p.full_name
                ) AS person_name_fa,
                NULLIF(
                    p.display_name_en,
                    ''
                ) AS person_name_en,

                o.public_reference AS organization_reference,
                o.parent_id AS organization_parent_id,
                COALESCE(
                    NULLIF(o.title_fa, ''),
                    o.title
                ) AS organization_title_fa,
                NULLIF(
                    o.title_en,
                    ''
                ) AS organization_title_en,

                ou.id AS org_unit_id,
                ou.public_reference AS org_unit_reference,
                COALESCE(
                    NULLIF(ou.title_fa, ''),
                    ou.title
                ) AS org_unit_title_fa,
                NULLIF(
                    ou.title_en,
                    ''
                ) AS org_unit_title_en,

                op.id AS organization_position_id,
                op.public_reference AS position_reference,
                COALESCE(
                    NULLIF(op.title_fa, ''),
                    NULLIF(op.title_override, ''),
                    pos.title
                ) AS position_title_fa,
                COALESCE(
                    NULLIF(op.title_en, ''),
                    NULLIF(pos.title_en, '')
                ) AS position_title_en

            FROM users u

            INNER JOIN persons p
                ON p.id = u.person_id

            INNER JOIN organization_appointments a
                ON a.person_id = p.id

            INNER JOIN organization_positions op
                ON op.id =
                    a.organization_position_id

            INNER JOIN organizations o
                ON o.id =
                    a.organization_id

            LEFT JOIN org_units ou
                ON ou.id =
                    op.org_unit_id

            INNER JOIN positions pos
                ON pos.id =
                    op.position_id

            WHERE u.id = ?
              AND a.public_reference = ?
              AND u.status = 'active'
              AND p.status = 'active'
              AND a.status = 'active'
              AND a.revoked_at IS NULL
              AND (
                    a.valid_from IS NULL
                    OR a.valid_from <= CURRENT_DATE
              )
              AND (
                    a.valid_to IS NULL
                    OR a.valid_to >= CURRENT_DATE
              )
              AND op.status = 'active'
              AND o.is_active = 1
              AND (
                    ou.id IS NULL
                    OR ou.status = 'active'
              )

            LIMIT 1
        ");

        $statement->execute([
            $userId,
            $appointmentReference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if ($row === false) {
            throw new RuntimeException(
                'automation_organizational_context_invalid'
            );
        }

        $organizations =
            $this->organizationGraph();

        $organizationId =
            (int) $row['organization_id'];

        if (
            !isset(
                $organizations[
                    $organizationId
                ]
            )
        ) {
            throw new RuntimeException(
                'automation_organization_missing'
            );
        }

        $root =
            $this->rootOrganization(
                $organizationId,
                $organizations
            );

        $rootId =
            (int) $root['id'];

        /*
         * Acting from the root organization gives the
         * appointment consolidated visibility over that
         * root and its descendants.
         *
         * Acting from a child organization is intentionally
         * restricted to that organization.
         */
        $accessibleOrganizationIds =
            $organizationId === $rootId
                ? $this->descendantIds(
                    $rootId,
                    $organizations
                )
                : [$organizationId];

        sort(
            $accessibleOrganizationIds,
            SORT_NUMERIC
        );

        $snapshot = [
            'person' => [
                'reference' =>
                    $row['person_reference'] ?? null,

                'name_fa' =>
                    $row['person_name_fa'] ?? null,

                'name_en' =>
                    $row['person_name_en'] ?? null,
            ],

            'appointment' => [
                'id' =>
                    (int) $row['appointment_id'],

                'reference' =>
                    (string) $row[
                        'appointment_reference'
                    ],

                'kind' =>
                    (string) (
                        $row[
                            'appointment_kind'
                        ] ?? ''
                    ),
            ],

            'root_organization' => [
                'id' => $rootId,

                'reference' =>
                    $root[
                        'public_reference'
                    ] ?? null,

                'title_fa' =>
                    $root['title_fa'] ?? null,

                'title_en' =>
                    $root['title_en'] ?? null,
            ],

            'organization' => [
                'id' =>
                    $organizationId,

                'reference' =>
                    $row[
                        'organization_reference'
                    ] ?? null,

                'title_fa' =>
                    $row[
                        'organization_title_fa'
                    ] ?? null,

                'title_en' =>
                    $row[
                        'organization_title_en'
                    ] ?? null,
            ],

            'org_unit' => [
                'id' =>
                    isset($row['org_unit_id'])
                        && $row['org_unit_id'] !== null
                            ? (int) $row[
                                'org_unit_id'
                            ]
                            : null,

                'reference' =>
                    $row[
                        'org_unit_reference'
                    ] ?? null,

                'title_fa' =>
                    $row[
                        'org_unit_title_fa'
                    ] ?? null,

                'title_en' =>
                    $row[
                        'org_unit_title_en'
                    ] ?? null,
            ],

            'position' => [
                'id' =>
                    (int) $row[
                        'organization_position_id'
                    ],

                'reference' =>
                    $row[
                        'position_reference'
                    ] ?? null,

                'title_fa' =>
                    $row[
                        'position_title_fa'
                    ] ?? null,

                'title_en' =>
                    $row[
                        'position_title_en'
                    ] ?? null,
            ],
        ];

        return [
            'user_id' => $userId,

            'person_reference' =>
                (string) (
                    $row[
                        'person_reference'
                    ] ?? ''
                ),

            'appointment_id' =>
                (int) $row[
                    'appointment_id'
                ],

            'appointment_reference' =>
                (string) $row[
                    'appointment_reference'
                ],

            'root_organization_id' =>
                $rootId,

            'root_organization_reference' =>
                (string) (
                    $root[
                        'public_reference'
                    ] ?? ''
                ),

            'organization_id' =>
                $organizationId,

            'organization_reference' =>
                (string) (
                    $row[
                        'organization_reference'
                    ] ?? ''
                ),

            'org_unit_id' =>
                isset($row['org_unit_id'])
                && $row['org_unit_id'] !== null
                    ? (int) $row[
                        'org_unit_id'
                    ]
                    : null,

            'org_unit_reference' =>
                $row[
                    'org_unit_reference'
                ] ?? null,

            'organization_position_id' =>
                (int) $row[
                    'organization_position_id'
                ],

            'position_reference' =>
                (string) (
                    $row[
                        'position_reference'
                    ] ?? ''
                ),

            'accessible_organization_ids' =>
                $accessibleOrganizationIds,

            'repository_scope' => [
                'root_organization_id' =>
                    $rootId,

                'organization_ids' =>
                    $accessibleOrganizationIds,
            ],

            'snapshot' => $snapshot,

            'snapshot_json' =>
                json_encode(
                    $snapshot,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
        ];
    }

    public function assertCorrespondenceAccess(
        array $correspondence,
        array $actor
    ): void {
        $organizationId =
            (int) (
                $correspondence[
                    'organization_id'
                ] ?? 0
            );

        $allowed =
            array_map(
                'intval',
                $actor[
                    'accessible_organization_ids'
                ] ?? []
            );

        if (
            $organizationId < 1
            || !in_array(
                $organizationId,
                $allowed,
                true
            )
        ) {
            throw new RuntimeException(
                'automation_correspondence_scope_denied'
            );
        }

        $storedRoot =
            (int) (
                $correspondence[
                    'root_organization_id'
                ] ?? 0
            );

        $actorRoot =
            (int) (
                $actor[
                    'root_organization_id'
                ] ?? 0
            );

        /*
         * NULL/0 root is tolerated only for legacy rows
         * created before the enterprise foundation.
         * Organization scope still applies above.
         */
        if (
            $storedRoot > 0
            && $storedRoot !== $actorRoot
        ) {
            throw new RuntimeException(
                'automation_correspondence_root_scope_denied'
            );
        }
    }

    private function organizationGraph(): array
    {
        $rows =
            $this->core
                ->query("
                    SELECT
                        id,
                        parent_id,
                        public_reference,
                        COALESCE(
                            NULLIF(title_fa, ''),
                            title
                        ) AS title_fa,
                        NULLIF(
                            title_en,
                            ''
                        ) AS title_en
                    FROM organizations
                    WHERE COALESCE(
                        is_active,
                        1
                    ) = 1
                    ORDER BY id
                ")
                ->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];

        $graph = [];

        foreach ($rows as $row) {
            $graph[
                (int) $row['id']
            ] = $row;
        }

        return $graph;
    }

    private function rootOrganization(
        int $organizationId,
        array $graph
    ): array {
        $current =
            $graph[$organizationId]
            ?? null;

        if ($current === null) {
            throw new RuntimeException(
                'automation_organization_missing'
            );
        }

        $visited = [];

        for (
            $depth = 0;
            $depth < 100;
            $depth++
        ) {
            $id =
                (int) $current['id'];

            if (isset($visited[$id])) {
                throw new RuntimeException(
                    'automation_organization_cycle_detected'
                );
            }

            $visited[$id] = true;

            $parentId =
                isset($current['parent_id'])
                && $current['parent_id'] !== null
                    ? (int) $current[
                        'parent_id'
                    ]
                    : 0;

            if ($parentId < 1) {
                return $current;
            }

            if (!isset($graph[$parentId])) {
                throw new RuntimeException(
                    'automation_root_organization_missing'
                );
            }

            $current =
                $graph[$parentId];
        }

        throw new RuntimeException(
            'automation_organization_depth_invalid'
        );
    }

    private function descendantIds(
        int $rootId,
        array $graph
    ): array {
        $children = [];

        foreach ($graph as $id => $row) {
            $parentId =
                isset($row['parent_id'])
                && $row['parent_id'] !== null
                    ? (int) $row[
                        'parent_id'
                    ]
                    : 0;

            if ($parentId > 0) {
                $children[
                    $parentId
                ][] = (int) $id;
            }
        }

        $result = [];
        $queue = [$rootId];

        while ($queue !== []) {
            $id = array_shift($queue);

            if (
                isset(
                    $result[$id]
                )
            ) {
                continue;
            }

            $result[$id] = true;

            foreach (
                $children[$id] ?? []
                as $childId
            ) {
                $queue[] =
                    (int) $childId;
            }
        }

        return array_map(
            'intval',
            array_keys($result)
        );
    }
}

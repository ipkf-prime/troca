<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;

/**
 * GENERIC_ORGANIZATION_NETWORK_SERVICE_V1
 *
 * Product-wide read contract for catalog-scoped organization graphs.
 *
 * This service intentionally knows nothing about a specific customer,
 * industry, geography convention, application module, or source system.
 *
 * A catalog is a network/holding/context.
 * organizations is the canonical directory.
 * organization_relations is the graph.
 */
final class OrganizationNetworkService extends BaseService
{
    public const DEFAULT_HIERARCHY_RELATION =
        'hierarchy_parent';

    private PDO $db;


    public function __construct(
        ?PDO $db = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve('core.primary');
    }


    public function catalogs(
        string $status = 'active'
    ): array {
        $statement = $this->db->prepare("
            SELECT
                id,
                public_reference,
                code,
                title,
                description,
                scope_code,
                owner_type_code,
                owner_reference,
                is_detachable,
                status,
                metadata_json,
                created_at,
                updated_at
            FROM organization_catalogs
            WHERE status = ?
            ORDER BY title, id
        ");

        $statement->execute([
            $status,
        ]);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
    }


    public function catalog(
        int|string $reference
    ): ?array {
        if (
            is_int($reference)
            || ctype_digit(
                trim((string) $reference)
            )
        ) {
            $statement = $this->db->prepare("
                SELECT *
                FROM organization_catalogs
                WHERE id = ?
                LIMIT 1
            ");

            $statement->execute([
                (int) $reference,
            ]);
        } else {
            $value = trim((string) $reference);

            $statement = $this->db->prepare("
                SELECT *
                FROM organization_catalogs
                WHERE code = ?
                   OR public_reference = ?
                ORDER BY
                    CASE WHEN code = ? THEN 0 ELSE 1 END,
                    id
                LIMIT 1
            ");

            $statement->execute([
                $value,
                $value,
                $value,
            ]);
        }

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }


    public function node(
        int|string $catalogReference,
        int|string $organizationReference
    ): ?array {
        $catalog = $this->requireCatalog(
            $catalogReference
        );

        $organizationId =
            $this->resolveOrganizationId(
                (int) $catalog['id'],
                $organizationReference
            );

        if ($organizationId < 1) {
            return null;
        }

        return $this->fetchNodeById(
            (int) $catalog['id'],
            $organizationId
        );
    }


    public function search(
        int|string $catalogReference,
        string $query = '',
        int $limit = 50,
        int $offset = 0
    ): array {
        $catalog = $this->requireCatalog(
            $catalogReference
        );

        $limit = max(
            1,
            min(200, $limit)
        );

        $offset = max(
            0,
            $offset
        );

        $query = trim($query);

        $sql = "
            SELECT
                entries.id
                    AS catalog_entry_id,
                entries.catalog_id,
                entries.record_mode_code,
                entries.source_record_reference,
                entries.status
                    AS catalog_entry_status,
                entries.metadata_json
                    AS catalog_entry_metadata_json,

                organizations.id
                    AS organization_id,
                organizations.public_reference
                    AS organization_public_reference,
                organizations.title,
                organizations.title_fa,
                organizations.title_en,
                organizations.short_title,
                organizations.is_active,
                organizations.created_at,
                organizations.updated_at
            FROM organization_catalog_entries
                AS entries
            INNER JOIN organizations
                ON organizations.id =
                    entries.organization_id
            WHERE entries.catalog_id = ?
              AND entries.status = 'active'
              AND organizations.is_active = 1
        ";

        $parameters = [
            (int) $catalog['id'],
        ];

        if ($query !== '') {
            $sql .= "
              AND (
                    organizations.title LIKE ?
                 OR organizations.title_fa LIKE ?
                 OR organizations.title_en LIKE ?
                 OR organizations.short_title LIKE ?
                 OR entries.source_record_reference LIKE ?
                 OR EXISTS (
                        SELECT 1
                        FROM organization_external_identifiers
                            AS identifiers
                        WHERE identifiers.organization_id =
                                organizations.id
                          AND (
                                identifiers.catalog_id IS NULL
                             OR identifiers.catalog_id =
                                entries.catalog_id
                          )
                          AND identifiers.status = 'active'
                          AND (
                                identifiers.identifier_value LIKE ?
                             OR identifiers.normalized_value LIKE ?
                          )
                    )
              )
            ";

            $like = '%' . $query . '%';

            $parameters = array_merge(
                $parameters,
                [
                    $like,
                    $like,
                    $like,
                    $like,
                    $like,
                    $like,
                    $like,
                ]
            );
        }

        $sql .= "
            ORDER BY
                COALESCE(
                    NULLIF(organizations.title_fa, ''),
                    organizations.title
                ),
                organizations.id
            LIMIT {$limit}
            OFFSET {$offset}
        ";

        $statement = $this->db->prepare(
            $sql
        );

        $statement->execute(
            $parameters
        );

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

        return array_map(
            fn(array $row): array =>
                $this->normalizeNode($row),
            $rows
        );
    }


    public function parents(
        int|string $catalogReference,
        int|string $organizationReference,
        string $relationCode =
            self::DEFAULT_HIERARCHY_RELATION
    ): array {
        $catalog = $this->requireCatalog(
            $catalogReference
        );

        $organizationId =
            $this->requireOrganizationId(
                (int) $catalog['id'],
                $organizationReference
            );

        $statement = $this->db->prepare("
            SELECT
                entries.id
                    AS catalog_entry_id,
                entries.catalog_id,
                entries.record_mode_code,
                entries.source_record_reference,
                entries.status
                    AS catalog_entry_status,
                entries.metadata_json
                    AS catalog_entry_metadata_json,

                organizations.id
                    AS organization_id,
                organizations.public_reference
                    AS organization_public_reference,
                organizations.title,
                organizations.title_fa,
                organizations.title_en,
                organizations.short_title,
                organizations.is_active,
                organizations.created_at,
                organizations.updated_at,

                relations.id
                    AS relation_id,
                relations.is_primary
                    AS relation_is_primary,
                relations.source_code
                    AS relation_source_code,
                relations.source_reference
                    AS relation_source_reference
            FROM organization_relations
                AS relations
            INNER JOIN organization_relation_types
                AS relation_types
                ON relation_types.id =
                    relations.relation_type_id
            INNER JOIN organization_catalog_entries
                AS entries
                ON entries.catalog_id =
                    relations.catalog_id
               AND entries.organization_id =
                    relations.source_organization_id
               AND entries.status = 'active'
            INNER JOIN organizations
                ON organizations.id =
                    relations.source_organization_id
            WHERE relations.catalog_id = ?
              AND relations.target_organization_id = ?
              AND relations.status = 'active'
              AND relation_types.code = ?
              AND relation_types.is_hierarchical = 1
              AND relation_types.status = 'active'
              AND organizations.is_active = 1
            ORDER BY
                relations.is_primary DESC,
                relations.id
        ");

        $statement->execute([
            (int) $catalog['id'],
            $organizationId,
            $relationCode,
        ]);

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

        return array_map(
            fn(array $row): array =>
                $this->normalizeNode($row),
            $rows
        );
    }


    public function parent(
        int|string $catalogReference,
        int|string $organizationReference,
        string $relationCode =
            self::DEFAULT_HIERARCHY_RELATION
    ): ?array {
        $parents = $this->parents(
            $catalogReference,
            $organizationReference,
            $relationCode
        );

        return $parents[0] ?? null;
    }


    public function children(
        int|string $catalogReference,
        int|string $organizationReference,
        string $relationCode =
            self::DEFAULT_HIERARCHY_RELATION
    ): array {
        $catalog = $this->requireCatalog(
            $catalogReference
        );

        $organizationId =
            $this->requireOrganizationId(
                (int) $catalog['id'],
                $organizationReference
            );

        $statement = $this->db->prepare("
            SELECT
                entries.id
                    AS catalog_entry_id,
                entries.catalog_id,
                entries.record_mode_code,
                entries.source_record_reference,
                entries.status
                    AS catalog_entry_status,
                entries.metadata_json
                    AS catalog_entry_metadata_json,

                organizations.id
                    AS organization_id,
                organizations.public_reference
                    AS organization_public_reference,
                organizations.title,
                organizations.title_fa,
                organizations.title_en,
                organizations.short_title,
                organizations.is_active,
                organizations.created_at,
                organizations.updated_at,

                relations.id
                    AS relation_id,
                relations.is_primary
                    AS relation_is_primary,
                relations.source_code
                    AS relation_source_code,
                relations.source_reference
                    AS relation_source_reference
            FROM organization_relations
                AS relations
            INNER JOIN organization_relation_types
                AS relation_types
                ON relation_types.id =
                    relations.relation_type_id
            INNER JOIN organization_catalog_entries
                AS entries
                ON entries.catalog_id =
                    relations.catalog_id
               AND entries.organization_id =
                    relations.target_organization_id
               AND entries.status = 'active'
            INNER JOIN organizations
                ON organizations.id =
                    relations.target_organization_id
            WHERE relations.catalog_id = ?
              AND relations.source_organization_id = ?
              AND relations.status = 'active'
              AND relation_types.code = ?
              AND relation_types.is_hierarchical = 1
              AND relation_types.status = 'active'
              AND organizations.is_active = 1
            ORDER BY
                COALESCE(
                    NULLIF(organizations.title_fa, ''),
                    organizations.title
                ),
                organizations.id
        ");

        $statement->execute([
            (int) $catalog['id'],
            $organizationId,
            $relationCode,
        ]);

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

        return array_map(
            fn(array $row): array =>
                $this->normalizeNode($row),
            $rows
        );
    }


    public function ancestors(
        int|string $catalogReference,
        int|string $organizationReference,
        string $relationCode =
            self::DEFAULT_HIERARCHY_RELATION,
        int $maxDepth = 64
    ): array {
        $catalog = $this->requireCatalog(
            $catalogReference
        );

        $start =
            $this->requireOrganizationId(
                (int) $catalog['id'],
                $organizationReference
            );

        $maxDepth = max(
            1,
            min(256, $maxDepth)
        );

        $visited = [
            $start => true,
        ];

        $queue = [
            [
                'organization_id' => $start,
                'depth' => 0,
            ],
        ];

        $result = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            $depth = (int) $current['depth'];

            if ($depth >= $maxDepth) {
                continue;
            }

            $parents = $this->parents(
                (int) $catalog['id'],
                (int) $current['organization_id'],
                $relationCode
            );

            foreach ($parents as $parent) {
                $id = (int) $parent[
                    'organization_id'
                ];

                if (isset($visited[$id])) {
                    continue;
                }

                $visited[$id] = true;

                $parent['network_depth'] =
                    $depth + 1;

                $result[] = $parent;

                $queue[] = [
                    'organization_id' => $id,
                    'depth' => $depth + 1,
                ];
            }
        }

        return $result;
    }


    public function descendants(
        int|string $catalogReference,
        int|string $organizationReference,
        string $relationCode =
            self::DEFAULT_HIERARCHY_RELATION,
        int $maxDepth = 64
    ): array {
        $catalog = $this->requireCatalog(
            $catalogReference
        );

        $start =
            $this->requireOrganizationId(
                (int) $catalog['id'],
                $organizationReference
            );

        $maxDepth = max(
            1,
            min(256, $maxDepth)
        );

        $visited = [
            $start => true,
        ];

        $queue = [
            [
                'organization_id' => $start,
                'depth' => 0,
            ],
        ];

        $result = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            $depth = (int) $current['depth'];

            if ($depth >= $maxDepth) {
                continue;
            }

            $children = $this->children(
                (int) $catalog['id'],
                (int) $current['organization_id'],
                $relationCode
            );

            foreach ($children as $child) {
                $id = (int) $child[
                    'organization_id'
                ];

                if (isset($visited[$id])) {
                    continue;
                }

                $visited[$id] = true;

                $child['network_depth'] =
                    $depth + 1;

                $result[] = $child;

                $queue[] = [
                    'organization_id' => $id,
                    'depth' => $depth + 1,
                ];
            }
        }

        return $result;
    }


    public function primaryPath(
        int|string $catalogReference,
        int|string $organizationReference,
        string $relationCode =
            self::DEFAULT_HIERARCHY_RELATION,
        int $maxDepth = 64
    ): array {
        $catalog = $this->requireCatalog(
            $catalogReference
        );

        $node = $this->node(
            (int) $catalog['id'],
            $organizationReference
        );

        if ($node === null) {
            return [];
        }

        $path = [
            $node,
        ];

        $visited = [
            (int) $node['organization_id'] => true,
        ];

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            $parent = $this->parent(
                (int) $catalog['id'],
                (int) $path[0]['organization_id'],
                $relationCode
            );

            if ($parent === null) {
                break;
            }

            $id = (int) $parent[
                'organization_id'
            ];

            if (isset($visited[$id])) {
                throw new RuntimeException(
                    'Organization network cycle detected.'
                );
            }

            $visited[$id] = true;

            array_unshift(
                $path,
                $parent
            );
        }

        return $path;
    }


    public function authorizationContext(
        int|string $catalogReference,
        int|string $organizationReference,
        string $relationCode =
            self::DEFAULT_HIERARCHY_RELATION
    ): array {
        $catalog = $this->requireCatalog(
            $catalogReference
        );

        $node = $this->node(
            (int) $catalog['id'],
            $organizationReference
        );

        if ($node === null) {
            throw new RuntimeException(
                'Organization is not an active member of the catalog.'
            );
        }

        $ancestors = $this->ancestors(
            (int) $catalog['id'],
            (int) $node['organization_id'],
            $relationCode
        );

        $ancestorReferences = [];

        foreach ($ancestors as $ancestor) {
            $ancestorReferences[] =
                (string) $ancestor[
                    'organization_reference'
                ];
        }

        return [
            'scope_type' =>
                'organization',

            'scope_reference' =>
                (string) $node[
                    'organization_reference'
                ],

            'ancestors' => [
                'organization' =>
                    array_values(
                        array_unique(
                            $ancestorReferences
                        )
                    ),
            ],

            'attributes' => [
                'catalog_id' =>
                    (int) $catalog['id'],

                'catalog_code' =>
                    (string) $catalog['code'],

                'catalog_reference' =>
                    (string) $catalog[
                        'public_reference'
                    ],

                'hierarchy_relation_code' =>
                    $relationCode,

                'organization_id' =>
                    (int) $node[
                        'organization_id'
                    ],

                'organization_reference' =>
                    (string) $node[
                        'organization_reference'
                    ],
            ],
        ];
    }


    private function requireCatalog(
        int|string $reference
    ): array {
        $catalog = $this->catalog(
            $reference
        );

        if ($catalog === null) {
            throw new RuntimeException(
                'Organization catalog was not found.'
            );
        }

        if (
            (string) ($catalog['status'] ?? '')
            !== 'active'
        ) {
            throw new RuntimeException(
                'Organization catalog is not active.'
            );
        }

        return $catalog;
    }


    private function resolveOrganizationId(
        int $catalogId,
        int|string $reference
    ): int {
        if (
            is_int($reference)
            || ctype_digit(
                trim((string) $reference)
            )
        ) {
            $statement = $this->db->prepare("
                SELECT organizations.id
                FROM organization_catalog_entries
                    AS entries
                INNER JOIN organizations
                    ON organizations.id =
                        entries.organization_id
                WHERE entries.catalog_id = ?
                  AND entries.organization_id = ?
                  AND entries.status = 'active'
                  AND organizations.is_active = 1
                LIMIT 1
            ");

            $statement->execute([
                $catalogId,
                (int) $reference,
            ]);

            return (int) $statement
                ->fetchColumn();
        }

        $value = trim(
            (string) $reference
        );

        if ($value === '') {
            return 0;
        }

        $statement = $this->db->prepare("
            SELECT organizations.id
            FROM organization_catalog_entries
                AS entries
            INNER JOIN organizations
                ON organizations.id =
                    entries.organization_id
            WHERE entries.catalog_id = ?
              AND entries.status = 'active'
              AND organizations.is_active = 1
              AND (
                    organizations.public_reference = ?
                 OR entries.source_record_reference = ?
              )
            ORDER BY
                CASE
                    WHEN organizations.public_reference = ?
                        THEN 0
                    ELSE 1
                END,
                organizations.id
            LIMIT 1
        ");

        $statement->execute([
            $catalogId,
            $value,
            $value,
            $value,
        ]);

        return (int) $statement
            ->fetchColumn();
    }


    private function requireOrganizationId(
        int $catalogId,
        int|string $reference
    ): int {
        $id = $this->resolveOrganizationId(
            $catalogId,
            $reference
        );

        if ($id < 1) {
            throw new RuntimeException(
                'Organization is not an active member of the catalog.'
            );
        }

        return $id;
    }


    private function fetchNodeById(
        int $catalogId,
        int $organizationId
    ): ?array {
        $statement = $this->db->prepare("
            SELECT
                entries.id
                    AS catalog_entry_id,
                entries.catalog_id,
                entries.record_mode_code,
                entries.source_record_reference,
                entries.status
                    AS catalog_entry_status,
                entries.metadata_json
                    AS catalog_entry_metadata_json,

                organizations.id
                    AS organization_id,
                organizations.public_reference
                    AS organization_public_reference,
                organizations.title,
                organizations.title_fa,
                organizations.title_en,
                organizations.short_title,
                organizations.is_active,
                organizations.created_at,
                organizations.updated_at
            FROM organization_catalog_entries
                AS entries
            INNER JOIN organizations
                ON organizations.id =
                    entries.organization_id
            WHERE entries.catalog_id = ?
              AND entries.organization_id = ?
              AND entries.status = 'active'
              AND organizations.is_active = 1
            LIMIT 1
        ");

        $statement->execute([
            $catalogId,
            $organizationId,
        ]);

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $this->normalizeNode($row)
            : null;
    }


    private function normalizeNode(
        array $row
    ): array {
        $publicReference = trim(
            (string) (
                $row[
                    'organization_public_reference'
                ]
                ?? ''
            )
        );

        $row['organization_reference'] =
            $publicReference !== ''
                ? $publicReference
                : (string) (
                    $row[
                        'organization_id'
                    ]
                    ?? ''
                );

        return $row;
    }
}

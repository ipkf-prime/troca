<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Ticketing\TicketingDelegatedScopeRepositoryInterface;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;

/**
 * TICKETING_PROJECT_MEMBER_SCOPE_RUNTIME_REPOSITORY_V1
 *
 * Canonical runtime binding:
 *
 * active project member
 *   -> ticketing_support_project_member_scopes
 *   -> generic project dimension code/value
 *   -> canonical dimension ancestor paths
 *
 * Important separation:
 * - Project-member delegated administration scopes live here.
 * - Ticketing access grants remain data/resource visibility policies.
 * - Support routing nodes/relations are not used as the hierarchy source.
 * - External/reference/managed dimension values share the same runtime
 *   authorization representation once materialized in dimension values.
 */
final class TicketingDelegatedScopeRepository
    implements TicketingDelegatedScopeRepositoryInterface
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

    public function delegatedScopesForUserProject(
        int $userId,
        int $projectId
    ): array {
        if (
            $userId < 1
            || $projectId < 1
        ) {
            return [];
        }

        $statement =
            $this->db->prepare("
                SELECT
                    s.id,
                    s.public_reference,
                    s.project_member_id,
                    s.scope_type_code,
                    s.scope_reference,
                    s.access_mode_code,
                    s.capabilities_json,
                    s.is_primary,
                    s.status,
                    s.valid_from,
                    s.valid_until

                FROM
                    ticketing_support_project_members pm

                INNER JOIN
                    ticketing_support_project_member_scopes s

                    ON s.project_member_id =
                        pm.id

                WHERE
                    pm.project_id = ?

                  AND pm.user_reference = ?

                  AND pm.left_at
                        IS NULL

                  AND pm.role_code
                        IN ('member', 'manager')

                  AND s.status =
                        'active'

                ORDER BY
                    s.is_primary DESC,
                    s.id
            ");

        $statement->execute([
            $projectId,
            'user:' . $userId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }

    public function resourceScopeForProject(
        int $projectId,
        string $scopeTypeCode,
        string $scopeReference
    ): ?array {
        $scopeTypeCode =
            trim($scopeTypeCode);

        $scopeReference =
            trim($scopeReference);

        if (
            $projectId < 1
            || $scopeTypeCode === ''
            || $scopeReference === ''
        ) {
            return null;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    d.id
                        AS dimension_id,

                    d.code
                        AS scope_type_code,

                    d.hierarchy_mode_code,
                    d.supports_descendants,

                    v.id
                        AS dimension_value_id,

                    v.value_reference
                        AS scope_reference

                FROM
                    ticketing_scope_dimensions d

                INNER JOIN
                    ticketing_scope_dimension_values v

                    ON v.dimension_id =
                        d.id

                WHERE
                    d.project_id = ?

                  AND d.code = ?

                  AND d.status =
                        'active'

                  AND v.value_reference = ?

                  AND v.status =
                        'active'

                LIMIT 1
            ");

        $statement->execute([
            $projectId,
            $scopeTypeCode,
            $scopeReference,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return null;
        }

        $ancestorReferences = [];

        $isTree =
            strtolower(
                trim(
                    (string) (
                        $row[
                            'hierarchy_mode_code'
                        ]
                        ?? ''
                    )
                )
            ) === 'tree';

        $supportsDescendants =
            !empty(
                $row[
                    'supports_descendants'
                ]
            );

        if (
            $isTree
            && $supportsDescendants
        ) {
            $ancestors =
                $this->db->prepare("
                    SELECT
                        ancestor.value_reference

                    FROM
                        ticketing_scope_dimension_value_paths path

                    INNER JOIN
                        ticketing_scope_dimension_values ancestor

                        ON ancestor.dimension_id =
                            path.dimension_id

                       AND ancestor.id =
                            path.ancestor_value_id

                    WHERE
                        path.dimension_id = ?

                      AND path.descendant_value_id = ?

                      AND path.depth > 0

                      AND ancestor.status =
                            'active'

                    ORDER BY
                        path.depth,
                        ancestor.id
                ");

            $ancestors->execute([
                (int) $row[
                    'dimension_id'
                ],

                (int) $row[
                    'dimension_value_id'
                ],
            ]);

            foreach (
                $ancestors->fetchAll(
                    PDO::FETCH_COLUMN
                )
                ?: []
                as $reference
            ) {
                $reference =
                    trim(
                        (string) $reference
                    );

                if ($reference !== '') {
                    $ancestorReferences[
                        $reference
                    ] = true;
                }
            }
        }

        return [
            'scope_type_code' =>
                (string) $row[
                    'scope_type_code'
                ],

            'scope_reference' =>
                (string) $row[
                    'scope_reference'
                ],

            'ancestor_scope_references' =>
                array_keys(
                    $ancestorReferences
                ),
        ];
    }
}

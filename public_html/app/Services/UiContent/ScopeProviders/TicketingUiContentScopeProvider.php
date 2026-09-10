<?php

declare(strict_types=1);

namespace App\Services\UiContent\ScopeProviders;

use App\Services\Ticketing\PortalContextResolverService;
use App\Services\UiContent\UiContentContext;
use App\Services\UiContent\UiContentScopeProviderInterface;
use InvalidArgumentException;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;


/**
 * Ticketing hierarchy:
 *
 * Module -> Project -> Portal
 *
 * Public fine-scope resolution uses only an explicitly
 * registered active Portal Host.
 */
final class TicketingUiContentScopeProvider
    implements UiContentScopeProviderInterface
{
    private ?PortalContextResolverService $portals;

    private ?PDO $db;


    public function __construct(
        ?PortalContextResolverService $portals = null,
        ?PDO $db = null
    ) {
        $this->portals = $portals;
        $this->db = $db;
    }


    public function moduleKey(): string
    {
        return 'ticketing';
    }


    public function resolveRequestScope(
        string $host,
        string $uri
    ): array {

        $portal =
            $this->portalResolver()
                ->resolveByHost(
                    $host
                );


        if (!is_array($portal)) {
            return [
                'scope_path' => [],
                'metadata' => [],
            ];
        }


        $projectReference =
            trim(
                (string) (
                    $portal[
                        'project_public_reference'
                    ]
                    ?? ''
                )
            );


        $portalReference =
            trim(
                (string) (
                    $portal[
                        'portal_public_reference'
                    ]
                    ?? ''
                )
            );


        if (
            $projectReference === ''
            ||
            $portalReference === ''
        ) {
            return [
                'scope_path' => [],
                'metadata' => [],
            ];
        }


        $scopePath =
            $this->normalizeScopePath([
                [
                    'type' =>
                        'project',

                    'reference' =>
                        $projectReference,
                ],

                [
                    'type' =>
                        'portal',

                    'reference' =>
                        $portalReference,
                ],
            ]);


        $metadata =
            $this->describeScopePath(
                $scopePath
            );


        $metadata =
            array_replace(
                $metadata,
                [
                    'realm_reference' =>
                        trim(
                            (string) (
                                $portal[
                                    'realm_public_reference'
                                ]
                                ?? ''
                            )
                        ),

                    'realm_title' =>
                        trim(
                            (string) (
                                $portal[
                                    'realm_title'
                                ]
                                ?? ''
                            )
                        ),

                    'hostname' =>
                        trim(
                            (string) (
                                $portal[
                                    'hostname'
                                ]
                                ?? ''
                            )
                        ),
                ]
            );


        return [
            'scope_path' =>
                $scopePath,

            'metadata' =>
                $metadata,
        ];
    }



    public function normalizeScopePath(
        array $scopePath
    ): array {

        if ($scopePath === []) {
            return [];
        }


        if (count($scopePath) > 2) {
            throw new InvalidArgumentException(
                'ticketing_ui_content_scope_depth_invalid'
            );
        }


        $expected = [
            'project',
            'portal',
        ];


        foreach (
            array_values(
                $scopePath
            )
            as $index => $scope
        ) {
            if (!is_array($scope)) {
                throw new InvalidArgumentException(
                    'ticketing_ui_content_scope_invalid'
                );
            }


            $type =
                strtolower(
                    trim(
                        (string) (
                            $scope['type']
                            ?? ''
                        )
                    )
                );


            if (
                !isset($expected[$index])
                ||
                $type !== $expected[$index]
            ) {
                throw new InvalidArgumentException(
                    'ticketing_ui_content_scope_order_invalid'
                );
            }
        }


        return
            (
                new UiContentContext(
                    'ticketing',
                    'fa',
                    $scopePath
                )
            )->scopePath();
    }


    public function describeScopePath(
        array $scopePath
    ): array {

        $scopePath =
            $this->normalizeScopePath(
                $scopePath
            );


        if ($scopePath === []) {
            return [];
        }


        $projectReference =
            trim(
                (string) (
                    $scopePath[0][
                        'reference'
                    ]
                    ?? ''
                )
            );


        if ($projectReference === '') {
            return [];
        }


        $portalReference =
            isset($scopePath[1])
                ? trim(
                    (string) (
                        $scopePath[1][
                            'reference'
                        ]
                        ?? ''
                    )
                )
                : '';


        if ($portalReference === '') {

            $statement =
                $this->connection()
                    ->prepare("
                        SELECT
                            p.public_reference
                                AS project_reference,

                            p.title
                                AS project_title,

                            p.color_code
                                AS project_color_code,

                            p.icon_code
                                AS project_icon_code

                        FROM
                            ticketing_support_projects p

                        WHERE
                            p.public_reference = ?

                          AND p.is_active = 1

                          AND p.archived_at IS NULL

                        LIMIT 1
                    ");


            $statement->execute([
                $projectReference,
            ]);


            $row =
                $statement->fetch(
                    PDO::FETCH_ASSOC
                );


            return
                is_array($row)
                    ? $this->normalizeMetadata(
                        $row
                    )
                    : [];
        }


        $statement =
            $this->connection()
                ->prepare("
                    SELECT
                        p.public_reference
                            AS project_reference,

                        p.title
                            AS project_title,

                        p.color_code
                            AS project_color_code,

                        p.icon_code
                            AS project_icon_code,

                        po.public_reference
                            AS portal_reference,

                        po.title
                            AS portal_title,

                        r.public_reference
                            AS realm_reference,

                        r.title
                            AS realm_title

                    FROM
                        ticketing_support_projects p

                    INNER JOIN
                        ticketing_support_portals po

                      ON po.project_id = p.id

                     AND po.public_reference = ?

                     AND po.status = 'active'

                     AND po.archived_at IS NULL

                    INNER JOIN
                        ticketing_support_realms r

                      ON r.id = po.realm_id

                     AND r.project_id = p.id

                     AND r.status = 'active'

                     AND r.archived_at IS NULL

                    WHERE
                        p.public_reference = ?

                      AND p.is_active = 1

                      AND p.archived_at IS NULL

                    LIMIT 1
                ");


        $statement->execute([
            $portalReference,
            $projectReference,
        ]);


        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array($row)
                ? $this->normalizeMetadata(
                    $row
                )
                : [];
    }


    public function scopeCatalog(
        array $parentScopePath = []
    ): array {

        $db =
            $this->connection();


        $rows =
            $db->query("
                SELECT
                    p.public_reference
                        AS project_reference,

                    p.title
                        AS project_title,

                    po.public_reference
                        AS portal_reference,

                    po.title
                        AS portal_title

                FROM ticketing_support_projects p

                INNER JOIN
                    ticketing_support_portals po

                  ON po.project_id =
                     p.id

                 AND po.status =
                     'active'

                 AND po.archived_at
                     IS NULL

                INNER JOIN
                    ticketing_support_realms r

                  ON r.id =
                     po.realm_id

                 AND r.project_id =
                     p.id

                 AND r.status =
                     'active'

                 AND r.archived_at
                     IS NULL

                WHERE
                    p.is_active = 1

                  AND p.archived_at
                      IS NULL

                ORDER BY
                    p.title,
                    po.title,
                    po.id
            ")->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];


        $result = [];
        $projects = [];


        foreach ($rows as $row) {

            $projectReference =
                trim(
                    (string) (
                        $row[
                            'project_reference'
                        ]
                        ?? ''
                    )
                );

            $portalReference =
                trim(
                    (string) (
                        $row[
                            'portal_reference'
                        ]
                        ?? ''
                    )
                );


            if (
                $projectReference === ''
                ||
                $portalReference === ''
            ) {
                continue;
            }


            if (
                !isset(
                    $projects[
                        $projectReference
                    ]
                )
            ) {
                $projects[
                    $projectReference
                ] = true;


                $result[] = [
                    'depth' =>
                        1,

                    'type' =>
                        'project',

                    'reference' =>
                        $projectReference,

                    'label' =>
                        trim(
                            (string) (
                                $row[
                                    'project_title'
                                ]
                                ?? $projectReference
                            )
                        ),

                    'scope_path' => [
                        [
                            'type' =>
                                'project',

                            'reference' =>
                                $projectReference,
                        ],
                    ],
                ];
            }


            $result[] = [
                'depth' =>
                    2,

                'type' =>
                    'portal',

                'reference' =>
                    $portalReference,

                'label' =>
                    trim(
                        (string) (
                            $row[
                                'portal_title'
                            ]
                            ?? $portalReference
                        )
                    ),

                'parent_reference' =>
                    $projectReference,

                'scope_path' => [
                    [
                        'type' =>
                            'project',

                        'reference' =>
                            $projectReference,
                    ],

                    [
                        'type' =>
                            'portal',

                        'reference' =>
                            $portalReference,
                    ],
                ],
            ];
        }


        return $result;
    }


    private function normalizeMetadata(
        array $row
    ): array {

        $result = [];


        foreach ([
            'project_reference',
            'project_title',
            'project_color_code',
            'project_icon_code',
            'portal_reference',
            'portal_title',
            'realm_reference',
            'realm_title',
            'hostname',
        ] as $key) {

            if (
                !array_key_exists(
                    $key,
                    $row
                )
            ) {
                continue;
            }


            $result[$key] =
                trim(
                    (string) (
                        $row[$key]
                        ?? ''
                    )
                );
        }


        return $result;
    }


    private function portalResolver():
        PortalContextResolverService
    {
        $this->portals ??=
            new PortalContextResolverService();

        return $this->portals;
    }


    private function connection(): PDO
    {
        $this->db ??=
            (
                new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );

        return $this->db;
    }
}

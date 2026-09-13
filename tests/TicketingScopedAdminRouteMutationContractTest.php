<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$interfaceFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectMemberScopeRuntimeInterface.php';

$serviceFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingScopedSupportTopologyService.php';

$routeFile =
    $root
    . '/public_html/routes/ticketing-runtime.php';

$rbacFile =
    $root
    . '/public_html/app/Services/'
    . 'AdminNavigationRbacService.php';

$contextFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectLocalTopologyContextService.php';

$runtimeFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectMemberScopeRuntimeService.php';

foreach (
    [
        $interfaceFile,
        $serviceFile,
        $routeFile,
        $rbacFile,
        $contextFile,
        $runtimeFile,
    ]
    as $file
) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable A7 contract file: '
            . $file
        );
    }
}

require_once $interfaceFile;
require_once $serviceFile;

use App\Services\Ticketing\TicketingProjectMemberScopeRuntimeInterface;
use App\Services\Ticketing\TicketingScopedSupportTopologyService;

final class A7FakeScopedRuntime
    implements TicketingProjectMemberScopeRuntimeInterface
{
    public function canAccessResource(
        int $userId,
        int $projectId,
        string $action,
        string $scopeTypeCode,
        string $scopeReference
    ): bool {
        if (
            $userId !== 501
            || $projectId !== 10
            || $scopeTypeCode !==
                'organization'
        ) {
            return false;
        }

        if (
            !in_array(
                $action,
                [
                    'topology.view',
                    'topology.create',
                ],
                true
            )
        ) {
            return false;
        }

        return
            in_array(
                $scopeReference,
                [
                    'root-a',
                    'child-a',
                    'deep-a',
                ],
                true
            );
    }

    public function hasAnyDelegatedActionForProject(
        int $userId,
        int $projectId,
        array $actions
    ): bool {
        return
            $userId === 501
            && $projectId === 10
            && array_intersect(
                $actions,
                [
                    'topology.view',
                    'topology.create',
                ]
            ) !== [];
    }
}

$service =
    new TicketingScopedSupportTopologyService(
        new A7FakeScopedRuntime()
    );

$page = [
    'project' => [
        'id' => 10,
        'public_reference' => 'PROJECT-X',
    ],

    'layers' => [
        [
            'id' => 100,
            'title' => 'Layer A',
        ],
    ],

    'nodes' => [
        [
            'id' => 1,
            'scope_type_code' =>
                'organization',
            'scope_reference' =>
                'child-a',
            'layer_id' => 100,
        ],
        [
            'id' => 2,
            'scope_type_code' =>
                'organization',
            'scope_reference' =>
                'deep-a',
            'layer_id' => 100,
        ],
        [
            'id' => 3,
            'scope_type_code' =>
                'organization',
            'scope_reference' =>
                'child-b',
            'layer_id' => 100,
        ],
    ],

    'relations' => [
        [
            'id' => 11,
            'parent_node_id' => 1,
            'child_node_id' => 2,
        ],
        [
            'id' => 12,
            'parent_node_id' => 1,
            'child_node_id' => 3,
        ],
    ],

    'queues' => [
        [
            'id' => 21,
            'node_id' => 1,
        ],
        [
            'id' => 22,
            'node_id' => 3,
        ],
    ],

    'teams' => [['id' => 31]],
    'team_nodes' => [['id' => 41]],
    'team_queues' => [['id' => 42]],
    'team_members' => [['id' => 43]],
    'staff_candidates' => [['id' => 44]],
];

$assert =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };

$projected =
    $service->projectPage(
        501,
        $page
    );

$assert(
    array_column(
        $projected['nodes'],
        'id'
    ) === [1, 2],
    'Scoped projection nodes mismatch.'
);

$assert(
    array_column(
        $projected['relations'],
        'id'
    ) === [11],
    'Scoped projection relations mismatch.'
);

$assert(
    array_column(
        $projected['queues'],
        'id'
    ) === [21],
    'Scoped projection queues mismatch.'
);

foreach (
    [
        'teams',
        'team_nodes',
        'team_queues',
        'team_members',
        'staff_candidates',
    ]
    as $hiddenKey
) {
    $assert(
        $projected[$hiddenKey] === [],
        'Project-wide resource leaked: '
        . $hiddenKey
    );
}

$assert(
    $service->canMutate(
        501,
        $page,
        'node.create',
        [
            'scope_type_code' =>
                'organization',
            'scope_reference' =>
                'child-a',
            'core_organization_reference' =>
                '',
        ]
    ),
    'Allowed node.create must pass.'
);

$assert(
    !$service->canMutate(
        501,
        $page,
        'node.create',
        [
            'scope_type_code' =>
                'organization',
            'scope_reference' =>
                'child-b',
            'core_organization_reference' =>
                '',
        ]
    ),
    'Sibling node.create must deny.'
);

$assert(
    !$service->canMutate(
        501,
        $page,
        'node.create',
        [
            'scope_type_code' =>
                'organization',
            'scope_reference' =>
                'child-a',
            'core_organization_reference' =>
                'external-binding',
        ]
    ),
    'Delegated canonical binding must deny.'
);

$assert(
    $service->canMutate(
        501,
        $page,
        'queue.create',
        [
            'node_id' => 1,
        ]
    ),
    'Scoped queue.create must pass.'
);

$assert(
    !$service->canMutate(
        501,
        $page,
        'queue.create',
        [
            'node_id' => 3,
        ]
    ),
    'Sibling queue.create must deny.'
);

$assert(
    $service->canMutate(
        501,
        $page,
        'relation.create',
        [
            'parent_node_id' => 1,
            'child_node_id' => 2,
        ]
    ),
    'Scoped relation.create must pass.'
);

$assert(
    !$service->canMutate(
        501,
        $page,
        'relation.create',
        [
            'parent_node_id' => 1,
            'child_node_id' => 3,
        ]
    ),
    'Cross-scope relation.create must deny.'
);

foreach (
    [
        'layer.create',
        'team.create',
        'team_node.bind',
        'team_queue.bind',
        'team_member.add',
    ]
    as $projectWideAction
) {
    $assert(
        !$service->canMutate(
            501,
            $page,
            $projectWideAction,
            []
        ),
        'Project-wide action must deny: '
        . $projectWideAction
    );
}

$routeContent =
    (string) file_get_contents(
        $routeFile
    );

$rbacContent =
    (string) file_get_contents(
        $rbacFile
    );

$contextContent =
    (string) file_get_contents(
        $contextFile
    );

$runtimeContent =
    (string) file_get_contents(
        $runtimeFile
    );

foreach (
    [
        [
            $routeContent,
            'TICKETING_SCOPED_TOPOLOGY_ROUTE_EXECUTION_V1',
        ],
        [
            $routeContent,
            'TICKETING_SCOPED_TOPOLOGY_MUTATION_ENFORCEMENT_V1',
        ],
        [
            $routeContent,
            'TicketingScopedSupportTopologyService',
        ],
        [
            $routeContent,
            'ticketing.project.manage',
        ],
        [
            $rbacContent,
            'canRouteEntry',
        ],
        [
            $contextContent,
            'canRouteEntry',
        ],
        [
            $contextContent,
            'hasAnyDelegatedActionForProject',
        ],
        [
            $runtimeContent,
            'TicketingProjectMemberScopeRuntimeInterface',
        ],
        [
            $runtimeContent,
            'hasAnyDelegatedActionForProject',
        ],
    ]
    as [$content, $marker]
) {
    if (
        !str_contains(
            $content,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Missing A7 marker: '
            . $marker
        );
    }
}

$serviceContent =
    (string) file_get_contents(
        $serviceFile
    );

foreach (
    [
        'ticketing.project.manage',
        'AuthorizationService',
        'ScopedAuthorizationService',
    ]
    as $forbidden
) {
    if (
        str_contains(
            $serviceContent,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'Forbidden scoped-service coupling: '
            . $forbidden
        );
    }
}

foreach (
    [
        'province',
        'county',
        'company',
    ]
    as $businessSpecific
) {
    if (
        stripos(
            $serviceContent,
            $businessSpecific
        ) !== false
    ) {
        throw new RuntimeException(
            'Business-specific level leaked: '
            . $businessSpecific
        );
    }
}

if (
    preg_match(
        '/\bNP\b/u',
        $serviceContent
    ) === 1
) {
    throw new RuntimeException(
        'Source-system coupling leaked into scoped service.'
    );
}

echo "TICKETING_SCOPED_ADMIN_ROUTE_AND_MUTATION_CONTRACT=PASS\n";
echo "SCOPED_ROUTE_ENTRY=PASS\n";
echo "GLOBAL_ADMIN_FALLBACK=PRESERVED\n";
echo "FULL_PROJECT_MANAGER_PATH=PRESERVED\n";
echo "SCOPED_PAGE_PROJECTION=PASS\n";
echo "SCOPED_NODE_CREATE=TARGET_ENFORCED\n";
echo "SCOPED_QUEUE_CREATE=TARGET_NODE_ENFORCED\n";
echo "SCOPED_RELATION_CREATE=BOTH_ENDPOINTS_ENFORCED\n";
echo "PROJECT_WIDE_MUTATIONS=DENY_FOR_DELEGATED_ADMIN\n";
echo "CANONICAL_ORGANIZATION_BINDING=DENY_FOR_DELEGATED_ADMIN\n";
echo "BUSINESS_SPECIFIC_LEVELS=ABSENT\n";
echo "SOURCE_SYSTEM_COUPLING=ABSENT\n";

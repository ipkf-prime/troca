<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$runtimeInterfaceFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectMemberScopeRuntimeInterface.php';

$interfaceFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingDelegatedScopeRepositoryInterface.php';

$repositoryFile =
    $root
    . '/public_html/app/Repositories/'
    . 'TicketingDelegatedScopeRepository.php';

$evaluatorFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingScopedTopologyAdminEvaluator.php';

$serviceFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectMemberScopeRuntimeService.php';

foreach (
    [
        $runtimeInterfaceFile,
        $interfaceFile,
        $repositoryFile,
        $evaluatorFile,
        $serviceFile,
    ]
    as $file
) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable A6 contract file: '
            . $file
        );
    }
}

require_once $runtimeInterfaceFile;
require_once $interfaceFile;
require_once $evaluatorFile;
require_once $serviceFile;

use App\Services\Ticketing\TicketingDelegatedScopeRepositoryInterface;
use App\Services\Ticketing\TicketingProjectMemberScopeRuntimeService;
use App\Services\Ticketing\TicketingScopedTopologyAdminEvaluator;

final class A6FakeDelegatedScopeRepository
    implements TicketingDelegatedScopeRepositoryInterface
{
    public function delegatedScopesForUserProject(
        int $userId,
        int $projectId
    ): array {
        if (
            $userId !== 501
            || $projectId !== 10
        ) {
            return [];
        }

        return [
            [
                'scope_type_code' =>
                    'organization',

                'scope_reference' =>
                    'root-a',

                'access_mode_code' =>
                    'descendants',

                'capabilities_json' =>
                    json_encode(
                        [
                            'topology.view' =>
                                true,

                            'topology.create' =>
                                true,

                            'topology.update' =>
                                true,

                            'topology.delete' =>
                                false,
                        ],
                        JSON_THROW_ON_ERROR
                    ),

                'status' =>
                    'active',

                'valid_from' =>
                    '2026-01-01 00:00:00',

                'valid_until' =>
                    '2026-12-31 23:59:59',
            ],
        ];
    }

    public function resourceScopeForProject(
        int $projectId,
        string $scopeTypeCode,
        string $scopeReference
    ): ?array {
        if (
            $projectId !== 10
            || $scopeTypeCode !==
                'organization'
        ) {
            return null;
        }

        $resources = [
            'root-a' => [
                'scope_type_code' =>
                    'organization',

                'scope_reference' =>
                    'root-a',

                'ancestor_scope_references' =>
                    [],
            ],

            'child-a' => [
                'scope_type_code' =>
                    'organization',

                'scope_reference' =>
                    'child-a',

                'ancestor_scope_references' => [
                    'root-a',
                ],
            ],

            'deep-a' => [
                'scope_type_code' =>
                    'organization',

                'scope_reference' =>
                    'deep-a',

                'ancestor_scope_references' => [
                    'child-a',
                    'root-a',
                ],
            ],

            'child-b' => [
                'scope_type_code' =>
                    'organization',

                'scope_reference' =>
                    'child-b',

                'ancestor_scope_references' => [
                    'root-b',
                ],
            ],
        ];

        return
            $resources[
                $scopeReference
            ]
            ?? null;
    }
}

$service =
    new TicketingProjectMemberScopeRuntimeService(
        new A6FakeDelegatedScopeRepository(),
        new TicketingScopedTopologyAdminEvaluator()
    );

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

$assert(
    $service->canAccessResource(
        501,
        10,
        'topology.view',
        'organization',
        'child-a'
    ),
    'Delegated descendant view must allow.'
);

$assert(
    $service->canAccessResource(
        501,
        10,
        'topology.create',
        'organization',
        'deep-a'
    ),
    'Delegated deep descendant create must allow.'
);

$assert(
    !$service->canAccessResource(
        501,
        10,
        'topology.delete',
        'organization',
        'child-a'
    ),
    'Missing delete capability must deny.'
);

$assert(
    !$service->canAccessResource(
        501,
        10,
        'topology.create',
        'organization',
        'child-b'
    ),
    'Sibling branch must deny.'
);

$assert(
    !$service->canAccessResource(
        501,
        20,
        'topology.create',
        'organization',
        'child-a'
    ),
    'Cross-project lookup must deny.'
);

$assert(
    !$service->canAccessResource(
        999,
        10,
        'topology.create',
        'organization',
        'child-a'
    ),
    'User without delegated scopes must deny.'
);

$assert(
    !$service->canAccessResource(
        501,
        10,
        'topology.create',
        'unknown',
        'child-a'
    ),
    'Unknown scope type must deny.'
);

$assert(
    !$service->canAccessResource(
        501,
        10,
        'topology.create',
        'organization',
        'missing'
    ),
    'Unresolved resource must deny.'
);

$repositoryContent =
    (string) file_get_contents(
        $repositoryFile
    );

$serviceContent =
    (string) file_get_contents(
        $serviceFile
    );

$requiredRepositoryMarkers = [
    'ticketing_support_project_member_scopes',
    'ticketing_support_project_members',
    'ticketing_scope_dimensions',
    'ticketing_scope_dimension_values',
    'ticketing_scope_dimension_value_paths',
    'hierarchy_mode_code',
    'supports_descendants',
    "IN ('member', 'manager')",
    "'user:' . \$userId",
];

foreach (
    $requiredRepositoryMarkers
    as $marker
) {
    if (
        !str_contains(
            $repositoryContent,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Missing A6 repository marker: '
            . $marker
        );
    }
}

foreach (
    [
        'ticketing_support_node_relations',
        'ticketing_access_grants',
    ]
    as $forbiddenRepositoryDependency
) {
    if (
        str_contains(
            $repositoryContent,
            $forbiddenRepositoryDependency
        )
    ) {
        throw new RuntimeException(
            'Wrong hierarchy/authority source in A6 repository: '
            . $forbiddenRepositoryDependency
        );
    }
}

foreach (
    [
        'ticketing.project.manage',
        'ScopedAuthorizationService',
        'AuthorizationService',
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
            'Forbidden A6 authority coupling: '
            . $forbidden
        );
    }
}

foreach (
    [
        $repositoryContent,
        $serviceContent,
    ]
    as $genericContent
) {
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
                $genericContent,
                $businessSpecific
            ) !== false
        ) {
            throw new RuntimeException(
                'Business-specific level leaked into A6 runtime: '
                . $businessSpecific
            );
        }
    }

    if (
        preg_match(
            '/\bNP\b/u',
            $genericContent
        ) === 1
    ) {
        throw new RuntimeException(
            'Source-system coupling leaked into A6 runtime.'
        );
    }
}

echo "TICKETING_PROJECT_MEMBER_SCOPE_RUNTIME_BINDING=PASS\n";
echo "PROJECT_MEMBER_SCOPES=CANONICAL_DELEGATED_ADMIN_SOURCE\n";
echo "DIMENSION_VALUE_PATHS=CANONICAL_DESCENDANT_SOURCE\n";
echo "SUPPORT_ROUTING_RELATIONS_AS_SCOPE_HIERARCHY=ABSENT\n";
echo "DATA_ACCESS_GRANTS_AS_ADMIN_AUTHORITY=ABSENT\n";
echo "DESCENDANT_RESOURCE_ALLOW=PASS\n";
echo "SIBLING_RESOURCE_DENY=PASS\n";
echo "CROSS_PROJECT_DENY=PASS\n";
echo "MISSING_SCOPE_DENY=PASS\n";
echo "UNRESOLVED_RESOURCE_DENY=PASS\n";
echo "GLOBAL_PERMISSION_ALIAS=ABSENT\n";
echo "BUSINESS_SPECIFIC_LEVELS=ABSENT\n";
echo "SOURCE_SYSTEM_COUPLING=ABSENT\n";

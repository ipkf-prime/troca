<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$interfaceFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectLocalAdminScopeProviderInterface.php';

$adapterFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectScopedAdminScopeProvider.php';

$authorityFile =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectLocalAdminAuthorityService.php';

foreach (
    [
        $interfaceFile,
        $adapterFile,
        $authorityFile,
    ]
    as $file
) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable A3 contract file: '
            . $file
        );
    }
}

require_once $interfaceFile;
require_once $adapterFile;
require_once $authorityFile;

use App\Services\Ticketing\TicketingProjectLocalAdminAuthorityService;
use App\Services\Ticketing\TicketingProjectLocalAdminScopeProviderInterface;

final class A3FakeProjectLocalAdminScopeProvider
    implements TicketingProjectLocalAdminScopeProviderInterface
{
    public function projectScope(
        int $userId,
        int $projectId
    ): ?array {
        $scopes = [
            101 => [
                10 => [
                    'project_id' => 10,
                    'project_role_code' => 'manager',
                    'is_project_manager' => true,
                    'topology_full_project' => true,
                ],
                20 => [
                    'project_id' => 20,
                    'project_role_code' => 'member',
                    'is_project_manager' => false,
                    'topology_full_project' => false,
                ],
            ],
            102 => [
                10 => [
                    'project_id' => 10,
                    'project_role_code' => 'member',
                    'is_project_manager' => false,
                    'topology_full_project' => false,
                ],
            ],
            103 => [
                10 => [
                    'project_id' => 10,
                    'project_role_code' => 'manager',
                    'is_project_manager' => true,
                    'topology_full_project' => false,
                ],
            ],
            104 => [
                10 => [
                    /*
                     * Defensive test: provider result itself is inconsistent.
                     */
                    'project_id' => 99,
                    'project_role_code' => 'manager',
                    'is_project_manager' => true,
                    'topology_full_project' => true,
                ],
            ],
        ];

        $scope =
            $scopes[$userId][$projectId]
            ?? null;

        return
            is_array($scope)
                ? $scope
                : null;
    }
}

$authority =
    new TicketingProjectLocalAdminAuthorityService(
        new A3FakeProjectLocalAdminScopeProvider()
    );

$context = [
    'realm_project_id' => 10,
    'resource_project_id' => 10,
    'resource_realm_id' => 500,
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

foreach (
    [
        'topology.view',
        'topology.create',
        'topology.update',
        'topology.delete',
    ]
    as $action
) {
    $assert(
        $authority->can(
            101,
            10,
            500,
            $action,
            $context
        ),
        'Project manager same Project/Realm must allow: '
        . $action
    );
}

$assert(
    !$authority->can(
        102,
        10,
        500,
        'topology.view',
        $context
    ),
    'Non-manager must be denied.'
);

$assert(
    !$authority->can(
        101,
        20,
        500,
        'topology.view',
        [
            'realm_project_id' => 20,
            'resource_project_id' => 20,
            'resource_realm_id' => 500,
        ]
    ),
    'Manager in another project must not cross project role boundary.'
);

$assert(
    !$authority->can(
        101,
        10,
        500,
        'topology.update',
        [
            'realm_project_id' => 20,
            'resource_project_id' => 20,
            'resource_realm_id' => 500,
        ]
    ),
    'Cross-project resource context must fail closed.'
);

$assert(
    !$authority->can(
        101,
        10,
        500,
        'topology.update',
        [
            'realm_project_id' => 10,
            'resource_project_id' => 10,
            'resource_realm_id' => 501,
        ]
    ),
    'Cross-Realm resource context must fail closed.'
);

$assert(
    !$authority->can(
        101,
        10,
        500,
        'topology.view',
        []
    ),
    'Unresolved resource context must fail closed.'
);

$assert(
    !$authority->can(
        103,
        10,
        500,
        'topology.view',
        $context
    ),
    'Partial/inconsistent manager topology projection must fail closed.'
);

$assert(
    !$authority->can(
        104,
        10,
        500,
        'topology.view',
        $context
    ),
    'Provider scope project mismatch must fail closed.'
);

foreach (
    [
        '',
        'ticketing.project.manage',
        '/admin/ticketing/projects',
        'routing.update',
        'topology.publish',
    ]
    as $unknownAction
) {
    $assert(
        !$authority->can(
            101,
            10,
            500,
            $unknownAction,
            $context
        ),
        'Unknown/global action must fail closed: '
        . $unknownAction
    );
}

$assert(
    !$authority->can(
        0,
        10,
        500,
        'topology.view',
        $context
    ),
    'Invalid user must fail closed.'
);

$assert(
    !$authority->can(
        101,
        0,
        500,
        'topology.view',
        $context
    ),
    'Invalid project must fail closed.'
);

$assert(
    !$authority->can(
        101,
        10,
        0,
        'topology.view',
        $context
    ),
    'Invalid Realm must fail closed.'
);

$authorityContent =
    (string) file_get_contents(
        $authorityFile
    );

foreach (
    [
        'AuthorizationService',
        'ScopedAuthorizationService',
        'ticketing.project.manage',
    ]
    as $forbiddenMarker
) {
    if (
        str_contains(
            $authorityContent,
            $forbiddenMarker
        )
    ) {
        throw new RuntimeException(
            'Forbidden authority coupling: '
            . $forbiddenMarker
        );
    }
}

if (
    preg_match(
        '/\bNP\b/u',
        $authorityContent
    ) === 1
) {
    throw new RuntimeException(
        'Project-local authority must remain source-system agnostic.'
    );
}

echo "TICKETING_GENERIC_PROJECT_LOCAL_ADMIN_AUTHORITY_CONTRACT=PASS\n";
echo "PROJECT_MANAGER_SAME_PROJECT_REALM=ALLOW\n";
echo "NON_MANAGER=DENY\n";
echo "CROSS_PROJECT=DENY\n";
echo "CROSS_REALM=DENY\n";
echo "UNRESOLVED_CONTEXT=DENY\n";
echo "GLOBAL_PERMISSION_ALIAS=ABSENT\n";
echo "CORE_SCOPED_AUTHORIZATION_COUPLING=ABSENT\n";
echo "SOURCE_SYSTEM_COUPLING=ABSENT\n";

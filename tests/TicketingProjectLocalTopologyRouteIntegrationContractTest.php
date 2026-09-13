<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$rbac =
    $root
    . '/public_html/app/Services/AdminNavigationRbacService.php';

$bridge =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectScopedAccessService.php';

$authority =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectLocalAdminAuthorityService.php';

$context =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingProjectLocalTopologyContextService.php';

$repository =
    $root
    . '/public_html/app/Repositories/'
    . 'SupportTopologyAdminRepository.php';

$routeSeed =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'EnableTicketingTopologyManagementRoutes.php';

foreach (
    [
        $rbac,
        $bridge,
        $authority,
        $context,
        $repository,
        $routeSeed,
    ]
    as $file
) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable A4 contract file: '
            . $file
        );
    }
}

$rbacContent =
    (string) file_get_contents($rbac);

$bridgeContent =
    (string) file_get_contents($bridge);

$authorityContent =
    (string) file_get_contents($authority);

$contextContent =
    (string) file_get_contents($context);

$repositoryContent =
    (string) file_get_contents($repository);

$repositoryNormalized =
    preg_replace(
        '/\s+/u',
        ' ',
        $repositoryContent
    )
    ?? $repositoryContent;

$routeSeedContent =
    (string) file_get_contents($routeSeed);

$mustContain =
    static function (
        string $content,
        string $needle,
        string $label
    ): void {
        if (!str_contains(
            $content,
            $needle
        )) {
            throw new RuntimeException(
                'Missing A4 marker: '
                . $label
                . ' :: '
                . $needle
            );
        }
    };

$mustContain(
    $rbacContent,
    'TICKETING_PROJECT_LOCAL_TOPOLOGY_ADMIN_GATE_V1',
    'rbac'
);

$mustContain(
    $rbacContent,
    'TicketingProjectLocalTopologyContextService',
    'rbac'
);

$mustContain(
    $rbacContent,
    "'topology.view'",
    'rbac'
);

$mustContain(
    $rbacContent,
    "'topology.create'",
    'rbac'
);

$mustContain(
    $rbacContent,
    '#^/admin/ticketing/projects/([A-Za-z0-9_-]+)/topology$#',
    'rbac'
);

$mustContain(
    $repositoryContent,
    'projectDefaultRealmContextByReference',
    'repository'
);

$mustContain(
    $repositoryContent,
    'ticketing_support_realms',
    'repository'
);

$mustContain(
    $repositoryNormalized,
    'r.project_id = p.id',
    'repository-normalized'
);

$mustContain(
    $repositoryNormalized,
    "r.status = 'active'",
    'repository-normalized'
);

$mustContain(
    $contextContent,
    'resource_context',
    'context'
);

$mustContain(
    $contextContent,
    'realm_project_id',
    'context'
);

$mustContain(
    $contextContent,
    'resource_project_id',
    'context'
);

$mustContain(
    $contextContent,
    'resource_realm_id',
    'context'
);

$mustContain(
    $contextContent,
    'TicketingProjectLocalAdminAuthorityService',
    'context'
);

$mustContain(
    $routeSeedContent,
    'ticketing.project.manage',
    'global-route-seed'
);

$mustContain(
    $routeSeedContent,
    '/admin/ticketing/projects/{public_reference}/topology',
    'global-route-seed'
);

foreach (
    [
        '/admin/ticketing/projects',
        '/admin/ticketing/participants',
        '/admin/ticketing/portals',
        '/admin/ticketing/statuses',
        '/admin/ticketing/sla',
    ]
    as $forbidden
) {
    if (
        str_contains(
            $bridgeContent,
            "'" . $forbidden . "'"
        )
        ||
        str_contains(
            $bridgeContent,
            '"' . $forbidden . '"'
        )
    ) {
        throw new RuntimeException(
            'Operational bridge leaked global admin prefix: '
            . $forbidden
        );
    }
}

foreach (
    [
        $authorityContent,
        $contextContent,
    ]
    as $localContent
) {
    foreach (
        [
            'ticketing.project.manage',
            'ScopedAuthorizationService',
        ]
        as $forbidden
    ) {
        if (
            str_contains(
                $localContent,
                $forbidden
            )
        ) {
            throw new RuntimeException(
                'Forbidden local authority coupling: '
                . $forbidden
            );
        }
    }

    if (
        preg_match(
            '/\bNP\b/u',
            $localContent
        ) === 1
    ) {
        throw new RuntimeException(
            'Project-local topology authority must remain project-generic.'
        );
    }
}

if (
    substr_count(
        $rbacContent,
        '#^/admin/ticketing/projects/([A-Za-z0-9_-]+)/topology$#'
    ) !== 1
) {
    throw new RuntimeException(
        'Local topology path matcher must be exact and singular.'
    );
}

echo "TICKETING_PROJECT_LOCAL_TOPOLOGY_ROUTE_INTEGRATION_CONTRACT=PASS\n";
echo "EXISTING_TOPOLOGY_ROUTE_REUSED=YES\n";
echo "GLOBAL_PROJECT_ADMIN_PERMISSION=UNCHANGED\n";
echo "PROJECT_LOCAL_GATE=EXACT_TOPOLOGY_ONLY\n";
echo "PROJECT_REALM_CONTEXT=AUTHORITATIVE_DB_RESOLUTION\n";
echo "GET_ACTION=topology.view\n";
echo "POST_ACTION=topology.create\n";
echo "OPERATIONAL_BRIDGE_GLOBAL_ADMIN_LEAK=ABSENT\n";
echo "CORE_SCOPED_AUTHORIZATION_COUPLING=ABSENT\n";
echo "SOURCE_SYSTEM_COUPLING=ABSENT\n";

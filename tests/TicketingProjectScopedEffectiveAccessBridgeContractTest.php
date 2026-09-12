<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$paths = [
    'repository' =>
        $root
        . '/public_html/app/Repositories/TicketStaffOperationsRepository.php',

    'staff_service' =>
        $root
        . '/public_html/app/Services/Ticketing/TicketStaffOperationsService.php',

    'bridge' =>
        $root
        . '/public_html/app/Services/Ticketing/TicketingProjectScopedAccessService.php',

    'dynamic_navigation' =>
        $root
        . '/public_html/app/Services/DynamicAdminNavigationService.php',

    'panel' =>
        $root
        . '/public_html/app/Services/AdminPanelService.php',

    'static_rbac' =>
        $root
        . '/public_html/app/Services/AdminNavigationRbacService.php',

    'dynamic_rbac' =>
        $root
        . '/public_html/app/Services/DynamicRouteAccessService.php',

    'runtime' =>
        $root
        . '/public_html/routes/ticketing-runtime.php',
];

foreach ($paths as $key => $file) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable contract file: '
            . $key
        );
    }
}

$content = [];

foreach ($paths as $key => $file) {
    $content[$key] =
        (string) file_get_contents(
            $file
        );
}

$mustContain =
    static function (
        string $key,
        string $needle
    ) use (
        $content
    ): void {
        if (
            !str_contains(
                $content[$key],
                $needle
            )
        ) {
            throw new RuntimeException(
                'Missing contract marker: '
                . $key
                . ' :: '
                . $needle
            );
        }
    };

$mustContain(
    'runtime',
    'Ticketing membership is authoritative'
);

$mustContain(
    'repository',
    "pm.role_code IN ('member', 'manager')"
);

$mustContain(
    'repository',
    'Dynamic Data Scope is an intersection'
);

$mustContain(
    'repository',
    'TICKETING_PROJECT_LOCAL_EFFECTIVE_SCOPE_SUMMARY_V1'
);

$mustContain(
    'repository',
    'topology_full_project'
);

$mustContain(
    'repository',
    'resource_scope_authority'
);

$mustContain(
    'staff_service',
    'TICKETING_PROJECT_LOCAL_EFFECTIVE_ACCESS_SERVICE_V1'
);

$mustContain(
    'staff_service',
    'public function accessScopes('
);

$mustContain(
    'bridge',
    'TICKETING_PROJECT_SCOPED_EFFECTIVE_ACCESS_BRIDGE_V1'
);

$mustContain(
    'bridge',
    'public function projectScope('
);

$mustContain(
    'bridge',
    'public function isProjectManager('
);

$mustContain(
    'bridge',
    'ticketing_cartable_plus_dynamic_data_scope'
);

$mustContain(
    'dynamic_navigation',
    'TICKETING_PROJECT_SCOPED_NAVIGATION_BRIDGE_V1'
);

$mustContain(
    'panel',
    'TICKETING_PROJECT_SCOPED_PANEL_BRIDGE_V1'
);

$mustContain(
    'static_rbac',
    'TicketingProjectScopedAccessService'
);

$mustContain(
    'dynamic_rbac',
    'TicketingProjectScopedAccessService'
);

/*
 * Critical boundary:
 * the module-owned bridge may open only operational Ticketing surfaces.
 * It must never convert local project manager into global project admin.
 */
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
            $content['bridge'],
            "'" . $forbidden . "'"
        )
        ||
        str_contains(
            $content['bridge'],
            '"' . $forbidden . '"'
        )
    ) {
        throw new RuntimeException(
            'Project-local bridge leaked global admin surface: '
            . $forbidden
        );
    }
}

/*
 * Do not create a parallel generic scoped ACL inside Ticketing.
 */
if (
    str_contains(
        $content['bridge'],
        'ScopedAuthorizationService'
    )
) {
    throw new RuntimeException(
        'Ticketing bridge must not depend on generic ScopedAuthorizationService.'
    );
}

echo "TICKETING_PROJECT_SCOPED_EFFECTIVE_ACCESS_BRIDGE_CONTRACT=PASS\n";
echo "LOCAL_PROJECT_MANAGER_GLOBAL_ADMIN_ESCALATION=FORBIDDEN\n";
echo "RESOURCE_SCOPE_AUTHORITY=TICKETING_CARTABLE_PLUS_DYNAMIC_DATA_SCOPE\n";

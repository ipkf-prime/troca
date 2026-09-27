<?php

declare(strict_types=1);

$root=dirname(__DIR__);

$read=
    static function(string $relative) use ($root): string {
        $content=
            file_get_contents(
                $root.'/'.$relative
            );

        if(!is_string($content)){
            throw new RuntimeException(
                'Unreadable contract file: '
                .$relative
            );
        }

        return $content;
    };

$expect=
    static function(
        bool $condition,
        string $message
    ): void {
        if(!$condition){
            throw new RuntimeException(
                $message
            );
        }
    };

$bridge=$read(
    'public_html/app/Services/Ticketing/'
    .'TicketingProjectScopedAccessService.php'
);

$memberService=$read(
    'public_html/app/Services/Ticketing/'
    .'TicketProjectMemberAccessService.php'
);

$memberRoutes=$read(
    'public_html/routes/'
    .'ticketing-project-membership.php'
);

$moduleSso=$read(
    'public_html/app/Services/'
    .'ModuleSsoService.php'
);

$staffRepository=$read(
    'public_html/app/Repositories/'
    .'TicketStaffOperationsRepository.php'
);

foreach([
    'T2_PROJECT_MANAGER_MEMBER_ADMIN_BRIDGE_V1',
    'isProjectManagerReference',
    'T2_PROJECT_MANAGER_MEMBER_ADMIN_PATH_V1',
    '#^/admin/ticketing/projects/([A-Za-z0-9_-]+)/members(?:/.*)?$#',
    'ticketing_support_projects',
] as $marker){
    $expect(
        str_contains(
            $bridge,
            $marker
        ),
        'Project-local bridge missing: '
        .$marker
    );
}

$expect(
    !str_contains(
        $bridge,
        "'/admin/ticketing/projects'"
    )
    &&
    !str_contains(
        $bridge,
        '"/admin/ticketing/projects"'
    ),
    'Project-local bridge leaked global project administration.'
);

foreach([
    'T2_PROJECT_MEMBER_SERVICE_AUTHORIZATION_V1',
    'actorCanManageProject',
    'TicketingProjectScopedAccessService',
    "'ticketing.project.manage'",
] as $marker){
    $expect(
        str_contains(
            $memberService,
            $marker
        ),
        'Member service authorization missing: '
        .$marker
    );
}

$expect(
    substr_count(
        $memberService,
        'T2_PROJECT_MEMBER_SERVICE_AUTHORIZATION_V1'
    )===5,
    'Every project-member mutation must re-authorize.'
);

$sectionPos=
    strpos(
        $memberRoutes,
        'TICKETING_PROJECT_MEMBER_ACCESS_CENTER_ROUTES'
    );

$expect(
    $sectionPos!==false,
    'Project-member route section missing.'
);

$section=
    substr(
        $memberRoutes,
        (int)$sectionPos
    );

$legacyGuard=
    "        \$context =\n"
    ."            \$adminGuard(\n"
    ."                \$response,\n"
    ."                '/admin/ticketing/projects'\n"
    ."            );\n";

$expect(
    !str_contains(
        $section,
        $legacyGuard
    ),
    'Member routes still use global project guard.'
);

$expect(
    substr_count(
        $section,
        ". '/members'"
    )>=6,
    'Project-local member guard path missing.'
);

/*
 * Adopt the already-present T2 operational SSO bridge rather than
 * duplicating or replacing it.
 */
foreach([
    'PROJECT_LOCAL_TICKETING_SSO_OPERATIONAL_BRIDGE_V1',
    '$operationalTicketingAllowed',
    'TicketingProjectScopedAccessService',
    'canAccessPath(',
    '!$this->isRequesterTicketingReturnPath(',
    '!$operationalTicketingAllowed',
] as $marker){
    $expect(
        str_contains(
            $moduleSso,
            $marker
        ),
        'Operational staff SSO bridge missing: '
        .$marker
    );
}

foreach([
    'role_permissions',
    'INSERT INTO permissions',
    'INSERT INTO role_permissions',
] as $forbidden){
    $expect(
        !str_contains(
            $bridge,
            $forbidden
        )
        &&
        !str_contains(
            $memberService,
            $forbidden
        )
        &&
        !str_contains(
            $moduleSso,
            $forbidden
        ),
        'Unexpected global permission mutation logic: '
        .$forbidden
    );
}

$expect(
    !str_contains(
        $staffRepository,
        'NP-000016'
    ),
    'Tenant-specific ticket-number example remains.'
);

echo "T2_STAFF_MANAGER_AUTHORIZATION_CLOSURE=PASS\n";
echo "PROJECT_LOCAL_MANAGER_MEMBER_ADMIN=YES\n";
echo "STAFF_SSO_EXISTING_T2_BRIDGE=ADOPTED\n";
echo "GLOBAL_CORE_PERMISSION_GRANT_TO_STAFF=NO\n";
echo "TENANT_SPECIFIC_COMMENT=REMOVED\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static function (string $relative) use ($root): string {
        $content =
            file_get_contents(
                $root . '/' . ltrim($relative, '/')
            );

        if ($content === false) {
            throw new RuntimeException(
                'Unable to read: ' . $relative
            );
        }

        return $content;
    };

$expect =
    static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

$panel =
    $read(
        'public_html/app/Services/AdminPanelService.php'
    );

$dynamic =
    $read(
        'public_html/app/Services/DynamicAdminNavigationService.php'
    );

$rbac =
    $read(
        'public_html/app/Services/AdminNavigationRbacService.php'
    );

$access =
    $read(
        'public_html/app/Services/AccessControlService.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/access-control.php'
    );

$web =
    $read(
        'public_html/routes/web.php'
    );

$seed =
    $read(
        'public_html/system/Database/Seeds/AuthRbacSeeder.php'
    );

$governance =
    $read(
        'public_html/system/Database/Migrations/'
        . 'SeedExistingDynamicRoleGovernance.php'
    );

$expect(
    substr_count(
        $dynamic,
        'مدیریت راهنما، اعلان و خطا'
    ) >= 2,
    'Canonical help-text title missing.'
);

$expect(
    str_contains($panel, "'key' => 'access-control'")
    && str_contains($panel, "'access.roles.manage'")
    && str_contains($panel, "'access.users.search'")
    && str_contains($panel, "'access.users.manage'")
    && str_contains($panel, "'access.audit.view'"),
    'Dashboard access-control capability set invalid.'
);

$expect(
    str_contains($dynamic, 'normalizeSystemManagementItems')
    && str_contains($dynamic, "'access.roles.manage'")
    && str_contains($dynamic, "'access.users.search'")
    && str_contains($dynamic, "'access.users.manage'")
    && str_contains($dynamic, "'access.audit.view'"),
    'Sidebar access-control capability set invalid.'
);

$expect(
    str_contains($rbac, 'accessControlPermissionsForPath')
    && str_contains(
        $rbac,
        "'/admin/access-control' => 'access.roles.manage'"
    ),
    'Central access-control route contract missing.'
);

foreach ([
    '/admin/settings/core-features',
    '/admin/settings/file-infrastructure',
    '/admin/settings/modules',
] as $settingsPath) {
    $expect(
        str_contains(
            $rbac,
            "'{$settingsPath}' => 'admin.settings.manage'"
        ),
        'Settings route remains unmapped: '
        . $settingsPath
    );
}

$expect(
    str_contains($access, 'access_surface_capabilities')
    && str_contains($access, "'users_manage' =>")
    && str_contains($access, "'scopes' =>"),
    'AccessControlService capability model missing.'
);

$expect(
    str_contains($view, '$canRoles')
    && str_contains($view, '$canUsers')
    && str_contains($view, '$canAudit')
    && str_contains($view, '$canScopes'),
    'Access-control view capability projection missing.'
);

$accessRoutes = [
    '/admin/access-control',
    '/admin/access-control/roles',
    '/admin/access-control/roles/create',
    '/admin/access-control/roles/update',
    '/admin/access-control/scopes',
    '/admin/access-control/users',
    '/admin/access-control/users/default-role',
    '/admin/access-control/users/roles',
];

foreach ($accessRoutes as $route) {
    $expect(
        str_contains(
            $web,
            "\$adminGuard(\$response, '{$route}')"
        ),
        'Access-control route does not use '
        . 'its canonical guard path: '
        . $route
    );
}

$systemStart =
    strpos(
        $seed,
        "'system_admin' => ["
    );

$systemEnd =
    $systemStart === false
        ? false
        : strpos(
            $seed,
            "'province_admin' => [",
            $systemStart
        );

$expect(
    $systemStart !== false
    && $systemEnd !== false,
    'system_admin seed block missing.'
);

$systemBlock =
    substr(
        $seed,
        (int) $systemStart,
        (int) $systemEnd
        - (int) $systemStart
    );

$expect(
    !str_contains(
        $systemBlock,
        "'access.manage'"
    ),
    'system_admin must not receive root access.manage.'
);

$expect(
    str_contains(
        $governance,
        "permissions.code = 'access.roles.manage'"
    ),
    'system_admin granular role-management seed missing.'
);

echo "SYSTEM_MANAGEMENT_ACCESS_SURFACE_CANONICALIZATION=PASS\n";
echo "CARD_SIDEBAR_TITLE_PARITY=PASS\n";
echo "CARD_SIDEBAR_PERMISSION_PARITY=PASS\n";
echo "ACCESS_CONTROL_ROUTE_RULES=PASS\n";
echo "SYSTEM_ADMIN_ROLE_MANAGEMENT=ALLOW\n";
echo "SYSTEM_ADMIN_ROOT_ACCESS_MANAGE=DENY\n";

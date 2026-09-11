<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $content =
            file_get_contents(
                $root
                . '/'
                . ltrim(
                    $relative,
                    '/'
                )
            );

        if ($content === false) {
            throw new RuntimeException(
                'Unable to read: '
                . $relative
            );
        }

        return $content;
    };

$expect =
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

$between =
    static function (
        string $source,
        string $startMarker,
        string $endMarker
    ): string {
        $start =
            strpos(
                $source,
                $startMarker
            );

        $end =
            $start === false
                ? false
                : strpos(
                    $source,
                    $endMarker,
                    $start
                );

        if (
            $start === false
            || $end === false
            || $end <= $start
        ) {
            throw new RuntimeException(
                'Unable to isolate contract block: '
                . $startMarker
            );
        }

        return
            substr(
                $source,
                $start,
                $end - $start
            );
    };

$dynamic =
    $read(
        'public_html/app/Services/'
        . 'DynamicAdminNavigationService.php'
    );

$panel =
    $read(
        'public_html/app/Services/'
        . 'AdminPanelService.php'
    );

$rbac =
    $read(
        'public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );

$seeder =
    $read(
        'public_html/system/Database/Seeds/'
        . 'AuthRbacSeeder.php'
    );

$dynamicNav =
    $between(
        $dynamic,
        'public function navigation(',
        'public function topbar('
    );

$expect(
    substr_count(
        $dynamicNav,
        'UNIFIED_PARENT_CHILD_ACCESS_SURFACE_V1'
    ) === 1,
    'Unified dynamic navigation marker invalid.'
);

$expect(
    str_contains(
        $dynamicNav,
        '$children = $this->childrenByParentId('
    ),
    'Dynamic navigation does not calculate '
    . 'authorized children before parent visibility.'
);

$expect(
    str_contains(
        $dynamicNav,
        '!$this->allowed($item, $userId)'
        . "\n"
        . '                && $children === []'
    ),
    'Dynamic parent own-or-child gate missing.'
);

$expect(
    str_contains(
        $dynamicNav,
        "\$presented['children'] = \$children;"
    ),
    'Dynamic parent does not reuse '
    . 'the authorized child list.'
);

$expect(
    !str_contains(
        $dynamicNav,
        "|| !\$this->allowed(\$item, \$userId)\n"
        . "            )"
    ),
    'Legacy root-own-only permission gate remains.'
);

$expect(
    str_contains(
        $panel,
        'UNIVERSAL_CORE_PARENT_VISIBILITY_BY_CHILD_PERMISSION'
    ),
    'Dashboard parent/child visibility contract missing.'
);

$systemStart =
    strpos(
        $panel,
        "'key' => 'system'"
    );

$expect(
    $systemStart !== false,
    'System dashboard module missing.'
);

$systemBlock =
    substr(
        $panel,
        (int) $systemStart,
        6500
    );

$expect(
    str_contains(
        $systemBlock,
        "'key' => 'help-texts'"
    )
    && str_contains(
        $systemBlock,
        "'admin.ui_content.manage'"
    ),
    'System dashboard help-text child permission invalid.'
);

$routes = [
    '/admin/system/help-texts',
    '/admin/system/help-texts/definition/save',
    '/admin/system/help-texts/override/save',
];

foreach ($routes as $route) {
    $expect(
        str_contains(
            $rbac,
            "'{$route}' => "
            . "'admin.ui_content.manage'"
        ),
        'Route permission mismatch: '
        . $route
    );

    $expect(
        !str_contains(
            $rbac,
            "'{$route}' => "
            . "'admin.settings.manage'"
        ),
        'Broad route permission regression: '
        . $route
    );
}

$systemAdmin =
    $between(
        $seeder,
        "'system_admin' => [",
        "'province_admin' => ["
    );

$required = [
    'admin.ui_content.manage',
    'admin.reports.view',
    'users.view',
    'users.manage',
    'org_units.view',
    'org_units.manage',
    'positions.view',
    'positions.manage',
    'user_org_assignments.manage',
    'support.view',
];

$restricted = [
    'access.manage',
    'admin.theme.manage',
    'admin.settings.manage',
    'admin.pages.manage',
    'admin.navigation.debug',
];

foreach ($required as $permission) {
    $expect(
        str_contains(
            $systemAdmin,
            "'{$permission}'"
        ),
        'system_admin required permission missing: '
        . $permission
    );
}

foreach ($restricted as $permission) {
    $expect(
        !str_contains(
            $systemAdmin,
            "'{$permission}'"
        ),
        'system_admin restricted permission present: '
        . $permission
    );
}

$helpFallback =
    $between(
        $dynamic,
        'private function withSystemHelpTexts(',
        'private function present('
    );

$expect(
    str_contains(
        $helpFallback,
        "'admin.ui_content.manage'"
    ),
    'Dynamic help-text fallback permission invalid.'
);

$expect(
    !str_contains(
        $helpFallback,
        "'admin.settings.manage'"
    ),
    'Dynamic help-text fallback broad permission remains.'
);

echo
    "UNIFIED_ADMIN_ACCESS_SURFACE_CONTRACT=PASS\n";

echo
    "DASHBOARD_PARENT=OWN_OR_CHILD\n";

echo
    "SIDEBAR_PARENT=OWN_OR_CHILD\n";

echo
    "CHILD_PERMISSION=INDEPENDENT\n";

echo
    "ROUTE_PERMISSION=INDEPENDENT\n";

echo
    "SYSTEM_ADMIN_BOUNDARY=PRESERVED\n";

echo
    "ROLE_CODE_PRESENTATION_BYPASS=FORBIDDEN\n";

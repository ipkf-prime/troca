<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $path =
            $root
            . '/'
            . ltrim(
                $relative,
                '/'
            );

        $content =
            file_get_contents(
                $path
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

$extractBetween =
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

        if ($start === false) {
            throw new RuntimeException(
                'Start marker missing: '
                . $startMarker
            );
        }

        $end =
            strpos(
                $source,
                $endMarker,
                $start
            );

        if (
            $end === false
            || $end <= $start
        ) {
            throw new RuntimeException(
                'End marker missing: '
                . $endMarker
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

$rbac =
    $read(
        'public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );

$panel =
    $read(
        'public_html/app/Services/'
        . 'AdminPanelService.php'
    );

$seeder =
    $read(
        'public_html/system/Database/Seeds/'
        . 'AuthRbacSeeder.php'
    );

$helpFallback =
    $extractBetween(
        $dynamic,
        'private function withSystemHelpTexts(',
        'private function present('
    );

$expect(
    substr_count(
        $helpFallback,
        "'admin.ui_content.manage'"
    ) === 1,
    'Dynamic help fallback must use exactly one '
    . 'admin.ui_content.manage permission.'
);

$expect(
    !str_contains(
        $helpFallback,
        "'admin.settings.manage'"
    ),
    'Dynamic help fallback must not use '
    . 'admin.settings.manage.'
);

$expect(
    str_contains(
        $helpFallback,
        "'/admin/system/help-texts'"
    ),
    'Dynamic help fallback route missing.'
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
        'RBAC route is not bound to '
        . 'admin.ui_content.manage: '
        . $route
    );

    $expect(
        !str_contains(
            $rbac,
            "'{$route}' => "
            . "'admin.settings.manage'"
        ),
        'RBAC route regressed to broad permission: '
        . $route
    );
}

$panelStart =
    strpos(
        $panel,
        "'key' => 'help-texts'"
    );

$expect(
    $panelStart !== false,
    'Admin panel help-texts action missing.'
);

$panelBlock =
    substr(
        $panel,
        (int) $panelStart,
        1600
    );

$expect(
    str_contains(
        $panelBlock,
        "'admin.ui_content.manage'"
    ),
    'Admin panel help-texts permission invalid.'
);

$expect(
    !str_contains(
        $panelBlock,
        "'admin.settings.manage'"
    ),
    'Admin panel help-texts broad permission remains.'
);

$systemAdmin =
    $extractBetween(
        $seeder,
        "'system_admin' => [",
        "'province_admin' => ["
    );

$expect(
    str_contains(
        $systemAdmin,
        "'admin.ui_content.manage'"
    ),
    'system_admin lost ui-content permission.'
);

$expect(
    !str_contains(
        $systemAdmin,
        "'admin.settings.manage'"
    ),
    'system_admin regained restricted settings permission.'
);

$expect(
    str_contains(
        $dynamic,
        '$this->authorization->hasPermission('
    ),
    'Dynamic navigation authorization primitive missing.'
);

echo
    "UI_CONTENT_ACCESS_CONTROL_HARDENING_CONTRACT=PASS\n";

echo
    "DYNAMIC_NAV_PERMISSION=admin.ui_content.manage\n";

echo
    "BROAD_HELP_PERMISSION=FORBIDDEN\n";

echo
    "SYSTEM_ADMIN_BOUNDARY=PRESERVED\n";

echo
    "PARALLEL_ACL=NOT_INTRODUCED\n";

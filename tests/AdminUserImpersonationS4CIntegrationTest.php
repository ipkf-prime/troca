<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $source =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($source)) {
            throw new RuntimeException(
                'Unable to read '
                . $relative
            );
        }

        return $source;
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

$lifecycle =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationSessionLifecycleService.php'
    );

$guard =
    $read(
        'public_html/system/Http/Middleware/'
        . 'ImpersonationMutationGuardMiddleware.php'
    );

$routes =
    $read(
        'public_html/routes/admin-users-manage.php'
    );

$users =
    $read(
        'public_html/resources/views/admin/users.php'
    );

$workspace =
    $read(
        'public_html/resources/views/admin/partials/'
        . 'entity-workspace.php'
    );

$layout =
    $read(
        'public_html/resources/views/admin/layout.php'
    );

$uiService =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationUiService.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'SeedAdminImpersonationDynamicUiContent.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


/*
 * Explicit stop.
 */
foreach ([
    'public function stop(',
    'string $nonce',
    'hash_equals(',
    "'nonce_mismatch'",
    "'explicit_stop'",
    '->restoreState(',
] as $token) {
    $expect(
        str_contains(
            $lifecycle,
            $token
        ),
        'Stop lifecycle token missing: '
        . $token
    );
}

echo "S4C_NONCE_BOUND_STOP=PASS\n";


/*
 * Terminal logout.
 */
$expect(
    str_contains(
        $lifecycle,
        'public function terminateForLogout()'
    ),
    'Terminal logout API missing.'
);

$expect(
    str_contains(
        $guard,
        '->terminateForLogout()'
    )
    && !str_contains(
        $guard,
        '$lifecycle->restore()'
    ),
    'Guard terminal logout semantics invalid.'
);

echo "S4C_TERMINAL_LOGOUT=PASS\n";


/*
 * Exact routes.
 */
$expect(
    substr_count(
        $routes,
        "'/admin/users/{id}/impersonate'"
    ) === 1,
    'Start route count invalid.'
);

$expect(
    substr_count(
        $routes,
        "'/admin/impersonation/stop'"
    ) === 1,
    'Stop route count invalid.'
);

foreach ([
    'new \IPKF\Security\Csrf()',
    '->start(',
    '->stop(',
    "'active_role_assignment_id'",
    "'return_path'",
] as $token) {
    $expect(
        str_contains(
            $routes,
            $token
        ),
        'Route security token missing: '
        . $token
    );
}

echo "S4C_START_STOP_ROUTES=PASS\n";
echo "S4C_ROUTE_CSRF=PASS\n";


/*
 * Dynamic scoped authorization is consumed for action
 * visibility; no role title is used for security.
 */
$expect(
    str_contains(
        $routes,
        'ImpersonationAuthorizationService()'
    )
    && str_contains(
        $routes,
        '->decide('
    ),
    'Scoped authorization decision missing.'
);

echo "S4C_DYNAMIC_SCOPED_ACTION_VISIBILITY=PASS\n";


/*
 * Dynamic content keys.
 */
$keys = [
    'core.users.impersonation.action',
    'core.users.impersonation.confirm.title',
    'core.users.impersonation.confirm.body',
    'core.users.impersonation.banner',
    'core.users.impersonation.return',
    'core.users.impersonation.readonly',
    'core.users.impersonation.denied',
    'core.users.impersonation.expired',
];

foreach ($keys as $key) {
    $expect(
        substr_count(
            $uiService,
            $key
        ) === 1,
        'UI service key missing: '
        . $key
    );

    $expect(
        substr_count(
            $migration,
            $key
        ) === 1,
        'Migration key missing: '
        . $key
    );
}

echo "S4C_DYNAMIC_UI_KEYS=8_PASS\n";


$expect(
    str_contains(
        $migration,
        "private const SEED"
    )
    && str_contains(
        $migration,
        'FOR UPDATE'
    )
    && str_contains(
        $migration,
        'beginTransaction()'
    )
    && str_contains(
        $migration,
        'ownership_collision'
    ),
    'Managed UI seed safety contract missing.'
);

echo "S4C_DYNAMIC_UI_OWNERSHIP_GUARD=PASS\n";


$expect(
    str_contains(
        $registry,
        'SeedAdminImpersonationDynamicUiContent::class'
    ),
    'S4C migration registry binding missing.'
);

echo "S4C_MIGRATION_REGISTERED=PASS\n";


/*
 * UI surfaces.
 */
$expect(
    substr_count(
        $users,
        'impersonation_action'
    ) >= 2,
    'Desktop/mobile list action binding missing.'
);

$expect(
    str_contains(
        $workspace,
        "\$workspace['actions']"
    ),
    'Workspace action contract missing.'
);

$expect(
    str_contains(
        $layout,
        '/admin/impersonation/stop'
    )
    && str_contains(
        $layout,
        "'nonce'"
    )
    && str_contains(
        $layout,
        'admin-impersonation-banner'
    ),
    'Global impersonation banner/stop missing.'
);

echo "S4C_LIST_ACTION_DESKTOP_MOBILE=PASS\n";
echo "S4C_DETAIL_WORKSPACE_ACTION=PASS\n";
echo "S4C_GLOBAL_BANNER_STOP=PASS\n";


/*
 * Impersonation human-facing copy must live only in
 * managed dynamic content migration, not route/view.
 */
$humanCopy = [
    'ورود به حساب کاربر',
    'تأیید ورود موقت',
    'در حال مشاهده سامانه با هویت کاربر دیگری هستید.',
    'بازگشت به حساب اصلی',
    'این حالت فقط برای مشاهده است و عملیات تغییردهنده مسدود است.',
    'ورود به محیط این کاربر مجاز نیست.',
];

foreach ([
    $routes,
    $users,
    $workspace,
    $layout,
] as $runtimeSource) {
    foreach ($humanCopy as $text) {
        $expect(
            !str_contains(
                $runtimeSource,
                $text
            ),
            'Hardcoded impersonation UI copy detected.'
        );
    }
}

echo "S4C_RUNTIME_IMPERSONATION_UI_HARDCODE=NO\n";


/*
 * The Stop endpoint must be the only mutation exception
 * added by S4C.
 */
$expect(
    str_contains(
        $guard,
        "'POST /admin/impersonation/stop'"
    )
    && str_contains(
        $guard,
        "'POST /auth/logout'"
    ),
    'S4C mutation allow-list incomplete.'
);

echo "S4C_MUTATION_ALLOWLIST=PASS\n";


/*
 * UI action must be explicitly suppressed while any
 * impersonation context is active. Do not rely on the
 * Effective User accidentally lacking permission.
 */
$expect(
    str_contains(
        $routes,
        'ImpersonationSessionLifecycleService()'
    )
    && str_contains(
        $routes,
        '->status()'
    )
    && str_contains(
        $routes,
        "\$presentation[\n                    'active'"
    ),
    'Explicit active impersonation action suppression missing.'
);

echo "S4C_ACTIVE_IMPERSONATION_START_ACTION=HIDDEN\n";


/*
 * Persistent banner contract requires Target identity in
 * addition to mode/read-only/return action.
 */
$expect(
    str_contains(
        $layout,
        "'target_identity'"
    )
    && str_contains(
        $layout,
        "\$user[\n                            'name'"
    )
    && str_contains(
        $layout,
        "'effective_user_id'"
    )
    && str_contains(
        $layout,
        'admin-impersonation-banner__target'
    ),
    'Global banner target identity binding missing.'
);

$expect(
    str_contains(
        $migration,
        'در حال مشاهده سامانه با هویت کاربر زیر هستید:'
    ),
    'Dynamic banner copy does not introduce Target identity.'
);

echo "S4C_GLOBAL_BANNER_TARGET_IDENTITY=PASS\n";

echo "ADMIN_USER_IMPERSONATION_S4C_INTEGRATION=PASS\n";

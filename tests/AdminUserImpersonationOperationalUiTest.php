<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $value =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'read_failed:'
                . $relative
            );
        }

        return $value;
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


$ui =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationUiService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'admin-users-manage.php'
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

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'ExtendAdminUserImpersonationOperationalMode.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


/*
 * Legacy S4C key compatibility.
 *
 * New keys must not create substring collisions for the
 * original exact-count source contract.
 */
$legacyKeys = [
    'core.users.impersonation.action',
    'core.users.impersonation.confirm.title',
    'core.users.impersonation.confirm.body',
    'core.users.impersonation.banner',
    'core.users.impersonation.return',
    'core.users.impersonation.readonly',
    'core.users.impersonation.denied',
    'core.users.impersonation.expired',
];

foreach ($legacyKeys as $key) {
    $expect(
        substr_count(
            $ui,
            $key
        ) === 1,
        'legacy_ui_key_collision:'
        . $key
    );
}

echo "R2B_LEGACY_UI_KEY_COLLISION=NO\n";


$newKeys = [
    'core.users.impersonation.mode.label',
    'core.users.impersonation.mode.observe',
    'core.users.impersonation.mode.operate',
    'core.users.impersonation.confirm.operate',
    'core.users.impersonation.operate.banner',
    'core.users.impersonation.operate',
    'core.users.impersonation.mutation.blocked',
    'core.users.impersonation.sensitive.blocked',
    'core.users.impersonation.operate.denied',
    'core.users.impersonation.audit.unavailable',
];

foreach ($newKeys as $key) {
    $expect(
        str_contains(
            $ui,
            $key
        ),
        'ui_key_missing:'
        . $key
    );

    $expect(
        str_contains(
            $migration,
            $key
        ),
        'migration_key_missing:'
        . $key
    );
}

echo "R2B_DYNAMIC_UI_KEYS=10_PASS\n";


foreach ([
    'users.impersonate.operate',
    'roles.can_manage_other_users = 1',
    'base_permission.code = ?',
    'is_sensitive',
] as $token) {
    $expect(
        str_contains(
            $migration,
            $token
        ),
        'permission_contract_missing:'
        . $token
    );
}

echo "R2B_DYNAMIC_PERMISSION=PASS\n";


foreach ([
    'mode_options',
    'users.impersonate.operate',
    "'mode',",
    'MODE_OBSERVE',
] as $token) {
    $expect(
        str_contains(
            $routes,
            $token
        ),
        'route_contract_missing:'
        . $token
    );
}

echo "R2B_ROUTE_MODE=PASS\n";


$expect(
    str_contains(
        $users,
        'name="mode"'
    )
    && str_contains(
        $workspace,
        'name="mode"'
    ),
    'mode_selector_missing'
);

echo "R2B_MODE_SELECTOR=PASS\n";


foreach ([
    'data-impersonation-mode',
    "'readonly_blocked'",
    "'sensitive_blocked'",
    "'operate_denied'",
    "'audit_unavailable'",
    'selectedMode?.dataset.confirmMessage',
] as $token) {
    $expect(
        str_contains(
            $layout,
            $token
        ),
        'layout_contract_missing:'
        . $token
    );
}

echo "R2B_LAYOUT_MODE_NOTICE=PASS\n";


$expect(
    substr_count(
        $registry,
        'ExtendAdminUserImpersonationOperationalMode::class'
    ) === 1,
    'migration_registry_count_invalid'
);

echo "R2B_MIGRATION_REGISTERED=PASS\n";


$expect(
    !str_contains(
        $layout,
        'در حال کار در سامانه با هویت کاربر زیر هستید:'
    )
    && !str_contains(
        $layout,
        'حالت عملیاتی فعال است؛'
    )
    && !str_contains(
        $layout,
        'امکان انجام عملیات تغییردهنده وجود ندارد.'
    ),
    'runtime_operational_copy_hardcoded'
);

echo "R2B_RUNTIME_COPY_HARDCODE=NO\n";


foreach ([
    'super_admin',
    'system_admin',
    'central_admin',
    'province_admin',
    'county_admin',
    'company_admin',
] as $roleName) {
    $expect(
        !str_contains(
            strtolower(
                $migration
            ),
            $roleName
        ),
        'role_name_hardcode:'
        . $roleName
    );
}

echo "R2B_ROLE_NAME_AUTHORIZATION=NO\n";
echo "ADMIN_USER_IMPERSONATION_R2B_R1=PASS\n";

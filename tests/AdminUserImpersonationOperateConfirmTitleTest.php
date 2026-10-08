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
        . 'ImpersonationOperateGrantUiService.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'admin-users-manage.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'AddAdminUserImpersonationOperateConfirmTitles.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$grantKey =
    'core.users.impersonation.operate.access.confirm.grant.title';

$revokeKey =
    'core.users.impersonation.operate.access.confirm.revoke.title';


foreach ([
    $grantKey,
    $revokeKey,
] as $key) {
    $expect(
        substr_count(
            $ui,
            $key
        ) === 1,
        'ui_confirm_title_key_invalid:'
        . $key
    );

    $expect(
        substr_count(
            $migration,
            $key
        ) === 1,
        'migration_confirm_title_key_invalid:'
        . $key
    );
}

echo "A2R6_DYNAMIC_CONFIRM_TITLE_KEYS=2_PASS\n";


$expect(
    !str_contains(
        $ui,
        'core.users.impersonation.confirm.title'
    ),
    'generic_temporary_login_confirm_title_still_bound'
);

echo "A2R6_GENERIC_CONFIRM_TITLE_BINDING=NO\n";


$expect(
    str_contains(
        $route,
        "'confirm_grant_title'"
    ),
    'grant_confirm_title_route_binding_missing'
);

$expect(
    str_contains(
        $route,
        "'confirm_revoke_title'"
    ),
    'revoke_confirm_title_route_binding_missing'
);

echo "A2R6_ROUTE_CONFIRM_TITLE_BINDINGS=PASS\n";


/*
 * Persian UI copy must live only in managed
 * dynamic content source, not in the route/view.
 */
foreach ([
    'تأیید اعطای دسترسی عملیاتی',
    'تأیید لغو دسترسی عملیاتی',
] as $text) {
    $expect(
        !str_contains(
            $route,
            $text
        ),
        'persian_confirm_title_hardcoded_in_route'
    );

    $expect(
        str_contains(
            $migration,
            $text
        ),
        'dynamic_confirm_title_seed_missing'
    );
}

echo "A2R6_ROUTE_UI_TEXT_HARDCODE=NO\n";


$expect(
    substr_count(
        $registry,
        'AddAdminUserImpersonationOperateConfirmTitles::class'
    ) === 1,
    'confirm_title_migration_registry_invalid'
);

echo "A2R6_MIGRATION_REGISTERED=PASS\n";
echo "ADMIN_USER_IMPERSONATION_DYNAMIC_CONFIRM_TITLES=PASS\n";

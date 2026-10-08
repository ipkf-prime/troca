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

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'AccessControlRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationOperateGrantService.php'
    );

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

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'user-detail.php'
    );

$guard =
    $read(
        'public_html/system/Http/Middleware/'
        . 'ImpersonationMutationGuardMiddleware.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'SeedAdminUserImpersonationOperateGrantUiContent.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


foreach ([
    'users.impersonate.operate',
    'users.impersonate.operate.assign',
    'permissionForAssignment(',
    'ImpersonationAuthorizationService::PERMISSION',
    'baseCapabilityActive(',
    'impersonation_operate_self_grant_denied',
    "'allow'",
    "'deny'",
] as $token) {
    $expect(
        str_contains(
            $service,
            $token
        ),
        'person_grant_service_contract_missing:'
        . $token
    );
}

echo "A2_PERSON_GRANT_SERVICE=PASS\n";


foreach ([
    'saveImpersonationOperateOverride(',
    'impersonationOperateOverride(',
    'ON DUPLICATE KEY UPDATE',
    "'impersonation_operate_access_granted'",
    "'impersonation_operate_access_revoked'",
    "'users.impersonate.operate'",
    "'users.impersonate.operate.assign'",
] as $token) {
    $expect(
        str_contains(
            $repository,
            $token
        ),
        'repository_contract_missing:'
        . $token
    );
}

$expect(
    str_contains(
        $repository,
        'permissions.code NOT IN'
    ),
    'generic_policy_special_override_protection_missing'
);

echo "A2_SPECIAL_OVERRIDE_PERSISTENCE=PASS\n";
echo "A2_GENERIC_POLICY_DELETE_PROTECTION=PASS\n";


foreach ([
    '/admin/users/{id}/impersonation-operate-access',
    'operate_access_status',
    'ImpersonationOperateGrantService',
    'ImpersonationOperateGrantUiService',
    "'reason'",
    "'effect'",
] as $token) {
    $expect(
        str_contains(
            $route,
            $token
        ),
        'route_contract_missing:'
        . $token
    );
}

echo "A2_ROUTE_BINDING=PASS\n";


$expect(
    str_contains(
        $guard,
        "'/admin/users'"
    )
    && str_contains(
        $guard,
        'SENSITIVE_PREFIXES'
    ),
    'a2_route_not_inside_sensitive_boundary'
);

echo "A2_IMPERSONATED_GRANT_REVOKE_BLOCKED=PASS\n";


foreach ([
    'impersonation_operate_access',
    'name="reason"',
    'minlength="3"',
    'maxlength="500"',
    'data-confirm-title',
    'data-confirm-message',
] as $token) {
    $expect(
        str_contains(
            $view,
            $token
        ),
        'view_contract_missing:'
        . $token
    );
}

echo "A2_ACCESS_TAB_UI=PASS\n";


$newKeys = [
    'core.users.impersonation.operate.access.title',
    'core.users.impersonation.operate.access.description',
    'core.users.impersonation.operate.access.status.allowed',
    'core.users.impersonation.operate.access.status.denied',
    'core.users.impersonation.operate.access.status.ineligible',
    'core.users.impersonation.operate.access.grant',
    'core.users.impersonation.operate.access.revoke',
    'core.users.impersonation.operate.access.reason.label',
    'core.users.impersonation.operate.access.reason.placeholder',
    'core.users.impersonation.operate.access.confirm.grant',
    'core.users.impersonation.operate.access.confirm.revoke',
    'core.users.impersonation.operate.access.feedback.updated',
    'core.users.impersonation.operate.access.feedback.denied',
];

foreach ($newKeys as $key) {
    $expect(
        str_contains(
            $ui,
            $key
        ),
        'ui_service_key_missing:'
        . $key
    );

    $expect(
        str_contains(
            $migration,
            $key
        ),
        'ui_migration_key_missing:'
        . $key
    );

    $expect(
        !str_contains(
            $view,
            $key
        ),
        'ui_key_hardwired_into_view:'
        . $key
    );
}

echo "A2_DYNAMIC_UI_KEYS=13_PASS\n";


foreach ([
    'super_admin',
    'system_admin',
    'central_admin',
    'province_admin',
    'county_admin',
] as $roleName) {
    $expect(
        !str_contains(
            strtolower(
                $service
            ),
            $roleName
        ),
        'role_name_authorization_found:'
        . $roleName
    );
}

echo "A2_ROLE_NAME_AUTHORIZATION=NO\n";


$expect(
    substr_count(
        $registry,
        'SeedAdminUserImpersonationOperateGrantUiContent::class'
    ) === 1,
    'a2_migration_registry_invalid'
);

echo "A2_MIGRATION_REGISTERED=PASS\n";
echo "ADMIN_USER_IMPERSONATION_PERSON_GRANT_UI_AUDIT=PASS\n";

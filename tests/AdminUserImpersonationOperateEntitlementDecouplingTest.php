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

$service =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationOperateGrantService.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'admin-users-manage.php'
    );

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

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'RefineAdminUserImpersonationOperateEntitlementModel.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


$expect(
    str_contains(
        $service,
        "'base_capability_active'"
    ),
    'base_capability_state_missing'
);

$expect(
    str_contains(
        $service,
        'baseCapabilityActive('
    ),
    'base_capability_probe_missing'
);

$expect(
    !str_contains(
        $service,
        'impersonation_operate_recipient_ineligible'
    ),
    'grant_time_base_gate_still_present'
);

$expect(
    str_contains(
        $service,
        "'can_grant' =>"
    )
    && str_contains(
        $service,
        "\$effect !== 'allow'"
    ),
    'person_entitlement_grant_contract_missing'
);

echo "A2R2_GRANT_INDEPENDENT_FROM_CURRENT_BASE=PASS\n";


$expect(
    str_contains(
        $route,
        "'base_capability_active'"
    )
    && str_contains(
        $route,
        "'status_ineligible'"
    ),
    'informational_base_status_missing'
);

echo "A2R2_UI_BASE_STATUS=PASS\n";


/*
 * Runtime usage must STILL require Operate permission
 * and canonical impersonation authorization.
 */
foreach ([
    'users.impersonate.operate',
    'permissionForAssignment(',
] as $token) {
    $expect(
        str_contains(
            $lifecycle,
            $token
        )
        || str_contains(
            $guard,
            $token
        ),
        'runtime_operate_gate_missing:'
        . $token
    );
}

$expect(
    str_contains(
        $guard,
        'new ImpersonationAuthorizationService()'
    )
    || str_contains(
        $guard,
        'ImpersonationAuthorizationService'
    ),
    'runtime_scope_revalidation_missing'
);

echo "A2R2_RUNTIME_BASE_AND_SCOPE_GATES_PRESERVED=PASS\n";


foreach ([
    'core.users.impersonation.operate.access.description',
    'core.users.impersonation.operate.access.status.allowed',
    'core.users.impersonation.operate.access.status.ineligible',
] as $key) {
    $expect(
        str_contains(
            $migration,
            $key
        ),
        'copy_refinement_key_missing:'
        . $key
    );
}

echo "A2R2_DYNAMIC_COPY_REFINEMENT=PASS\n";


$expect(
    substr_count(
        $registry,
        'RefineAdminUserImpersonationOperateEntitlementModel::class'
    ) === 1,
    'a2r2_registry_binding_invalid'
);

echo "A2R2_MIGRATION_REGISTERED=PASS\n";
echo "ADMIN_USER_IMPERSONATION_OPERATE_ENTITLEMENT_DECOUPLING=PASS\n";

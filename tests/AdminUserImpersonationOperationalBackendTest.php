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

$context =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationContextService.php'
    );

$lifecycle =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationSessionLifecycleService.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'ImpersonationAuditRepository.php'
    );

$audit =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationAuditService.php'
    );

$guard =
    $read(
        'public_html/system/Http/Middleware/'
        . 'ImpersonationMutationGuardMiddleware.php'
    );


foreach ([
    'MODE_OBSERVE',
    'MODE_OPERATE',
    'invalid_impersonation_mode',
    "'mode'",
] as $token) {
    $expect(
        str_contains(
            $context,
            $token
        ),
        'context_missing:'
        . $token
    );
}

echo "R2A_CONTEXT_MODE=PASS\n";


foreach ([
    'users.impersonate.operate',
    'permissionForAssignment(',
    'actorAssignment(',
    "'operate_permission_missing'",
    "'actor_assignment_id'",
] as $token) {
    $expect(
        str_contains(
            $lifecycle,
            $token
        ),
        'lifecycle_missing:'
        . $token
    );
}

echo "R2A_START_OPERATE_AUTH=PASS\n";


foreach ([
    'impersonation_operation_attempted',
    'impersonation_operation_completed',
] as $token) {
    $expect(
        str_contains(
            $repository,
            $token
        ),
        'repository_missing:'
        . $token
    );

    $expect(
        str_contains(
            $audit,
            $token
        ),
        'audit_missing:'
        . $token
    );

    $expect(
        str_contains(
            $guard,
            $token
        ),
        'guard_missing:'
        . $token
    );
}

echo "R2A_OPERATION_AUDIT=PASS\n";


foreach ([
    'ImpersonationAuthorizationService',
    '->decide(',
    'OPERATE_PERMISSION',
    'permissionForAssignment(',
    "'operate_authorization_invalid'",
] as $token) {
    $expect(
        str_contains(
            $guard,
            $token
        ),
        'operate_revalidation_missing:'
        . $token
    );
}

echo "R2A_PER_MUTATION_SCOPE_REVALIDATION=PASS\n";


foreach ([
    'SENSITIVE_PREFIXES',
    'SENSITIVE_SEGMENTS',
    "'sensitive_operation_during_impersonation'",
] as $token) {
    $expect(
        str_contains(
            $guard,
            $token
        ),
        'sensitive_boundary_missing:'
        . $token
    );
}

echo "R2A_SENSITIVE_BOUNDARY=PASS\n";


$blocked =
    strpos(
        $guard,
        "'impersonation_mutation_blocked'"
    );

$deny =
    strpos(
        $guard,
        '->status(403)'
    );

$expect(
    $blocked !== false
    && $deny !== false
    && $blocked < $deny,
    'S4B audit ordering invalid.'
);

echo "R2A_S4B_ORDER=PASS\n";


$expect(
    str_contains(
        $guard,
        'browserNavigation()'
    )
    && str_contains(
        $guard,
        "'readonly_blocked'"
    ),
    'Browser denial redirect missing.'
);

echo "R2A_BROWSER_DENIAL_BACKEND=PASS\n";


$expect(
    !str_contains(
        $guard,
        'Session::'
    ),
    'Guard must not access session directly.'
);

echo "R2A_DIRECT_SESSION_ACCESS=NO\n";
echo "ADMIN_USER_IMPERSONATION_R2A_R1=PASS\n";

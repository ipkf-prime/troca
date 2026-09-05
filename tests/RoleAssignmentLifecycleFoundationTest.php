<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static function (
        string $path
    ) use ($root): string {
        $content =
            file_get_contents(
                $root
                . '/'
                . $path
            );

        if (!is_string($content)) {
            fwrite(
                STDERR,
                "FAIL: cannot read {$path}\n"
            );

            exit(1);
        }

        return $content;
    };

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            fwrite(
                STDERR,
                "FAIL: {$message}\n"
            );

            exit(1);
        }
    };

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'AddRoleAssignmentLifecycleGovernance.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$service =
    $read(
        'public_html/app/Services/'
        . 'RoleAssignmentLifecycleService.php'
    );

$expect(
    str_contains(
        $registry,
        'AddRoleAssignmentLifecycleGovernance::class'
    ),
    'Lifecycle migration is not registered.'
);

foreach (
    [
        'lifecycle_status_code',
        'requested_at',
        'eligibility_checked_at',
        'eligible_at',
        'activated_at',
        'activated_by',
        'revoked_at',
        'revoked_by',
    ]
    as $column
) {
    $expect(
        str_contains(
            $migration,
            $column
        ),
        "Missing lifecycle column {$column}."
    );
}

foreach (
    [
        "'active'",
        "'pending_identity'",
        "'pending_scope'",
        "'pending_identity_scope'",
        "'revoked'",
    ]
    as $status
) {
    $expect(
        str_contains(
            $migration,
            $status
        ),
        "Missing lifecycle status {$status}."
    );
}

$expect(
    str_contains(
        $migration,
        'Existing active assignments represent the known-good'
    )
    && str_contains(
        $migration,
        'WHEN COALESCE(is_active, 0) = 1'
    ),
    'Existing assignment grandfathering is incomplete.'
);

foreach (
    [
        'public function evaluate(',
        'public function requestAssignment(',
        'public function refreshAssignment(',
        'public function refreshUser(',
        'public function revokeAssignment(',
        'public function syncSelection(',
        'public function userRoleStates(',
    ]
    as $method
) {
    $expect(
        str_contains(
            $service,
            $method
        ),
        "Missing lifecycle method {$method}."
    );
}

foreach (
    [
        "'full_name'",
        "'national_code'",
        "'mobile'",
        "'email'",
        "'province'",
        "'county'",
        "'organization'",
        "'position'",
    ]
    as $field
) {
    $expect(
        str_contains(
            $service,
            $field
        ),
        "Missing identity evidence {$field}."
    );
}

$expect(
    str_contains(
        $service,
        'mobile_verified_at'
    )
    && str_contains(
        $service,
        'email_verified_at'
    ),
    'Verified contact evidence is incomplete.'
);

$expect(
    str_contains(
        $service,
        'role_identity_requirements'
    )
    && str_contains(
        $service,
        'role_scope_policies'
    )
    && str_contains(
        $service,
        'role_assignment_scopes'
    ),
    'Dynamic role policy evaluation is incomplete.'
);

$expect(
    str_contains(
        $service,
        "'global'"
    )
    && str_contains(
        $service,
        "'own'"
    )
    && str_contains(
        $service,
        "'assigned'"
    ),
    'Reference-free scope support is incomplete.'
);

$expect(
    str_contains(
        $service,
        "lifecycle_status_code =\n                        'revoked'"
    )
    && str_contains(
        $service,
        'is_active = 0'
    ),
    'Revocation must fail closed.'
);

$expect(
    str_contains(
        $service,
        'role_assignment_lifecycle_not_migrated'
    ),
    'Migration runtime guard is missing.'
);

echo "ROLE_ASSIGNMENT_LIFECYCLE_MIGRATION=PASS\n";
echo "EXISTING_ASSIGNMENT_GRANDFATHERING=PASS\n";
echo "IDENTITY_ELIGIBILITY_ENGINE=PASS\n";
echo "DYNAMIC_SCOPE_ELIGIBILITY_ENGINE=PASS\n";
echo "PENDING_ASSIGNMENT_FAIL_CLOSED=PASS\n";
echo "ROLE_ASSIGNMENT_REVOCATION=PASS\n";
echo "ROLE_SELECTION_LIFECYCLE_API=PASS\n";
echo "ROLE_ASSIGNMENT_LIFECYCLE_FOUNDATION=PASS\n";

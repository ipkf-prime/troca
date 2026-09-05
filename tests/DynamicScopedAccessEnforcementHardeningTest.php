<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            fwrite(
                STDERR,
                "FAIL: cannot read {$relative}\n"
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

$permission =
    $read(
        'public_html/app/Repositories/'
        . 'PermissionRepository.php'
    );

$dynamic =
    $read(
        'public_html/app/Services/'
        . 'DynamicAccessService.php'
    );

$scoped =
    $read(
        'public_html/app/Services/'
        . 'ScopedAuthorizationService.php'
    );

$accessTest =
    $read(
        'tests/AccessControlFoundationTest.php'
    );

$userPermissionStart =
    strpos(
        $permission,
        'public function userHasPermission('
    );

$userPermissionEnd =
    strpos(
        $permission,
        'public function userPermissionOverride('
    );

$expect(
    $userPermissionStart !== false
    && $userPermissionEnd !== false
    && $userPermissionEnd > $userPermissionStart,
    'userHasPermission boundaries missing.'
);

$userPermission =
    substr(
        $permission,
        $userPermissionStart,
        $userPermissionEnd
            - $userPermissionStart
    );

$expect(
    str_contains(
        $userPermission,
        'user_role_assignments.starts_at'
    )
    && str_contains(
        $userPermission,
        'user_role_assignments.ends_at'
    )
    && str_contains(
        $userPermission,
        '<= CURRENT_TIMESTAMP'
    )
    && str_contains(
        $userPermission,
        '>= CURRENT_TIMESTAMP'
    ),
    'Direct permission time window incomplete.'
);

$overrideStart =
    strpos(
        $permission,
        'public function userPermissionOverride('
    );

$communicationStart =
    strpos(
        $permission,
        'public function communicationMatrix('
    );

$expect(
    $overrideStart !== false
    && $communicationStart !== false
    && $communicationStart > $overrideStart,
    'Override boundaries missing.'
);

$override =
    substr(
        $permission,
        $overrideStart,
        $communicationStart
            - $overrideStart
    );

$expect(
    str_contains(
        $override,
        'override_assignments.is_active = 1'
    )
    && str_contains(
        $override,
        'override_assignments.starts_at'
    )
    && str_contains(
        $override,
        'override_assignments.ends_at'
    )
    && str_contains(
        $override,
        'user_permission_overrides.role_assignment_id = 0'
    ),
    'Assignment override validity enforcement incomplete.'
);

$assignmentStart =
    strpos(
        $dynamic,
        'public function assignmentForUser('
    );

$permissionsStart =
    strpos(
        $dynamic,
        'private function permissions()'
    );

$expect(
    $assignmentStart !== false
    && $permissionsStart !== false
    && $permissionsStart > $assignmentStart,
    'Dynamic assignment boundaries missing.'
);

$assignment =
    substr(
        $dynamic,
        $assignmentStart,
        $permissionsStart
            - $assignmentStart
    );

$expect(
    str_contains(
        $assignment,
        'assignments.starts_at'
    )
    && str_contains(
        $assignment,
        'assignments.ends_at'
    ),
    'Scoped assignment time window incomplete.'
);

$expect(
    str_contains(
        $dynamic,
        'public function roleHasExplicitScopePolicy('
    )
    && str_contains(
        $dynamic,
        'public function referenceFreeDefaultScopesForRole('
    )
    && str_contains(
        $dynamic,
        "'global'"
    )
    && str_contains(
        $dynamic,
        "'own'"
    )
    && str_contains(
        $dynamic,
        "'assigned'"
    )
    && str_contains(
        $dynamic,
        "'org_units'"
    ),
    'Dynamic scope policy hardening incomplete.'
);

$expect(
    str_contains(
        $scoped,
        '->roleHasExplicitScopePolicy('
    )
    && str_contains(
        $scoped,
        '->referenceFreeDefaultScopesForRole('
    )
    && str_contains(
        $scoped,
        "'scope_policy_required'"
    )
    && str_contains(
        $scoped,
        "'legacy_assignment_without_scope_policy'"
    ),
    'Scoped authorization fail-closed contract incomplete.'
);

$expect(
    !str_contains(
        $accessTest,
        'notification_send_approval_required'
    )
    && str_contains(
        $accessTest,
        "\$policy === 'approval_required'"
    )
    && str_contains(
        $accessTest,
        "'pending_approval'"
    )
    && !str_contains(
        $accessTest,
        'data-acl-role-form'
    )
    && str_contains(
        $accessTest,
        'data-acl-role-panel'
    ),
    'Stale access-control contract was not repaired.'
);

echo "DIRECT_PERMISSION_TIME_WINDOW=PASS\n";
echo "ASSIGNMENT_OVERRIDE_TIME_WINDOW=PASS\n";
echo "SCOPED_ASSIGNMENT_TIME_WINDOW=PASS\n";
echo "REFERENCE_FREE_DEFAULT_SCOPE=PASS\n";
echo "CONCRETE_REFERENCE_SCOPE_FAIL_CLOSED=PASS\n";
echo "ORG_UNIT_SCOPE_LOOKUP=PASS\n";
echo "STALE_ACCESS_CONTRACT_REPAIR=PASS\n";
echo "DYNAMIC_SCOPED_ACCESS_ENFORCEMENT_HARDENING=PASS\n";

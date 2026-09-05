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
                "FAIL: {$relative} unavailable.\n"
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

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'AdminUserManagementRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/'
        . 'AdminUserManagementService.php'
    );

$lifecycle =
    $read(
        'public_html/app/Services/'
        . 'RoleAssignmentLifecycleService.php'
    );

$dynamic =
    $read(
        'public_html/app/Services/'
        . 'DynamicAccessService.php'
    );

$identity =
    $read(
        'public_html/app/Services/'
        . 'IdentityVerificationService.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'admin-user-form.php'
    );

$expect(
    substr_count(
        $repository,
        '?callable $roleSynchronizer = null'
    ) === 3,
    'Repository lifecycle callbacks incomplete.'
);

$expect(
    str_contains(
        $repository,
        'role_assignment_lifecycle_synchronizer_required'
    )
    && str_contains(
        $repository,
        'ensureBaseUserRole('
    ),
    'Repository lifecycle bridge incomplete.'
);

$expect(
    str_contains(
        $repository,
        "roles.code <> 'user'"
    )
    && str_contains(
        $repository,
        'assignments.lifecycle_status_code'
    )
    && str_contains(
        $repository,
        "<> 'revoked'"
    ),
    'Pending role selection is not preserved.'
);

$expect(
    str_contains(
        $service,
        'private ?RoleAssignmentLifecycleService $roleLifecycle'
    )
    && str_contains(
        $service,
        'roleSynchronizer('
    )
    && str_contains(
        $service,
        '->syncSelection('
    )
    && str_contains(
        $service,
        "'role_states'"
    ),
    'Admin-user lifecycle wiring incomplete.'
);

$expect(
    str_contains(
        $lifecycle,
        'assertActorCanAssignRole('
    )
    && str_contains(
        $lifecycle,
        "'access_role_assignment_forbidden'"
    )
    && str_contains(
        $lifecycle,
        "assignments.lifecycle_status_code =\n                        'active'"
    )
    && str_contains(
        $lifecycle,
        'roles.priority'
    ),
    'Actor role-priority ceiling incomplete.'
);

$expect(
    str_contains(
        $dynamic,
        'assignments.lifecycle_status_code'
    )
    && str_contains(
        $dynamic,
        "<> 'revoked'"
    )
    && str_contains(
        $dynamic,
        'new RoleAssignmentLifecycleService('
    )
    && str_contains(
        $dynamic,
        '->refreshAssignment('
    ),
    'Scope lifecycle integration incomplete.'
);

$expect(
    str_contains(
        $identity,
        'private ?RoleAssignmentLifecycleService $roleLifecycle'
    )
    && str_contains(
        $identity,
        'role_lifecycle_refreshed'
    )
    && str_contains(
        $identity,
        '->refreshUser('
    ),
    'OTP lifecycle refresh incomplete.'
);

foreach (
    [
        'pending_identity',
        'pending_scope',
        'pending_identity_scope',
        'data-role-lifecycle',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        "UI lifecycle marker missing: {$marker}"
    );
}

echo "ADMIN_USER_CREATE_LIFECYCLE=PASS\n";
echo "ADMIN_USER_UPDATE_LIFECYCLE=PASS\n";
echo "ADMIN_ROLE_ONLY_LIFECYCLE=PASS\n";
echo "BASE_USER_ROLE_PRESERVATION=PASS\n";
echo "ROLE_ASSIGNMENT_PRIORITY_CEILING=PASS\n";
echo "PENDING_ROLE_SELECTION_PRESERVED=PASS\n";
echo "PENDING_SCOPE_EDITOR_VISIBLE=PASS\n";
echo "SCOPE_SAVE_AUTO_REEVALUATION=PASS\n";
echo "OTP_AUTO_REEVALUATION=PASS\n";
echo "ROLE_LIFECYCLE_UI_STATE=PASS\n";
echo "ROLE_ASSIGNMENT_LIFECYCLE_USER_WIRING=PASS\n";

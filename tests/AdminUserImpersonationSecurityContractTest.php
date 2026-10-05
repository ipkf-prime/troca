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

        if (!is_file($path)) {
            fwrite(
                STDERR,
                "Missing file: {$relative}\n"
            );

            exit(1);
        }

        $content =
            file_get_contents(
                $path
            );

        if (!is_string($content)) {
            fwrite(
                STDERR,
                "Unreadable file: {$relative}\n"
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
        if ($condition) {
            return;
        }

        fwrite(
            STDERR,
            $message
            . PHP_EOL
        );

        exit(1);
    };

$contract =
    $read(
        'docs/security/'
        . 'admin-user-impersonation-security-contract.md'
    );

$access =
    $read(
        'public_html/app/Repositories/'
        . 'AccessControlRepository.php'
    );

$userList =
    $read(
        'public_html/app/Repositories/'
        . 'AdminUserListRepository.php'
    );

$auth =
    $read(
        'public_html/app/Services/'
        . 'AuthService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'admin-users-manage.php'
    );


/*
 * Contract invariants.
 */

$requiredContractTokens = [
    'users.impersonate',
    'is_sensitive: 1',
    'Actor User',
    'Effective User',
    'auth_impersonation',
    'actor_user_id',
    'effective_user_id',
    'actor_auth_snapshot',
    'IMPERSONATION_TTL_MINUTES',
    '30 دقیقه',
    'Read-only',
    'auth_impersonation_events',
    'impersonation_started',
    'impersonation_ended',
    'impersonation_expired',
    'impersonation_mutation_blocked',
    'Platform Audit',
    'Nested Impersonation',
    'Password Fingerprint',
    'active_role_assignment_id',
    'Session ID regenerate',
    'CSRF',
    'Fail Closed',
    'core.users.impersonation.action',
    'core.users.impersonation.banner',
    'core.users.impersonation.return',
    'no hardcoded UI copy PASS',
];

foreach (
    $requiredContractTokens
    as $token
) {
    $expect(
        str_contains(
            $contract,
            $token
        ),
        'Missing impersonation contract token: '
        . $token
    );
}

echo "IMPERSONATION_CONTRACT_TOKENS=PASS\n";


/*
 * Existing access-control foundation must be reused.
 */

$expect(
    str_contains(
        $access,
        "Database::columnExists('permissions', 'is_sensitive')"
    ),
    'Sensitive permission metadata foundation missing.'
);

$expect(
    str_contains(
        $access,
        'user_permission_overrides'
    ),
    'User permission override foundation missing.'
);

$expect(
    str_contains(
        $access,
        'role_permissions'
    ),
    'Role permission foundation missing.'
);

$expect(
    str_contains(
        $access,
        'user_role_assignments'
    ),
    'User role assignment foundation missing.'
);

$expect(
    str_contains(
        $access,
        'roles.priority'
    ),
    'Role priority foundation missing.'
);

echo "ACCESS_CONTROL_REUSE_FOUNDATION=PASS\n";


/*
 * User list already exposes priority required for UI eligibility.
 */

$expect(
    str_contains(
        $userList,
        'highest_role_priority'
    ),
    'Admin user role-priority read model missing.'
);

$expect(
    str_contains(
        $userList,
        'users.status'
    ),
    'Admin user status read model missing.'
);

echo "ADMIN_USER_ELIGIBILITY_READ_MODEL=PASS\n";


/*
 * Existing auth contract must be preserved.
 */

foreach ([
    "'auth_user_id'",
    "'auth_login_at'",
    "'auth_mfa_verified'",
    "'auth_password_fingerprint'",
    "'active_role_assignment_id'",
    'Session::regenerate()',
    'Session::destroy()',
    'currentUserId()',
    'currentUser()',
    'logout()',
] as $token) {
    $expect(
        str_contains(
            $auth,
            $token
        ),
        'Missing AuthService contract token: '
        . $token
    );
}

echo "AUTH_SESSION_FOUNDATION=PASS\n";


/*
 * Admin user management remains the UI entry point.
 */

$expect(
    str_contains(
        $routes,
        '/admin/users'
    ),
    'Admin user route foundation missing.'
);

$expect(
    str_contains(
        $routes,
        'adminGuard'
    ),
    'Admin guard foundation missing.'
);

$expect(
    str_contains(
        $routes,
        'AdminUserManagementService'
    ),
    'Admin user management service wiring missing.'
);

echo "ADMIN_USER_ENTRY_POINT=PASS\n";


/*
 * Platform audit storage must already exist somewhere
 * in the current source before implementation starts.
 */

$platformAuditFound = false;

$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root . '/public_html',
            FilesystemIterator::SKIP_DOTS
        )
    );

foreach ($iterator as $file) {
    if (
        !$file->isFile()
        || strtolower(
            $file->getExtension()
        ) !== 'php'
    ) {
        continue;
    }

    $size =
        $file->getSize();

    if (
        $size < 1
        || $size > 2_000_000
    ) {
        continue;
    }

    $content =
        file_get_contents(
            $file->getPathname()
        );

    if (
        is_string($content)
        && str_contains(
            $content,
            'platform_audit_events'
        )
    ) {
        $platformAuditFound = true;
        break;
    }
}

$expect(
    $platformAuditFound,
    'Platform audit storage foundation missing.'
);

$expect(
    is_file(
        $root
        . '/tests/'
        . 'PlatformAuditStorageContractTest.php'
    ),
    'Platform audit storage regression test missing.'
);

echo "PLATFORM_AUDIT_FOUNDATION=PASS\n";


/*
 * The design phase must not sneak runtime implementation
 * into the architecture contract test itself.
 */

$expect(
    !str_contains(
        $contract,
        'Password کاربر را Reset کرده و سپس'
    ),
    'Contract must not implement reset-based impersonation.'
);

$expect(
    str_contains(
        $contract,
        'Password کاربر را بخواند'
    )
    && str_contains(
        $contract,
        'OTP تولید کند'
    ),
    'Credential bypass prohibitions are incomplete.'
);

echo "NO_CREDENTIAL_BYPASS_CONTRACT=PASS\n";


echo "ADMIN_USER_IMPERSONATION_SECURITY_CONTRACT=PASS\n";
echo "RUNTIME_IMPLEMENTATION=NO\n";
echo "DATABASE_MUTATION=NO\n";

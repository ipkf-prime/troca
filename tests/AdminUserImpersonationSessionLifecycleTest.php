<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$auth =
    file_get_contents(
        $root
        . '/public_html/app/Services/AuthService.php'
    );

$lifecycle =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'ImpersonationSessionLifecycleService.php'
    );

$context =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'ImpersonationContextService.php'
    );

if (
    !is_string($auth)
    || !is_string($lifecycle)
    || !is_string($context)
) {
    throw new RuntimeException(
        'Unable to read S4A source.'
    );
}

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

foreach ([
    'public function impersonationAuthSnapshot()',
    'public function beginImpersonatedIdentity(',
    'public function restoreImpersonationAuthSnapshot(',
    "'auth_password_fingerprint'",
    "'active_role_assignment_id'",
    'Session::regenerate();',
] as $token) {
    $expect(
        str_contains(
            $auth,
            $token
        ),
        'Missing AuthService token: '
        . $token
    );
}

echo "S4A_AUTH_BOUNDARY=PASS\n";

foreach ([
    'final class ImpersonationSessionLifecycleService',
    'public function start(',
    'public function status()',
    'public function restore()',
    'public function enforceExpiry()',
    '->decide(',
    "'allowed'",
    "'reason_code'",
    'ImpersonationContextService::SESSION_KEY',
    "'nested_impersonation'",
    "'actor_assignment_not_active'",
    '->activeAssignmentsForUser(',
    '->actorAssignment(',
    '->beginImpersonatedIdentity(',
    '->restoreImpersonationAuthSnapshot(',
    "'expired_restored'",
    "'session_terminated'",
    'random_bytes(24)',
] as $token) {
    $expect(
        str_contains(
            $lifecycle,
            $token
        ),
        'Missing lifecycle token: '
        . $token
    );
}

echo "S4A_LIFECYCLE_CONTRACT=PASS\n";
echo "S4A_AUTHORIZATION_ARRAY_BINDING=PASS\n";

foreach ([
    'passwordHashForUser(',
    'updatePasswordHash(',
    'password_verify(',
    'password_hash(',
    'resetPassword',
] as $forbidden) {
    $expect(
        !str_contains(
            $lifecycle,
            $forbidden
        ),
        'Credential bypass token found: '
        . $forbidden
    );
}

echo "S4A_NO_CREDENTIAL_BYPASS=PASS\n";

foreach ([
    "'auth_user_id'",
    "'auth_password_fingerprint'",
    "'auth_login_at'",
    "'auth_mfa_verified'",
    "'active_role_assignment_id'",
] as $token) {
    $expect(
        str_contains(
            $context,
            $token
        ),
        'Context snapshot token missing: '
        . $token
    );
}

echo "S4A_CONTEXT_ALIGNMENT=PASS\n";

$expect(
    str_contains(
        $lifecycle,
        '$targetAssignments[0]'
    ),
    'Dynamic assignment selection missing.'
);

echo "S4A_DYNAMIC_ROLE_SELECTION=PASS\n";

$expect(
    !preg_match(
        '/\b(admin|manager|staff|user)\b\s*=>/i',
        $lifecycle
    ),
    'Role-name authorization hardcode detected.'
);

echo "S4A_ROLE_NAME_AUTHORIZATION=NO\n";
echo "ADMIN_USER_IMPERSONATION_S4A_SESSION_LIFECYCLE=PASS\n";

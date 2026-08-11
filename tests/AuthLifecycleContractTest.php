<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$auth = $read(
    'public_html/app/Services/AuthService.php'
);
$users = $read(
    'public_html/app/Repositories/UserRepository.php'
);
$mfa = $read(
    'public_html/app/Services/MfaService.php'
);
$sso = $read(
    'public_html/app/Services/ModuleSsoService.php'
);
$history = $read(
    'public_html/app/Services/LoginHistoryService.php'
);
$routes = $read(
    'public_html/routes/web.php'
);

$expect(
    str_contains($users, 'resetLoginFailures')
    && str_contains($users, 'updateLastLogin'),
    'Password-success state and completed-login state are not separated.'
);

$expect(
    !str_contains(
        substr(
            $users,
            strpos($users, 'public function resetLoginFailures'),
            strpos(
                $users,
                'public function updateLastLogin'
            ) - strpos(
                $users,
                'public function resetLoginFailures'
            )
        ),
        'last_login_at'
    ),
    'Password verification still updates last_login_at.'
);

$expect(
    str_contains($auth, 'finalizeLogin')
    && str_contains($auth, 'completePendingMfa')
    && str_contains($auth, 'resetLoginFailures')
    && str_contains($auth, 'updateLastLogin'),
    'Canonical authentication finalization is incomplete.'
);

$expect(
    !str_contains($auth, "'password_mfa'"),
    'New authentication flow still conflates password and MFA method.'
);

$expect(
    str_contains($mfa, "'auth_pending_auth_method'")
    && str_contains($mfa, "'auth_method' => \$authMethod"),
    'MFA pending state does not preserve original authentication method.'
);

$expect(
    substr_count(
        $routes,
        "startPending(\n                \$userId,\n                'token'"
    ) === 2,
    'Token MFA routes do not preserve token provenance.'
);

$expect(
    substr_count(
        $routes,
        "'token',\n        false"
    ) === 2,
    'Token login is not finalized as auth_method=token.'
);

$expect(
    str_contains($sso, "'mfa_verified' => (bool) Session::get")
    && str_contains($sso, 'safe_mfa_verified'),
    'Module SSO does not inherit the Core MFA state.'
);

$expect(
    str_contains($routes, "'sso',")
    && str_contains($routes, "safe_mfa_verified"),
    'Module SSO is not finalized with SSO provenance and inherited MFA state.'
);

$expect(
    !str_contains($routes, 'completeMfaLogin(')
    && !str_contains($routes, '->login($userId)'),
    'Legacy authentication completion call sites remain.'
);

$expect(
    str_contains($history, "'password_mfa'")
    && str_contains($history, "'توکن ورود و MFA'")
    && str_contains($history, "'ورود یکپارچه و MFA'"),
    'Login-history presentation does not preserve legacy records or MFA labels.'
);

echo "Authentication lifecycle contract checks passed.\n";

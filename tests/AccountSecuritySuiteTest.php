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

$mfaRepository = $read(
    'public_html/app/Repositories/MfaRepository.php'
);
$mfaService = $read(
    'public_html/app/Services/MfaService.php'
);
$securityService = $read(
    'public_html/app/Services/AccountSecurityService.php'
);
$routes = $read(
    'public_html/routes/account-security.php'
);
$securityView = $read(
    'public_html/resources/views/admin/security.php'
);
$passwordView = $read(
    'public_html/resources/views/admin/password.php'
);
$nav = $read(
    'public_html/resources/views/admin/partials/account-nav.php'
);
$loader = $read(
    'public_html/system/Routing/RouteLoader.php'
);

$expect(
    str_contains($mfaService, 'beginTotpSetup')
    && str_contains($mfaService, 'confirmPendingTotp')
    && str_contains($mfaService, 'disableTotp'),
    'Complete TOTP lifecycle is missing.'
);

$expect(
    str_contains($mfaRepository, 'saveVerifiedTotp')
    && str_contains($mfaRepository, 'disableTotpForUser')
    && str_contains($mfaRepository, 'revokeRecoveryCodes'),
    'MFA persistence lifecycle is incomplete.'
);

$expect(
    str_contains($securityService, 'verifyPassword')
    && str_contains($securityService, 'invalid_totp')
    && str_contains($securityService, 'regenerateRecoveryCodes'),
    'Sensitive MFA actions are not re-authenticated.'
);

$expect(
    str_contains($securityService, 'strlen($password) < 12')
    && str_contains($securityService, 'passwordClassCount')
    && str_contains($securityService, 'Session::regenerate'),
    'Password policy or session rotation is missing.'
);

$expect(
    str_contains(
        $routes,
        '/admin/security/mfa/totp/start'
    )
    && str_contains(
        $routes,
        '/admin/security/mfa/totp/confirm'
    )
    && str_contains(
        $routes,
        '/admin/security/mfa/totp/disable'
    )
    && str_contains(
        $routes,
        '/admin/security/recovery/regenerate'
    ),
    'Account security routes are incomplete.'
);

$expect(
    str_contains($securityView, 'برنامه Authenticator')
    && str_contains($securityView, 'کدهای بازیابی جدید')
    && str_contains($securityView, 'نشست جاری')
    && str_contains($securityView, 'data-copy-target'),
    'Security UI is incomplete.'
);

$expect(
    str_contains($passwordView, 'رمز عبور')
    && !str_contains($passwordView, 'کلمه عبور')
    && str_contains($passwordView, 'data-password-meter')
    && str_contains($passwordView, 'data-toggle-password'),
    'Password interface was not corrected.'
);

$expect(
    substr_count($nav, "'href' =>") === 5
    && str_contains($nav, 'overflow-x: auto')
    && str_contains($nav, '@media (max-width: 640px)'),
    'Compact responsive account navigation is missing.'
);

$expect(
    str_contains($loader, 'account-security.php'),
    'Account security route overrides are not loaded last.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $mfaRepository . $securityService
    ),
    'Destructive SQL is present.'
);

echo "Account security suite checks passed.\n";

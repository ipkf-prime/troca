<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static fn (string $file): string =>
        (string) file_get_contents(
            $root . '/' . $file
        );

$local =
    $read(
        'public_html/app/Services/'
        . 'AdminLoginReturnPathService.php'
    );

$module =
    $read(
        'public_html/app/Services/'
        . 'ModuleSsoService.php'
    );

$auth =
    $read(
        'public_html/app/Services/'
        . 'AuthService.php'
    );

$expect =
    static function (
        bool $ok,
        string $message
    ): void {
        if (!$ok) {
            throw new RuntimeException(
                $message
            );
        }
    };


foreach ([
    'USER_BOUND_LOGIN_RETURN_V1',
    'OWNER_COOKIE',
    'OWNER_BINDING_VERSION',
    "'owner_binding_version'",
    "'owner_fingerprint'",
    'rememberAuthenticatedOwner(',
    'currentOwnerFingerprint(',
    'ownerFingerprintMatchesUser(',
    'forgetOwnerHint(',
    'ipkf-admin-login-return-owner-v1:',
] as $needle) {
    $expect(
        str_contains(
            $local,
            $needle
        ),
        'local_missing:' . $needle
    );
}

$expect(
    !str_contains(
        $local,
        "'owner_user_id'"
    ),
    'raw_user_id_owner_storage_forbidden'
);


/*
 * Backward-compatible private contract preserved.
 */
$expect(
    str_contains(
        $module,
        'private function pendingReturnPath(): ?string'
    ),
    'legacy_pending_return_signature_missing'
);

$expect(
    str_contains(
        $module,
        'pendingReturnPath()'
    ),
    'legacy_pending_return_call_contract_missing'
);

foreach ([
    'pendingReturnPathForUser(',
    "'owner_binding_version'",
    "'owner_fingerprint'",
    'ownerFingerprintMatchesUser(',
] as $needle) {
    $expect(
        str_contains(
            $module,
            $needle
        ),
        'module_user_binding_missing:'
        . $needle
    );
}

foreach ([
    'USER_BOUND_LOGIN_RETURN_OWNER_V1',
    'rememberAuthenticatedOwner(',
    'forgetOwnerHint(',
] as $needle) {
    $expect(
        str_contains(
            $auth,
            $needle
        ),
        'auth_user_binding_missing:'
        . $needle
    );
}

foreach ([
    'DESTINATION_USER_BOUND_RETURN_V1',
    "'safe_return_owner_state'",
    "'matched'",
    "'mismatch'",
    'currentOwnerFingerprint()',
    'ownerFingerprintMatchesUser(',
    "\$module['route_path']",
] as $needle) {
    $expect(
        str_contains(
            $module,
            $needle
        ),
        'destination_owner_guard_missing:'
        . $needle
    );
}

/*
 * The destination ownership comparison is useful only
 * if SSO consume happens before local authentication
 * overwrites the host-local owner hint.
 */
$web =
    $read(
        'public_html/routes/web.php'
    );

$callback =
    strpos(
        $web,
        "\$router->get('/auth/module-sso/callback'"
    );

$consume =
    strpos(
        $web,
        'ModuleSsoService())->consume',
        $callback !== false
            ? $callback
            : 0
    );

$finalize =
    strpos(
        $web,
        '$auth->finalizeLogin(',
        $callback !== false
            ? $callback
            : 0
    );

$expect(
    $callback !== false
    && $consume !== false
    && $finalize !== false
    && $consume < $finalize,
    'module_callback_must_consume_before_finalize_login'
);

echo
    "ADMIN_LOGIN_RETURN_PATH_USER_BINDING_COMPAT_PASS\n";

echo
    "MODULE_DESTINATION_USER_BOUND_RETURN_PASS\n";

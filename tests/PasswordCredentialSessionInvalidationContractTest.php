<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $path
) use (
    $root
): string {
    $value =
        file_get_contents(
            $root . '/' . $path
        );

    if (!is_string($value)) {
        fwrite(
            STDERR,
            "FAIL_SOURCE_UNREADABLE={$path}\n"
        );

        exit(1);
    }

    return $value;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(
            STDERR,
            "FAIL={$message}\n"
        );

        exit(1);
    }
};

$auth = $read(
    'public_html/app/Services/AuthService.php'
);

$account = $read(
    'public_html/app/Services/AccountSecurityService.php'
);

$user = $read(
    'public_html/app/Repositories/UserRepository.php'
);

$recovery = $read(
    'public_html/app/Services/PasswordRecoveryService.php'
);

$expect(
    str_contains(
        $auth,
        'PASSWORD_CREDENTIAL_SESSION_FINGERPRINT_V1'
    ),
    'auth fingerprint marker missing'
);

$expect(
    substr_count(
        $auth,
        "'auth_password_fingerprint'"
    ) >= 3,
    'auth fingerprint lifecycle incomplete'
);

$expect(
    str_contains(
        $auth,
        'hash_equals('
    ),
    'constant-time fingerprint comparison missing'
);

$expect(
    str_contains(
        $auth,
        "'ipkf-auth-password-fingerprint-v1:'"
    ),
    'auth fingerprint domain missing'
);

$expect(
    str_contains(
        $account,
        'PASSWORD_CHANGE_SESSION_FINGERPRINT_REFRESH_V1'
    ),
    'self-service refresh marker missing'
);

$expect(
    str_contains(
        $account,
        "'ipkf-auth-password-fingerprint-v1:'"
    ),
    'self-service fingerprint domain missing'
);

$expect(
    str_contains(
        $account,
        "'auth_password_fingerprint'"
    ),
    'self-service session fingerprint refresh missing'
);

$expect(
    str_contains(
        $user,
        'PASSWORD_RECOVERY_TRUSTED_DEVICE_REVOCATION_V1'
    ),
    'trusted-device recovery marker missing'
);

$expect(
    str_contains(
        $user,
        "Database::tableExists("
    )
    && str_contains(
        $user,
        "'trusted_devices'"
    )
    && str_contains(
        $user,
        'UPDATE trusted_devices'
    )
    && str_contains(
        $user,
        'revoked_at IS NULL'
    ),
    'bulk trusted-device revocation incomplete'
);

$expect(
    str_contains(
        $user,
        'replacePasswordAfterRecovery'
    )
    && str_contains(
        $user,
        'failed_login_attempts = 0'
    )
    && str_contains(
        $user,
        'locked_until = NULL'
    ),
    'existing recovery finalizer contract changed'
);

$expect(
    str_contains(
        $recovery,
        'replacePasswordAfterRecovery'
    )
    && str_contains(
        $recovery,
        "'password_reset'"
    ),
    'password recovery completion path changed'
);

echo "PASSWORD_CREDENTIAL_SESSION_INVALIDATION_CONTRACT=PASS\n";

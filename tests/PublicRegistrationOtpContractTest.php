<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

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

$files = [
    'registration' =>
        $root
        . '/public_html/app/Services/'
        . 'PublicRegistrationService.php',

    'otp' =>
        $root
        . '/public_html/app/Services/'
        . 'PublicRegistrationOtpService.php',

    'repository' =>
        $root
        . '/public_html/app/Repositories/'
        . 'IdentityVerificationRepository.php',

    'delivery' =>
        $root
        . '/public_html/app/Services/'
        . 'IdentityOtpDeliveryService.php',

    'auth' =>
        $root
        . '/public_html/app/Services/'
        . 'AuthService.php',

    'routes' =>
        $root
        . '/public_html/routes/'
        . 'public-registration.php',

    'view' =>
        $root
        . '/public_html/resources/views/site/'
        . 'register-verify.php',

    'migration' =>
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreatePublicRegistrationOtpFoundation.php',

    'registry' =>
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php',
];

$source = [];

foreach (
    $files as $key => $path
) {
    $expect(
        is_readable($path),
        'A3 required file missing: '
        . $key
    );

    $content =
        file_get_contents(
            $path
        );

    $expect(
        is_string($content),
        'A3 required file unreadable: '
        . $key
    );

    $source[$key] =
        $content;
}

$expect(
    str_contains(
        $source['registration'],
        "'pending_verification'"
    )
    && str_contains(
        $source['registration'],
        'placeholder'
    ),
    'Pending-user credential isolation is missing.'
);

$expect(
    !str_contains(
        $source['registration'],
        'INSERT INTO'
        . "\n"
        . '                        user_role_assignments'
    ),
    'Base role must not be assigned before mobile verification.'
);

$expect(
    str_contains(
        $source['registration'],
        'UserRepository'
    )
    && str_contains(
        $source['registration'],
        'identityValueExists('
    )
    && str_contains(
        $source['otp'],
        'UserRepository'
    )
    && str_contains(
        $source['otp'],
        'identityValueExists('
    ),
    'Registration duplicate identity checks must use the canonical UserRepository authority.'
);

$expect(
    str_contains(
        $source['otp'],
        "'auth.registration.mobile_otp'"
    )
    && str_contains(
        $source['otp'],
        'public_registration:'
    ),
    'Registration OTP dynamic-template/purpose contract is missing.'
);

$expect(
    str_contains(
        $source['otp'],
        'MAX_VERIFY_ATTEMPTS = 5'
    )
    && str_contains(
        $source['otp'],
        'RESEND_COOLDOWN_SECONDS = 60'
    )
    && str_contains(
        $source['otp'],
        'MAX_USER_REQUESTS_10_MIN = 5'
    )
    && str_contains(
        $source['otp'],
        'MAX_IP_REQUESTS_10_MIN = 10'
    ),
    'A3 abuse-control contract is incomplete.'
);

$expect(
    str_contains(
        $source['otp'],
        'password_verify('
    )
    && str_contains(
        $source['otp'],
        'verification_attempts'
    )
    && str_contains(
        $source['otp'],
        "mobile_verified_at ="
    ),
    'OTP verification/activation contract is incomplete.'
);

$expect(
    str_contains(
        $source['otp'],
        "status = 'active'"
    )
    && str_contains(
        $source['otp'],
        'ensureExactlyBaseRole'
    ),
    'Fail-safe activation contract is missing.'
);

$expect(
    str_contains(
        $source['otp'],
        "password_hash = ''"
    ),
    'Consumed/superseded registration password hashes must be scrubbed.'
);

$expect(
    str_contains(
        $source['repository'],
        'recentChallengeCountByPurposePrefix'
    )
    && str_contains(
        $source['repository'],
        'recentChallengeCountByIp'
    )
    && str_contains(
        $source['repository'],
        'latestChallengeRecord'
    ),
    'Shared OTP repository rate/cooldown support is missing.'
);

$expect(
    str_contains(
        $source['delivery'],
        'auth.registration.mobile_otp'
    )
    || str_contains(
        $source['otp'],
        "'auth.registration.mobile_otp'"
    ),
    'Registration outbound content must use the dynamic template.'
);

foreach (
    [
        '/register',
        '/register/verify',
        '/register/verify/resend',
    ] as $route
) {
    $expect(
        str_contains(
            $source['routes'],
            $route
        ),
        'A3 public route missing: '
        . $route
    );
}

$expect(
    str_contains(
        $source['routes'],
        'public_registration_attempt_token'
    )
    && str_contains(
        $source['routes'],
        'public_registration_attempt_id'
    ),
    'Server-side registration-attempt session binding is missing.'
);

$expect(
    !str_contains(
        $source['routes'],
        'finalizeLogin('
    ),
    'Registration verification must not auto-login the user.'
);

$expect(
    str_contains(
        $source['view'],
        'autocomplete="one-time-code"'
    )
    && str_contains(
        $source['view'],
        'ارسال دوباره کد'
    )
    && str_contains(
        $source['view'],
        'شروع دوباره ثبت‌نام'
    ),
    'OTP public UI contract is incomplete.'
);

$expect(
    str_contains(
        $source['view'],
        'PUBLIC_SMS_WINDOW_STATUS_V1'
    )
    && str_contains(
        $source['view'],
        "'sms_window_closed'"
    )
    && str_contains(
        $source['view'],
        'SmsDeliveryPolicyService'
    )
    && str_contains(
        $source['view'],
        'start_time'
    )
    && str_contains(
        $source['view'],
        'end_time'
    )
    && str_contains(
        $source['view'],
        'ارسال پیامک فقط از ساعت'
    ),
    'Public SMS delivery-window message contract is incomplete.'
);

$expect(
    str_contains(
        $source['migration'],
        'public_registration_attempts'
    )
    && str_contains(
        $source['migration'],
        'ENGINE=InnoDB'
    )
    && str_contains(
        $source['migration'],
        'nonce_hash'
    ),
    'Registration-attempt metadata foundation is missing.'
);

$expect(
    str_contains(
        $source['migration'],
        'mfa_delivery_challenges_registration_rate_index'
    )
    && str_contains(
        $source['migration'],
        'mfa_delivery_challenges_ip_rate_index'
    ),
    'OTP challenge rate indexes are missing.'
);

$expect(
    str_contains(
        $source['registry'],
        'CreatePublicRegistrationOtpFoundation::class'
    ),
    'A3 migration is not registered.'
);

$expect(
    str_contains(
        $source['auth'],
        "!== 'active'"
    ),
    'Authentication must continue rejecting pending users.'
);


/*
 * PUBLIC_REGISTRATION_VERIFY_UX_A3_2A
 */
$expect(
    str_contains(
        $source['view'],
        '$successStatuses = ['
    )
    && str_contains(
        $source['view'],
        "'sent',"
    )
    && str_contains(
        $source['view'],
        "'resend_sent',"
    )
    && str_contains(
        $source['view'],
        'admin-alert admin-alert--success'
    )
    && str_contains(
        $source['view'],
        'admin-alert admin-alert--danger'
    ),
    'Registration verify status alert semantics are incomplete.'
);

$expect(
    str_contains(
        $source['view'],
        'data-public-registration-sms-hint'
    )
    && str_contains(
        $source['view'],
        'پیامک‌های تبلیغاتی'
    ),
    'Registration SMS promotional-blocking guidance is missing.'
);


/*
 * PUBLIC_REGISTRATION_BALE_MOBILE_ATTESTATION_A3_2B1_V2
 */
$expect(
    str_contains(
        $source['otp'],
        'public function baleEnrollment('
    )
    && str_contains(
        $source['otp'],
        'public function confirmBaleAttestation('
    )
    && str_contains(
        $source['otp'],
        'membershipAuthBaleProviders'
    )
    && str_contains(
        $source['otp'],
        'createEnrollment('
    ),
    'Public Bale self-enrollment contract is incomplete.'
);

$expect(
    str_contains(
        $source['otp'],
        'notification_messenger_enrollments'
    )
    && str_contains(
        $source['otp'],
        'notification_messenger_bindings'
    )
    && str_contains(
        $source['otp'],
        'enrollments.invited_by_user_id = ?'
    )
    && str_contains(
        $source['otp'],
        'enrollments.created_at >= ?'
    )
    && str_contains(
        $source['otp'],
        "enrollments.status_code ="
    )
    && str_contains(
        $source['otp'],
        "'verified'"
    )
    && str_contains(
        $source['otp'],
        "bindings.status_code ="
    )
    && str_contains(
        $source['otp'],
        "'active'"
    )
    && str_contains(
        $source['otp'],
        'bindings.revoked_at'
    ),
    'Current-attempt Bale enrollment/binding proof is incomplete.'
);

$expect(
    str_contains(
        $source['otp'],
        'if ($challengeId > 0)'
    )
    && str_contains(
        $source['otp'],
        '$this->activate('
    )
    && str_contains(
        $source['otp'],
        "mobile_verified_at ="
    )
    && str_contains(
        $source['otp'],
        "status = 'active'"
    )
    && str_contains(
        $source['otp'],
        'ensureExactlyBaseRole'
    )
    && str_contains(
        $source['otp'],
        'activationProof('
    ),
    'Bale verification must reuse the standard activation path.'
);

$expect(
    str_contains(
        $source['routes'],
        "'/register/verify/bale'"
    )
    && str_contains(
        $source['routes'],
        "'/register/verify/bale/confirm'"
    )
    && str_contains(
        $source['routes'],
        'baleEnrollment('
    )
    && str_contains(
        $source['routes'],
        'confirmBaleAttestation('
    )
    && str_contains(
        $source['routes'],
        '$registrationCsrfValid'
    )
    && str_contains(
        $source['routes'],
        "'public_registration_success'"
    ),
    'Public Bale POST route contract is incomplete.'
);

$expect(
    str_contains(
        $source['view'],
        'data-public-registration-bale-verify'
    )
    && str_contains(
        $source['view'],
        'data-public-registration-bale-confirm'
    )
    && str_contains(
        $source['view'],
        'action="/register/verify/bale"'
    )
    && str_contains(
        $source['view'],
        'action="/register/verify/bale/confirm"'
    )
    && str_contains(
        $source['view'],
        'target="_blank"'
    )
    && str_contains(
        $source['view'],
        'تأیید شماره از طریق بله'
    )
    && str_contains(
        $source['view'],
        'اشتراک شماره همراه من'
    )
    && str_contains(
        $source['view'],
        'بررسی وضعیت تأیید بله'
    ),
    'Public Bale mobile-verification UX is incomplete.'
);

$expect(
    !str_contains(
        $source['otp'],
        'auth.registration.email_otp'
    )
    && !str_contains(
        $source['routes'],
        'auth.registration.email_otp'
    ),
    'Email must not verify registration mobile ownership.'
);

$expect(
    str_contains(
        $source['otp'],
        "'auth.registration.mobile_otp'"
    )
    && str_contains(
        $source['view'],
        'autocomplete="one-time-code"'
    )
    && str_contains(
        $source['view'],
        'ارسال دوباره کد'
    ),
    'Primary SMS OTP registration path must remain intact.'
);

echo
    "PUBLIC_REGISTRATION_OTP_CONTRACT=PASS\n";

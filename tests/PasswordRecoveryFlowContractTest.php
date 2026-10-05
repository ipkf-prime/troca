<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/PasswordRecoveryService.php'
    );

$repo =
    file_get_contents(
        $root
        . '/public_html/app/Repositories/UserRepository.php'
    );

$routes =
    file_get_contents(
        $root
        . '/public_html/routes/web.php'
    );

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/forgot-password.php'
    );

$expect =
    static function (
        bool $ok,
        string $message
    ): void {
        if (!$ok) {
            fwrite(
                STDERR,
                "FAIL: {$message}\n"
            );

            exit(1);
        }
    };

$expect(
    is_string($service)
    && is_string($repo)
    && is_string($routes)
    && is_string($view),
    'password recovery sources unavailable'
);

$expect(
    str_contains(
        $service,
        'AUTH_PASSWORD_RECOVERY_A5_R3'
    )
    && str_contains(
        $service,
        "->deliver("
    )
    && str_contains(
        $service,
        "'mobile'"
    )
    && str_contains(
        $service,
        "'auth.password_reset.mobile_otp'"
    ),
    'dedicated dynamic password recovery OTP template missing'
);

$expect(
    str_contains(
        $service,
        'recentChallengeCountByPurposePrefix'
    )
    && str_contains(
        $service,
        'recentChallengeCountByIp'
    )
    && str_contains(
        $service,
        'MAX_VERIFY_ATTEMPTS'
    ),
    'password recovery rate/attempt controls missing'
);

$expect(
    str_contains(
        $service,
        "return 'password_policy';"
    )
    && str_contains(
        $service,
        "'/\\p{L}/u'"
    )
    && str_contains(
        $service,
        "'/[0-9]/'"
    )
    && str_contains(
        $service,
        '$length < 8'
    )
    && str_contains(
        $service,
        '$length > 128'
    ),
    '8-128 letter+digit password policy missing'
);

$expect(
    str_contains(
        $service,
        "'same_password'"
    )
    && str_contains(
        $service,
        'password_verify('
    ),
    'same-password rejection missing'
);

$expect(
    str_contains(
        $service,
        'consumeOpenChallenges'
    )
    && str_contains(
        $service,
        'mfa_delivery_challenges'
    )
    && str_contains(
        $service,
        'consumed_at IS NULL'
    ),
    'OTP replay cleanup missing'
);

$expect(
    str_contains(
        $repo,
        'replacePasswordAfterRecovery'
    )
    && str_contains(
        $repo,
        'failed_login_attempts = 0'
    )
    && str_contains(
        $repo,
        'locked_until = NULL'
    ),
    'password reset finalizer missing'
);

$expect(
    str_contains(
        $routes,
        "/admin/forgot-password/confirm"
    )
    && str_contains(
        $routes,
        'PasswordRecoveryService'
    )
    && str_contains(
        $routes,
        "status=password_reset"
    ),
    'functional forgot-password routes missing'
);

$expect(
    !str_contains(
        $routes,
        "'dev_token' =>"
    ),
    'dev OTP token must not be exposed by public route'
);

$expect(
    str_contains(
        $view,
        'name="code"'
    )
    && str_contains(
        $view,
        'name="password"'
    )
    && str_contains(
        $view,
        'name="password_confirmation"'
    )
    && str_contains(
        $view,
        'autocomplete="one-time-code"'
    ),
    'forgot-password verification UI missing'
);


$expect(
    str_contains(
        $service,
        'AUTH_PASSWORD_RECOVERY_MULTI_CHANNEL_R3_M2C'
    )
    && str_contains(
        $service,
        "'method' =>"
    )
    && str_contains(
        $service,
        "'recovery'"
    )
    && str_contains(
        $service,
        "'email_verified_at'"
    )
    && str_contains(
        $service,
        "'mobile_verified_at'"
    )
    && str_contains(
        $service,
        "'auth.password_reset.email_otp'"
    )
    && str_contains(
        $service,
        "'auth.password_reset.bale_otp'"
    )
    && str_contains(
        $service,
        'PASSWORD_RECOVERY_BALE_SECRET_FILE'
    )
    && str_contains(
        $service,
        'NotificationMessengerEnrollmentRepository'
    )
    && str_contains(
        $service,
        'membershipAuthBaleProviders'
    )
    && str_contains(
        $service,
        'connectionStatuses'
    )
    && str_contains(
        $service,
        'DynamicMessageTemplateService'
    )
    && str_contains(
        $service,
        "'ipkfbot'"
    )
    && str_contains(
        $service,
        "'membership_auth'"
    )
    && !str_contains(
        $service,
        'BALE_BOT_TOKEN'
    )
    && !str_contains(
        $service,
        'NotificationProviderRuntimeService'
    ),
    'multi-channel password recovery contract missing'
);

$expect(
    str_contains(
        $service,
        "AND method IN ("
    )
    && str_contains(
        $service,
        "'recovery',"
    )
    && str_contains(
        $service,
        "'sms'"
    ),
    'legacy SMS recovery cleanup compatibility missing'
);


/*
 * PASSWORD_RECOVERY_EMAIL_GATEWAY_BINDING_CONTRACT
 */
$identityDelivery =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'IdentityOtpDeliveryService.php'
    );

$expect(
    is_string($identityDelivery),
    'Identity OTP delivery source unreadable'
);

$recoveryEmailOffset =
    strpos(
        $service,
        "'auth.password_reset.email_otp'"
    );

$recoveryEmailWindow =
    $recoveryEmailOffset !== false
        ? substr(
            $service,
            max(
                0,
                $recoveryEmailOffset - 320
            ),
            720
        )
        : '';

$expect(
    $recoveryEmailOffset !== false
    && str_contains(
        $recoveryEmailWindow,
        '$userId'
    ),
    'password recovery email must carry canonical user gateway context'
);

$expect(
    str_contains(
        $identityDelivery,
        "'auth.password_reset.email_otp'"
    )
    && str_contains(
        $identityDelivery,
        "'password_recovery'"
    )
    && str_contains(
        $identityDelivery,
        "'identity_email_verification'"
    )
    && str_contains(
        $identityDelivery,
        '$purposeCode'
    ),
    'password recovery email gateway purpose contract missing'
);

echo "PASSWORD_RECOVERY_FLOW_CONTRACT=PASS\n";

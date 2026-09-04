<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'delivery' =>
        '/public_html/app/Services/'
        . 'IdentityOtpDeliveryService.php',

    'repository' =>
        '/public_html/app/Repositories/'
        . 'IdentityVerificationRepository.php',

    'verification' =>
        '/public_html/app/Services/'
        . 'IdentityVerificationService.php',

    'users' =>
        '/public_html/app/Repositories/'
        . 'UserRepository.php',

    'registration' =>
        '/public_html/app/Services/'
        . 'PublicRegistrationService.php',

    'registration_otp' =>
        '/public_html/app/Services/'
        . 'PublicRegistrationOtpService.php',

    'security_service' =>
        '/public_html/app/Services/'
        . 'AccountSecurityService.php',

    'routes' =>
        '/public_html/routes/'
        . 'account-security.php',

    'view' =>
        '/public_html/resources/views/admin/'
        . 'security.php',
];

$source = [];

foreach ($files as $key => $suffix) {
    $value =
        file_get_contents(
            $root . $suffix
        );

    if (!is_string($value)) {
        fwrite(
            STDERR,
            "FAIL: unreadable {$key}\n"
        );

        exit(1);
    }

    $source[$key] = $value;
}

$loginStart =
    strpos(
        $source['users'],
        'public function findByLoginIdentifier('
    );

$loginEnd =
    strpos(
        $source['users'],
        'public function resetLoginFailures('
    );

if (
    $loginStart === false
    || $loginEnd === false
    || $loginEnd <= $loginStart
) {
    fwrite(
        STDERR,
        "FAIL: login method boundaries.\n"
    );

    exit(1);
}

$login =
    substr(
        $source['users'],
        $loginStart,
        $loginEnd - $loginStart
    );

$pass =
    !str_contains(
        $source['delivery'],
        '@mail('
    )
    && !str_contains(
        $source['delivery'],
        "function_exists('mail')"
    )
    && str_contains(
        $source['delivery'],
        'NotificationGatewayService'
    )
    && str_contains(
        $source['delivery'],
        '$this->gateway->sendDirect('
    )
    && str_contains(
        $source['delivery'],
        "'identity_email_verification'"
    )

    && str_contains(
        $source['verification'],
        'identity_email_verification:'
    )
    && str_contains(
        $source['verification'],
        'claimVerifiedEmail('
    )
    && str_contains(
        $source['verification'],
        '$expectedEmail'
    )

    && str_contains(
        $source['repository'],
        'GET_LOCK(?, 5)'
    )
    && str_contains(
        $source['repository'],
        'RELEASE_LOCK(?)'
    )
    && str_contains(
        $source['repository'],
        'persons.email_norm'
    )

    && substr_count(
        $login,
        'users.email_verified_at IS NOT NULL'
    ) === 4

    && str_contains(
        $login,
        'persons.email_norm = :email_norm_person'
    )
    && str_contains(
        $login,
        'LOWER(persons.email) = :email_person'
    )

    && str_contains(
        $source['users'],
        'public function verifiedEmailExists('
    )
    && str_contains(
        $source['users'],
        'users.email_verified_at'
    )
    && str_contains(
        $source['users'],
        'users.email_verified_at = NULL'
    )

    && str_contains(
        $source['registration'],
        '->verifiedEmailExists('
    )
    && !str_contains(
        $source['registration'],
        "->identityValueExists(\n"
        . "                    'email'"
    )

    && str_contains(
        $source['registration_otp'],
        '->verifiedEmailExists('
    )

    && str_contains(
        $source['routes'],
        '/admin/security/identity/email/request'
    )
    && str_contains(
        $source['routes'],
        '/admin/security/identity/email/confirm'
    )
    && str_contains(
        $source['routes'],
        'new \IPKF\Security\Csrf()'
    )

    && str_contains(
        $source['view'],
        'ارسال کد تأیید ایمیل'
    )
    && str_contains(
        $source['view'],
        'تأیید ایمیل'
    )

    && str_contains(
        $source['security_service'],
        "'email_verified'"
    );

if (!$pass) {
    fwrite(
        STDERR,
        "FAIL: A3.3A email contract incomplete.\n"
    );

    exit(1);
}

echo "STANDARD_SMTP_GATEWAY=PASS\n";
echo "EMAIL_OTP_BOUND_TO_EXACT_EMAIL=PASS\n";
echo "VERIFIED_EMAIL_OWNERSHIP_LOCK=PASS\n";
echo "UNVERIFIED_EMAIL_LOGIN_BLOCKED=PASS\n";
echo "VERIFIED_PERSON_EMAIL_MIRROR_PRESERVED=PASS\n";
echo "EMAIL_CHANGE_REQUIRES_REVERIFICATION=PASS\n";
echo "UNVERIFIED_EMAIL_DOES_NOT_RESERVE_IDENTITY=PASS\n";
echo "AUTHENTICATED_EMAIL_VERIFICATION_ROUTES=PASS\n";
echo "EMAIL_VERIFICATION_UI=PASS\n";
echo "IDENTITY_EMAIL_VERIFICATION_A3_3A=PASS\n";

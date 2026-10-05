<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $path
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $path
    );

    if (!is_string($content)) {
        throw new RuntimeException(
            'Cannot read ' . $path
        );
    }

    return $content;
};

$must = static function (
    bool $ok,
    string $message
): void {
    if (!$ok) {
        throw new RuntimeException(
            $message
        );
    }
};

$otp = $read(
    'public_html/app/Services/'
    . 'PublicRegistrationOtpService.php'
);

$bale = $read(
    'public_html/app/Services/'
    . 'NotificationBaleEnrollmentService.php'
);

$route = $read(
    'public_html/routes/'
    . 'public-registration.php'
);

$security = $read(
    'public_html/app/Services/'
    . 'AccountSecurityService.php'
);

$verifyView = $read(
    'public_html/resources/views/site/'
    . 'register-verify.php'
);

$auth = $read(
    'public_html/app/Services/'
    . 'AuthService.php'
);

$must(
    str_contains(
        $otp,
        'baleOneClickReturnLink('
    )
    && str_contains(
        $otp,
        'consumeBaleOneClickReturn('
    )
    && str_contains(
        $otp,
        "'registration-bale-return-v1'"
    )
    && str_contains(
        $otp,
        'hash_hmac('
    )
    && str_contains(
        $otp,
        'hash_equals('
    ),
    'Signed Bale return contract missing.'
);

$must(
    str_contains(
        $otp,
        'enrollments.invited_by_user_id ='
    )
    && str_contains(
        $otp,
        'enrollments.user_id'
    )
    && str_contains(
        $otp,
        'users.status ='
    )
    && str_contains(
        $otp,
        "'pending_verification'"
    )
    && str_contains(
        $otp,
        'bindings.status_code ='
    ),
    'Bale return server-side proof is incomplete.'
);

$must(
    str_contains(
        $otp,
        'time() + 600'
    )
    && str_contains(
        $otp,
        '$expires > time() + 900'
    ),
    'Bale return expiry bounds are missing.'
);

$must(
    str_contains(
        $route,
        "'/register/verify/bale/return'"
    )
    && str_contains(
        $route,
        'consumeBaleOneClickReturn('
    )
    && str_contains(
        $route,
        '->finalizeLogin('
    )
    && str_contains(
        $route,
        "'token'"
    )
    && str_contains(
        $route,
        "'/admin/dashboard'"
    ),
    'One-click return/login route is incomplete.'
);

$must(
    str_contains(
        $bale,
        'baleOneClickReturnLink('
    )
    && str_contains(
        $bale,
        "'inline_keyboard'"
    )
    && str_contains(
        $bale,
        "'url' =>"
    )
    && str_contains(
        $bale,
        'بازگشت به سامانه و ورود'
    ),
    'Bale verified message does not contain return button.'
);

$must(
    str_contains(
        $verifyView,
        'data-public-registration-bale-verify'
    )
    && str_contains(
        $verifyView,
        'action="/register/verify/bale"'
    )
    && !str_contains(
        $verifyView,
        'data-public-registration-bale-confirm'
    )
    && !str_contains(
        $verifyView,
        'action="/register/verify/bale/confirm"'
    )
    && !str_contains(
        $verifyView,
        'بررسی وضعیت تأیید بله'
    ),
    'Legacy manual Bale confirmation UI is still exposed.'
);

echo "BALE_MANUAL_RETURN_CONFIRM_UI=REMOVED\n";

$must(
    str_contains(
        $security,
        '$passwordLength < 8'
    )
    && str_contains(
        $security,
        '$passwordLength > 128'
    )
    && str_contains(
        $security,
        'p{L}'
    )
    && str_contains(
        $security,
        'p{N}'
    )
    && substr_count(
        $security,
        'preg_match('
    ) >= 2
    && !str_contains(
        $security,
        'passwordClassCount($password) < 3'
    )
    && str_contains(
        $security,
        'Session::regenerate();'
    ),
    'Password policy is not aligned.'
);

$must(
    str_contains(
        $auth,
        'Session::regenerate();'
    )
    && str_contains(
        $auth,
        "Session::put('auth_user_id'"
    )
    && str_contains(
        $auth,
        'canAuthenticate($user)'
    ),
    'Final login session contract missing.'
);

echo "BALE_ONE_CLICK_SIGNED_RETURN=PASS\n";
echo "BALE_VERIFIED_ENROLLMENT_BINDING=PASS\n";
echo "BALE_RETURN_EXPIRES_BOUNDED=PASS\n";
echo "BALE_RETURN_FINALIZES_CORE_LOGIN=PASS\n";
echo "PASSWORD_POLICY_8_LETTER_NUMBER=PASS\n";
echo "CAPTCHA_FORCED=NO\n";
echo "PUBLIC_REGISTRATION_BALE_ONE_CLICK_RETURN_CONTRACT=PASS\n";

/*
 * PUBLIC_REGISTRATION_BALE_RETURN_SECRET_V2
 *
 * Magic-login signing must use a dedicated Core secret and must
 * not depend on the messenger provider bot token.
 */
$otpSource = file_get_contents(
    dirname(__DIR__)
    . '/public_html/app/Services/PublicRegistrationOtpService.php'
);

if (!is_string($otpSource)) {
    fwrite(STDERR, "FAIL: cannot read PublicRegistrationOtpService.php\n");
    exit(1);
}

$methodStart = strpos(
    $otpSource,
    'private function baleReturnSecret('
);

$methodEnd = $methodStart === false
    ? false
    : strpos(
        $otpSource,
        'private function baleReturnSignature(',
        $methodStart
    );

if (
    $methodStart === false
    || $methodEnd === false
) {
    fwrite(STDERR, "FAIL: baleReturnSecret method boundaries missing\n");
    exit(1);
}

$methodBlock = substr(
    $otpSource,
    $methodStart,
    $methodEnd - $methodStart
);

if (
    !str_contains(
        $methodBlock,
        'PUBLIC_REGISTRATION_BALE_RETURN_SECRET'
    )
    || str_contains(
        $methodBlock,
        "['bot_token']"
    )
    || str_contains(
        $methodBlock,
        '->secrets('
    )
) {
    fwrite(
        STDERR,
        "FAIL: one-click return secret remains coupled to Bale provider secret\n"
    );
    exit(1);
}

echo "BALE_ONE_CLICK_SECRET_DECOUPLED=PASS\n";

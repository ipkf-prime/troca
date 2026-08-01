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

$jalali = $read(
    'public_html/app/Support/JalaliDateInput.php'
);
$repository = $read(
    'public_html/app/Repositories/AdminUserManagementRepository.php'
);
$service = $read(
    'public_html/app/Services/AdminUserManagementService.php'
);
$form = $read(
    'public_html/resources/views/admin/admin-user-form.php'
);
$selfService = $read(
    'public_html/app/Services/SelfProfileService.php'
);
$verification = $read(
    'public_html/app/Services/IdentityVerificationService.php'
);
$identityChange = $read(
    'public_html/app/Services/SelfIdentityChangeService.php'
);
$account = $read(
    'public_html/resources/views/admin/account.php'
);
$routes = $read(
    'public_html/routes/user-profile-hotfix.php'
);
$loader = $read(
    'public_html/system/Routing/RouteLoader.php'
);

$expect(
    str_contains($jalali, 'toGregorian')
    && str_contains($jalali, 'fromGregorian'),
    'Jalali conversion is incomplete.'
);

$expect(
    str_contains($service, 'birth_date_jalali')
    && str_contains($form, 'تاریخ تولد شمسی'),
    'Jalali birth-date input is not wired.'
);

$expect(
    str_contains($repository, 'nationalCodeExists')
    && str_contains(
        $service,
        'این کد ملی قبلاً ثبت شده است.'
    )
    && str_contains(
        $selfService,
        'این کد ملی قبلاً ثبت شده است.'
    ),
    'National-code duplicate protection is incomplete.'
);

$expect(
    str_contains($repository, 'updateOwnProfile')
    && str_contains($routes, '/admin/profile/edit'),
    'Self-service identity editing is incomplete.'
);

$expect(
    str_contains(
        $repository,
        "(string) (\$data['address_line'] ?? '')"
    ),
    'Address NOT NULL persistence fix is missing.'
);

$expect(
    str_contains($service, 'identityChanged')
    && str_contains($service, 'email_verified')
    && str_contains($verification, 'markVerified'),
    'Verification reset/confirm lifecycle is incomplete.'
);

$expect(
    str_contains($identityChange, 'IdentityOtpDeliveryService')
    && str_contains($account, 'ارسال کد تغییر')
    && str_contains($account, 'کد OTP'),
    'OTP identity-change flow is incomplete.'
);

$expect(
    str_contains($form, "sortRoles('code')")
    && str_contains($repository, 'roles.code ASC'),
    'Default role-code sorting is missing.'
);

$expect(
    str_contains($loader, 'user-profile-hotfix.php'),
    'Hotfix routes are not loaded.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $repository
        . $service
        . $verification
        . $identityChange
    ),
    'Destructive SQL is present.'
);

echo "User management v0.5.2 hotfix checks passed.\n";

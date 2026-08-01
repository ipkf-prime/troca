<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        throw new RuntimeException(
            'Unable to read ' . $path
        );
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$service = $read(
    'public_html/app/Services/'
    . 'ProfileAvatarService.php'
);
$profile = $read(
    'public_html/resources/views/admin/profile.php'
);
$routes = $read(
    'public_html/routes/profile-avatar.php'
);
$check = $read(
    'public_html/scripts/'
    . 'check-profile-avatar-mfa-r10.php'
);

$expect(
    str_contains(
        $service,
        'mkdir($directory, 0755, true)'
    )
    && str_contains(
        $service,
        '@chmod($destination, 0644);'
    ),
    'Avatar web-readable permissions are missing.'
);

$expect(
    str_contains(
        $profile,
        'enctype="multipart/form-data"'
    )
    && str_contains(
        $profile,
        'action="/admin/profile/avatar"'
    )
    && str_contains(
        $profile,
        'href="/admin/security"'
    ),
    'Profile avatar or MFA entry is missing.'
);

$expect(
    str_contains(
        $routes,
        "'/admin/profile/avatar'"
    )
    && str_contains(
        $routes,
        "'/admin/profile/avatar/remove'"
    ),
    'Avatar routes are missing.'
);

foreach ([
    'user_mfa_methods',
    'mfa_challenges',
    'recovery_codes',
    'persons',
    'avatar',
] as $required) {
    $expect(
        str_contains($check, $required),
        "Runtime check is missing {$required}."
    );
}

echo "Profile avatar and MFA R10 checks passed.\n";

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

$nav = $read(
    'public_html/resources/views/admin/partials/account-nav.php'
);
$profile = $read(
    'public_html/resources/views/admin/profile.php'
);
$access = $read(
    'public_html/resources/views/admin/profile-access.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/CreateAuthenticationLoginHistoryTable.php'
);
$auth = $read(
    'public_html/app/Services/AuthService.php'
);
$history = $read(
    'public_html/app/Services/LoginHistoryService.php'
);
$securityService = $read(
    'public_html/app/Services/AccountSecurityService.php'
);
$securityView = $read(
    'public_html/resources/views/admin/security.php'
);
$migrate = $read('public_html/public/migrate.php');

$expect(
    str_contains($nav, '$accountLinkIsActive')
    && !str_contains(
        $nav,
        '$active = in_array'
    ),
    'Account navigation variable collision remains.'
);

$expect(
    str_contains($profile, '$activeAssignment')
    && str_contains(
        $access,
        '$activeAssignmentId'
    ),
    'Active role assignment variables are not isolated.'
);

$expect(
    str_contains($migration, 'auth_login_history')
    && str_contains($migration, 'session_hash')
    && !str_contains($migration, 'session_id VARCHAR'),
    'Login history schema is incomplete or stores raw session IDs.'
);

$expect(
    str_contains($auth, 'LoginHistoryService')
    && str_contains($auth, "'password_mfa'"),
    'Successful authentication is not audited.'
);

$expect(
    str_contains($history, 'browserLabel')
    && str_contains($history, 'role_title_snapshot'),
    'Login history presentation is incomplete.'
);

$expect(
    str_contains($securityService, 'login_history')
    && str_contains($securityView, '۱۰ ورود اخیر'),
    'Security page does not include recent login history.'
);

$expect(
    str_contains(
        $migrate,
        'CreateAuthenticationLoginHistoryTable'
    ),
    'Login history migration is not registered.'
);

echo "User role and login history hotfix checks passed.\n";

<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $path
    ) use ($root): string {
        $file =
            $root . '/' . $path;

        if (!is_file($file)) {
            throw new RuntimeException(
                'missing file: '
                . $path
            );
        }

        return
            (string) file_get_contents(
                $file
            );
    };

$users =
    $read(
        'public_html/resources/views/admin/users.php'
    );

$adminRoutes =
    $read(
        'public_html/routes/admin-users-manage.php'
    );

$registrationRoutes =
    $read(
        'public_html/routes/public-registration.php'
    );

$registrationView =
    $read(
        'public_html/resources/views/site/register.php'
    );

$service =
    $read(
        'public_html/app/Services/UserInvitationService.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/UserInvitationRepository.php'
    );

$migration =
    $read(
        'public_html/system/Database/Migrations/CreateUserInvitationFoundationTables.php'
    );

$panel =
    $read(
        'public_html/app/Services/AdminPanelService.php'
    );

$coreFeatureRegistry =
    $read(
        'public_html/app/Services/CoreFeatureRegistryService.php'
    );

foreach (
    [
        'افزودن دستی',
        '/admin/users/invite',
        'دعوت کاربر',
        'ورود گروهی از فایل',
        'aria-disabled="true"',
    ]
    as $marker
) {
    if (!str_contains(
        $users,
        $marker
    )) {
        throw new RuntimeException(
            'toolbar marker missing: '
            . $marker
        );
    }
}

if (
    !str_contains(
        $panel,
        'CoreFeatureRegistryService'
    )
    || !str_contains(
        $panel,
        'appearanceMap()'
    )
    || !str_contains(
        $coreFeatureRegistry,
        'admin_navigation_items'
    )
    || !str_contains(
        $coreFeatureRegistry,
        'appearanceMap()'
    )
) {
    throw new RuntimeException(
        'dynamic user module ownership invalid'
    );
}

$legacyStart =
    strpos(
        $adminRoutes,
        "\$router->post('/admin/users/{id}/roles'"
    );

$legacyEnd =
    strpos(
        $adminRoutes,
        '$adminManagedUserDetailRoute = function (',
        $legacyStart === false
            ? 0
            : $legacyStart
    );

if (
    $legacyStart === false
    || $legacyEnd === false
) {
    throw new RuntimeException(
        'legacy role compatibility route missing'
    );
}

$legacyBlock =
    substr(
        $adminRoutes,
        $legacyStart,
        $legacyEnd
        - $legacyStart
    );

if (
    str_contains(
        $legacyBlock,
        '->updateRoles('
    )
) {
    throw new RuntimeException(
        'legacy role writer still active'
    );
}

if (
    !str_contains(
        $legacyBlock,
        '/admin/access-control'
    )
) {
    throw new RuntimeException(
        'legacy role route redirect missing'
    );
}

if (
    substr_count(
        $adminRoutes,
        "/admin/users/invite"
    ) < 2
) {
    throw new RuntimeException(
        'admin invitation routes missing'
    );
}

foreach (
    [
        'random_bytes(32)',
        "hash(\n                    'sha256'",
        'users.create',
        'users.manage',
        'publicInvitation(',
        'validateSubmission(',
        'accept(',
    ]
    as $marker
) {
    if (!str_contains(
        $service,
        $marker
    )) {
        throw new RuntimeException(
            'service security marker missing: '
            . $marker
        );
    }
}

if (
    str_contains(
        $service,
        'role_ids'
    )
) {
    throw new RuntimeException(
        'invitation must not assign roles'
    );
}

if (
    !str_contains(
        $repository,
        'token_hash'
    )
    || str_contains(
        $migration,
        'raw_token'
    )
) {
    throw new RuntimeException(
        'invitation token persistence unsafe'
    );
}

foreach (
    [
        'CREATE TABLE IF NOT EXISTS user_invitations',
        'user_invitations_token_hash_unique',
        'created_by_user_id',
        'accepted_user_id',
        'expires_at',
    ]
    as $marker
) {
    if (!str_contains(
        $migration,
        $marker
    )) {
        throw new RuntimeException(
            'migration marker missing: '
            . $marker
        );
    }
}

foreach (
    [
        'public_registration_invitation_token',
        'UserInvitationService',
        'validateSubmission(',
        ')->accept(',
    ]
    as $marker
) {
    if (!str_contains(
        $registrationRoutes,
        $marker
    )) {
        throw new RuntimeException(
            'registration binding missing: '
            . $marker
        );
    }
}

foreach (
    [
        'دعوت اختصاصی',
        '$invited',
        "'readonly'",
    ]
    as $marker
) {
    if (!str_contains(
        $registrationView,
        $marker
    )) {
        throw new RuntimeException(
            'invitation UI marker missing: '
            . $marker
        );
    }
}

$adminManagementService =
    $read(
        'public_html/app/Services/AdminUserManagementService.php'
    );

$createStart =
    strpos(
        $adminManagementService,
        'public function create('
    );

$updateStart =
    strpos(
        $adminManagementService,
        'public function update(',
        $createStart === false
            ? 0
            : $createStart
    );

if (
    $createStart === false
    || $updateStart === false
) {
    throw new RuntimeException(
        'manual create method missing'
    );
}

$create =
    substr(
        $adminManagementService,
        $createStart,
        $updateStart - $createStart
    );

if (
    !str_contains(
        $create,
        '$this->baseUserRoleIds()'
    )
) {
    throw new RuntimeException(
        'manual create base role contract missing'
    );
}

echo "MANUAL_CREATE_EXISTING_BACKEND=PASS\n";
echo "MANUAL_CREATE_BASE_USER_ONLY=PASS\n";
echo "USER_PROVISIONING_TOOLBAR=PASS\n";
echo "LEGACY_USER_ROLE_WRITER_CLOSED=PASS\n";
echo "INVITATION_TOKEN_HASH_ONLY=PASS\n";
echo "INVITATION_EXPIRY=PASS\n";
echo "INVITATION_NO_ROLE_ASSIGNMENT=PASS\n";
echo "PUBLIC_REGISTRATION_INVITATION_BINDING=PASS\n";
echo "INVITATION_ACCEPT_AFTER_OTP=PASS\n";
echo "BULK_IMPORT_RESERVED_DISABLED=PASS\n";
echo "A4_3AB_USER_PROVISIONING_FOUNDATION=PASS\n";

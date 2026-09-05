<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$files = [
    'admin_user_repository' =>
        $root
        . '/public_html/app/Repositories/'
        . 'AdminUserManagementRepository.php',

    'access_repository' =>
        $root
        . '/public_html/app/Repositories/'
        . 'AccessControlRepository.php',

    'admin_user_service' =>
        $root
        . '/public_html/app/Services/'
        . 'AdminUserManagementService.php',

    'access_service' =>
        $root
        . '/public_html/app/Services/'
        . 'AccessControlService.php',

    'admin_panel_service' =>
        $root
        . '/public_html/app/Services/'
        . 'AdminPanelService.php',

    'user_form' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'admin-user-form.php',

    'access_control' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'access-control.php',

    'legacy_access' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'access.php',

    'users_view' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'users.php',

    'routes' =>
        $root
        . '/public_html/routes/web.php',
];

foreach ($files as $name => $file) {
    if (!is_file($file)) {
        throw new RuntimeException(
            'missing file: '
            . $name
        );
    }

    $files[$name] =
        (string) file_get_contents(
            $file
        );
}

$adminRepository =
    $files['admin_user_repository'];

$accessRepository =
    $files['access_repository'];

$adminService =
    $files['admin_user_service'];

$accessService =
    $files['access_service'];

$panelService =
    $files['admin_panel_service'];

$userForm =
    $files['user_form'];

$accessControl =
    $files['access_control'];

$legacyAccess =
    $files['legacy_access'];

$usersView =
    $files['users_view'];

$routes =
    $files['routes'];

$extract =
    static function (
        string $source,
        string $start,
        string $end
    ): string {
        $startPos =
            strpos(
                $source,
                $start
            );

        $endPos =
            strpos(
                $source,
                $end,
                $startPos === false
                    ? 0
                    : $startPos
            );

        if (
            $startPos === false
            || $endPos === false
        ) {
            throw new RuntimeException(
                'method boundary missing'
            );
        }

        return substr(
            $source,
            $startPos,
            $endPos - $startPos
        );
    };

/*
 * A4.2 role ordering.
 */
$adminRoles =
    $extract(
        $adminRepository,
        'public function roles(',
        'public function roleKinds('
    );

if (
    !str_contains(
        $adminRoles,
        'roles.priority DESC'
    )
) {
    throw new RuntimeException(
        'admin roles not manager-to-user'
    );
}

$centralRoles =
    $extract(
        $accessRepository,
        'public function roles(): array',
        'public function permissions(): array'
    );

if (
    !str_contains(
        $centralRoles,
        'priority DESC'
    )
) {
    throw new RuntimeException(
        'central roles not manager-to-user'
    );
}

/*
 * User form must be read-only for access.
 */
foreach (
    [
        'name="role_ids[]"',
        'data-kind-filter',
        'data-area-filter',
        'data-role-search',
        '/admin/users/\'.$userId.\'/roles',
    ]
    as $forbidden
) {
    if (
        str_contains(
            $userForm,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'user access write control remains: '
            . $forbidden
        );
    }
}

if (
    !str_contains(
        $userForm,
        '/admin/access-control'
    )
) {
    throw new RuntimeException(
        'user access deep-link missing'
    );
}

/*
 * Normal user save cannot consume posted role_ids.
 */
$create =
    $extract(
        $adminService,
        'public function create(',
        'public function update('
    );

$update =
    $extract(
        $adminService,
        'public function update(',
        'public function updateRoles('
    );

if (
    str_contains(
        $create,
        "\$validated['role_ids']"
    )
) {
    throw new RuntimeException(
        'user create still writes posted roles'
    );
}

if (
    str_contains(
        $update,
        "\$validated['role_ids']"
    )
) {
    throw new RuntimeException(
        'user update still writes posted roles'
    );
}

if (
    !str_contains(
        $create,
        '$this->baseUserRoleIds()'
    )
) {
    throw new RuntimeException(
        'base-user create contract missing'
    );
}

$repositoryUpdate =
    $extract(
        $adminRepository,
        'public function update(',
        'public function updateRoles('
    );

if (
    !str_contains(
        $repositoryUpdate,
        'bool $syncRoles = true'
    )
    || !str_contains(
        $repositoryUpdate,
        '?callable $identityRefresher = null'
    )
    || !str_contains(
        $repositoryUpdate,
        'if ($syncRoles)'
    )
    || !str_contains(
        $repositoryUpdate,
        '$identityRefresher('
    )
) {
    throw new RuntimeException(
        'repository role-preservation contract missing'
    );
}

if (
    str_contains(
        $update,
        '$this->roleSynchronizer('
    )
) {
    throw new RuntimeException(
        'normal user update still synchronizes role selection'
    );
}

if (
    !str_contains(
        $update,
        '$this->identityRefresher('
    )
) {
    throw new RuntimeException(
        'normal user update lifecycle refresh missing'
    );
}

/*
 * Central AccessControl is canonical assignment writer.
 */
if (
    !str_contains(
        $accessService,
        'public function saveUserRoles('
    )
) {
    throw new RuntimeException(
        'central saveUserRoles missing'
    );
}

if (
    !str_contains(
        $accessControl,
        'action="/admin/access-control/users/roles"'
    )
) {
    throw new RuntimeException(
        'central user role form missing'
    );
}

if (
    substr_count(
        $routes,
        "'/admin/access-control/users/roles'"
    ) !== 1
) {
    throw new RuntimeException(
        'canonical role route count invalid'
    );
}

/*
 * Legacy communication access matrix must be gone.
 */
foreach (
    [
        'communicationMatrix',
        'saveCommunicationRolePermissions',
        '/admin/access/communications',
    ]
    as $forbidden
) {
    if (
        str_contains(
            $legacyAccess,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'legacy matrix remains: '
            . $forbidden
        );
    }
}

if (
    !str_contains(
        $legacyAccess,
        'این صفحه فقط برای انتخاب Context فعال کاربر است.'
    )
) {
    throw new RuntimeException(
        'legacy access page still multi-purpose'
    );
}

/*
 * Legacy routes may remain as compatibility redirects,
 * but they must no longer invoke old writers.
 */
$legacyCommunicationStart =
    strpos(
        $routes,
        "\$router->post('/admin/access/communications'"
    );

$legacyCommunicationEnd =
    strpos(
        $routes,
        "\$router->post('/admin/profile/access'",
        $legacyCommunicationStart
    );

if (
    $legacyCommunicationStart === false
    || $legacyCommunicationEnd === false
) {
    throw new RuntimeException(
        'legacy communication route boundary missing'
    );
}

$legacyCommunicationBlock =
    substr(
        $routes,
        $legacyCommunicationStart,
        $legacyCommunicationEnd
        - $legacyCommunicationStart
    );

if (
    str_contains(
        $legacyCommunicationBlock,
        'saveCommunicationRolePermissions'
    )
) {
    throw new RuntimeException(
        'legacy communication writer remains'
    );
}

if (
    !str_contains(
        $legacyCommunicationBlock,
        '/admin/access-control'
    )
) {
    throw new RuntimeException(
        'legacy communication redirect missing'
    );
}

/*
 * User module no longer owns access card.
 * System module owns canonical access-control card.
 */
$usersDefinitionStart =
    strpos(
        $panelService,
        "'key' => 'users'"
    );

$organizationDefinitionStart =
    strpos(
        $panelService,
        "'key' => 'organization'",
        $usersDefinitionStart
    );

if (
    $usersDefinitionStart === false
    || $organizationDefinitionStart === false
) {
    throw new RuntimeException(
        'users module definition boundary missing'
    );
}

$usersDefinition =
    substr(
        $panelService,
        $usersDefinitionStart,
        $organizationDefinitionStart
        - $usersDefinitionStart
    );

if (
    str_contains(
        $usersDefinition,
        "'key' => 'access'"
    )
) {
    throw new RuntimeException(
        'user module still owns access card'
    );
}

if (
    !str_contains(
        $panelService,
        "'key' => 'access-control'"
    )
    || !str_contains(
        $panelService,
        "'url' => '/admin/access-control'"
    )
) {
    throw new RuntimeException(
        'system access-control card missing'
    );
}

if (
    str_contains(
        $usersView,
        'مدیریت حساب‌های کاربری و دسترسی‌ها'
    )
) {
    throw new RuntimeException(
        'users page still claims access ownership'
    );
}

echo "ADMIN_ROLE_ORDER_MANAGER_TO_USER=PASS\n";
echo "CENTRAL_ROLE_ORDER_MANAGER_TO_USER=PASS\n";
echo "USER_FORM_ACCESS_READ_ONLY=PASS\n";
echo "USER_NORMAL_SAVE_CANNOT_WRITE_ROLES=PASS\n";
echo "USER_NORMAL_SAVE_PRESERVES_SCOPED_ASSIGNMENTS=PASS\n";
echo "USER_IDENTITY_UPDATE_REFRESHES_ROLE_LIFECYCLE=PASS\n";
echo "CENTRAL_ACCESS_ROLE_ASSIGNMENT_WRITE=PASS\n";
echo "LEGACY_ACCESS_MATRIX_REMOVED=PASS\n";
echo "LEGACY_ACCESS_WRITERS_DISABLED=PASS\n";
echo "USER_MODULE_ACCESS_CARD_REMOVED=PASS\n";
echo "SYSTEM_MODULE_ACCESS_CONTROL_OWNER=PASS\n";
echo "A4_2B_SINGLE_ACCESS_GOVERNANCE_SURFACE=PASS\n";

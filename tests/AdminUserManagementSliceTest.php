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

$repository = $read(
    'public_html/app/Repositories/'
    . 'AdminUserManagementRepository.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'AdminUserManagementService.php'
);
$routes = $read(
    'public_html/routes/admin-users-manage.php'
);
$form = $read(
    'public_html/resources/views/admin/admin-user-form.php'
);
$users = $read(
    'public_html/resources/views/admin/users.php'
);
$loader = $read(
    'public_html/system/Routing/RouteLoader.php'
);

$expect(
    str_contains($service, "'users.create'")
    && str_contains($service, "'users.update'")
    && str_contains($service, "'users.manage'"),
    'Create/update authorization checks are missing.'
);

$expect(
    str_contains($service, "'permissions.assign'"),
    'Protected-role assignment guard is missing.'
);

$expect(
    str_contains($service, 'نمی‌توانید حساب فعال خودتان')
    && str_contains($repository, 'preserveSuperAdmin'),
    'Self-lockout safeguards are missing.'
);

$expect(
    str_contains($repository, 'beginTransaction')
    && str_contains($repository, 'rollBack')
    && str_contains($repository, 'commit'),
    'Transactional user persistence is missing.'
);

$expect(
    str_contains($repository, "scope_type = 'global'")
    && str_contains($repository, 'scope_id IS NULL'),
    'Global-role synchronization must preserve scoped assignments.'
);

$expect(
    str_contains($repository, "roles.code <> 'super_admin'"),
    'Protected role filtering is missing.'
);

$expect(
    str_contains($service, 'IdentityNormalizer')
    && str_contains($service, 'password_hash('),
    'Identity normalization or secure password hashing is missing.'
);

$expect(
    str_contains($routes, "/admin/users/create")
    && str_contains($routes, "/admin/users/{id}/edit"),
    'Create/edit routes are missing.'
);

$expect(
    str_contains($form, 'name="_token"')
    && str_contains($form, 'name="role_ids[]"')
    && str_contains($form, 'password_confirmation'),
    'User form security or required controls are missing.'
);

$expect(
    str_contains($users, 'ایجاد کاربر')
    && str_contains($users, '/edit')
    && str_contains($users, '$canUpdate'),
    'User list create/edit actions or UI permission guards are missing.'
);

$expect(
    str_contains($loader, 'admin-users-manage.php'),
    'Admin user management routes are not loaded.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $repository
    ),
    'Destructive schema SQL is present.'
);

echo "Admin user management slice checks passed.\n";

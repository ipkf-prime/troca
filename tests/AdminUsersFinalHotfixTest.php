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

$sort = $read(
    'public_html/app/Support/AdminTableSort.php'
);
$repository = $read(
    'public_html/app/Repositories/'
    . 'AdminUserListRepository.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'AdminUserListService.php'
);
$route = $read(
    'public_html/routes/admin-users-list.php'
);
$users = $read(
    'public_html/resources/views/admin/users.php'
);
$form = $read(
    'public_html/resources/views/admin/'
    . 'admin-user-form.php'
);
$profileAccess = $read(
    'public_html/resources/views/admin/'
    . 'profile-access.php'
);
$loader = $read(
    'public_html/system/Routing/RouteLoader.php'
);

$expect(
    str_contains($sort, 'array_key_exists($sort, $allowed)')
    && str_contains($sort, "['asc', 'desc']"),
    'Sort helper is not whitelist based.'
);

$expect(
    str_contains($repository, 'SORT_COLUMNS')
    && str_contains($repository, 'highest_role_title')
    && str_contains($repository, 'highest_role_priority')
    && !str_contains($repository, 'ORDER BY users.id ASC'),
    'Server-side user sorting or highest-role query is incomplete.'
);

$expect(
    str_contains($service, "'created_at'")
    && str_contains($service, "'highest_role'")
    && str_contains($service, 'AdminTableSort::resolve'),
    'User list sort state is incomplete.'
);

$expect(
    str_contains($route, "input('sort'")
    && str_contains($route, "input('dir'"),
    'Users route does not accept sort state.'
);

$expect(
    substr_count($users, 'admin-sort-link') >= 2
    && str_contains($users, 'بالاترین نقش')
    && str_contains($users, 'admin-users-toolbar')
    && str_contains($users, 'col-username'),
    'Sortable aligned users table is incomplete.'
);

$expect(
    str_contains($form, 'نقش پیش‌فرض')
    && str_contains($form, 'کد نقش')
    && str_contains($form, 'مرجع حوزه')
    && str_contains($form, 'data-role-sort'),
    'Role selection table is not complete or sortable.'
);

$expect(
    str_contains($profileAccess, 'انتخاب‌شده')
    && str_contains($profileAccess, 'نقش فعال')
    && str_contains($profileAccess, 'مرجع حوزه')
    && str_contains($profileAccess, "=== 'user'"),
    'Active/default role presentation is incomplete.'
);

$expect(
    str_contains($loader, 'admin-users-list.php')
    && strpos($loader, 'admin-users-list.php')
        > strpos($loader, 'admin-users-manage.php'),
    'Users list override is not loaded after current routes.'
);

echo "Admin users final hotfix checks passed.\n";

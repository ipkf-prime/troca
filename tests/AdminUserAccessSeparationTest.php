<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string =>
    (string) file_get_contents($root . $path);

$service = $read('/public_html/app/Services/AdminUserManagementService.php');
$repository = $read('/public_html/app/Repositories/AdminUserManagementRepository.php');
$routes = $read('/public_html/routes/admin-users-manage.php');
$form = $read('/public_html/resources/views/admin/admin-user-form.php');
$notification = $read('/public_html/system/Database/Seeds/NotificationCoreSeeder.php');
$communication = $read('/public_html/system/Database/Seeds/CommunicationCenterSeeder.php');

$checks = [
    'role-only service' => str_contains($service, 'public function updateRoles('),
    'role-only repository' => str_contains($repository, 'public function updateRoles('),
    'independent route' => str_contains($routes, "\$router->post('/admin/users/{id}/roles'"),
    'access mutation delegated to control center' =>
        !str_contains(
            $form,
            'formnovalidate>ذخیره نقش‌ها و دسترسی‌ها'
        )
        && str_contains(
            $form,
            'این بخش فقط وضعیت فعلی را نمایش می‌دهد.'
        )
        && str_contains(
            $form,
            '/admin/access-control'
        ),
    'notification system admin grant' => str_contains($notification, "\$assign->execute([\$code, 'system_admin']);"),
    'communication super admin grant' =>
        str_contains(
            $communication,
            "\$assign->execute([\$code, 'super_admin']);"
        )
        && !str_contains(
            $communication,
            "\$assign->execute([\$code, 'system_admin']);"
        ),
];

$failed = array_keys(array_filter(
    $checks,
    static fn (bool $passed): bool => !$passed
));
if ($failed !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}

echo "Admin user access separation tests passed.\n";

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
    'validation bypass button' => str_contains($form, 'formnovalidate>ذخیره نقش‌ها و دسترسی‌ها'),
    'notification system admin grant' => str_contains($notification, "\$assign->execute([\$code, 'system_admin']);"),
    'communication system admin grant' => str_contains($communication, "\$assign->execute([\$code, 'system_admin']);"),
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

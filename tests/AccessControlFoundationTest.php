<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        fwrite(STDERR, "FAIL: cannot read {$path}\n");
        exit(1);
    }

    return $content;
};

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'CreateAccessControlFoundation.php'
);
$repository = $read(
    'public_html/app/Repositories/AccessControlRepository.php'
);
$permissionRepository = $read(
    'public_html/app/Repositories/PermissionRepository.php'
);
$authorization = $read(
    'public_html/app/Services/AuthorizationService.php'
);
$service = $read(
    'public_html/app/Services/AccessControlService.php'
);
$policy = $read(
    'public_html/app/Services/'
    . 'NotificationSendAccessPolicyService.php'
);
$send = $read(
    'public_html/app/Services/NotificationSendCenterService.php'
);
$routes = $read('public_html/routes/web.php');
$view = $read(
    'public_html/resources/views/admin/access-control.php'
);

$expect(
    str_contains($migration, 'user_permission_overrides')
    && str_contains($migration, 'access_control_change_logs')
    && str_contains($migration, 'notifications.send.direct')
    && str_contains($migration, 'notifications.send.request'),
    'Access schema or permissions are incomplete.'
);

$expect(
    str_contains($permissionRepository, 'userPermissionOverride')
    && str_contains($authorization, "\$override === 'allow'"),
    'User override precedence is incomplete.'
);

$expect(
    str_contains($repository, 'saveRolePermissions')
    && str_contains($repository, 'saveUserPolicy')
    && str_contains($service, 'notification_policy'),
    'Access management operations are incomplete.'
);

$expect(
    str_contains($policy, 'approval_required')
    && str_contains($send, 'notification_send_approval_required')
    && str_contains(
        $send,
        'notification_send_manual_target_forbidden'
    ),
    'Notification access enforcement is incomplete.'
);

$expect(
    str_contains($routes, '/admin/access-control/users')
    && str_contains($view, 'سیاست ارسال اعلان')
    && str_contains($view, 'data-acl-role-form'),
    'Access management UI or routes are incomplete.'
);

echo "Access control foundation checks passed.\n";

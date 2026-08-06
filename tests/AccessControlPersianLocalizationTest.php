<?php

$root = dirname(__DIR__);
$migration = file_get_contents(
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CompleteAccessControlPersianLocalization.php'
);
$view = file_get_contents(
    $root . '/public_html/resources/views/admin/access-control.php'
);
$migrate = file_get_contents(
    $root . '/public_html/public/migrate.php'
);

if (!is_string($migration) || !is_string($view) || !is_string($migrate)) {
    fwrite(STDERR, "Access-control localization sources are missing.\n");
    exit(1);
}

$permissionCodes = [
    'account.profile.view',
    'account.security.view',
    'account.password.change',
    'account.theme.manage',
    'admin.theme.manage',
    'admin.dashboard.view',
    'access.manage',
    'admin.settings.manage',
    'admin.pages.manage',
    'admin.reports.view',
    'admin.navigation.debug',
    'admin.route.debug',
    'roles.view',
    'roles.create',
    'roles.update',
    'roles.delete',
    'permissions.view',
    'permissions.assign',
    'auth.login_token.issue',
    'org_units.view',
    'positions.view',
    'user_org_assignments.manage',
    'organizations.view',
    'organizations.update',
    'support.view',
    'system.diagnostics.view',
    'system.installer.view',
    'users.view',
    'users.create',
    'users.update',
    'users.delete',
    'users.manage',
    'work.project.view',
    'work.project.manage',
    'work.audit.view',
    'work.settings.manage',
    'work.project.admin',
];

foreach ($permissionCodes as $code) {
    if (!str_contains($migration, "'{$code}'")) {
        fwrite(STDERR, "Missing localized permission: {$code}\n");
        exit(1);
    }
}

$requiredPersianLabels = [
    'مشاهده سازمان‌ها',
    'ویرایش سازمان‌ها',
    'مشاهده تاریخچه تغییرات مدیریت کار',
    'مدیریت تعاریف و تنظیمات مدیریت کار',
    "'organizations' => 'سازمان‌ها'",
    "'org_units' => 'واحدهای سازمانی'",
    '$scopeLabel',
    '$auditTargetLabel',
    '$auditChangeLabel',
];

foreach ($requiredPersianLabels as $label) {
    if (
        !str_contains($migration, $label)
        && !str_contains($view, $label)
    ) {
        fwrite(STDERR, "Missing Persian localization marker: {$label}\n");
        exit(1);
    }
}

if (
    !str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\'
        . 'CompleteAccessControlPersianLocalization()'
    )
) {
    fwrite(STDERR, "Persian localization migration is not registered.\n");
    exit(1);
}

foreach ([
    'View organizations',
    'Update organizations',
    'Workها',
    'تغییرات Work',
    'تنظیمات Work',
] as $forbidden) {
    if (str_contains($migration, $forbidden)) {
        fwrite(STDERR, "Legacy English label remains: {$forbidden}\n");
        exit(1);
    }
}

echo "Access control Persian localization checks passed.\n";

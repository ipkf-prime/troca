<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$viewPath = $root
    . '/public_html/resources/views/admin/'
    . 'access-control.php';
$migrationPath = $root
    . '/public_html/system/Database/Migrations/'
    . 'RefineAccessControlExperience.php';
$migratePath = $root . '/public_html/public/migrate.php';

$read = static function (string $path): string {
    $content = file_get_contents($path);

    if (!is_string($content)) {
        fwrite(STDERR, "FAIL: {$path} is not readable.\n");
        exit(1);
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

$view = $read($viewPath);
$migration = $read($migrationPath);
$migrate = $read($migratePath);

$expect(
    str_contains($view, "preg_match('/^\\d{3}$/', \$status)")
    && str_contains($view, 'as $code => $tabTitle')
    && !str_contains($view, 'as $code => $title'),
    'Numeric status suppression or page-title isolation is incomplete.'
);

$expect(
    str_contains($view, "'auth' => 'احراز هویت'")
    && str_contains($view, "'users' => 'کاربران'")
    && str_contains($view, "'system' => 'زیرساخت سامانه'")
    && str_contains($view, "'audit' => 'ممیزی و تاریخچه'")
    && str_contains($view, '$groupLabel($groupTitle)'),
    'Persian module or permission-group labels are incomplete.'
);

$expect(
    str_contains($view, 'data-acl-role-search')
    && str_contains($view, 'class="acl-protected-note"')
    && str_contains($view, '@media (max-width: 980px)')
    && str_contains($view, 'class="acl-tech" dir="ltr"'),
    'Role search, protected-role state, tablet behavior, or technical detail hiding is incomplete.'
);

$expect(
    str_contains($migration, "title = 'مدیریت سامانه'")
    && str_contains($migration, "'access-control'")
    && str_contains($migration, "'/admin/access-control'")
    && str_contains($migration, "'سطوح و نقش‌های دسترسی'")
    && str_contains($migration, "'access.roles.manage'")
    && str_contains($migration, "'access.users.manage'"),
    'Access-control navigation or route authorization is incomplete.'
);

$expect(
    str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\RefineAccessControlExperience()'
    ),
    'Access-control refinement migration is not registered.'
);

echo "Access control experience refinement checks passed.\n";

<?php

$root = dirname(__DIR__);
$migrationPath = $root
    . '/public_html/system/Database/Migrations/'
    . 'CompleteAccessControlCatalogDescriptions.php';

$migration = file_get_contents($migrationPath);
$migrate = file_get_contents(
    $root . '/public_html/public/migrate.php'
);

if (!is_string($migration) || !is_string($migrate)) {
    fwrite(STDERR, "Access-control description sources are missing.\n");
    exit(1);
}

$expected = [
    'automation.audit.view' =>
        'مشاهده سوابق ثبت، ارجاع و تغییرات مکاتبات اداری.',
    'notifications.routing.manage' =>
        'مدیریت قواعد انتخاب کانال، سرویس‌دهنده و مسیر ارسال اعلان‌ها.',
    'organizations.manage' =>
        'ایجاد، ویرایش و مدیریت سازمان‌های سامانه.',
    'positions.manage' =>
        'ایجاد، ویرایش و مدیریت پست‌ها و سمت‌های سازمانی.',
    'org_units.manage' =>
        'ایجاد، ویرایش و مدیریت ساختار و واحدهای سازمانی.',
    'work.item.view' =>
        'مشاهده فهرست و جزئیات تسک‌های مدیریت کار.',
    'work.item.create' =>
        'ایجاد تسک جدید در پروژه‌های مدیریت کار.',
    'work.item.update' =>
        'ویرایش مشخصات و تغییر وضعیت تسک‌ها.',
    'work.item.assign' =>
        'تعیین یا تغییر مسئول اجرای تسک‌ها.',
    'work.settings.view' =>
        'مشاهده تعاریف و تنظیمات ماژول مدیریت کار.',
];

if (count($expected) !== 10) {
    fwrite(STDERR, "Expected description count is not 10.\n");
    exit(1);
}

foreach ($expected as $code => $description) {
    if (!str_contains($migration, "'{$code}'")) {
        fwrite(STDERR, "Missing permission code: {$code}\n");
        exit(1);
    }

    if (!str_contains($migration, "'{$description}'")) {
        fwrite(STDERR, "Missing description for: {$code}\n");
        exit(1);
    }
}

foreach ([
    'SET description = ?',
    'description IS NULL',
    "OR description = ''",
    'updated_at = CURRENT_TIMESTAMP',
] as $marker) {
    if (!str_contains($migration, $marker)) {
        fwrite(STDERR, "Missing migration marker: {$marker}\n");
        exit(1);
    }
}

if (
    !str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\'
        . 'CompleteAccessControlCatalogDescriptions()'
    )
) {
    fwrite(STDERR, "Description migration is not registered.\n");
    exit(1);
}

echo "Access control catalog description checks passed.\n";

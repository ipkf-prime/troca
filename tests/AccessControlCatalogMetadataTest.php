<?php

$root = dirname(__DIR__);
$migrationPath = $root
    . '/public_html/system/Database/Migrations/'
    . 'FinalizeAccessControlCatalogMetadata.php';

$migration = file_get_contents($migrationPath);
$migrate = file_get_contents(
    $root . '/public_html/public/migrate.php'
);

if (!is_string($migration) || !is_string($migrate)) {
    fwrite(STDERR, "Access-control catalog metadata sources are missing.\n");
    exit(1);
}

$expected = [
    'automation.correspondence.cartable.view' =>
        'مکاتبات اداری',
    'automation.correspondence.close' =>
        'مکاتبات اداری',
    'automation.correspondence.create' =>
        'مکاتبات اداری',
    'automation.correspondence.edit_draft' =>
        'مکاتبات اداری',
    'automation.correspondence.register' =>
        'مکاتبات اداری',
    'automation.correspondence.route' =>
        'مکاتبات اداری',
    'automation.correspondence.view' =>
        'مکاتبات اداری',
    'automation.registry.manage' =>
        'دفاتر ثبت مکاتبات',
    'messages.admin.manage' =>
        'پیام‌رسان داخلی',
    'messages.admin.view' =>
        'پیام‌رسان داخلی',
    'messages.reply' =>
        'پیام‌رسان داخلی',
    'messages.send' =>
        'پیام‌رسان داخلی',
    'messages.view' =>
        'پیام‌رسان داخلی',
    'notifications.send.manage' =>
        'ارسال اعلان',
    'notifications.preferences.self' =>
        'ترجیحات اعلان',
    'notifications.providers.manage' =>
        'سرویس‌دهندگان اعلان',
    'notifications.reports.view' =>
        'گزارش‌های اعلان',
    'appointments.assign' =>
        'انتصاب‌ها',
    'appointments.manage' =>
        'انتصاب‌ها',
    'organizational_context.switch' =>
        'جایگاه سازمانی فعال',
    'signature_authorizations.manage' =>
        'مجوزهای امضا',
    'signatures.manage' =>
        'امضاها',
    'signatures.view' =>
        'امضاها',
    'notifications.deliveries.view' =>
        'گزارش تحویل اعلان',
    'notifications.manage' =>
        'اعلان‌ها',
    'notifications.view' =>
        'اعلان‌ها',
    'notifications.templates.manage' =>
        'قالب‌های اعلان',
];

if (count($expected) !== 27) {
    fwrite(STDERR, "Expected permission count is not 27.\n");
    exit(1);
}

foreach ($expected as $code => $group) {
    if (!str_contains($migration, "'{$code}'")) {
        fwrite(STDERR, "Missing permission code: {$code}\n");
        exit(1);
    }

    if (!str_contains($migration, "'{$group}'")) {
        fwrite(STDERR, "Missing display group: {$group}\n");
        exit(1);
    }
}

foreach ([
    'SET display_group = ?',
    "WHEN description IS NULL OR description = ''",
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
        . 'FinalizeAccessControlCatalogMetadata()'
    )
) {
    fwrite(STDERR, "Catalog metadata migration is not registered.\n");
    exit(1);
}

echo "Access control catalog metadata checks passed.\n";

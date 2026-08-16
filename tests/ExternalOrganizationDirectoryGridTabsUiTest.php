<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-external-organizations.php'
    );

if (!is_string($view)) {
    fwrite(
        STDERR,
        "FAIL: View unavailable.\n"
    );

    exit(1);
}

foreach ([
    'external-directory-grid-tabs-v2',
    'external-directory-organization-grid',
    'external-directory-destination-grid',
    'external-directory-table',
    'external-directory-tabs',
    '$activeDirectoryTab',
    '$selectedDirectoryPointReference',
    '$directoryTabUrl',
    "'profile'",
    "'destinations'",
    "'contacts'",
    "'addresses'",
    'اطلاعات سازمان',
    'مقصدهای مکاتباتی',
    'راه‌های ارتباطی',
    'نشانی‌ها',
    'external-directory-destination-selector',
    '<th>عنوان سازمان</th>',
    '<th>عنوان کوتاه</th>',
    '<th>شناسه ملی</th>',
    '<th>عنوان مقصد</th>',
    '<th>روش ترجیحی</th>',
    'پیش‌فرض',
] as $required) {
    if (
        !str_contains(
            $view,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Grid/tab contract missing: {$required}\n"
        );

        exit(1);
    }
}

if (
    str_contains(
        $view,
        'admin-card admin-card--link'
    )
) {
    fwrite(
        STDERR,
        "FAIL: Organization card list still present.\n"
    );

    exit(1);
}

if (
    str_contains(
        $view,
        'name="code"'
    )
) {
    fwrite(
        STDERR,
        "FAIL: Technical destination code visible.\n"
    );

    exit(1);
}

echo "External organization grid/tab UI checks passed.\n";

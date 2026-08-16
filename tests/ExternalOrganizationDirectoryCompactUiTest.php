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
    'external-directory-compact-ui',
    'external-directory-collapse',
    'external-directory-organization-edit',
    'external-directory-create-destination',
    '+ سازمان جدید',
    'ویرایش اطلاعات سازمان',
    '+ افزودن مقصد مکاتباتی',
    'gap: 10px',
    'مقصد پیش‌فرض',
] as $required) {
    if (
        !str_contains(
            $view,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Compact UI contract missing: {$required}\n"
        );

        exit(1);
    }
}

if (
    str_contains(
        $view,
        '<details open>'
    )
) {
    fwrite(
        STDERR,
        "FAIL: Heavy details still open by default.\n"
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

echo "External organization compact UI checks passed.\n";

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
    'external-directory-real-action-buttons-v1',
    'external-directory-header-actions',
    'display: inline-flex',
    'background: #0d766a',
    'color: #fff !important',
    '+ سازمان جدید',
    'مشاهده',
    'ویرایش اطلاعات سازمان',
    '+ مقصد مکاتباتی',
    'ویرایش مقصد',
    '+ راه ارتباطی',
    '+ نشانی',
] as $required) {
    if (
        !str_contains(
            $view,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Button contract missing: {$required}\n"
        );

        exit(1);
    }
}

if (
    str_contains(
        $view,
        'id="create-organization-action"'
    )
) {
    fwrite(
        STDERR,
        "FAIL: Standalone add-organization card remains.\n"
    );

    exit(1);
}

echo "External directory real action button checks passed.\n";

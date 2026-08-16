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
    'external-directory-action-driven-v1',
    '$directoryMode',
    "'create-organization'",
    "'edit-organization'",
    "'create-destination'",
    "'edit-destination'",
    "'manage-contacts'",
    "'manage-addresses'",
    'profile-readonly-summary',
    'create-destination-action',
    'point-readonly-actions',
    '+ سازمان جدید',
    'مشاهده',
    'ویرایش اطلاعات سازمان',
    '+ مقصد مکاتباتی',
    'ویرایش مقصد',
    '+ راه ارتباطی',
    '+ نشانی',
    'external-directory-readonly-grid',
] as $required) {
    if (
        !str_contains(
            $view,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Action-driven contract missing: {$required}\n"
        );

        exit(1);
    }
}

if (
    str_contains(
        $view,
        ">انتخاب‌شده<"
    )
) {
    fwrite(
        STDERR,
        "FAIL: Selected-state text still used as action.\n"
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

echo "External organization action-driven UI checks passed.\n";

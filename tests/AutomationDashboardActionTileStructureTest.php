<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'automation-dashboard.php'
);

if (!is_string($view)) {
    fwrite(
        STDERR,
        "FAIL: Dashboard view unavailable.\n"
    );

    exit(1);
}

$tileCount =
    substr_count(
        $view,
        'class="admin-action-tile '
    );

$bodyCount =
    substr_count(
        $view,
        'class="admin-action-tile__body"'
    );

if ($tileCount !== 6) {
    fwrite(
        STDERR,
        "FAIL: Expected 6 action tiles, found {$tileCount}.\n"
    );

    exit(1);
}

if ($bodyCount !== 6) {
    fwrite(
        STDERR,
        "FAIL: Expected 6 tile bodies, found {$bodyCount}.\n"
    );

    exit(1);
}

foreach ([
    '<strong>فهرست مکاتبات</strong>',
    '<strong>نامه وارده</strong>',
    '<strong>نامه صادره</strong>',
    '<strong>نامه داخلی</strong>',
    '<strong>سازمان‌های بیرونی</strong>',
    '<strong>قالب‌های نامه</strong>',
] as $required) {
    if (!str_contains(
        $view,
        $required
    )) {
        fwrite(
            STDERR,
            "FAIL: Missing normalized title: {$required}\n"
        );

        exit(1);
    }
}

foreach ([
    '<strong>ثبت نامه وارده</strong>',
    '<strong>ایجاد نامه صادره</strong>',
    '<strong>ایجاد نامه داخلی</strong>',
] as $obsolete) {
    if (str_contains(
        $view,
        $obsolete
    )) {
        fwrite(
            STDERR,
            "FAIL: Obsolete long title remains: {$obsolete}\n"
        );

        exit(1);
    }
}

echo "Automation dashboard action tile structure checks passed.\n";

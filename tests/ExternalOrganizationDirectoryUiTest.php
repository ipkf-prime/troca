<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-external-organizations.php'
    );

$dashboard =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-dashboard.php'
    );

if (
    !is_string($view)
    || !is_string($dashboard)
) {
    fwrite(
        STDERR,
        "FAIL: UI source unavailable.\n"
    );

    exit(1);
}

foreach ([
    'سازمان‌های بیرونی و دبیرخانه‌های مقصد',
    'فهرست سازمان‌های بیرونی',
    '+ سازمان جدید',
    'دبیرخانه‌ها و نقاط مکاتباتی',
    'راه‌های ارتباطی',
    'نشانی‌های تکمیلی',
    'preferred_dispatch_channel_code',
    'contact_type_code',
    'supports_dispatch',
    'supports_followup',
    'postal_code',
    '/admin/automation/external-organizations/save',
    '/admin/automation/external-organizations/contact-points/save',
    '/admin/automation/external-organizations/contact-methods/save',
    '/admin/automation/external-organizations/addresses/save',
    'new \\IPKF\\Security\\Csrf()',
] as $required) {
    if (
        !str_contains(
            $view,
            $required
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: View contract missing: {$required}\n"
        );

        exit(1);
    }
}

foreach ([
    'DELETE ',
    '{organization_reference}',
    '{contact_point_reference}',
    '{method_reference}',
    '{address_reference}',
] as $forbidden) {
    if (
        str_contains(
            $view,
            $forbidden
        )
    ) {
        fwrite(
            STDERR,
            "FAIL: Forbidden UI contract: {$forbidden}\n"
        );

        exit(1);
    }
}

if (
    !str_contains(
        $dashboard,
        'automation.external_directory.manage'
    )
    || !str_contains(
        $dashboard,
        '/admin/automation/external-organizations'
    )
    || !str_contains(
        $dashboard,
        'سازمان‌های بیرونی'
    )
) {
    fwrite(
        STDERR,
        "FAIL: Dashboard tile contract missing.\n"
    );

    exit(1);
}

echo "External organization directory UI checks passed.\n";

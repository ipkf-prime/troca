<?php

$root = dirname(__DIR__);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'automation-secretariat-management.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expect(
    str_contains(
        $view,
        'انتخاب دبیرخانه'
    ),
    'Membership form must have neutral desk option.'
);

$expect(
    str_contains(
        $view,
        'انتخاب انتصاب سازمانی'
    ),
    'Membership form must have neutral appointment option.'
);

$expect(
    str_contains(
        $view,
        "'secretariat_desk_reference'\n"
        . "                                ) === ''"
    ),
    'Fresh membership form must not preselect a desk.'
);

$expect(
    str_contains(
        $view,
        "'appointment_reference'\n"
        . "                                ) === ''"
    ),
    'Fresh membership form must not preselect an appointment.'
);

$expect(
    str_contains(
        $view,
        "'membership_role_code',\n"
        . "                                        'operator'"
    ),
    'Operator role should remain the harmless role default.'
);

echo "Automation secretariat membership form default checks passed.\n";

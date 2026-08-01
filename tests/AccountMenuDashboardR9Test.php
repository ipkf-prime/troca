<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        throw new RuntimeException(
            'Unable to read ' . $path
        );
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

$layout = $read(
    'public_html/resources/views/admin/layout.php'
);
$dashboard = $read(
    'public_html/resources/views/admin/dashboard.php'
);
$seeder = $read(
    'public_html/system/Database/Seeds/'
    . 'CommunicationCenterSeeder.php'
);

$expect(
    !str_contains($layout, '$legacyAccountNav'),
    'Legacy account menu is still merged.'
);

$expect(
    str_contains(
        $layout,
        ':not(.admin-bell-icon)'
    ),
    'Bell icon is still hidden.'
);

$homePosition = strpos(
    $seeder,
    "'account-home'"
);
$profilePosition = strpos(
    $seeder,
    "'account-profile'"
);
$cartablePosition = strpos(
    $seeder,
    "'account-cartable'"
);
$logoutPosition = strpos(
    $seeder,
    "'account-logout'"
);

$expect(
    $homePosition !== false
    && $profilePosition !== false
    && $cartablePosition !== false
    && $logoutPosition !== false
    && $homePosition < $profilePosition
    && $profilePosition < $cartablePosition
    && $cartablePosition < $logoutPosition,
    'Account menu order is incorrect.'
);

$expect(
    str_contains(
        $seeder,
        "'پیشخوان اصلی'"
    )
    && str_contains(
        $seeder,
        "'/admin/dashboard'"
    )
    && str_contains(
        $seeder,
        "'core'"
    ),
    'Main dashboard account item is incomplete.'
);

foreach ([
    'اطلاعات حساب',
    'امنیت و ورود',
    'نقش‌ها و دسترسی‌های من',
    'جایگاه سازمانی فعال',
    'تغییر کلمه عبور',
    'پوسته نمایشی من',
] as $obsoleteTitle) {
    $expect(
        !str_contains(
            $seeder,
            "'{$obsoleteTitle}'"
        ),
        "Obsolete account item remains: {$obsoleteTitle}"
    );
}

$expect(
    str_contains($dashboard, 'max-width: none;')
    && str_contains($dashboard, 'width: 100%;')
    && str_contains($dashboard, 'justify-self: stretch;'),
    'Dashboard tiles are not forced to fill grid columns.'
);

echo "Account menu and main dashboard R9 checks passed.\n";

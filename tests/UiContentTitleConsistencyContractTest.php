<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$canonical =
    'مدیریت راهنما، اعلان و خطا';

$panel =
    (string) file_get_contents(
        $root
        . '/public_html/app/Services/AdminPanelService.php'
    );

$route =
    (string) file_get_contents(
        $root
        . '/public_html/routes/system-help-texts.php'
    );

$view =
    (string) file_get_contents(
        $root
        . '/public_html/resources/views/admin/help-texts.php'
    );


$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };


$expect(
    str_contains(
        $panel,
        "'key' => 'help-texts'"
    ),
    'Help content card missing.'
);

$expect(
    str_contains(
        $panel,
        "'مدیریت راهنما، اعلان و خطا'"
    ),
    'Card title mismatch.'
);

$expect(
    str_contains(
        $panel,
        'مدیریت متن‌های راهنما، اعلان‌ها و پیام‌های خطای سامانه'
    ),
    'Card description mismatch.'
);

$expect(
    substr_count(
        $route,
        $canonical
    ) >= 1,
    'Route title mismatch.'
);

$expect(
    substr_count(
        $view,
        $canonical
    ) >= 3,
    'View title consistency mismatch.'
);

foreach (
    [
        'راهنماها و پیام‌های سیستمی',
        "'title' => 'راهنماها'",
        'مدیریت متن‌های راهنما و پیام‌های آموزشی سامانه',
    ]
    as $legacy
) {
    $expect(
        !str_contains(
            $panel
            . "\n"
            . $route
            . "\n"
            . $view,
            $legacy
        ),
        'Legacy title remains: '
        . $legacy
    );
}


echo "UI_CONTENT_TITLE_CONSISTENCY=PASS\n";
echo "CANONICAL_TITLE={$canonical}\n";
echo "CARD_TITLE=PASS\n";
echo "PAGE_TITLE=PASS\n";
echo "BREADCRUMB_TITLE=PASS\n";
echo "HERO_TITLE=PASS\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $path
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $path
    );

    if (!is_string($content)) {
        fwrite(
            STDERR,
            "FAIL: cannot read {$path}\n"
        );
        exit(1);
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

$view = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$style = $read(
    'public_html/resources/views/admin/partials/'
    . 'communication-style.php'
);

$expect(
    str_contains(
        $view,
        'notification-send-minimal-overview-v061'
    )
    && str_contains(
        $view,
        'data-send-live-summary'
    )
    && str_contains(
        $view,
        'data-send-overview-step'
    )
    && str_contains(
        $view,
        'MutationObserver'
    ),
    'Minimal send overview behavior is incomplete.'
);

$expect(
    str_contains($view, "'کانال',")
    && str_contains($view, "'مقصد دستی',")
    && str_contains($view, "'بازبینی'"),
    'Compact send step titles are incomplete.'
);

$expect(
    str_contains(
        $style,
        'notification-send-minimal-responsive-v061'
    )
    && str_contains(
        $style,
        '.notification-send-live-summary'
    )
    && str_contains(
        $style,
        'position: sticky'
    )
    && str_contains(
        $style,
        '@media (max-width: 430px)'
    ),
    'Minimal responsive send styles are incomplete.'
);

echo "Notification send minimal responsive UI checks passed.\n";

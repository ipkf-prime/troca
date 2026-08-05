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
        'data-send-content-step'
    )
    && str_contains(
        $view,
        'data-send-subject-field'
    )
    && str_contains(
        $view,
        'data-send-body-count'
    )
    && str_contains(
        $view,
        'data-send-content-status'
    ),
    'Content composition markers are incomplete.'
);

$expect(
    str_contains(
        $view,
        'data-send-dropzone'
    )
    && str_contains(
        $view,
        'data-send-media-feedback'
    )
    && str_contains(
        $view,
        'DataTransfer'
    )
    && str_contains(
        $view,
        'validateContent'
    ),
    'Media picker experience is incomplete.'
);

$expect(
    str_contains(
        $view,
        "subjectField.hidden"
    )
    && str_contains(
        $view,
        "contentStep?.classList.toggle"
    )
    && str_contains(
        $view,
        "'چندرسانه‌ای · '"
    ),
    'Conditional content behavior is incomplete.'
);

$expect(
    str_contains(
        $style,
        'notification-content-experience-v061'
    )
    && str_contains(
        $style,
        '.notification-send-content-step.has-media'
    )
    && str_contains(
        $style,
        '.notification-send-media-preview__type'
    )
    && str_contains(
        $style,
        '@media (max-width: 430px)'
    ),
    'Content experience styles are incomplete.'
);

echo "Notification content experience UI checks passed.\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$builder = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/'
    . 'Correspondence/CorrespondenceViewModelBuilder.php'
);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'automation-correspondence-detail.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );

        exit(1);
    }
};

$expect(
    is_string($builder)
    && is_string($view),
    'Sources must be readable.'
);

$expect(
    str_contains(
        $builder,
        "'direction_code' => (string) "
        . "(\$row['direction_code'] ?? '')"
    ),
    'Detail view-model must expose raw correspondence direction.'
);

$expect(
    str_contains(
        $view,
        "(\$c['direction_code'] ?? '') === 'incoming'"
    ),
    'External metadata must be direction-aware.'
);

$expect(
    str_contains(
        $view,
        "'شماره بیرونی'"
    )
    && str_contains(
        $view,
        "'تاریخ بیرونی'"
    )
    && str_contains(
        $view,
        "'شماره ثبت رسمی'"
    )
    && str_contains(
        $view,
        "'تاریخ ثبت رسمی'"
    ),
    'Summary must retain incoming external and official registration fields.'
);

echo "Automation correspondence directional summary checks passed.\n";

$detailView = file_get_contents(
    dirname(__DIR__)
    . '/public_html/resources/views/admin/'
    . 'automation-correspondence-detail.php'
);

if (
    !str_contains(
        $detailView,
        "(\$c['summary'] ?? '—') !== '—'"
    )
) {
    fwrite(
        STDERR,
        "FAIL: Empty correspondence summary must not render placeholder dash.\n"
    );
    exit(1);
}

echo "Empty correspondence summary visibility check passed.\n";

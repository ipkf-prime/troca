<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$viewPath = $root
    . '/public_html/resources/views/admin/'
    . 'communication-settings.php';

$stylePath = $root
    . '/public_html/resources/views/admin/partials/'
    . 'communication-style.php';

$view = file_get_contents($viewPath);
$style = file_get_contents($stylePath);

if (!is_string($view) || !is_string($style)) {
    fwrite(STDERR, "FAIL: runtime source unreadable.\n");
    exit(1);
}

$subjectDeclaration = <<<'JS'
                    const subject =
                        form.querySelector(
                            '[data-send-subject]'
                        );
JS;

$digitsDeclaration = <<<'JS'
                    const digits =
                        new Intl.NumberFormat('fa-IR');
JS;

$checks = [
    str_contains($view, $subjectDeclaration),
    str_contains($view, $digitsDeclaration),
    str_contains(
        $view,
        'renderFiles();'
    ),
    str_contains(
        $view,
        'showStep(1);'
    ),
    str_contains(
        $style,
        'notification-send-wizard-runtime-hotfix-v061'
    ),
    str_contains(
        $style,
        '.notification-send-review[hidden]'
    ),
];

foreach ($checks as $index => $passed) {
    if (!$passed) {
        fwrite(
            STDERR,
            'FAIL: wizard runtime check '
            . ($index + 1)
            . " failed.\n"
        );
        exit(1);
    }
}

echo "Notification send wizard runtime checks passed.\n";

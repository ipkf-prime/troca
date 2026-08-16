<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$view = file_get_contents(
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
    'external-directory-linear-details-v1',
    'grid-template-columns: 180px minmax(0, 1fr)',
    'border-radius: 0',
    'background: transparent',
    '.external-directory-readonly-item:last-child',
    '@media (max-width: 720px)',
    'profile-readonly-summary',
    'point-readonly-actions',
] as $required) {
    if (!str_contains(
        $view,
        $required
    )) {
        fwrite(
            STDERR,
            "FAIL: Linear detail contract missing: {$required}\n"
        );

        exit(1);
    }
}

if (str_contains(
    $view,
    'repeat('
    . "\n"
    . '                auto-fit'
)) {
    fwrite(
        STDERR,
        "FAIL: Old card grid layout still present.\n"
    );

    exit(1);
}

echo "External directory linear detail UI checks passed.\n";

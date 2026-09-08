<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


$fail =
    static function (
        string $message
    ): never {
        fwrite(
            STDERR,
            $message
            . PHP_EOL
        );

        exit(1);
    };


$assert =
    static function (
        bool $condition,
        string $message
    ) use (
        $fail
    ): void {
        if (!$condition) {
            $fail($message);
        }
    };


$requiredFiles = [
    'docs/PLATFORM_FOUNDATION_CONTRACT.md',

    'public_html/app/Support/PlatformPresentation.php',

    'public_html/public/assets/admin/css/foundation.css',
    'public_html/public/assets/admin/js/foundation.js',

    'public_html/resources/views/admin/layout.php',
];


foreach (
    $requiredFiles
    as $file
) {
    $assert(
        is_file(
            $root
            . '/'
            . $file
        ),
        'Required Foundation file missing: '
        . $file
    );
}


require_once
    $root
    . '/public_html/app/Support/AdminFormat.php';

require_once
    $root
    . '/public_html/app/Support/JalaliDateInput.php';

require_once
    $root
    . '/public_html/app/Support/PlatformPresentation.php';


$presentation =
    \App\Support\PlatformPresentation::class;


$assert(
    $presentation::LOCALE
        === 'fa-IR',
    'Platform locale contract failed.'
);

$assert(
    $presentation::DIRECTION
        === 'rtl',
    'Platform direction contract failed.'
);


$assert(
    $presentation::digits(
        '1234567890'
    ) === '۱۲۳۴۵۶۷۸۹۰',
    'Persian digit presentation failed.'
);


$assert(
    $presentation::normalizeDigits(
        '۱۲٣۴۵6'
    ) === '123456',
    'Persian/Arabic/ASCII digit normalization failed.'
);


$jalali =
    $presentation::jalaliInputFromGregorian(
        '2026-09-08'
    );


$assert(
    preg_match(
        '/^[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}$/u',
        $jalali
    ) === 1,
    'Jalali input presentation is not Persian-digit YYYY/MM/DD.'
);


$assert(
    $presentation::gregorianDateFromJalali(
        $jalali
    ) === '2026-09-08',
    'Jalali/Gregorian round-trip failed.'
);


$css =
    file_get_contents(
        $root
        . '/public_html/public/assets/admin/css/foundation.css'
    );

$js =
    file_get_contents(
        $root
        . '/public_html/public/assets/admin/js/foundation.js'
    );

$layout =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/layout.php'
    );

$theme =
    file_get_contents(
        $root
        . '/public_html/app/Services/AdminThemeService.php'
    );

$moduleContract =
    file_get_contents(
        $root
        . '/public_html/app/Services/AdminModuleUiContract.php'
    );


foreach ([
    '--ui-font-family',
    '--ui-primary',
    '--ui-surface',
    '--ui-text',
    '--ui-border',
    '--ui-control-height',
    '--ui-radius',
    '--ui-space-4',
    '.ui-input',
    '.ui-select',
    '.ui-button',
    '.ui-checkbox',
    '.ui-form-grid',
    '.ui-table-wrap',
    ':focus-visible',
    'prefers-reduced-motion',
] as $needle) {

    $assert(
        str_contains(
            (string) $css,
            $needle
        ),
        'Foundation CSS contract missing: '
        . $needle
    );
}


foreach ([
    'toEnglishDigits',
    'toPersianDigits',
    'data-ui-normalize-digits',
] as $needle) {

    $assert(
        str_contains(
            (string) $js,
            $needle
        ),
        'Foundation JS contract missing: '
        . $needle
    );
}


foreach ([
    'foundation_css',
    'foundation_js',
] as $needle) {

    $assert(
        str_contains(
            (string) $theme,
            $needle
        ),
        'Theme asset contract missing: '
        . $needle
    );

    $assert(
        str_contains(
            (string) $layout,
            $needle
        ),
        'Admin layout asset contract missing: '
        . $needle
    );
}


$assert(
    str_contains(
        (string) $moduleContract,
        '/assets/admin/css/foundation.css'
    ),
    'Foundation CSS is not protected as a shared module asset.'
);

$assert(
    str_contains(
        (string) $moduleContract,
        '/assets/admin/js/foundation.js'
    ),
    'Foundation JS is not protected as a shared module asset.'
);


/*
 * Main Admin shell is the canonical authenticated UI shell and must
 * declare the exact platform locale/direction contract.
 */
$assert(
    str_contains(
        (string) $layout,
        'lang="fa-IR"'
    )
    &&
    str_contains(
        (string) $layout,
        'dir="rtl"'
    ),
    'Admin layout fa-IR/RTL contract failed.'
);


$legacyLayout =
    file_get_contents(
        $root
        . '/public_html/resources/views/layouts/app.php'
    );


$assert(
    str_contains(
        (string) $legacyLayout,
        'lang="fa-IR"'
    )
    &&
    str_contains(
        (string) $legacyLayout,
        'dir="rtl"'
    ),
    'Generic layout fa-IR/RTL contract failed.'
);


echo
    "PLATFORM_FOUNDATION_CONTRACT_PASS"
    . PHP_EOL;

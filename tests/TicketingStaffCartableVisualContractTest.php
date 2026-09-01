<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$cssPath =
    $root
    . '/public_html/public/assets/admin/css/ticketing.css';

$viewPath =
    $root
    . '/public_html/resources/views/admin/ticketing-staff.php';


$css =
    file_get_contents(
        $cssPath
    );

$view =
    file_get_contents(
        $viewPath
    );


if (
    !is_string($css)
    ||
    !is_string($view)
) {
    throw new RuntimeException(
        'Ticketing visual contract source unavailable.'
    );
}


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


foreach ([
    'TICKETING_SHARED_ICON_VISUAL_CONTRACT_V2',
    '.ticketing-icon-action--primary',
    '.ticketing-icon-action--takeover',
    '.ticketing-icon-action--transfer',
    '.ticketing-icon-action--escalate',
    'background: #fff !important',
    'width: 16px !important',
    '.ticketing-col-status',
    'width: 160px !important',
    '.ticketing-col-activity',
    'width: 125px !important',
    'white-space: nowrap !important',
    'min-width: 1075px !important',
] as $marker) {
    $expect(
        str_contains(
            $css,
            $marker
        ),
        'Ticketing shared visual contract missing: '
        . $marker
    );
}


foreach ([
    'ticketing-col-status',
    'ticketing-col-activity',
    'ticketing-col-actions',
    'ticketing-staff-icon-actions',
    'title="مشاهده تیکت"',
    'title="تحویل گرفتن تیکت"',
    'title="انتقال به کارشناس دیگر"',
    'aria-label="مشاهده تیکت"',
    'aria-label="انتقال به کارشناس دیگر"',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Staff cartable visual/accessibility contract missing: '
        . $marker
    );
}


/*
 * Primary-green may exist for textual actions elsewhere,
 * but the final shared icon contract must explicitly neutralize
 * every icon-only semantic modifier.
 */
foreach ([
    '.ticketing-icon-action--primary',
    '.ticketing-icon-action--takeover',
    '.ticketing-icon-action--transfer',
    '.ticketing-icon-action--escalate',
] as $selector) {
    $position =
        strrpos(
            $css,
            $selector
        );

    $expect(
        $position !== false,
        'Final icon selector missing: '
        . $selector
    );

    $slice =
        substr(
            $css,
            $position,
            900
        );

    $expect(
        str_contains(
            $slice,
            '#fff'
        )
        ||
        str_contains(
            $slice,
            '#edf7f1'
        ),
        'Final icon visual is not neutral: '
        . $selector
    );
}


echo "TICKETING_STAFF_CARTABLE_VISUAL_CONTRACT_PASS\n";

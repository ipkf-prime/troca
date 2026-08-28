<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


require_once
    $root
    . '/public_html/app/Support/'
    . 'TicketingIcon.php';


$read =
    static function (
        string $relative
    ) use ($root): string {

        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Cannot read '
                . $relative
            );
        }

        return $content;
    };


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


$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-staff.php'
    );

$css =
    $read(
        'public_html/public/assets/admin/css/'
        . 'ticketing.css'
    );


foreach ([
    'view',
    'takeover',
    'transfer',
    'escalate',
    'search',
    'reset',
    'confirm',
] as $icon) {

    $svg =
        \App\Support\TicketingIcon::svg(
            $icon
        );

    $expect(
        str_contains(
            $svg,
            '<svg'
        )
        &&
        str_contains(
            $svg,
            'aria-hidden="true"'
        ),
        'Invalid Ticketing icon: '
        . $icon
    );
}


foreach ([
    'مشاهده تیکت',
    'تحویل گرفتن تیکت',
    'انتقال به کارشناس دیگر',
    '$escalationTooltip',
    'اعمال جستجو',
    'بازنشانی جستجو',
    'title="مشاهده تیکت"',
    'title="تحویل گرفتن تیکت"',
    'title="انتقال به کارشناس دیگر"',
    'aria-label',
] as $marker) {

    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Icon UI marker missing: '
        . $marker
    );
}


foreach ([
    '.ticketing-icon-action',
    '.ticketing-icon-action',
    '.ticketing-transfer-menu__body',
    '.ticketing-staff-icon-actions',
] as $marker) {

    $expect(
        str_contains(
            $css,
            $marker
        ),
        'Shared icon CSS missing: '
        . $marker
    );
}


$expect(
    !str_contains(
        $view,
        '>مشاهده<'
    ),
    'Visible text View button remains.'
);


echo
    "TICKETING_ICON_ACTION_CONTRACT_PASS\n";

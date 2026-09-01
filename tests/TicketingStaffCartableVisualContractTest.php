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



/*
 * TICKETING_R4B_CARTABLE_VISUAL_CONTRACT
 */
$repository =
    file_get_contents(
        $root
        . '/public_html/app/Repositories/'
        . 'TicketStaffOperationsRepository.php'
    );

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'TicketStaffOperationsService.php'
    );

$expect(
    is_string($repository)
    && is_string($service),
    'R4B repository/service unavailable.'
);

$priorityOrder =
    strpos(
        $repository,
        'pr.severity DESC'
    );

$activityOrder =
    strpos(
        $repository,
        't.last_activity_at DESC',
        $priorityOrder === false
            ? 0
            : $priorityOrder
    );

$idOrder =
    strpos(
        $repository,
        't.id DESC',
        $activityOrder === false
            ? 0
            : $activityOrder
    );

$expect(
    $priorityOrder !== false
    && $activityOrder !== false
    && $idOrder !== false
    && $priorityOrder < $activityOrder
    && $activityOrder < $idOrder,
    'R4B sort contract changed.'
);

foreach ([
    'AS assignee_user_reference',
    'pr.color AS priority_color',
] as $marker) {
    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'R4B repository marker missing: '
        . $marker
    );
}

foreach ([
    'viewer_user_reference',
    "'user:' . \$userId",
] as $marker) {
    $expect(
        str_contains(
            $service,
            $marker
        ),
        'R4B service marker missing: '
        . $marker
    );
}

foreach ([
    'TICKETING_R4B_CARTABLE_VISUAL',
    'ticketing-staff-row--assigned-to-me',
    'ticketing-staff-priority',
    'priority_color',
    '--ticketing-priority-color',
    'به من',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'R4B view marker missing: '
        . $marker
    );
}

foreach ([
    'TICKETING_R4B_CARTABLE_VISUAL',
    '--ticketing-priority-color',
    '.ticketing-staff-priority',
    '.ticketing-assigned-to-me-badge',
] as $marker) {
    $expect(
        str_contains(
            $css,
            $marker
        ),
        'R4B CSS marker missing: '
        . $marker
    );
}

echo "TICKETING_R4B_CARTABLE_VISUAL_CONTRACT_PASS\n";


/*
 * TICKETING_R4B_CARTABLE_VISUAL_POLISH_CONTRACT
 */
foreach ([
    'ticketing-assignee-name--mine',
    'title="این تیکت به شما تخصیص داده شده است"',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'R4B assignee-name polish missing: '
        . $marker
    );
}

$expect(
    !str_contains(
        $view,
        '>به من<'
    )
    && !str_contains(
        $view,
        ">\n                                            به من\n"
    ),
    'R4B old assigned-to-me badge still rendered.'
);

foreach ([
    'TICKETING_R4B_CARTABLE_VISUAL_POLISH',
    '.ticketing-assignee-name--mine',
    'min-width: 92px !important',
    'width: 92px !important',
    'min-width: 112px !important',
    'width: 112px !important',
    'display: none !important',
] as $marker) {
    $expect(
        str_contains(
            $css,
            $marker
        ),
        'R4B visual polish CSS missing: '
        . $marker
    );
}

echo "TICKETING_STAFF_CARTABLE_VISUAL_CONTRACT_PASS\n";

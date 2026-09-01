<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/public_html/app/Services/'
    . 'DynamicAdminNavigationService.php';

$text =
    file_get_contents($path);

if (!is_string($text)) {
    throw new RuntimeException(
        'DynamicAdminNavigationService unreadable.'
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

$start =
    strpos(
        $text,
        'REQUESTER_TICKETING_NAVIGATION_RUNTIME'
    );

$end =
    strpos(
        $text,
        '        foreach ($items as $item) {',
        $start === false
            ? 0
            : $start
    );

$expect(
    $start !== false
    &&
    $end !== false
    &&
    $end > $start,
    'Requester navigation block unavailable.'
);

$block =
    substr(
        $text,
        $start,
        $end - $start
    );

foreach ([
    'if ($hasMembership)',
    'TICKETING_POST_LEAVE_NAVIGATION_GUARD',
    '} else {',
    'array_filter(',
    "'ticketing-my-tickets'",
    "'ticketing-create'",
] as $marker) {

    $expect(
        str_contains(
            $block,
            $marker
        ),
        'Post-leave navigation marker missing: '
        . $marker
    );
}

$guardPosition =
    strpos(
        $block,
        'TICKETING_POST_LEAVE_NAVIGATION_GUARD'
    );

$expect(
    $guardPosition !== false,
    'Post-leave guard unavailable.'
);

$guard =
    substr(
        $block,
        $guardPosition
    );

/*
 * Only operational requester items are filtered.
 */
$expect(
    !str_contains(
        $guard,
        "'ticketing-dashboard'"
    ),
    'Dashboard must remain after leave.'
);

$expect(
    !str_contains(
        $guard,
        "'ticketing-membership'"
    ),
    'Membership workspace must remain after leave.'
);

echo
    "TICKETING_POST_LEAVE_NAVIGATION_GUARD_PASS\n";

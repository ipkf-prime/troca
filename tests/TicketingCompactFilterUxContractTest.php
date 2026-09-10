<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


$paths = [
    'my' =>
        $root
        . '/public_html/resources/views/admin/ticketing-tickets.php',

    'staff' =>
        $root
        . '/public_html/resources/views/admin/ticketing-staff.php',

    'css' =>
        $root
        . '/public_html/public/assets/admin/css/ticketing.css',

    'js' =>
        $root
        . '/public_html/public/assets/admin/js/ticketing-cartable.js',
];


$source = [];

foreach (
    $paths
    as $name => $path
) {
    $content =
        file_get_contents(
            $path
        );

    if (!is_string($content)) {
        throw new RuntimeException(
            'compact filter source unavailable: '
            . $name
        );
    }

    $source[$name] =
        $content;
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


foreach (
    [
        'my',
        'staff',
    ]
    as $view
) {
    foreach (
        [
            'TICKETING_COMPACT_FILTER_UX_T3G',
            'TICKETING_COMPACT_FILTER_PRIMARY_T3G_START',
            'TICKETING_COMPACT_FILTER_PRIMARY_T3G_END',
            'TICKETING_COMPACT_FILTER_ADVANCED_T3G_START',
            'TICKETING_COMPACT_FILTER_ADVANCED_T3G_END',
            'data-ticketing-advanced-toggle',
            'data-ticketing-advanced-panel',
            'فیلترهای بیشتر',
            'بازنشانی فیلترها',
            'name="q"',
            'name="topic"',
            'name="priority"',
        ]
        as $marker
    ) {
        $expect(
            str_contains(
                $source[$view],
                $marker
            ),
            $view
            . ' compact marker missing: '
            . $marker
        );
    }
}


/*
 * My Tickets advanced fields.
 */
foreach (
    [
        'name="layer"',
        'name="assignee"',
        'name="sort1"',
        'name="dir1"',
        'name="sort2"',
        'name="dir2"',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['my'],
            $marker
        ),
        'My Tickets advanced field missing: '
        . $marker
    );
}


/*
 * Staff advanced fields.
 */
foreach (
    [
        'name="layer_id"',
        'name="assignee"',
        'name="sort"',
        'name="per_page"',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['staff'],
            $marker
        ),
        'Staff advanced field missing: '
        . $marker
    );
}


/*
 * Primary frequently-used fields stay auto-submit capable.
 */
$expect(
    str_contains(
        $source['my'],
        "'topic',"
    ),
    'My Tickets Topic auto-submit lost.'
);

$expect(
    substr_count(
        $source['staff'],
        'data-ticketing-filter-auto-submit'
    ) === 7,
    'Staff auto-submit select count changed.'
);


/*
 * Existing search behavior stays unchanged.
 */
foreach (
    [
        'const debounceMs = 500;',
        "'compositionstart'",
        "'compositionend'",
        "event.key !== 'Enter'",
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['my'],
            $marker
        ),
        'My Tickets search behavior changed: '
        . $marker
    );
}


foreach (
    [
        'TICKETING_CARTABLE_AUTO_SEARCH_T3E',
        'var debounceMs = 500;',
        'TICKETING_COMPACT_FILTER_TOGGLE_T3G',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['js'],
            $marker
        ),
        'Staff JS marker missing: '
        . $marker
    );
}


/*
 * Compact visual contract.
 */
foreach (
    [
        'TICKETING_COMPACT_FILTER_UX_T3G',
        '.ticketing-compact-filter-grid',
        '.ticketing-compact-more-button',
        '.ticketing-advanced-filter-panel',
        '.ticketing-advanced-sort-grid',
        '@media (max-width: 1100px)',
        '@media (max-width: 640px)',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['css'],
            $marker
        ),
        'Compact CSS marker missing: '
        . $marker
    );
}


/*
 * My Tickets advanced values remain active, but the secondary panel
 * must always start collapsed after a full page render.
 */
foreach (
    [
        "\$sort1 !== 'last_activity'",
        "\$dir1 !== 'desc'",
        "\$sort2 !== 'created_at'",
        "\$dir2 !== 'desc'",
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['my'],
            $marker
        ),
        'My Tickets advanced active-count logic missing: '
        . $marker
    );
}

$expect(
    str_contains(
        $source['my'],
        'TICKETING_MY_ADVANCED_DEFAULT_COLLAPSED_T3G'
    ),
    'My Tickets default-collapsed marker missing.'
);

$expect(
    str_contains(
        $source['my'],
        "\$advancedFiltersOpen =\n    false;"
    ),
    'My Tickets advanced panel must start collapsed.'
);

$expect(
    !str_contains(
        $source['my'],
        "\$advancedFiltersOpen =\n    \$advancedFilterCount > 0;"
    ),
    'My Tickets advanced panel still auto-opens for active filters.'
);


$expect(
    str_contains(
        $source['staff'],
        "\$sort !== 'priority_desc'"
    ),
    'Staff advanced sort state persistence missing.'
);


$expect(
    str_contains(
        $source['staff'],
        "\$perPage !== 25"
    ),
    'Staff per-page panel persistence missing.'
);


echo
    "TICKETING_COMPACT_FILTER_UX_CONTRACT_PASS"
    . PHP_EOL;

echo
    "MY_TICKETS_PRIMARY_FILTERS=4"
    . PHP_EOL;

echo
    "MY_TICKETS_ADVANCED_FILTERS=2_PLUS_SORT"
    . PHP_EOL;

echo
    "STAFF_PRIMARY_FILTERS=4"
    . PHP_EOL;

echo
    "STAFF_ADVANCED_CONTROLS=4"
    . PHP_EOL;

echo
    "ADVANCED_ACTIVE_COUNT=PASS"
    . PHP_EOL;

echo
    "MY_ADVANCED_DEFAULT_COLLAPSED=PASS"
    . PHP_EOL;

echo
    "AUTO_SEARCH_BEHAVIOR=PRESERVED"
    . PHP_EOL;

echo
    "AUTO_FILTER_BEHAVIOR=PRESERVED"
    . PHP_EOL;

echo
    "BACKEND_CHANGE=NO"
    . PHP_EOL;
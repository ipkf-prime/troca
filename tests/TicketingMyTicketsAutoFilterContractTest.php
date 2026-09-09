<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);

$view =
    $root
    . '/public_html/resources/views/admin/ticketing-tickets.php';


$fail =
    static function (
        string $message
    ): never {

        fwrite(
            STDERR,
            'T3D CONTRACT FAILED: '
            . $message
            . PHP_EOL
        );

        exit(1);
    };


$assert =
    static function (
        bool $condition,
        string $message
    ) use ($fail): void {

        if (!$condition) {
            $fail(
                $message
            );
        }
    };


$content =
    file_get_contents(
        $view
    );


$assert(
    is_string($content),
    'view read failed'
);


$assert(
    str_contains(
        $content,
        'method="get"'
    ),
    'GET transport missing'
);


$assert(
    str_contains(
        $content,
        'action="/admin/ticketing/tickets"'
    ),
    'canonical form action missing'
);


$assert(
    str_contains(
        $content,
        'data-ticketing-auto-filter-form'
    ),
    'auto-filter form marker missing'
);


$assert(
    str_contains(
        $content,
        'name="project"'
    ),
    'project preservation field missing'
);


foreach (
    [
        'q',
        'status',
        'priority',
        'layer',
        'assignee',
        'sort1',
        'dir1',
        'sort2',
        'dir2',
    ]
    as $field
) {
    $assert(
        str_contains(
            $content,
            'name="'
            . $field
            . '"'
        ),
        'form field missing: '
        . $field
    );
}


$startMarker =
    '<!-- TICKETING_MY_TICKETS_AUTO_FILTER_T3D_START -->';

$endMarker =
    '<!-- TICKETING_MY_TICKETS_AUTO_FILTER_T3D_END -->';


$start =
    strpos(
        $content,
        $startMarker
    );

$end =
    strpos(
        $content,
        $endMarker
    );


$assert(
    $start !== false,
    'start marker missing'
);

$assert(
    $end !== false,
    'end marker missing'
);

$assert(
    $end > $start,
    'marker order invalid'
);


$block =
    substr(
        $content,
        $start,
        $end
        - $start
        + strlen($endMarker)
    );


$assert(
    is_string($block),
    'auto-filter block extraction failed'
);


foreach (
    [
        'status',
        'priority',
        'layer',
        'assignee',
        'sort1',
        'dir1',
        'sort2',
        'dir2',
    ]
    as $field
) {
    $assert(
        str_contains(
            $block,
            "'"
            . $field
            . "'"
        ),
        'auto-submit field missing: '
        . $field
    );
}


$assert(
    !str_contains(
        $block,
        "'sort3'"
    ),
    'unexpected third sort level'
);


$assert(
    str_contains(
        $block,
        'const debounceMs = 500;'
    ),
    '500 ms debounce missing'
);


$assert(
    str_contains(
        $block,
        "'compositionstart'"
    ),
    'IME compositionstart guard missing'
);


$assert(
    str_contains(
        $block,
        "'compositionend'"
    ),
    'IME compositionend guard missing'
);


$assert(
    str_contains(
        $block,
        "event.key !== 'Enter'"
    ),
    'immediate Enter handling missing'
);


$assert(
    str_contains(
        $block,
        'event.preventDefault();'
    ),
    'native Enter duplicate-submit guard missing'
);


$assert(
    str_contains(
        $block,
        'form.requestSubmit();'
    ),
    'requestSubmit missing'
);


$assert(
    str_contains(
        $block,
        'form.submit();'
    ),
    'legacy submit fallback missing'
);


$assert(
    str_contains(
        $block,
        "'change'"
    ),
    'select change auto-submit missing'
);


$assert(
    str_contains(
        $block,
        "'input'"
    ),
    'search input listener missing'
);


foreach (
    [
        'fetch(',
        'XMLHttpRequest',
        'axios',
        '$.ajax',
    ]
    as $ajaxMarker
) {
    $assert(
        !str_contains(
            $block,
            $ajaxMarker
        ),
        'AJAX marker forbidden: '
        . $ajaxMarker
    );
}


$assert(
    !str_contains(
        $block,
        'name="page"'
    ),
    'page parameter must not be invented'
);


$manualApplyButton =
    preg_match(
        '/<button\b[^>]*>.*?اعمال.*?<\/button>/su',
        $content
    );


$assert(
    $manualApplyButton === 0,
    'manual Apply button still present'
);


$assert(
    str_contains(
        $content,
        'بازنشانی'
    ),
    'Reset control missing'
);


$assert(
    str_contains(
        $content,
        "\$sort1 !== 'last_activity'"
    ),
    'sort panel primary persistence missing'
);


$assert(
    str_contains(
        $content,
        "\$dir1 !== 'desc'"
    ),
    'sort panel direction persistence missing'
);


$assert(
    str_contains(
        $content,
        "\$sort2 !== 'created_at'"
    ),
    'sort panel secondary persistence missing'
);


$assert(
    str_contains(
        $content,
        "\$dir2 !== 'desc'"
    ),
    'sort panel secondary direction persistence missing'
);


echo
    "TICKETING_MY_TICKETS_AUTO_FILTER_CONTRACT=PASS"
    . PHP_EOL;

echo
    "SEARCH_DEBOUNCE_MS=500"
    . PHP_EOL;

echo
    "SEARCH_ENTER_IMMEDIATE=PASS"
    . PHP_EOL;

echo
    "SEARCH_IME_SAFE=PASS"
    . PHP_EOL;

echo
    "AUTO_SELECT_COUNT=8"
    . PHP_EOL;

echo
    "MANUAL_APPLY_BUTTON=ABSENT"
    . PHP_EOL;

echo
    "RESET_CONTROL=PRESENT"
    . PHP_EOL;

echo
    "PROJECT_CONTEXT=PRESERVED"
    . PHP_EOL;

echo
    "AJAX=NO"
    . PHP_EOL;

echo
    "SORT_LEVELS=2"
    . PHP_EOL;
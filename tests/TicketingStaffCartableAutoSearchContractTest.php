<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);

$viewPath =
    $root
    . '/public_html/resources/views/admin/'
    . 'ticketing-staff.php';

$jsPath =
    $root
    . '/public_html/public/assets/admin/js/'
    . 'ticketing-cartable.js';


$view =
    file_get_contents(
        $viewPath
    );

$js =
    file_get_contents(
        $jsPath
    );


if (
    !is_string($view)
    ||
    !is_string($js)
) {
    throw new RuntimeException(
        'T3E source unavailable.'
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


/*
 * Existing cartable transport and filter context remain server-side GET.
 */
foreach (
    [
        'method="get"',
        'action="/admin/ticketing/staff"',
        'name="scope"',
        'name="q"',
        'name="ticket_status"',
        'name="priority"',
        'name="layer_id"',
        'name="assignee"',
        'name="sort"',
        'name="per_page"',
        'aria-label="بازنشانی فیلترها"',
        'TICKETING_CARTABLE_AUTO_FILTER_SCRIPT_V1',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'T3E view marker missing: '
        . $marker
    );
}


/*
 * No page field exists in the filter form, therefore every form submit
 * naturally returns to page 1 while preserving pagination links separately.
 */
$filterStart =
    strpos(
        $view,
        '<form'
    );

$filterClass =
    strpos(
        $view,
        'ticketing-staff-search ticketing-staff-filter-grid'
    );

$filterEnd =
    strpos(
        $view,
        '</form>',
        $filterClass === false
            ? 0
            : $filterClass
    );


$expect(
    $filterClass !== false
    &&
    $filterEnd !== false
    &&
    $filterEnd > $filterClass,
    'T3E filter form boundary unavailable.'
);


$filterBlock =
    substr(
        $view,
        $filterClass,
        $filterEnd - $filterClass
    );


$expect(
    is_string(
        $filterBlock
    ),
    'T3E filter block extraction failed.'
);


$expect(
    !str_contains(
        $filterBlock,
        'name="page"'
    ),
    'T3E filter form unexpectedly carries page state.'
);


$expect(
    substr_count(
        $view,
        'data-ticketing-filter-auto-submit'
    ) === 6,
    'Existing select auto-submit count changed.'
);


$expect(
    str_contains(
        $view,
        'data-ticketing-search-auto-submit'
    ),
    'Search auto-submit marker missing.'
);


$expect(
    !str_contains(
        $view,
        'aria-label="اعمال فیلترها"'
    ),
    'Manual search/apply button still rendered.'
);


$expect(
    str_contains(
        $view,
        'aria-label="بازنشانی فیلترها"'
    ),
    'Reset control was removed.'
);


/*
 * Existing select behavior must remain intact.
 */
foreach (
    [
        'TICKETING_CARTABLE_AUTO_FILTER_V1',
        '[data-ticketing-filter-auto-submit]',
        "'change'",
        'form.requestSubmit',
        'form.submit();',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $js,
            $marker
        ),
        'Existing select auto-filter contract changed: '
        . $marker
    );
}


/*
 * T3E text-search behavior.
 */
foreach (
    [
        'TICKETING_CARTABLE_AUTO_SEARCH_T3E',
        'var debounceMs = 500;',
        '[data-ticketing-search-auto-submit]',
        "'compositionstart'",
        "'compositionend'",
        "'input'",
        "'search'",
        "'keydown'",
        "event.key !== 'Enter'",
        'event.isComposing',
        'event.preventDefault();',
        'window.setTimeout',
        'window.clearTimeout',
        'form.requestSubmit',
        'form.submit();',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $js,
            $marker
        ),
        'T3E auto-search marker missing: '
        . $marker
    );
}


/*
 * Search and select filters remain non-AJAX.
 */
foreach (
    [
        'fetch(',
        'XMLHttpRequest',
        'axios',
        '$.ajax',
        'jQuery.ajax',
    ]
    as $marker
) {
    $expect(
        !str_contains(
            $js,
            $marker
        ),
        'T3E AJAX marker forbidden: '
        . $marker
    );
}


echo
    "TICKETING_STAFF_CARTABLE_AUTO_SEARCH_CONTRACT_PASS"
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
    "SEARCH_NATIVE_CLEAR=PASS"
    . PHP_EOL;

echo
    "SELECT_AUTO_SUBMIT_COUNT=6"
    . PHP_EOL;

echo
    "MANUAL_SEARCH_BUTTON=ABSENT"
    . PHP_EOL;

echo
    "RESET_CONTROL=PRESENT"
    . PHP_EOL;

echo
    "FILTER_PAGE_FIELD=ABSENT"
    . PHP_EOL;

echo
    "AJAX=NO"
    . PHP_EOL;
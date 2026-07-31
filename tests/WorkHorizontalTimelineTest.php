<?php

$root = dirname(__DIR__);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'work-project-show.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expect(
    str_contains(
        $view,
        'work-project-timeline__viewport'
    ),
    'Horizontal timeline viewport is missing.'
);

$expect(
    str_contains($view, 'overflow-x: auto'),
    'Horizontal scrolling is missing.'
);

$expect(
    str_contains($view, 'direction: rtl')
    && str_contains($view, 'display: flex'),
    'Newest-first RTL horizontal track is missing.'
);

$expect(
    str_contains($view, 'data-timeline-entry')
    && str_contains($view, 'data-timeline-tooltip'),
    'Timeline point and tooltip contract is missing.'
);

$expect(
    str_contains($view, "addEventListener(\n"
        . "                    'mouseenter'")
    && str_contains($view, "'focus'"),
    'Hover and keyboard focus behavior is missing.'
);

$expect(
    str_contains($view, 'مشاهده آیتم مرتبط'),
    'Related item navigation is missing.'
);

$expect(
    !str_contains(
        $view,
        'work-project-timeline__body'
    ),
    'Old vertical timeline card layout remains.'
);

echo "Work horizontal timeline checks passed.\n";

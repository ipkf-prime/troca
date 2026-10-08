<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/user-detail.php'
    );

$layout =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/layout.php'
    );

if (
    !is_string($view)
    || !is_string($layout)
) {
    throw new RuntimeException(
        'compact_ui_source_read_failed'
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


$expect(
    substr_count(
        $view,
        'admin-impersonation-operate-access-form'
    ) === 1,
    'compact_form_class_invalid'
);

echo "A2R5R3_COMPACT_FORM_CLASS=PASS\n";


$marker =
    'ADMIN_IMPERSONATION_OPERATE_ACCESS_COMPACT_V1';

$expect(
    substr_count(
        $layout,
        $marker
    ) === 1,
    'compact_css_marker_invalid'
);

$expect(
    str_contains(
        $layout,
        'grid-template-columns: minmax(0, 1fr) max-content;'
    ),
    'desktop_one_row_grid_missing'
);

echo "A2R5R3_DESKTOP_ONE_ROW=PASS\n";


$expect(
    str_contains(
        $layout,
        '@media (max-width: 640px)'
    )
    && str_contains(
        $layout,
        'grid-template-columns: 1fr;'
    ),
    'responsive_mobile_stack_missing'
);

echo "A2R5R3_MOBILE_RESPONSIVE=PASS\n";


/*
 * Correct boundary:
 * stop at the START OF THE PHYSICAL </style> LINE,
 * not at the '<' character of </style>.
 */
$markerPosition =
    strpos(
        $layout,
        $marker
    );

$styleClose =
    strpos(
        $layout,
        '</style>',
        $markerPosition === false
            ? 0
            : $markerPosition
    );

$expect(
    $markerPosition !== false
    && $styleClose !== false
    && $styleClose > $markerPosition,
    'compact_css_style_bounds_invalid'
);

$beforeClose =
    substr(
        $layout,
        0,
        $styleClose
    );

$lastNewline =
    strrpos(
        $beforeClose,
        "\n"
    );

$styleLineStart =
    $lastNewline === false
        ? 0
        : $lastNewline + 1;

$expect(
    $styleLineStart > $markerPosition,
    'closing_style_line_start_invalid'
);

$closingIndent =
    substr(
        $layout,
        $styleLineStart,
        $styleClose - $styleLineStart
    );

$expect(
    preg_match(
        '/^[ \t]*$/',
        $closingIndent
    ) === 1,
    'closing_style_indent_invalid'
);

$block =
    substr(
        $layout,
        $markerPosition,
        $styleLineStart - $markerPosition
    );

$lines =
    preg_split(
        '/\R/',
        $block
    ) ?: [];

foreach ($lines as $index => $line) {
    $expect(
        preg_match(
            '/[ \t]+$/',
            $line
        ) !== 1,
        'compact_css_trailing_whitespace_line_'
        . ($index + 1)
    );
}

echo "A2R5R3_CSS_BLOCK_BOUNDARY=PASS\n";
echo "A2R5R3_COMPACT_BLOCK_TRAILING_WHITESPACE=NO\n";
echo "ADMIN_USER_IMPERSONATION_OPERATE_GRANT_COMPACT_UI=PASS\n";

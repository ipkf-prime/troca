<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

define(
    'BASE_PATH',
    $root . '/public_html'
);

require BASE_PATH . '/bootstrap/app.php';


$codec =
    new \App\Services\Automation\Correspondence\CorrespondenceRichTextContent();


$encoded =
    $codec->encodeHtml(
        '<h2 data-align="center">'
        . 'عنوان'
        . '</h2>'
        . '<p '
        . 'onclick="alert(1)" '
        . 'style="position:fixed">'
        . '<strong>پررنگ</strong>'
        . '<em>مورب</em>'
        . '<u>زیرخط</u>'
        . '<strike>خط‌خورده</strike>'
        . '<script>alert(1)</script>'
        . '<a href="javascript:alert(2)">بد</a>'
        . '<a href="https://example.com">خوب</a>'
        . '</p>'
    );


if (!$codec->isRich($encoded)) {
    throw new RuntimeException(
        'Rich envelope missing.'
    );
}


foreach ([
    '<script',
    'onclick=',
    'style=',
    'javascript:',
] as $forbidden) {
    if (
        str_contains(
            strtolower($encoded),
            strtolower($forbidden)
        )
    ) {
        throw new RuntimeException(
            'Unsafe content retained: '
            . $forbidden
        );
    }
}


foreach ([
    '<strong>پررنگ</strong>',
    '<em>مورب</em>',
    '<u>زیرخط</u>',
    '<strike>خط‌خورده</strike>',
    'data-align="center"',
    'href="https://example.com"',
] as $required) {
    if (
        !str_contains(
            $encoded,
            $required
        )
    ) {
        throw new RuntimeException(
            'Allowed formatting missing: '
            . $required
        );
    }
}


$empty =
    $codec->encodeHtml(
        '<p><br></p>'
    );

if ($empty !== '') {
    throw new RuntimeException(
        'Visually empty rich text accepted.'
    );
}


$legacy =
    '<b>متن ساده قدیمی</b>';

$legacyRendered =
    $codec->renderStored(
        $legacy
    );

if (
    str_contains(
        $legacyRendered,
        '<b>'
    )
    ||
    !str_contains(
        $legacyRendered,
        '&lt;b&gt;'
    )
) {
    throw new RuntimeException(
        'Legacy plain compatibility failed.'
    );
}


$command =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'Automation/Correspondence/'
        . 'CorrespondenceCommandService.php'
    );

$form =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-correspondence-form.php'
    );

$partial =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'partials/'
        . 'automation-correspondence-rich-editor.php'
    );

$detail =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-correspondence-detail.php'
    );


foreach ([
    $command,
    $form,
    $partial,
    $detail,
] as $source) {
    if (!is_string($source)) {
        throw new RuntimeException(
            'D6C2B source unavailable.'
        );
    }
}


foreach ([
    'content_format_code',
    'CorrespondenceRichTextContent',
    'encodeHtml(',
] as $required) {
    if (
        !str_contains(
            $command,
            $required
        )
    ) {
        throw new RuntimeException(
            'Command marker missing: '
            . $required
        );
    }
}


if (
    !str_contains(
        $form,
        'automation-correspondence-rich-editor.php'
    )
) {
    throw new RuntimeException(
        'Editor partial not connected.'
    );
}


foreach ([
    'contenteditable="true"',
    'data-rich-editor-shell',
    'data-rich-command="bold"',
    'data-rich-command="italic"',
    'data-rich-command="underline"',
    'data-rich-command="strikeThrough"',
    'data-rich-command="insertUnorderedList"',
    'data-rich-command="insertOrderedList"',
    'data-rich-align="justify"',
    'data-rich-indent="in"',
    'data-rich-link',
    'data-rich-count',
    'fallback.required = false;',
    'textLength() === 0',
    "'maxlength'",
] as $required) {
    if (
        !str_contains(
            $partial,
            $required
        )
    ) {
        throw new RuntimeException(
            'Editor contract missing: '
            . $required
        );
    }
}


if (
    !str_contains(
        $detail,
        '->renderStored('
    )
    ||
    !str_contains(
        $detail,
        'automation-rich-content'
    )
) {
    throw new RuntimeException(
        'Rich renderer missing.'
    );
}


if (
    str_contains(
        $detail,
        "nl2br(admin_h(\$c['content']"
    )
) {
    throw new RuntimeException(
        'Legacy-only detail renderer remains.'
    );
}


echo "D6C2B-R3 rich-text contract/security checks passed.\n";

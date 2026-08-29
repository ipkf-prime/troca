<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$viewPath =
    $root
    . '/public_html/resources/views/admin/'
    . 'ticketing-ticket-detail.php';

$cssPath =
    $root
    . '/public_html/public/assets/admin/css/'
    . 'ticketing.css';

$view =
    file_get_contents(
        $viewPath
    );

$css =
    file_get_contents(
        $cssPath
    );

if (
    !is_string($view)
    ||
    !is_string($css)
) {
    throw new RuntimeException(
        'A8D3 sources cannot be read.'
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

$exactLineCount =
    static function (
        string $text,
        string $value
    ): int {
        return preg_match_all(
            '/^[ \t]*'
            . preg_quote(
                $value,
                '/'
            )
            . '[ \t]*$/m',
            $text
        );
    };


/*
 * ----------------------------------------------------------
 * A8D3 identity
 * ----------------------------------------------------------
 */

$expect(
    substr_count(
        $view,
        'ticketing_detail_a8d3'
    ) >= 1,
    'A8D3 View marker missing.'
);

$expect(
    substr_count(
        $view,
        'ticketing_detail_a8d3_three_tabs'
    ) === 1,
    'A8D3 three-tab View marker invalid.'
);

$expect(
    substr_count(
        $css,
        'ticketing_detail_a8d3'
    ) >= 1,
    'A8D3 CSS marker missing.'
);

$expect(
    substr_count(
        $css,
        'ticketing_detail_a8d3_three_tabs'
    ) === 1,
    'A8D3 three-tab CSS marker invalid.'
);


/*
 * ----------------------------------------------------------
 * Three-tab structure
 * ----------------------------------------------------------
 */

foreach ([
    'data-ticketing-detail-tab="status"',
    'data-ticketing-detail-tab="conversation"',
    'data-ticketing-detail-tab="history"',
    'data-ticketing-detail-panel="status"',
    'data-ticketing-detail-panel="conversation"',
    'data-ticketing-detail-panel="history"',
] as $attribute) {
    $expect(
        $exactLineCount(
            $view,
            $attribute
        ) === 1,
        'Three-tab attribute contract failed: '
        . $attribute
    );
}

$expect(
    preg_match(
        '/>\s*وضعیت\s*</u',
        $view
    ) === 1,
    'Status tab label missing.'
);

$expect(
    preg_match(
        '/>\s*سوابق گفتگو\s*</u',
        $view
    ) === 1,
    'Conversation-history tab label missing.'
);

$expect(
    preg_match(
        '/>\s*تاریخچه\s*</u',
        $view
    ) >= 1,
    'History tab label missing.'
);

$expect(
    str_contains(
        $view,
        "activate(\n        'status'"
    ),
    'Status must be the default tab.'
);


/*
 * ----------------------------------------------------------
 * Ticket-number presentation
 * ----------------------------------------------------------
 */

$expect(
    substr_count(
        $view,
        '::ticketNumberFromRow('
    ) === 1,
    'Ticket number must render exactly once.'
);

$expect(
    !str_contains(
        $view,
        'subjectHeading'
    ),
    'Subject-specific number-removal JS must not exist.'
);

$expect(
    !str_contains(
        $view,
        'Remove the redundant standalone ticket-number label'
    ),
    'Client-side number-removal hack must not exist.'
);


/*
 * ----------------------------------------------------------
 * Conversation contract
 * ----------------------------------------------------------
 */

$expect(
    str_contains(
        $view,
        'ticketing-message-bubble'
    ),
    'Message bubble class missing.'
);

$expect(
    str_contains(
        $view,
        'data-ticketing-message-author='
    ),
    'Message author semantic attribute missing.'
);

$expect(
    str_contains(
        $css,
        'data-ticketing-message-author="requester"'
    ),
    'Requester bubble CSS missing.'
);

$expect(
    str_contains(
        $css,
        'data-ticketing-message-author="staff"'
    ),
    'Staff bubble CSS missing.'
);

$expect(
    str_contains(
        $css,
        'content: "درخواست‌کننده";'
    ),
    'Requester role label missing.'
);

$expect(
    str_contains(
        $css,
        'content: "کارشناس";'
    ),
    'Staff role label missing.'
);


/*
 * ----------------------------------------------------------
 * Existing A8D1/A8D2 lifecycle contracts
 * ----------------------------------------------------------
 */

$requesterSections =
    preg_match_all(
        '/data-ticketing-requester-reply(?!-)/',
        $view
    );

$staffSections =
    preg_match_all(
        '/data-ticketing-staff-reply(?!-)/',
        $view
    );

$expect(
    $requesterSections === 1,
    'Requester lifecycle section contract changed.'
);

$expect(
    $staffSections === 1,
    'Staff lifecycle section contract changed.'
);

$expect(
    substr_count(
        $view,
        'data-ticketing-requester-reply-form'
    ) === 1,
    'Requester form contract changed.'
);

$expect(
    substr_count(
        $view,
        'data-ticketing-staff-reply-form'
    ) === 1,
    'Staff form contract changed.'
);


/*
 * ----------------------------------------------------------
 * Obsolete UI
 * ----------------------------------------------------------
 */

$expect(
    !str_contains(
        $view,
        'پیوست در مرحله عملیاتی بعدی'
    ),
    'Obsolete future-operation note remains.'
);


echo
    "TICKETING_DETAIL_INTERACTION_UI_PASS\n";

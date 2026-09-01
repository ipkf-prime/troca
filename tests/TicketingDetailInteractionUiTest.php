<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$viewPath = $root
    . '/public_html/resources/views/admin/'
    . 'ticketing-ticket-detail.php';
$cssPath = $root
    . '/public_html/public/assets/admin/css/'
    . 'ticketing.css';

$view = file_get_contents($viewPath);
$css = file_get_contents($cssPath);

if (!is_string($view) || !is_string($css)) {
    throw new RuntimeException('A8D3 sources cannot be read.');
}

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$exactLineCount = static function (string $text, string $value): int {
    return preg_match_all(
        '/^[ \t]*' . preg_quote($value, '/') . '[ \t]*$/m',
        $text
    );
};

$expect(
    substr_count($view, 'ticketing_detail_a8d3') >= 1
    && substr_count(
        $view,
        'ticketing_detail_a8d3_three_tabs'
    ) === 1,
    'A8D3 View identity changed.'
);
$expect(
    substr_count($css, 'ticketing_detail_a8d3') >= 1
    && substr_count(
        $css,
        'ticketing_detail_a8d3_three_tabs'
    ) === 1,
    'A8D3 CSS identity changed.'
);

foreach ([
    'data-ticketing-detail-tab="status"',
    'data-ticketing-detail-tab="conversation"',
    'data-ticketing-detail-tab="history"',
    'data-ticketing-detail-panel="status"',
    'data-ticketing-detail-panel="conversation"',
    'data-ticketing-detail-panel="history"',
] as $attribute) {
    $expect(
        $exactLineCount($view, $attribute) === 1,
        'Three-tab attribute contract failed: ' . $attribute
    );
}

foreach ([
    'پاسخ و عملیات',
    'تاریخچه',
    'جزئیات',
] as $label) {
    $expect(
        str_contains($view, $label),
        'Detail tab label missing: ' . $label
    );
}

$expect(
    str_contains(
        $view,
        "activate(\n        'conversation'"
    )
    && !str_contains(
        $view,
        "activate(\n        'status'"
    ),
    'Conversation/history must be the default detail tab.'
);

$expect(
    substr_count($view, '::ticketNumberFromRow(') === 1,
    'Ticket number must render exactly once.'
);
$expect(
    !str_contains($view, 'subjectHeading')
    && !str_contains(
        $view,
        'Remove the redundant standalone ticket-number label'
    ),
    'Obsolete ticket-number client hack exists.'
);

foreach ([
    'ticketing-message-bubble',
    'data-ticketing-message-author=',
] as $marker) {
    $expect(
        str_contains($view, $marker),
        'Conversation contract missing: ' . $marker
    );
}
foreach ([
    'data-ticketing-message-author="requester"',
    'data-ticketing-message-author="staff"',
    'content: "درخواست‌کننده";',
    'content: "کارشناس";',
] as $marker) {
    $expect(
        str_contains($css, $marker),
        'Conversation CSS contract missing: ' . $marker
    );
}

$expect(
    preg_match_all(
        '/data-ticketing-requester-reply(?!-)/',
        $view
    ) === 1,
    'Requester lifecycle section count changed.'
);
$expect(
    preg_match_all(
        '/data-ticketing-staff-reply(?!-)/',
        $view
    ) === 1,
    'Staff lifecycle section count changed.'
);
$expect(
    substr_count(
        $view,
        'data-ticketing-requester-reply-form'
    ) === 1
    && substr_count(
        $view,
        'data-ticketing-staff-reply-form'
    ) === 1,
    'Reply form counts changed.'
);

foreach ([
    'data-ticketing-requester-resolve-form',
    'name="attachments[]"',
    'value="update"',
    'value="resolve"',
    'مشکلم حل شد',
    'افزودن توضیح',
    'ticket_requester_updated',
    'ticket_requester_resolved',
] as $marker) {
    $expect(
        str_contains($view, $marker),
        'Requester interaction marker missing: ' . $marker
    );
}

$expect(
    substr_count($view, '$lifecycleActionCsrf') === 4,
    'Independent lifecycle CSRF contract changed.'
);
$expect(
    substr_count(
        $view,
        'enctype="multipart/form-data"'
    ) >= 2
    && substr_count(
        $view,
        'name="attachments[]"'
    ) >= 2,
    'Requester/staff file composers must remain multipart.'
);
$expect(
    !str_contains(
        $view,
        'پیوست در مرحله عملیاتی بعدی'
    ),
    'Obsolete attachment note remains.'
);


/*
 * ----------------------------------------------------------
 * R4A2-R1 requester resolved-update UX
 * ----------------------------------------------------------
 */

foreach ([
    'ticketing_requester_actions_r4a2r1',
    'data-ticketing-requester-reopen-note',
    'این تیکت حل‌شده است.',
    'دوباره به وضعیت «در حال بررسی»',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Requester resolved-update UX missing: '
        . $marker
    );
}

foreach ([
    'ticketing_requester_actions_r4a2r1',
    '[data-ticketing-requester-resolve-form]',
    'margin-block-start: 14px;',
    '.ticketing-requester-reopen-note',
] as $marker) {
    $expect(
        str_contains(
            $css,
            $marker
        ),
        'Requester action spacing CSS missing: '
        . $marker
    );
}

$expect(
    str_contains(
        $view,
        "'resolved',"
    )
    && str_contains(
        $view,
        '$lifecycleRequesterCanUpdate'
    ),
    'Resolved ticket must retain requester update UI.'
);


/*
 * R4A2-R3 unified timeline.
 */

foreach ([
    'ticketing_unified_timeline_r4a2r3',
    'data-ticketing-unified-timeline',
    'data-ticketing-timeline-kind="message"',
    'data-ticketing-event-source',
    'data-ticketing-details-host',
    'buildUnifiedTimeline',
    'buildDetails',
    'localizeFilePickers',
    'انتخاب فایل',
    'فایلی انتخاب نشده است',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Unified Timeline View marker missing: '
        . $marker
    );
}

$expect(
    preg_match(
        '/data-ticketing-detail-tab="history"'
        . '[\s\S]{0,300}'
        . '>\s*جزئیات\s*</u',
        $view
    ) === 1,
    'Third detail tab must be جزئیات.'
);

foreach ([
    'ticketing_unified_timeline_r4a2r3',
    '.ticketing-unified-timeline',
    '.ticketing-timeline-event',
    '.ticketing-timeline-item--message',
    '.ticketing-details-stack',
    '.ticketing-file-picker-control',
    'inline-size: min(100%, 920px);',
    'max-inline-size: none;',
] as $marker) {
    $expect(
        str_contains(
            $css,
            $marker
        ),
        'Unified Timeline CSS marker missing: '
        . $marker
    );
}

$expect(
    str_contains(
        $view,
        'ticketing-message-attachments'
    )
    &&
    str_contains(
        $view,
        'data-ticketing-message-toggle'
    )
    &&
    str_contains(
        $view,
        'data-ticketing-attachment'
    ),
    'Message attachment or expand UX changed.'
);


/*
 * R4A2-R4 Timeline polish.
 */

foreach ([
    'ticketing_timeline_polish_r4a2r4',
    'appendEventRun',
    'ticketingTimelineEventGroup',
    'نمایش ',
    'رویداد میانی',
    'پروژه، موضوع، مرحله، تیم و مسئول جاری رسیدگی',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Timeline polish View marker missing: '
        . $marker
    );
}

$expect(
    !str_contains(
        $view,
        "const metrics =\n"
        . "            document.querySelector(\n"
        . "                '.ticketing-detail-metrics'"
    ),
    'Details tab must not clone duplicate summary metrics.'
);

foreach ([
    'ticketing_timeline_polish_r4a2r4',
    '.ticketing-timeline-event-group',
    '.ticketing-timeline-event-group__summary',
    '.ticketing-timeline-event-group__body',
    'font-size: 12px;',
] as $marker) {
    $expect(
        str_contains(
            $css,
            $marker
        ),
        'Timeline polish CSS marker missing: '
        . $marker
    );
}

echo "TICKETING_DETAIL_INTERACTION_UI_PASS\n";


/*
 * ----------------------------------------------------------
 * R4A final tab-scope regression
 * ----------------------------------------------------------
 */

foreach ([
    'TICKETING_DETAIL_TAB_SCOPE_RECONCILIATION_V2',
    'TICKETING_LEGACY_SUMMARY_RELOCATION_DISABLED',
    'legacySummarySections',
    'detailMetrics',
    'const lifecycleActions =',
    'panelByLabel',
    "'جزئیات'",
    "'پاسخ و عملیات'",
    "'تاریخچه'",
    'generatedDetailsHost.remove();',
    'detailsPanel.append(',
    'ticketing-details-panel',
    'ticketing-details-metrics-block',
    'ticketing-details-summary-block',
    'TICKETING_REAL_TICKET_DETAILS',
    "'مشخصات تیکت'",
    'operationsPanel.append(',
    'historyPanel.append(',
    "data-ticketing-tab-scope-reconciled",
] as $marker) {

    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Final tab-scope marker missing: '
        . $marker
    );
}

$expect(
    !str_contains(
        $view,
        'priorityReplySlot.append(priorityPanel)'
    ),
    'Priority still depends on transient reply slot.'
);

$expect(
    !str_contains(
        $view,
        'Summary sections rendered immediately before the'
    ),
    'Legacy generic SECTION relocation remains.'
);

$expect(
    str_contains(
        $view,
        "detailsPanel.append(\n"
        . "                        detailMetrics"
    ),
    'Metrics are not scoped to Details.'
);

$expect(
    str_contains(
        $view,
        'TICKETING_REAL_TICKET_DETAILS'
    )
    &&
    str_contains(
        $view,
        "'مشخصات تیکت'"
    )
    &&
    str_contains(
        $view,
        'generatedDetailsHost.remove();'
    ),
    'Real ticket details contract is incomplete.'
);

$expect(
    str_contains(
        $view,
        'ticketing-details-panel'
    )
    &&
    str_contains(
        $view,
        'ticketing-details-metrics-block'
    )
    &&
    str_contains(
        $view,
        'ticketing-details-summary-block'
    ),
    'Details spacing View contract is incomplete.'
);


/*
 * CSS spacing contract must be checked against CSS,
 * not against the ticket-detail View.
 */

$ticketingCssPath =
    __DIR__
    . '/../public_html/public/assets/admin/css/ticketing.css';

$expect(
    is_file(
        $ticketingCssPath
    ),
    'Ticketing CSS file is unavailable.'
);

$ticketingCss =
    file_get_contents(
        $ticketingCssPath
    );

$expect(
    is_string(
        $ticketingCss
    ),
    'Ticketing CSS could not be read.'
);

$expect(
    str_contains(
        $ticketingCss,
        'TICKETING_REAL_TICKET_DETAILS_SPACING'
    )
    &&
    str_contains(
        $ticketingCss,
        '.ticketing-details-panel'
    )
    &&
    str_contains(
        $ticketingCss,
        'gap: 18px;'
    )
    &&
    str_contains(
        $ticketingCss,
        '.ticketing-details-summary-block'
    ),
    'Details spacing CSS contract is incomplete.'
);

$expect(
    !str_contains(
        $view,
        'section.remove();'
    ),
    'Authoritative server ticket summary is still removed.'
);

$expect(
    str_contains(
        $view,
        "operationsPanel.append(\n"
        . "                    priorityPanel"
    ),
    'Priority control is not scoped to Operations.'
);

$expect(
    str_contains(
        $view,
        "operationsPanel.append(\n"
        . "                    lifecycleActions"
    ),
    'Lifecycle actions are not scoped to Operations.'
);

$expect(
    str_contains(
        $view,
        "historyPanel.append(\n"
        . "                    priorityHistory"
    ),
    'Priority history is not scoped to History.'
);

echo
    "TICKETING_DETAIL_FINAL_TAB_SCOPE_PASS\n";

<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


$repositoryPath =
    $root
    . '/public_html/app/Repositories/'
    . 'TicketStaffOperationsRepository.php';


$source =
    file_get_contents(
        $repositoryPath
    );


if (!is_string($source)) {
    throw new RuntimeException(
        'unicode_search_repository_unreadable'
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
    str_contains(
        $source,
        'TICKETING_CARTABLE_UTF8_SEARCH_COLLATION_T3E'
    ),
    'unicode search contract marker missing'
);


foreach (
    [
        't.ticket_number',
        't.subject',
        't.support_topic_title_snapshot',
        't.support_project_title_snapshot',
        'p.title',
        'assignee.display_name_snapshot',
        't.requester_display_name_snapshot',
    ]
    as $column
) {
    $marker =
        "CONVERT(\n"
        . "                    "
        . $column
        . "\n"
        . "                    USING utf8mb4\n"
        . "                ) COLLATE utf8mb4_unicode_ci";


    $expect(
        str_contains(
            $source,
            $marker
        ),
        'unicode search conversion missing: '
        . $column
    );
}


$expect(
    substr_count(
        $source,
        'CONVERT(? USING utf8mb4)'
    ) >= 7,
    'unicode search parameter conversion count invalid'
);


$expect(
    substr_count(
        $source,
        'COLLATE utf8mb4_unicode_ci'
    ) >= 14,
    'explicit collation contract incomplete'
);


/*
 * Preserve literal LIKE wildcard escaping.
 */
foreach (
    [
        "'\\\\'",
        "'%'",
        "'_'",
        "'\\\\\\\\'",
        "'\\\\%'",
        "'\\\\_'",
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source,
            $marker
        ),
        'LIKE escaping contract missing: '
        . $marker
    );
}


/*
 * Numeric convenience lookup must remain intact.
 */
foreach (
    [
        '/^0*(\d{1,18})$/',
        'SUBSTRING_INDEX(',
        'AS UNSIGNED',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source,
            $marker
        ),
        'numeric ticket lookup contract missing: '
        . $marker
    );
}


/*
 * Scope / visibility contract must remain present.
 */
foreach (
    [
        'dataScopeClause(',
        'visibleNodeClause(',
        'current_assignee_project_member_id',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source,
            $marker
        ),
        'visibility contract missing: '
        . $marker
    );
}


echo
    "TICKETING_STAFF_CARTABLE_UNICODE_SEARCH_CONTRACT_PASS"
    . PHP_EOL;

echo
    "SEARCH_COLUMNS_NORMALIZED=7"
    . PHP_EOL;

echo
    "TARGET_CHARSET=utf8mb4"
    . PHP_EOL;

echo
    "TARGET_COLLATION=utf8mb4_unicode_ci"
    . PHP_EOL;

echo
    "DATABASE_SCHEMA_CHANGE=NO"
    . PHP_EOL;

echo
    "NUMERIC_LOOKUP=PRESERVED"
    . PHP_EOL;

echo
    "DATA_SCOPE=PRESERVED"
    . PHP_EOL;
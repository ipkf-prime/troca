<?php

declare(strict_types=1);

$servicePath =
    __DIR__
    . '/../public_html/app/Services/Ticketing/'
    . 'TicketStaffOperationsService.php';

$source =
    file_get_contents(
        $servicePath
    );

if (!is_string($source)) {
    throw new RuntimeException(
        'staff_service_unreadable'
    );
}


$assert =
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


$assert(
    str_contains(
        $source,
        'TICKETING_CARTABLE_SUMMARY_COUNTS_V1'
    ),
    'summary_count_contract_marker_missing'
);


$assert(
    !str_contains(
        $source,
        '$countFilters ='
    ),
    'summary_counts_still_inherit_list_filters'
);


$assert(
    str_contains(
        $source,
        "'ticket_status' =>\n                'active'"
    ),
    'summary_active_status_missing'
);


$assert(
    str_contains(
        $source,
        "'q' =>\n                ''"
    ),
    'summary_search_reset_missing'
);


$assert(
    str_contains(
        $source,
        "'priority' =>\n                ''"
    ),
    'summary_priority_reset_missing'
);


$assert(
    str_contains(
        $source,
        "'layer_id' =>\n                0"
    ),
    'summary_layer_reset_missing'
);


$assert(
    str_contains(
        $source,
        "'assignee' =>\n                ''"
    ),
    'summary_assignee_reset_missing'
);


$assert(
    str_contains(
        $source,
        '$scopeFilters ='
        . "\n"
        . '                $summaryFilters;'
    ),
    'summary_scope_contract_missing'
);


echo
    "TICKETING_STAFF_CARTABLE_FILTER_CONTRACT_PASS\n";

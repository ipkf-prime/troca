<?php

declare(strict_types=1);


$path =
    __DIR__
    . '/../public_html/app/Services/Ticketing/'
    . 'TicketingSlaRuntimeService.php';


$source =
    file_get_contents(
        $path
    );


if (!is_string($source)) {
    throw new RuntimeException(
        'sla_runtime_source_unreadable'
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


$marker =
    'TICKETING_SLA_ESCALATION_PAST_DUE_GUARD_V1';


$markerPosition =
    strpos(
        $source,
        $marker
    );


$assert(
    $markerPosition !== false,
    'sla_past_due_guard_marker_missing'
);


$blockStart =
    strrpos(
        substr(
            $source,
            0,
            $markerPosition
        ),
        '/*'
    );


$blockEnd =
    strpos(
        $source,
        '$nodeId =',
        $markerPosition
    );


$assert(
    $blockStart !== false
    &&
    $blockEnd !== false
    &&
    $blockEnd > $blockStart,
    'sla_success_escalation_guard_block_not_found'
);


$block =
    substr(
        $source,
        $blockStart,
        $blockEnd - $blockStart
    );


$assert(
    str_contains(
        $block,
        '$resolutionMetAt === null'
    ),
    'resolution_met_guard_missing'
);


$assert(
    str_contains(
        $block,
        '$resolutionDue > $now'
    ),
    'resolution_future_guard_missing'
);


$assert(
    str_contains(
        $block,
        '$resolutionDue < $repeatAt'
    ),
    'resolution_repeat_order_guard_missing'
);


$futurePosition =
    strpos(
        $block,
        '$resolutionDue > $now'
    );

$earlierPosition =
    strpos(
        $block,
        '$resolutionDue < $repeatAt'
    );


$assert(
    $futurePosition !== false
    &&
    $earlierPosition !== false
    &&
    $futurePosition < $earlierPosition,
    'resolution_future_guard_order_invalid'
);


$unsafe =
<<<'UNSAFE'
            if (
                $resolutionMetAt === null
                &&
                $resolutionDue < $repeatAt
            ) {
UNSAFE;


$assert(
    !str_contains(
        $source,
        $unsafe
    ),
    'unsafe_past_resolution_override_still_present'
);


echo
    "TICKETING_SLA_ESCALATION_PAST_DUE_GUARD_CONTRACT_PASS\n";

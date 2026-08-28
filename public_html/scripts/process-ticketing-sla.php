<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}


define(
    'BASE_PATH',
    dirname(__DIR__)
);

require
    BASE_PATH
    . '/bootstrap/app.php';

restore_error_handler();
restore_exception_handler();


$apply = false;
$limit = 100;


foreach ($argv as $argument) {

    if ($argument === '--apply') {
        $apply = true;
        continue;
    }


    if (
        str_starts_with(
            $argument,
            '--limit='
        )
    ) {
        $limit =
            max(
                1,
                min(
                    500,
                    (int) substr(
                        $argument,
                        8
                    )
                )
            );
    }
}


$service =
    new \App\Services\Ticketing\TicketingSlaService();


$result =
    $service->initializeEligible(
        $limit,
        $apply
    );


echo
    'MODE='
    . (
        $apply
            ? 'APPLY'
            : 'DRY_RUN'
    )
    . PHP_EOL;


foreach ([
    'scanned',
    'eligible',
    'initialized',
    'skipped',
] as $field) {

    echo
        strtoupper($field)
        . '='
        . (int) (
            $result[$field]
            ?? 0
        )
        . PHP_EOL;
}


foreach (
    $result['items']
        ?? []
    as $item
) {

    echo
        'ITEM='
        . json_encode(
            $item,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        )
        . PHP_EOL;
}


/*
 * A8A intentionally performs only SLA enrollment.
 *
 * Breach processing, pause/resume and automatic graph
 * escalation are enabled in A8B after browser/DB E2E.
 */
echo "AUTO_ESCALATION=A8B\n";

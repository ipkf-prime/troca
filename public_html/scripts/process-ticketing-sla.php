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
$limit = 200;

$runtimeOnly = false;
$enrollOnly = false;


foreach ($argv as $argument) {

    if ($argument === '--apply') {
        $apply = true;
        continue;
    }


    if ($argument === '--runtime-only') {
        $runtimeOnly = true;
        continue;
    }


    if ($argument === '--enroll-only') {
        $enrollOnly = true;
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
                    1000,
                    (int) substr(
                        $argument,
                        8
                    )
                )
            );
    }
}


if (
    $runtimeOnly
    &&
    $enrollOnly
) {
    fwrite(
        STDERR,
        "runtime-only and enroll-only are mutually exclusive.\n"
    );

    exit(2);
}


$mode =
    $apply
        ? 'APPLY'
        : 'DRY_RUN';


echo
    'MODE='
    . $mode
    . PHP_EOL;


if (!$runtimeOnly) {

    $enrollment =
        (
            new \App\Services\Ticketing\TicketingSlaService()
        )->initializeEligible(
            $limit,
            $apply
        );


    echo
        'ENROLL_SCANNED='
        . (int) (
            $enrollment[
                'scanned'
            ]
            ?? 0
        )
        . PHP_EOL;

    echo
        'ENROLL_ELIGIBLE='
        . (int) (
            $enrollment[
                'eligible'
            ]
            ?? 0
        )
        . PHP_EOL;

    echo
        'ENROLL_INITIALIZED='
        . (int) (
            $enrollment[
                'initialized'
            ]
            ?? 0
        )
        . PHP_EOL;

    echo
        'ENROLL_SKIPPED='
        . (int) (
            $enrollment[
                'skipped'
            ]
            ?? 0
        )
        . PHP_EOL;


    foreach (
        $enrollment['items']
            ?? []
        as $item
    ) {

        echo
            'ENROLL_ITEM='
            . json_encode(
                $item,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
            . PHP_EOL;
    }
}


if (!$enrollOnly) {

    $runtime =
        (
            new \App\Services\Ticketing\TicketingSlaRuntimeService()
        )->process(
            $limit,
            $apply
        );


    foreach ([
        'scanned',
        'paused',
        'resumed',
        'response_met',
        'response_breached',
        'resolution_met',
        'resolution_breached',
        'auto_escalated',
        'auto_escalation_blocked',
        'completed',
    ] as $field) {

        echo
            'RUNTIME_'
            . strtoupper(
                $field
            )
            . '='
            . (int) (
                $runtime[$field]
                ?? 0
            )
            . PHP_EOL;
    }


    foreach (
        $runtime['items']
            ?? []
        as $item
    ) {

        echo
            'RUNTIME_ITEM='
            . json_encode(
                $item,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
            . PHP_EOL;
    }
}


echo "AUTO_ESCALATION=ENABLED\n";
echo "SCHEDULER=EXTERNAL\n";

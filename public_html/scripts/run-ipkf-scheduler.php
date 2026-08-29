<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
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

$options =
    getopt(
        '',
        [
            'application:',
            'limit::',
            'sync-only',
        ]
    );

$application =
    strtolower(
        trim(
            (string) (
                $options['application']
                ?? ''
            )
        )
    );

if ($application === '') {
    fwrite(
        STDERR,
        "APPLICATION_REQUIRED\n"
    );

    exit(2);
}

$engine =
    \IPKF\Scheduler\SchedulerRuntime::engine(
        $application
    );

if (
    array_key_exists(
        'sync-only',
        $options
    )
) {
    $engine->synchronize();

    echo
        "SCHEDULER_SYNC=PASS\n"
        . "APPLICATION={$application}\n";

    exit(0);
}

$limit =
    max(
        1,
        min(
            100,
            (int) (
                $options['limit']
                ?? 20
            )
        )
    );

$result =
    $engine->runDue(
        $limit
    );

echo
    json_encode(
        $result,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    )
    . PHP_EOL;

exit(
    (int) (
        $result['failed']
        ?? 0
    ) > 0
        ? 1
        : 0
);

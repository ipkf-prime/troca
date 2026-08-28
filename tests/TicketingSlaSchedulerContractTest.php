<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$worker =
    file_get_contents(
        $root
        . '/public_html/scripts/'
        . 'process-ticketing-sla.php'
    );


$wrapper =
    file_get_contents(
        $root
        . '/public_html/scripts/'
        . 'run-ticketing-sla-scheduled.sh'
    );


if (
    !is_string($worker)
    ||
    !is_string($wrapper)
) {
    throw new RuntimeException(
        'Cannot read SLA scheduler sources.'
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
        $worker,
        'SCHEDULER=EXTERNAL'
    ),
    'Worker scheduler contract missing.'
);


$expect(
    !str_contains(
        $worker,
        'CRON=NOT_ENABLED'
    ),
    'Worker must not claim cron is disabled.'
);


foreach ([
    '#!/usr/bin/env bash',
    'process-ticketing-sla.php',
    '--apply',
    '--limit=200',
    'TICKETING_SLA_SCHEDULED_START',
    'TICKETING_SLA_SCHEDULED_END',
    'ticketing-sla-worker.log',
    'MAX_LOG_BYTES',
    '20971520',
    'php_not_found',
    'worker_missing',
] as $marker) {

    $expect(
        str_contains(
            $wrapper,
            $marker
        ),
        'Scheduler wrapper marker missing: '
        . $marker
    );
}


$expect(
    !str_contains(
        $wrapper,
        'crontab'
    ),
    'Runtime wrapper must not modify crontab.'
);


echo
    "TICKETING_SLA_SCHEDULER_CONTRACT_PASS\n";

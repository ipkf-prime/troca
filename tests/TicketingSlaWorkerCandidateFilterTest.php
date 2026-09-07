<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$content =
    file_get_contents(
        $root
        . '/public_html/app/Repositories/'
        . 'TicketingSlaRepository.php'
    );


if (!is_string($content)) {
    throw new RuntimeException(
        'Cannot read TicketingSlaRepository.'
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


foreach ([
    'AND EXISTS',
    'ticketing_sla_policies candidate_policy',
    'ticketing_business_calendars candidate_calendar',
    'candidate_policy.effective_from_at',
    '<= t.created_at',
    'candidate_policy.effective_to_at',
    'candidate_policy.project_id',
    'candidate_policy.service_id',
    'candidate_policy.topic_id',
    'candidate_policy.queue_id',
] as $marker) {

    $expect(
        str_contains(
            $content,
            $marker
        ),
        'Enrollment candidate filter missing: '
        . $marker
    );
}


foreach ([
    "ss.state_code =\n                            'paused'",
    't.first_response_at',
    't.resolved_at',
    't.closed_at',
    's.is_closed = 1',
    'ss.next_action_at',
    '<= UTC_TIMESTAMP()',
    'ss.response_due_at',
    'ss.resolution_due_at',
    'p.pause_statuses_json',
    'LOCATE(',
] as $marker) {

    $expect(
        str_contains(
            $content,
            $marker
        ),
        'Runtime actionable filter missing: '
        . $marker
    );
}


/*
 * A maxed state with next_action_at=NULL must not be
 * continuously selected merely because state_code=breached.
 *
 * But explicit resolution_due_at remains an actionable signal
 * so the later Resolution SLA breach cannot be lost.
 */
$expect(
    !str_contains(
        $content,
        "OR ss.state_code = 'breached'"
    ),
    'Breached state must not be unconditionally actionable.'
);


echo
    "TICKETING_SLA_WORKER_CANDIDATE_FILTER_PASS\n";

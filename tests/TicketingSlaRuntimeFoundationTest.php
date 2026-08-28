<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$read =
    static function (
        string $relative
    ) use ($root): string {

        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Cannot read '
                . $relative
            );
        }

        return $content;
    };


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


$staff =
    $read(
        'public_html/app/Repositories/'
        . 'TicketStaffOperationsRepository.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketingSlaRepository.php'
    );

$runtime =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketingSlaRuntimeService.php'
    );

$worker =
    $read(
        'public_html/scripts/'
        . 'process-ticketing-sla.php'
    );


foreach ([
    'public function escalateSystem(',
    'private function executeEscalation(',
    "'system:ticketing-sla'",
    "'موتور SLA تیکتینگ'",
    "'sla-auto-escalation'",
    '$this->nextEscalationRelation(',
    '$this->routeForNode(',
    '$this->leastLoadedMember(',
    '$this->replaceAssignment(',
] as $marker) {

    $expect(
        str_contains(
            $staff,
            $marker
        ),
        'Shared A7 escalation marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $staff,
        "'manual-escalation'"
    ),
    'Manual escalation reason was lost.'
);


foreach ([
    'runtimeCandidates(',
    'markResponseMet(',
    'markResponseBreached(',
    'markResolutionMet(',
    'markResolutionBreached(',
    'pauseState(',
    'resumeState(',
    'markAutoEscalated(',
    'scheduleNextAction(',
    'currentTicketRouting(',
    'recordSlaEvent(',
] as $marker) {

    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'SLA repository runtime marker missing: '
        . $marker
    );
}


foreach ([
    'sla_paused',
    'sla_resumed',
    'sla_response_met',
    'sla_response_breached',
    'sla_resolution_met',
    'sla_resolution_breached',
    'sla_auto_escalated',
    'sla_auto_escalation_blocked',
    'pause_statuses_json',
    'escalateSystem(',
    'businessMinutesBetween(',
    'addBusinessMinutes(',
] as $marker) {

    $expect(
        str_contains(
            $runtime,
            $marker
        ),
        'SLA runtime marker missing: '
        . $marker
    );
}


foreach ([
    '--apply',
    '--runtime-only',
    '--enroll-only',
    'TicketingSlaRuntimeService',
    'AUTO_ESCALATION=ENABLED',
    'CRON=NOT_ENABLED',
] as $marker) {

    $expect(
        str_contains(
            $worker,
            $marker
        ),
        'SLA worker marker missing: '
        . $marker
    );
}


$expect(
    !str_contains(
        $worker,
        'crontab'
    ),
    'Worker must not mutate crontab.'
);


$expect(
    !str_contains(
        $runtime,
        'ticketing_support_node_relations'
    )
    &&
    !str_contains(
        $runtime,
        'ticketing_support_team_queues'
    )
    &&
    !str_contains(
        $runtime,
        'ticketing_support_team_nodes'
    )
    &&
    !str_contains(
        $runtime,
        'ticketing_support_team_members'
    ),
    'SLA runtime must not duplicate A7 routing engine.'
);


echo
    "TICKETING_SLA_RUNTIME_FOUNDATION_PASS\n";

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


$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateTicketingSlaFoundation.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketingSlaRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketingSlaService.php'
    );

$worker =
    $read(
        'public_html/scripts/'
        . 'process-ticketing-sla.php'
    );


foreach ([
    'ticketing_business_calendars',
    'ticketing_business_calendar_hours',
    'ticketing_business_calendar_exceptions',
    'ticketing_sla_policies',
    'ticketing_ticket_sla_states',
    'ticketing_sla_events',

    'response_minutes',
    'resolution_minutes',
    'pause_statuses_json',
    'breach_action_code',
    'max_auto_escalations',
    'next_action_at',

    'Asia/Tehran',

    'waiting_requester',

    "'low'",
    "'normal'",
    "'high'",
    "'urgent'",
] as $marker) {

    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'SLA migration marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $registry,
        'CreateTicketingSlaFoundation::class'
    ),
    'A8 migration not registered.'
);


foreach ([
    'initializationCandidates(',
    'policyForTicket(',
    'calendar(',
    'createState(',
    'ticketing.primary',
] as $marker) {

    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'SLA repository marker missing: '
        . $marker
    );
}


foreach ([
    'initializeEligible(',
    'addBusinessMinutes(',
    'response_due_at',
    'resolution_due_at',
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'SLA service marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $worker,
        "PHP_SAPI !== 'cli'"
    )
    &&
    str_contains(
        $worker,
        '--apply'
    )
    &&
    str_contains(
        $worker,
        'AUTO_ESCALATION=ENABLED'
    ),
    'Safe SLA worker contract missing.'
);


foreach ([
    'np-',
    'np_',
    'اتحادیه',
    'نهاده',
] as $forbidden) {

    $expect(
        !str_contains(
            mb_strtolower(
                $migration,
                'UTF-8'
            ),
            mb_strtolower(
                $forbidden,
                'UTF-8'
            )
        ),
        'Project-specific SLA hardcode found: '
        . $forbidden
    );
}


require_once
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'BusinessCalendarService.php';


$calendarService =
    new \App\Services\Ticketing\BusinessCalendarService();


$calendar = [
    'timezone' => 'UTC',

    'hours' => [
        [
            'weekday_iso' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_working' => 1,
        ],

        [
            'weekday_iso' => 2,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_working' => 1,
        ],
    ],

    'exceptions' => [],
];


$start =
    new DateTimeImmutable(
        '2026-08-24 16:30:00',
        new DateTimeZone('UTC')
    );


$due =
    $calendarService
        ->addBusinessMinutes(
            $start,
            120,
            $calendar
        );


$expect(
    $due->format(
        'Y-m-d H:i:s'
    )
    ===
    '2026-08-25 10:30:00',
    'Business-hour carryover calculation failed.'
);


$minutes =
    $calendarService
        ->businessMinutesBetween(
            $start,
            $due,
            $calendar
        );


$expect(
    $minutes === 120,
    'Business-minute interval calculation failed.'
);


$holidayCalendar =
    $calendar;

$holidayCalendar['exceptions'] = [
    [
        'exception_date' =>
            '2026-08-24',

        'exception_type_code' =>
            'holiday',

        'segment_order' =>
            0,

        'start_time' =>
            null,

        'end_time' =>
            null,
    ],
];


$holidayDue =
    $calendarService
        ->addBusinessMinutes(
            new DateTimeImmutable(
                '2026-08-24 10:00:00',
                new DateTimeZone(
                    'UTC'
                )
            ),
            60,
            $holidayCalendar
        );


$expect(
    $holidayDue->format(
        'Y-m-d H:i:s'
    )
    ===
    '2026-08-25 10:00:00',
    'Holiday exclusion calculation failed.'
);


echo
    "TICKETING_SLA_FOUNDATION_PASS\n";

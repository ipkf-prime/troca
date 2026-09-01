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
                'Unreadable: '
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


$access =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketStaffReplyAccessService.php'
    );

$staff =
    $read(
        'public_html/app/Repositories/'
        . 'TicketStaffOperationsRepository.php'
    );

$routing =
    $read(
        'public_html/app/Repositories/'
        . 'TicketCreateRoutingRepository.php'
    );

$detail =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );


foreach ([
    'TICKETING_OPERATIONAL_STAFF_ROLE_ALIGNMENT',
    'assignee_project_role_code',
    'assignee_team_member_id',
    'assignee_staff_role_code',
    'tickets.current_support_team_id',
    "'member'",
    "'manager'",
    "'agent'",
    "'supervisor'",
    'reply_assignment_invalid',
] as $marker) {

    $expect(
        str_contains(
            $access,
            $marker
        ),
        'Reply operational staff marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $staff,
        'TICKETING_OPERATIONAL_PROJECT_ROLE_FILTER'
    ),
    'Staff operations role marker missing.'
);

$expect(
    substr_count(
        $staff,
        "pm.role_code IN ('member', 'manager')"
    ) >= 3,
    'Staff operations do not enforce project staff role.'
);


$expect(
    str_contains(
        $routing,
        'TICKETING_ROUTING_OPERATIONAL_STAFF_ROLE_FILTER'
    ),
    'Routing role marker missing.'
);

$expect(
    substr_count(
        $routing,
        "pm.role_code IN ('member', 'manager')"
    ) >= 3,
    'Automatic routing does not enforce staff project role.'
);


$expect(
    str_contains(
        $detail,
        'نقش پروژه یا عضویت تیم کارشناس'
    ),
    'Invalid assignment UI is not role-aware.'
);


echo
    "TICKETING_OPERATIONAL_STAFF_ROLE_ALIGNMENT_PASS\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once
    $root
    . '/public_html/app/Support/'
    . 'AdminFormat.php';

require_once
    $root
    . '/public_html/app/Support/'
    . 'TicketingDisplay.php';

use App\Support\TicketingDisplay;

$read =
    static function (
        string $relative
    ) use ($root): string {
        $value =
            file_get_contents(
                $root . '/' . $relative
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'Unreadable source: '
                . $relative
            );
        }

        return $value;
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


$display =
    $read(
        'public_html/app/Support/'
        . 'TicketingDisplay.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketRepository.php'
    );

$detail =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );

$list =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-tickets.php'
    );

$dashboard =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-dashboard.php'
    );

$topology =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-topology.php'
    );


$expect(
    TicketingDisplay::ticketNumberFromRow([
        'ticket_number' =>
            'NP-000005',

        'support_project_title_snapshot' =>
            'سامانه نهاده پخش (نپ)',

        'project_title' =>
            'عنوان تغییرکرده',
    ]) === 'نپ-۰۰۰۰۰۵',
    'Persian project-aware ticket number is invalid.'
);


$expect(
    TicketingDisplay::statusTitle(
        'new'
    ) === 'جدید',
    'New status is not Persian.'
);

$expect(
    TicketingDisplay::statusTitle(
        'in_progress'
    ) === 'در حال بررسی',
    'In-progress status is not Persian.'
);

$expect(
    TicketingDisplay::eventTitle(
        'ticket_routed'
    ) === 'تیکت مسیریابی شد',
    'Routing event is not Persian.'
);

$expect(
    TicketingDisplay::eventTitle(
        'ticket_assigned'
    ) ===
        'تیکت به کارشناس تخصیص یافت',
    'Assignment event is not Persian.'
);

$expect(
    TicketingDisplay::eventTitle(
        'unknown_event_code'
    ) !== 'unknown_event_code',
    'Unknown event exposes its raw code.'
);

$expect(
    TicketingDisplay::assignmentModeTitle(
        'least_loaded'
    ) === 'کم‌بارترین کارشناس',
    'Assignment mode is not Persian.'
);

$expect(
    TicketingDisplay::staffRoleTitle(
        'agent'
    ) === 'کارشناس',
    'Staff role is not Persian.'
);


foreach ([
    'ticket_created',
    'ticket_routed',
    'ticket_assigned',
    'ticket_reassigned',
    'ticket_taken_over',
    'ticket_transferred',
    'ticket_escalated',
    'ticket_status_changed',
    'ticket_resolved',
    'ticket_closed',
    'ticket_reopened',
] as $eventCode) {
    $expect(
        str_contains(
            $display,
            "'{$eventCode}'"
        ),
        'Missing Persian event contract: '
        . $eventCode
    );
}


foreach ([
    't.ticket_number LIKE ?',
    "SUBSTRING_INDEX(",
    't.support_project_title_snapshot',
    "'۰' => '0'",
    "'٠' => '0'",
] as $marker) {
    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Flexible search marker missing: '
        . $marker
    );
}


foreach ([
    $detail,
    $list,
    $dashboard,
] as $view) {
    $expect(
        str_contains(
            $view,
            'TicketingDisplay::ticketNumberFromRow'
        ),
        'A ticket-number view bypasses Persian display.'
    );
}


$expect(
    str_contains(
        $detail,
        'TicketingDisplay::eventTitle'
    )
    && str_contains(
        $detail,
        'TicketingDisplay::statusTitle'
    )
    && !str_contains(
        $detail,
        '?? $eventCode'
    ),
    'Detail event/status localization is incomplete.'
);


$expect(
    str_contains(
        $topology,
        'TicketingDisplay::assignmentModeTitle'
    )
    && str_contains(
        $topology,
        'TicketingDisplay::staffRoleTitle'
    )
    && !str_contains(
        $topology,
        "ticketing_h(\$queue['assignment_mode_code'])"
    )
    && !str_contains(
        $topology,
        "ticketing_h(\$member['staff_role_code'])"
    ),
    'Topology visible technical codes remain.'
);


echo
    "TICKETING_PERSIAN_DISPLAY_CONTRACT_PASS\n";

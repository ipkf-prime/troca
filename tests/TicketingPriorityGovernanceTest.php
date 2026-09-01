<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $relative) use ($root): string {
    $text = file_get_contents($root . '/' . $relative);

    if (!is_string($text)) {
        throw new RuntimeException(
            'Cannot read: ' . $relative
        );
    }

    return $text;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$priorityRepository = $read(
    'public_html/app/Repositories/'
    . 'TicketPriorityManagementRepository.php'
);
$priorityService = $read(
    'public_html/app/Services/Ticketing/'
    . 'TicketPriorityManagementService.php'
);
$staffRepository = $read(
    'public_html/app/Repositories/'
    . 'TicketStaffOperationsRepository.php'
);
$ticketRepository = $read(
    'public_html/app/Repositories/'
    . 'TicketRepository.php'
);
$routes = $read(
    'public_html/routes/ticketing-runtime.php'
);
$detail = $read(
    'public_html/resources/views/admin/'
    . 'ticketing-ticket-detail.php'
);
$css = $read(
    'public_html/public/assets/admin/css/'
    . 'ticketing.css'
);

$expect(
    preg_match(
        "/'priority'\\s*=>\\s*'p\\.severity'/s",
        $ticketRepository
    ) === 1,
    'TicketRepository priority severity sort map missing.'
);

$priorityOrder = strpos(
    $staffRepository,
    'pr.severity DESC'
);
$activityOrder = strpos(
    $staffRepository,
    't.last_activity_at DESC',
    $priorityOrder === false ? 0 : $priorityOrder
);
$idOrder = strpos(
    $staffRepository,
    't.id DESC',
    $activityOrder === false ? 0 : $activityOrder
);

$expect(
    $priorityOrder !== false
    && $activityOrder !== false
    && $idOrder !== false
    && $priorityOrder < $activityOrder
    && $activityOrder < $idOrder,
    'Staff cartable order is not priority/activity/id.'
);

foreach ([
    'ticket_priority_changed',
    'ticketing_priorities',
    'ticketing_support_team_members',
    "pm.role_code IN (",
    "tm.staff_role_code IN (",
    'FOR UPDATE',
    'priority_code_snapshot',
    'ticketing_ticket_sla_states',
    'sla_recalculation_required',
    'sla_recalculation_performed',
    "'reason' => \$reason",
] as $marker) {
    $expect(
        str_contains($priorityRepository, $marker),
        'Priority repository marker missing: ' . $marker
    );
}

$expect(
    !str_contains(
        $priorityRepository,
        'UPDATE ticketing_ticket_sla_states'
    ),
    'Priority governance must not mutate SLA state.'
);

$updateStart = strpos(
    $priorityRepository,
    'UPDATE ticketing_tickets'
);
$updateEnd = strpos(
    $priorityRepository,
    'WHERE id = ?',
    $updateStart === false ? 0 : $updateStart
);

$expect(
    $updateStart !== false
    && $updateEnd !== false,
    'Priority ticket update statement missing.'
);

$updateBlock = substr(
    $priorityRepository,
    $updateStart,
    $updateEnd - $updateStart
);

foreach ([
    'priority_code',
    'updated_by_user_reference',
    'updated_at',
] as $marker) {
    $expect(
        str_contains($updateBlock, $marker),
        'Priority update field missing: ' . $marker
    );
}

foreach ([
    'status_code',
    'last_activity_at',
    'current_support_',
    'current_assignee',
    'matched_routing_rule',
    'resolved_at',
    'closed_at',
] as $forbidden) {
    $expect(
        !str_contains($updateBlock, $forbidden),
        'Priority update mutates forbidden field: '
        . $forbidden
    );
}

$expect(
    str_contains(
        $priorityService,
        "'ticketing.ticket.reply'"
    )
    && !str_contains(
        $priorityService,
        'TicketingSla'
    ),
    'Priority authorization/SLA service contract invalid.'
);

foreach ([
    '/admin/ticketing/tickets/{public_reference}/priority',
    'TICKETING_PRIORITY_GOVERNANCE_ROUTE',
    'TicketPriorityManagementService',
    'priority_reason',
    '/admin/ticketing/tickets/{public_reference}/reply',
] as $marker) {
    $expect(
        str_contains($routes, $marker),
        'Priority route marker missing: ' . $marker
    );
}

$expect(
    preg_match(
        '/\$request->input\(\s*\'sort1\'\s*,\s*\'priority\'\s*\)/s',
        $routes
    ) === 1
    && preg_match(
        '/\$request->input\(\s*\'sort2\'\s*,\s*\'last_activity\'\s*\)/s',
        $routes
    ) === 1,
    'General ticket default sorting is not priority/activity.'
);

foreach ([
    'TICKETING_PRIORITY_GOVERNANCE',
    'data-ticketing-priority-governance',
    'name="priority_reason"',
    'ثبت تغییر اولویت',
    'ticket_priority_changed',
    'تغییر اولویت',
    'priorityGovernanceHistory',
    'data-ticketing-priority-relocate',
    'نیازمند بازبینی SLA',
] as $marker) {
    $expect(
        str_contains($detail, $marker),
        'Priority detail marker missing: ' . $marker
    );
}

$expect(
    str_contains(
        $css,
        'TICKETING_PRIORITY_GOVERNANCE_STYLES'
    )
    && str_contains(
        $css,
        '.ticketing-priority-governance'
    ),
    'Priority governance CSS contract missing.'
);

echo "TICKETING_PRIORITY_GOVERNANCE_PASS\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $relative) use ($root): string {
    $text = file_get_contents($root . '/' . $relative);

    if (!is_string($text)) {
        throw new RuntimeException('Unreadable: ' . $relative);
    }

    return $text;
};

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$transitionRepository = $read(
    'public_html/app/Repositories/'
    . 'TicketLifecycleTransitionRepository.php'
);
$transitionService = $read(
    'public_html/app/Services/Ticketing/'
    . 'TicketLifecycleTransitionService.php'
);
$lifecycleRepository = $read(
    'public_html/app/Repositories/TicketLifecycleRepository.php'
);
$runtime = $read(
    'public_html/routes/ticketing-runtime.php'
);
$view = $read(
    'public_html/resources/views/admin/ticketing-ticket-detail.php'
);

foreach ([
    'TICKETING_RESOLVE_CLOSE_REOPEN_DOMAIN',
    'public function capabilities(',
    'public function transition(',
    "'resolve'",
    "'close'",
    "'reopen'",
    "'ticket_resolved'",
    "'ticket_closed'",
    "'ticket_reopened'",
    "'resolved'",
    "'closed'",
    "'in_progress'",
    'resolved_at',
    'closed_at',
    'FOR UPDATE',
    'current_assignee_project_member_id',
    'actor_project_role_code',
    'actor_staff_role_code',
    'assignment_preserved',
    'routing_preserved',
    "reference('TEVT')",
] as $marker) {
    $expect(
        str_contains(
            $transitionRepository,
            $marker
        ),
        'Lifecycle repository marker missing: '
        . $marker
    );
}

foreach ([
    'TicketLifecycleTransitionRepository',
    'public function capabilities(',
    'public function transition(',
    'lifecycle_owner_required',
    'lifecycle_resolve_first',
    'lifecycle_reopen_forbidden',
] as $marker) {
    $expect(
        str_contains(
            $transitionService,
            $marker
        ),
        'Lifecycle service marker missing: '
        . $marker
    );
}

foreach ([
    '/resolve',
    '/close',
    '/reopen',
    '$lifecycleActionCsrf',
] as $marker) {
    $expect(
        str_contains($runtime . $view, $marker),
        'Existing lifecycle runtime/UI missing: ' . $marker
    );
}

foreach ([
    'public function requesterResolve(',
    'FOR UPDATE',
    'hash_equals(',
    'ticket_requester_resolved',
    'resolved_at = COALESCE(',
    'requester_resolve_forbidden_state',
    "'closed'",
    "'cancelled'",
    'already_resolved',
    'assignment_preserved',
] as $marker) {
    $expect(
        str_contains($lifecycleRepository, $marker),
        'Requester resolve contract missing: ' . $marker
    );
}

$expect(
    str_contains($view, 'مشکلم حل شد')
    && str_contains(
        $view,
        'data-ticketing-requester-resolve-form'
    ),
    'Requester resolve action is missing from detail UI.'
);

echo "TICKETING_RESOLVE_CLOSE_REOPEN_LIFECYCLE_PASS\n";

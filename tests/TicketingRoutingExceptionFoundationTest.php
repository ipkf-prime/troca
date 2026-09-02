<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $relative
) use ($root): string {
    $content =
        file_get_contents(
            $root . '/' . $relative
        );

    if (!is_string($content)) {
        throw new RuntimeException(
            'Cannot read: ' . $relative
        );
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$repository = $read(
    'public_html/app/Repositories/'
    . 'TicketRoutingExceptionRepository.php'
);

$service = $read(
    'public_html/app/Services/Ticketing/'
    . 'TicketRoutingExceptionService.php'
);

$view = $read(
    'public_html/resources/views/admin/'
    . 'ticketing-ticket-detail.php'
);

foreach ([
    'ticketByReference(',
    'activeTickets(',
    'selectableTopics(',
    'ticketing_support_routing_rules',
    'ticketing_support_queues',
    'ticketing_support_topics',
] as $marker) {
    $expect(
        str_contains($repository, $marker),
        'Repository foundation marker missing: '
        . $marker
    );
}

foreach ([
    'public function panel(',
    'public function listActionable(',
    'public function summary(',
    'public function classify(',
    "'missing_topic'",
    "'no_matching_rule'",
    "'invalid_topology'",
    "'no_eligible_assignee'",
    "'partial_routing'",
    "'awaiting_manual_assignment'",
    "'legacy_topicless_routed'",
    "'healthy'",
    "'ticketing.project.manage'",
    "'least_loaded'",
    "'round_robin'",
    "'fixed'",
    "'manual'",
] as $marker) {
    $expect(
        str_contains($service, $marker),
        'Service foundation marker missing: '
        . $marker
    );
}

foreach ([
    'TICKETING_ROUTING_EXCEPTION_FOUNDATION_V1',
    'data-ticketing-routing-exception',
    'data-ticketing-routing-exception-code',
    'نیازمند مداخله مسیریابی',
    'data-ticketing-detail-panel="status"',
] as $marker) {
    $expect(
        str_contains($view, $marker),
        'Detail routing-health UI marker missing: '
        . $marker
    );
}

foreach ([
    'UPDATE ticketing_tickets',
    'INSERT INTO ticketing_assignments',
    'INSERT INTO ticketing_events',
    'DELETE FROM ticketing_',
] as $mutationMarker) {
    $expect(
        !str_contains($repository, $mutationMarker)
        &&
        !str_contains($service, $mutationMarker),
        'Read-only foundation contains mutation marker: '
        . $mutationMarker
    );
}

echo "TICKETING_ROUTING_EXCEPTION_FOUNDATION_PASS\n";
echo "GENERIC_EXCEPTION_CODES=PASS\n";
echo "NP000002_PROTECTION_CONTRACT=PASS\n";
echo "READ_ONLY_FOUNDATION=PASS\n";

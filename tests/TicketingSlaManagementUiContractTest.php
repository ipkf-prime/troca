<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$files = [
    'repository' =>
        'public_html/app/Repositories/'
        . 'TicketingSlaPolicyAdminRepository.php',

    'service' =>
        'public_html/app/Services/Ticketing/'
        . 'TicketingSlaPolicyAdminService.php',

    'view' =>
        'public_html/resources/views/admin/'
        . 'ticketing-sla-management.php',

    'runtime' =>
        'public_html/routes/'
        . 'ticketing-runtime.php',

    'projects' =>
        'public_html/resources/views/admin/'
        . 'ticketing-projects.php',
];

$content = [];

foreach (
    $files
    as $name => $relative
) {
    $value =
        file_get_contents(
            $root
            . '/'
            . $relative
        );

    if (!is_string($value)) {
        throw new RuntimeException(
            'cannot_read:'
            . $name
        );
    }

    $content[$name] =
        $value;
}


foreach ([
    'TICKETING_SLA_VERSIONED_POLICY_WRITE_V1',
    'effective_to_at',
    "status = 'inactive'",
    'project_id <=> ?',
    'service_id <=> ?',
    'topic_id <=> ?',
    'queue_id <=> ?',
    'UTC_TIMESTAMP()',
] as $marker) {

    if (
        !str_contains(
            $content[
                'repository'
            ],
            $marker
        )
    ) {
        throw new RuntimeException(
            'repository_marker_missing:'
            . $marker
        );
    }
}


foreach ([
    "'global'",
    "'project'",
    "'service'",
    "'topic'",
    "'queue'",
    "'waiting_requester'",
    "'escalate'",
    "'none'",
    'createVersion(',
    'scopeKey(',
] as $marker) {

    if (
        !str_contains(
            $content[
                'service'
            ],
            $marker
        )
    ) {
        throw new RuntimeException(
            'service_marker_missing:'
            . $marker
        );
    }
}


foreach ([
    'TICKETING_SLA_MANAGEMENT_UI_V1',
    "'/admin/ticketing/sla'",
    "'/admin/ticketing/sla/{public_reference}/disable'",
    'new \IPKF\Security\Csrf()',
    "'/admin/ticketing/projects'",
] as $marker) {

    if (
        !str_contains(
            $content[
                'runtime'
            ],
            $marker
        )
    ) {
        throw new RuntimeException(
            'route_marker_missing:'
            . $marker
        );
    }
}


foreach ([
    'data-ticketing-sla-management',
    'scope_type',
    'project_id',
    'service_id',
    'topic_id',
    'queue_id',
    'priority_code',
    'calendar_id',
    'response_minutes',
    'resolution_minutes',
    'pause_statuses[]',
    'auto_escalate',
    'max_auto_escalations',
    'escalation_repeat_minutes',
    '/admin/system/scheduler',
] as $marker) {

    if (
        !str_contains(
            $content[
                'view'
            ],
            $marker
        )
    ) {
        throw new RuntimeException(
            'view_marker_missing:'
            . $marker
        );
    }
}


if (
    !str_contains(
        $content['projects'],
        'TICKETING_SLA_MANAGEMENT_ENTRY_V1'
    )
    ||
    !str_contains(
        $content['projects'],
        'href="/admin/ticketing/sla"'
    )
) {
    throw new RuntimeException(
        'project_sla_entry_missing'
    );
}


echo
    "TICKETING_SLA_MANAGEMENT_UI_CONTRACT_PASS"
    . PHP_EOL;

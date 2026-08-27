<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$creation =
    file_get_contents(
        $root
        . '/public_html/app/Repositories/'
        . 'TicketCreateRoutingRepository.php'
    );

$repository =
    file_get_contents(
        $root
        . '/public_html/app/Repositories/'
        . 'TicketRepository.php'
    );

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'ticketing-ticket-form.php'
    );

$routes =
    file_get_contents(
        $root
        . '/public_html/routes/'
        . 'ticketing-runtime.php'
    );


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
    'optionsForUser',
    'selectionForUser',
    'intakeRoute',
    'leastLoadedAssignee',

    'support_project_id',
    'support_service_id',
    'requester_participant_id',

    'current_support_layer_id',
    'current_support_node_id',
    'current_support_queue_id',
    'current_support_team_id',
    'current_assignee_project_member_id',

    'ticketing_assignments',
    'project_member_id',
    'support_node_id',
    'support_queue_id',
    'support_team_id',

    'automatic-intake-routing',
    'ticket_routed',
    'ticket_assigned',
] as $needle) {
    $expect(
        str_contains(
            $creation,
            $needle
        ),
        'Create/routing contract missing: '
        . $needle
    );
}


foreach ([
    'viewer_user_reference',
    'ticketing_assignments va',
    'va.unassigned_at IS NULL',
] as $needle) {
    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Assigned visibility contract missing: '
        . $needle
    );
}


foreach ([
    'TicketCreateRoutingRepository',
    'support_project_id',
    'support_service_id',
    'optionsForUser',
    'selectionForUser',
] as $needle) {
    $expect(
        str_contains(
            $service,
            $needle
        ),
        'TicketService project-aware contract missing: '
        . $needle
    );
}


foreach ([
    'name="support_project_id"',
    'name="support_service_id"',
    'data-project=',
    'syncServices',
] as $needle) {
    $expect(
        str_contains(
            $view,
            $needle
        ),
        'Create form contract missing: '
        . $needle
    );
}


foreach ([
    "'support_project_id'",
    "'support_service_id'",
    "(int) \$context['user_id']",
] as $needle) {
    $expect(
        str_contains(
            $routes,
            $needle
        ),
        'Create route contract missing: '
        . $needle
    );
}


foreach ([
    'np-intake',
    'np-l1',
    'np-l2',
    'np-l3',
    'np-l4',
    'اتحادیه',
    'استان',
    'نهاده',
] as $forbidden) {
    $combined =
        $creation
        . $repository
        . $service;

    $expect(
        !str_contains(
            mb_strtolower(
                $combined,
                'UTF-8'
            ),
            mb_strtolower(
                $forbidden,
                'UTF-8'
            )
        ),
        'NP-specific hardcode leaked into runtime: '
        . $forbidden
    );
}


$expect(
    !str_contains(
        $creation,
        'core.primary'
    ),
    'Ticket creation runtime must not query Core DB.'
);


echo "TICKETING_PROJECT_AWARE_CREATE_ROUTING_PASS\n";

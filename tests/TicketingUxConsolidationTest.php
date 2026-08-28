<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $path
    ) use ($root): string {

        $value =
            file_get_contents(
                $root . '/' . $path
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'Cannot read ' . $path
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


$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$list =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-tickets.php'
    );

$form =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-form.php'
    );

$detail =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );

$routing =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-routing.php'
    );

$topology =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-topology.php'
    );

$css =
    $read(
        'public_html/public/assets/admin/css/'
        . 'ticketing.css'
    );


foreach ([
    'viewerProjectTabs',
    'viewerLayers',
    'viewerAssignees',
    'project_reference',
    'current_support_layer_id',
    'current_support_team_id',
    'current_assignee_project_member_id',
    'assignee_name',
] as $needle) {
    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Repository UX missing: '
        . $needle
    );
}


foreach ([
    'project_tabs',
    'layer_options',
    'assignee_options',
    'sort_options',
    'array_merge(',
] as $needle) {
    $expect(
        str_contains(
            $service,
            $needle
        ),
        'TicketService UX missing: '
        . $needle
    );
}


foreach ([
    "'support_topic_id'",
    "'project_reference'",
    "'layer_id'",
    "'assignee_id'",
    "'sort1'",
    "'sort2'",
] as $needle) {
    $expect(
        str_contains(
            $routes,
            $needle
        ),
        'Route UX missing: '
        . $needle
    );
}


foreach ([
    'ticketing-project-tabs',
    'مرحله جاری',
    'کارشناس جاری',
    'مرتب‌سازی چندمرحله‌ای',
    'در انتظار تخصیص',
    'در انتظار مسیریابی',
] as $needle) {
    $expect(
        str_contains(
            $list,
            $needle
        ),
        'Ticket grid missing: '
        . $needle
    );
}


foreach ([
    'data-admin-tab="ticket-info"',
    'data-admin-tab="ticket-detail"',
    'اطلاعات تیکت',
    'شرح و پیوست',
    'زیرسامانه',
] as $needle) {
    $expect(
        str_contains(
            $form,
            $needle
        ),
        'Create form missing: '
        . $needle
    );
}


foreach ([
    'ticketing-route-summary',
    'مرحله جاری',
    'کارشناس جاری',
] as $needle) {
    $expect(
        str_contains(
            $detail,
            $needle
        ),
        'Detail route summary missing: '
        . $needle
    );
}


foreach ([
    'data-admin-tab="routing-topics"',
    'data-admin-tab="routing-rules"',
    'مرجع دامنه',
    'اولویت قانون',
] as $needle) {
    $expect(
        str_contains(
            $routing,
            $needle
        ),
        'Routing UI missing: '
        . $needle
    );
}


foreach ([
    'data-admin-tab="topology-layers"',
    'data-admin-tab="topology-nodes"',
    'data-admin-tab="topology-relations"',
    'data-admin-tab="topology-teams"',
    'data-admin-tab="topology-queues"',
    'data-admin-tab="topology-members"',
    'شناسه مرجع سازمان',
    'در اختیار گرفتن',
] as $needle) {
    $expect(
        str_contains(
            $topology,
            $needle
        ),
        'Topology UI missing: '
        . $needle
    );
}


foreach ([
    'Ticketing UX consolidation v2',
    'ticketing-project-tab',
    'ticketing-filter-grid',
    'ticketing-route-summary',
] as $needle) {
    $expect(
        str_contains(
            $css,
            $needle
        ),
        'CSS UX missing: '
        . $needle
    );
}


echo "TICKETING_UX_CONSOLIDATION_PASS\n";

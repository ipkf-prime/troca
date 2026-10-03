<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$paths = [
    'repository' =>
        $root
        . '/public_html/app/Repositories/TicketWorkPolicyAdminRepository.php',

    'service' =>
        $root
        . '/public_html/app/Services/Work/TicketWorkPolicyAdminService.php',

    'view' =>
        $root
        . '/public_html/resources/views/admin/work-ticket-policy.php',

    'routes' =>
        $root
        . '/public_html/routes/work-settings.php',

    'ui' =>
        $root
        . '/public_html/resources/ui-content/ticket-work-policy-ui.json',
];

foreach ($paths as $name => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            'MISSING='
            . $name
            . ':'
            . $path
            . PHP_EOL
        );

        exit(1);
    }
}

$repository =
    (string) file_get_contents(
        $paths['repository']
    );

$service =
    (string) file_get_contents(
        $paths['service']
    );

$view =
    (string) file_get_contents(
        $paths['view']
    );

$routes =
    (string) file_get_contents(
        $paths['routes']
    );

$uiRaw =
    (string) file_get_contents(
        $paths['ui']
    );

$ui =
    json_decode(
        $uiRaw,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

$checks = [
    'lifecycle_table_registered' =>
        str_contains(
            $repository,
            "'lifecycle' => 'work_ticket_lifecycle_sync_rules'"
        ),

    'lifecycle_project_options' =>
        str_contains(
            $repository,
            'public function workProjects(): array'
        )
        && str_contains(
            $service,
            "'work_project_id'"
        ),

    'lifecycle_status_options' =>
        str_contains(
            $repository,
            'public function workStatuses(): array'
        )
        && str_contains(
            $service,
            "'work_status_id'"
        ),

    'lifecycle_page_kind' =>
        str_contains(
            $service,
            "'lifecycle' =>"
        )
        && str_contains(
            $service,
            "\$this->kindPage('lifecycle')"
        ),

    'lifecycle_reference_prefix' =>
        str_contains(
            $service,
            "'TWLSR-'"
        ),

    'view_lifecycle_tab' =>
        str_contains(
            $view,
            'kind=lifecycle'
        )
        && str_contains(
            $view,
            "page.lifecycle_tab"
        ),

    'view_dynamic_options' =>
        str_contains(
            $view,
            '$dynamicFieldOptions'
        ),

    'view_admin_layout_shell' =>
        str_contains(
            $view,
            'ob_start();'
        )
        && str_contains(
            $view,
            "require __DIR__ . '/layout.php';"
        )
        && str_contains(
            $view,
            "$ui->text('page.title')"
        ),

    'route_lifecycle_kind' =>
        substr_count(
            $routes,
            "'lifecycle'"
        ) >= 4,

    'ui_lifecycle_tab' =>
        (
            $ui['page'][
                'lifecycle_tab'
            ]
            ?? ''
        ) !== '',

    'ui_ticket_actions' =>
        array_keys(
            $ui['options'][
                'ticket_action_code'
            ]
            ?? []
        ) === [
            'resolve',
            'close',
            'reopen',
        ],
];

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

$all =
    $repository
    . $service
    . $view
    . $routes
    . $uiRaw;

if (
    preg_match(
        '/سامانه نهاده|TSP-NEP|TSVC-NEP|NP-000|WRK-PRJ-D53B/i',
        $all
    )
) {
    $failed[] =
        'customer_project_literal';
}

if ($failed !== []) {
    fwrite(
        STDERR,
        'FAILED='
        . implode(',', $failed)
        . PHP_EOL
    );

    exit(1);
}

echo
    'TICKET_WORK_LIFECYCLE_SYNC_MANAGEMENT_UI_TEST=PASS'
    . PHP_EOL;

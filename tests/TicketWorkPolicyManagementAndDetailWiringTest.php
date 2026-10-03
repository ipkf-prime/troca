<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'admin_repository' =>
        $root
        . '/public_html/app/Repositories/'
        . 'TicketWorkPolicyAdminRepository.php',

    'admin_service' =>
        $root
        . '/public_html/app/Services/Work/'
        . 'TicketWorkPolicyAdminService.php',

    'ui_service' =>
        $root
        . '/public_html/app/Services/Work/'
        . 'TicketWorkUiContentService.php',

    'ui_json' =>
        $root
        . '/public_html/resources/ui-content/'
        . 'ticket-work-policy-ui.json',

    'management_view' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'work-ticket-policy.php',

    'work_settings_route' =>
        $root
        . '/public_html/routes/work-settings.php',

    'work_settings_view' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'work-settings.php',

    'ticket_route' =>
        $root
        . '/public_html/routes/ticketing-runtime.php',

    'ticket_view' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        throw new RuntimeException(
            'R7R12 file missing: '
            . $name
        );
    }
}

$source = [];

foreach ($files as $name => $path) {
    $value = file_get_contents($path);

    if (!is_string($value)) {
        throw new RuntimeException(
            'Unable to read R7R12 file: '
            . $name
        );
    }

    $source[$name] = $value;
}

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
    'work_ticket_destination_rules',
    'work_ticket_access_rules',
    'SHOW COLUMNS',
    'array_intersect_key',
    'setActive',
] as $needle) {
    $expect(
        str_contains(
            $source['admin_repository'],
            $needle
        ),
        'Admin repository contract missing: '
        . $needle
    );
}

foreach ([
    'work.settings.view',
    'work.settings.manage',
    '/admin/work/settings/ticket-work-policy',
    'TICKET_WORK_POLICY_ADMIN_UI_V1',
] as $needle) {
    $expect(
        str_contains(
            $source['work_settings_route'],
            $needle
        ),
        'Policy management route contract missing: '
        . $needle
    );
}

foreach ([
    'TICKET_WORK_CREATE_FROM_TICKET_ROUTE_V1',
    '/work-items',
    'createLinkedItem',
    'ticket_work_created',
    'ticket_work_forbidden',
    'ticket_work_unavailable',
] as $needle) {
    $expect(
        str_contains(
            $source['ticket_route'],
            $needle
        ),
        'Ticket Work create route contract missing: '
        . $needle
    );
}

foreach ([
    'TICKET_WORK_TICKET_DETAIL_TAB_V1',
    "'tab.view'",
    "'links.view'",
    "'item.open'",
    "'item.create_from_ticket'",
    'data-ticket-work-panel',
    'data-ticket-work-create-form',
] as $needle) {
    $expect(
        str_contains(
            $source['ticket_view'],
            $needle
        ),
        'Ticket detail Work tab contract missing: '
        . $needle
    );
}

$expect(
    str_contains(
        $source['work_settings_view'],
        'TICKET_WORK_POLICY_ADMIN_UI_ENTRY_V1'
    ),
    'Work Settings entry is missing.'
);

$json =
    json_decode(
        $source['ui_json'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

foreach ([
    'page',
    'work_settings',
    'ticket_detail',
    'status',
    'fields',
    'options',
] as $group) {
    $expect(
        isset($json[$group])
        && is_array($json[$group]),
        'UI JSON group missing: '
        . $group
    );
}

/*
 * Check only R7R12-owned source blocks. Legacy route/view files predate this
 * phase and are not retroactively judged by the new no-customer-literal gate.
 */
$ownedWorkSettingsRoute =
    substr(
        $source['work_settings_route'],
        (int) strpos(
            $source['work_settings_route'],
            'TICKET_WORK_POLICY_ADMIN_UI_V1'
        )
    );

$ownedWorkSettingsView =
    substr(
        $source['work_settings_view'],
        (int) strpos(
            $source['work_settings_view'],
            'TICKET_WORK_POLICY_ADMIN_UI_ENTRY_V1'
        )
    );

$ownedTicketRoute =
    substr(
        $source['ticket_route'],
        (int) strpos(
            $source['ticket_route'],
            'TICKET_WORK_CREATE_FROM_TICKET_ROUTE_V1'
        )
    );

$ownedTicketView =
    substr(
        $source['ticket_view'],
        (int) strpos(
            $source['ticket_view'],
            'TICKET_WORK_TICKET_DETAIL_TAB_V1'
        )
    );

$phpSource =
    $source['admin_repository']
    . $source['admin_service']
    . $source['ui_service']
    . $source['management_view']
    . $ownedWorkSettingsRoute
    . $ownedWorkSettingsView
    . $ownedTicketRoute
    . $ownedTicketView;

foreach ([
    'سامانه نپ',
    'سامانه پایش',
    'اتحادیه مرکزی',
    'payesh',
    'TSP-NEP',
    'WRK-PRJ-D53B07904826FE67',
] as $forbidden) {
    $expect(
        !str_contains(
            mb_strtolower(
                $phpSource,
                'UTF-8'
            ),
            mb_strtolower(
                $forbidden,
                'UTF-8'
            )
        ),
        'Customer literal detected in R7R12 source: '
        . $forbidden
    );
}

/*
 * UI PHP source must not embed the new Persian copy. The JSON file owns it.
 */
foreach ([
    'سیاست اتصال تیکت به مدیریت کار',
    'ایجاد آیتم کاری از تیکت',
    'قواعد مقصد',
    'قواعد دسترسی',
] as $uiLiteral) {
    $expect(
        !str_contains(
            $source['management_view']
            . $ownedWorkSettingsView
            . $ownedTicketView,
            $uiLiteral
        ),
        'New UI literal hardcoded in PHP: '
        . $uiLiteral
    );
}

/*
 * Create-from-ticket route must not write Ticketing tables or mutate Ticket
 * status. Work write authority belongs to the orchestrator.
 */
$marker =
    strpos(
        $source['ticket_route'],
        'TICKET_WORK_CREATE_FROM_TICKET_ROUTE_V1'
    );

$expect(
    $marker !== false,
    'Create-from-ticket route marker missing.'
);

$ownedRoute =
    substr(
        $source['ticket_route'],
        (int) $marker
    );

foreach ([
    'UPDATE ticketing_',
    'INSERT INTO ticketing_',
    'DELETE FROM ticketing_',
    'status_code =',
] as $forbidden) {
    $expect(
        !str_contains(
            $ownedRoute,
            $forbidden
        ),
        'Ticket mutation detected in create route: '
        . $forbidden
    );
}

/*
 * Installer must not seed policy data. Management source may write only in
 * response to authorized route/service invocation.
 */
$expect(
    !str_contains(
        $source['admin_service'],
        'seed'
    ),
    'Policy admin service must not seed default rules.'
);

echo
    "TICKET_WORK_POLICY_MANAGEMENT_AND_DETAIL_WIRING_TEST=PASS\n";

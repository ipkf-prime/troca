<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'TicketWorkBridgeService.php'
    );

if (!is_string($service)) {
    throw new RuntimeException(
        'Unable to read TicketWorkBridgeService.'
    );
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
    'TICKET_WORK_DYNAMIC_POLICY_INTEGRATION_V1',
    'TicketWorkPolicyRepository',
    'TicketWorkPolicyService',
    '->ticketContext(',
    '->resolveDestination(',
    '->resolveAccess(',
    "'dynamic_rule'",
    "'project_binding_fallback'",
    "'tab.view'",
    "'links.view'",
    "'item.open'",
    "'item.create_from_ticket'",
    "'project.select'",
    '$nativeCanView',
    '$nativeCanCreate',
    'applyDestinationDefaults(',
    "'created_from'",
    'workLaunch(',
] as $marker) {
    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Dynamic Ticket->Work integration marker missing: '
        . $marker
    );
}

$expect(
    strpos(
        $service,
        '$this->policy->resolveDestination('
    )
    <
    strpos(
        $service,
        '$this->primaryProjectBinding('
    ),
    'Dynamic destination must be evaluated before fallback binding.'
);

$expect(
    str_contains(
        $service,
        '$nativeCanView'
    )
    && str_contains(
        $service,
        "(\$actions['tab.view'] ?? false)"
    ),
    'Tab access must combine dynamic policy and native Work ACL.'
);

$expect(
    str_contains(
        $service,
        '$nativeCanCreate'
    )
    && str_contains(
        $service,
        "'item.create_from_ticket'"
    ),
    'Create-from-ticket must combine dynamic policy and native Work ACL.'
);

foreach ([
    'TSP-NEP',
    'WRK-PRJ-D53B07904826FE67',
    'WPSB-17D8FAEC5ECA73B4680ECA3F',
    'سامانه نهاده پخش',
    'np-l1',
    'np-l2',
    'np-l3',
    'np-l4',
] as $forbidden) {
    $expect(
        !str_contains(
            $service,
            $forbidden
        ),
        'Customer-specific literal leaked into orchestrator: '
        . $forbidden
    );
}

$expect(
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\b\s+ticketing_/i',
        $service
    ) !== 1,
    'Ticket mutation SQL is forbidden.'
);

echo "TICKET_WORK_DYNAMIC_POLICY_INTEGRATION_TEST=PASS\n";

<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$paths = [
    'migration' =>
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateWorkTicketPolicyFoundation.php',

    'registry' =>
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php',

    'repository' =>
        $root
        . '/public_html/app/Repositories/'
        . 'TicketWorkPolicyRepository.php',

    'service' =>
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'TicketWorkPolicyService.php',
];

$source = [];

foreach ($paths as $name => $path) {
    $text =
        file_get_contents(
            $path
        );

    if (!is_string($text)) {
        throw new RuntimeException(
            'Unreadable source: '
            . $name
        );
    }

    $source[$name] =
        $text;
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

$migration =
    $source['migration'];

$registry =
    $source['registry'];

$repository =
    $source['repository'];

$service =
    $source['service'];

foreach ([
    'work_ticket_destination_rules',
    'work_ticket_access_rules',
    'ticketing_project_reference',
    'ticketing_subdomain_reference',
    'ticketing_service_reference',
    'ticketing_topic_reference',
    'ticketing_layer_reference',
    'ticketing_node_reference',
    'ticketing_queue_reference',
    'ticketing_team_reference',
    'default_item_type',
    'default_status_id',
    'default_priority_code',
    'default_assignee_reference',
    'principal_type_code',
    'principal_reference',
    'allow_tab_view',
    'allow_links_view',
    'allow_item_open',
    'allow_item_create_from_ticket',
    'allow_project_select',
    'rule_priority',
] as $marker) {
    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'Migration contract missing: '
        . $marker
    );
}

$expect(
    str_contains(
        $migration,
        'public function up(): void'
    )
    && str_contains(
        $migration,
        'public function down(): void'
    ),
    'Migration runner contract missing.'
);

$expect(
    !str_contains(
        $migration,
        'public function up(\\PDO'
    )
    && !str_contains(
        $migration,
        'public function down(\\PDO'
    ),
    'Legacy migration method signature forbidden.'
);

$expect(
    str_contains(
        $registry,
        '\\IPKF\\Database\\Migrations\\'
        . 'CreateWorkTicketPolicyFoundation::class,'
    ),
    'Policy migration registry entry missing.'
);

foreach ([
    'ticketing_support_subdomains',
    'current_assignee_project_member_id',
    'actor_project_role_code',
    'actor_staff_role_code',
    'destinationCandidates(',
    'accessCandidates(',
    'rule_priority DESC',
    'specificity DESC',
    'principal_specificity DESC',
] as $marker) {
    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Repository contract missing: '
        . $marker
    );
}

foreach ([
    "'tab.view'",
    "'links.view'",
    "'item.open'",
    "'item.create_from_ticket'",
    "'project.select'",
    "'user'",
    "'project_role'",
    "'team_role'",
    "'current_assignee'",
    "'project_member'",
    "'team_member'",
    "'any_staff'",
    "'default_effect' => 'deny'",
    "'member'",
    "'manager'",
] as $marker) {
    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Policy service contract missing: '
        . $marker
    );
}

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
            $migration,
            $forbidden
        )
        && !str_contains(
            $repository,
            $forbidden
        )
        && !str_contains(
            $service,
            $forbidden
        ),
        'Customer-specific literal leaked into source: '
        . $forbidden
    );
}

$expect(
    preg_match(
        '/REFERENCES\\s+ticketing_/i',
        $migration
    ) !== 1,
    'Cross-database Ticketing FK is forbidden.'
);

$expect(
    !str_contains(
        $migration,
        'INSERT INTO work_ticket_'
    ),
    'Policy migration must not seed customer policy rows.'
);

echo "TICKET_WORK_POLICY_FOUNDATION_TEST=PASS\n";

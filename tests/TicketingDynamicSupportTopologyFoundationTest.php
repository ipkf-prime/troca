<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateTicketingDynamicSupportTopologyFoundation.php'
    );

$registry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
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
    'ticketing_support_layers',
    'ticketing_support_nodes',
    'ticketing_support_node_relations',
    'ticketing_support_teams',
    'ticketing_support_team_nodes',
    'ticketing_support_queues',
    'ticketing_support_team_queues',
    'ticketing_support_team_members',

    'rank_order',
    'can_observe_descendants',
    'can_assist_descendants',
    'can_takeover_descendants',
    'can_transfer_downward',

    'parent_node_id',
    'child_node_id',
    'allow_escalation',
    'allow_downward_transfer',

    'assignment_mode_code',
    'max_open_per_agent',

    'project_member_id',
    'staff_role_code',
    'workload_weight',

    'current_support_layer_id',
    'current_support_node_id',
    'current_support_queue_id',
    'current_support_team_id',
    'current_assignee_project_member_id',

    'ticketing_assignments',
    'support_node_id',
    'support_queue_id',
    'support_team_id',
] as $needle) {
    $expect(
        str_contains(
            $migration,
            $needle
        ),
        'Topology contract missing: '
        . $needle
    );
}


foreach ([
    'payesh',
    'np_',
    'rural',
    'cooperative',
    'province_support',
    'national_union',
    'تعاونی',
    'اتحادیه',
    'استان',
    'شهرستان',
    'نهاده',
] as $forbidden) {
    $expect(
        !str_contains(
            mb_strtolower(
                $migration,
                'UTF-8'
            ),
            mb_strtolower(
                $forbidden,
                'UTF-8'
            )
        ),
        'Business-specific topology hardcode detected: '
        . $forbidden
    );
}


$expect(
    str_contains(
        $registry,
        'CreateTicketingDynamicSupportTopologyFoundation::class'
    ),
    'T3A4 migration is not registered.'
);


$expect(
    !str_contains(
        $migration,
        'core.primary'
    ),
    'Ticketing topology must not access Core DB directly.'
);


$expect(
    !str_contains(
        $migration,
        'organizations(id)'
    ),
    'Cross-database organization FK is forbidden.'
);


echo "TICKETING_DYNAMIC_SUPPORT_TOPOLOGY_FOUNDATION_PASS\n";

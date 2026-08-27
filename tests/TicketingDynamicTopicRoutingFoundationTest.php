<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $path
    ) use ($root): string {

        $content =
            file_get_contents(
                $root . '/' . $path
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Could not read '
                . $path
            );
        }

        return $content;
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


$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateTicketingDynamicTopicRoutingFoundation.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'SupportTopicRoutingAdminRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'SupportTopicRoutingAdminService.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-routing.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );


foreach ([
    'ticketing_support_topics',
    'parent_topic_id',
    'is_selectable',
    'is_default',

    'ticketing_support_routing_rules',

    'scope_type_code',
    'scope_reference',

    'target_layer_id',
    'target_node_id',
    'target_queue_id',
    'target_team_id',

    'fixed_project_member_id',
    'assignment_mode_code',
    'priority',

    'matcher_json',

    'support_topic_id',
    'support_topic_title_snapshot',
    'matched_routing_rule_id',
] as $needle) {

    $expect(
        str_contains(
            $migration,
            $needle
        ),
        'Migration contract missing: '
        . $needle
    );
}


foreach ([
    'createTopic',
    'createRule',
    'teamOwnsNodeAndQueue',
    'memberBelongsToTeam',
] as $needle) {

    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Repository contract missing: '
        . $needle
    );
}


foreach ([
    'topic.create',
    'rule.create',
    'organization',
    'least_loaded',
    'round_robin',
    'fixed',
] as $needle) {

    $expect(
        str_contains(
            $service,
            $needle
        ),
        'Service contract missing: '
        . $needle
    );
}


foreach ([
    'موضوعات پشتیبانی',
    'قوانین مسیریابی',
    'لایه مقصد',
    'گره مقصد',
    'صف مقصد',
    'تیم مقصد',
    'کارشناس ثابت',
] as $needle) {

    $expect(
        str_contains(
            $view,
            $needle
        ),
        'UI contract missing: '
        . $needle
    );
}


$expect(
    substr_count(
        $routes,
        '/admin/ticketing/projects/{public_reference}/routing'
    ) >= 2,
    'Routing admin GET/POST routes missing.'
);


foreach ([
    'CreateTicketingDynamicTopicRoutingFoundation::class',
    'EnableTicketingTopicRoutingManagementRoutes::class',
] as $needle) {

    $expect(
        str_contains(
            $registry,
            $needle
        ),
        'Registry contract missing: '
        . $needle
    );
}


foreach ([
    'np-l1',
    'np-l2',
    'np-intake',
    'payesh',
    'نهاده',
    'اتحادیه',
    'استان',
] as $forbidden) {

    $combined =
        $migration
        . $repository
        . $service
        . $routes;

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
        'Project-specific hardcode detected: '
        . $forbidden
    );
}


echo
    "TICKETING_DYNAMIC_TOPIC_ROUTING_FOUNDATION_PASS\n";

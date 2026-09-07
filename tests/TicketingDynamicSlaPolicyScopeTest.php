<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'ExtendTicketingSlaPolicyScopes.php'
    );

$registry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$repository =
    file_get_contents(
        $root
        . '/public_html/app/Repositories/'
        . 'TicketingSlaRepository.php'
    );

foreach ([
    'migration' => $migration,
    'registry' => $registry,
    'repository' => $repository,
] as $name => $content) {

    if (!is_string($content)) {
        throw new RuntimeException(
            $name . '_unreadable'
        );
    }
}


foreach ([
    'service_id',
    'topic_id',
    'ticketing_sla_policy_resolution_v2_index',
] as $marker) {

    if (
        !str_contains(
            $migration,
            $marker
        )
    ) {
        throw new RuntimeException(
            'migration_marker_missing:'
            . $marker
        );
    }
}


if (
    !str_contains(
        $registry,
        'ExtendTicketingSlaPolicyScopes::class'
    )
) {
    throw new RuntimeException(
        'dynamic_sla_migration_not_registered'
    );
}


foreach ([
    't.support_service_id',
    't.support_topic_id',
    'candidate_policy.service_id',
    'candidate_policy.topic_id',
    '$serviceId',
    '$topicId',
    'p.service_id',
    'p.topic_id',
    'TICKETING_DYNAMIC_SLA_SCOPE_PRECEDENCE_V1',
] as $marker) {

    if (
        !str_contains(
            $repository,
            $marker
        )
    ) {
        throw new RuntimeException(
            'repository_marker_missing:'
            . $marker
        );
    }
}


$start =
    strpos(
        $repository,
        'TICKETING_DYNAMIC_SLA_SCOPE_PRECEDENCE_V1'
    );

$end =
    strpos(
        $repository,
        'p.sort_order DESC',
        $start
    );

if (
    $start === false
    ||
    $end === false
    ||
    $start >= $end
) {
    throw new RuntimeException(
        'precedence_scope_invalid'
    );
}

$block =
    substr(
        $repository,
        $start,
        $end - $start
    );

$positions = [];

foreach ([
    'topic' =>
        'WHEN p.topic_id',

    'service' =>
        'WHEN p.service_id',

    'queue' =>
        'WHEN p.queue_id',

    'project' =>
        'WHEN p.project_id',
] as $name => $marker) {

    $position =
        strpos(
            $block,
            $marker
        );

    if ($position === false) {
        throw new RuntimeException(
            'precedence_marker_missing:'
            . $name
        );
    }

    $positions[$name] =
        $position;
}


if (
    !(
        $positions['topic']
        <
        $positions['service']

        &&
        $positions['service']
        <
        $positions['queue']

        &&
        $positions['queue']
        <
        $positions['project']
    )
) {
    throw new RuntimeException(
        'dynamic_sla_precedence_invalid'
    );
}


echo
    "TICKETING_DYNAMIC_SLA_POLICY_SCOPE_PASS"
    . PHP_EOL;

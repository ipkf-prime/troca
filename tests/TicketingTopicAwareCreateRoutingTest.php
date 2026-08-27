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
                'Cannot read '
                . $path
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
        . 'TicketCreateRoutingRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$form =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-form.php'
    );


foreach ([
    'selectableTopicsForUser',
    'hasSelectableTopics',
    'topicForSelection',
    'resolveRoute',

    'ticketing_support_routing_rules',

    'scope_type_code',
    'organization',

    'routing_rule_id',
    'routing_rule_reference',

    'support_topic_id',
    'support_topic_title_snapshot',
    'matched_routing_rule_id',

    'fixedAssignee',
    'roundRobinAssignee',
    'leastLoadedAssignee',

    'automatic-intake-routing',
    'routing-rule:',
] as $needle) {
    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Repository runtime contract missing: '
        . $needle
    );
}


foreach ([
    'support_topic_id',
    'hasSelectableTopics',
    'topicForSelection',
    "'topics' =>",
] as $needle) {
    $expect(
        str_contains(
            $service,
            $needle
        ),
        'TicketService topic contract missing: '
        . $needle
    );
}


foreach ([
    'name="support_topic_id"',
    'ticket-support-topic',
    'data-project=',
    'data-service=',
    'syncTopics',
] as $needle) {
    $expect(
        str_contains(
            $form,
            $needle
        ),
        'Ticket form topic contract missing: '
        . $needle
    );
}


foreach ([
    'np-intake',
    'np-l1',
    'np-l2',
    'np-l3',
    'np-l4',
    'نهاده',
    'اتحادیه',
    'سهمیه',
    'باربری',
    'payesh',
] as $forbidden) {

    $combined =
        $repository
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
        'Business hardcode leaked into engine: '
        . $forbidden
    );
}


$expect(
    !str_contains(
        $repository,
        'core.primary'
    ),
    'Create routing must not query Core DB.'
);


echo
    "TICKETING_TOPIC_AWARE_CREATE_ROUTING_PASS\n";

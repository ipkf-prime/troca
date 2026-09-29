<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$files = [
    'migration' =>
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateTicketingSupportSubdomainFoundation.php',

    'create' =>
        $root
        . '/public_html/app/Repositories/'
        . 'TicketCreateRoutingRepository.php',

    'service' =>
        $root
        . '/public_html/app/Services/Ticketing/'
        . 'TicketService.php',

    'form' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'ticketing-ticket-form.php',

    'admin' =>
        $root
        . '/public_html/app/Repositories/'
        . 'SupportTopicRoutingAdminRepository.php',

    'routing' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'ticketing-routing.php',
];

$content = [];

foreach ($files as $key => $path) {
    $value =
        file_get_contents(
            $path
        );

    if (!is_string($value)) {
        throw new RuntimeException(
            'Source unavailable: '
            . $path
        );
    }

    $content[$key] = $value;
}

$contracts = [
    'migration' => [
        'ticketing_support_subdomains',
        'subdomain_id BIGINT UNSIGNED',
        'ticketing_support_services_subdomain_fk',
    ],

    'create' => [
        'ticketing_support_subdomains sd',
        'sd.id = s.subdomain_id',
        'sd.project_id = s.project_id',
        'sd.is_active = 1',
        's.subdomain_id IS NULL',
        "'subdomains' =>",
        "'subdomain_id' =>",
    ],

    'service' => [
        '$subdomainOptions = [];',
        "\$createOptions['subdomains']",
        "'subdomains' =>",
    ],

    'form' => [
        'ticket-support-subdomain',
        'function syncSubdomains()',
        'function syncServices()',
        'function syncTopics()',
        'serviceSubdomain',
        'data-ticketing-subdomain-field',
    ],

    'admin' => [
        'public function subdomains(',
        'FROM ticketing_support_subdomains',
        'subdomain_id,',
        "'subdomains' =>",
    ],

    'routing' => [
        '$subdomains =',
        'data-ticketing-routing-subdomain-filter',
        'serviceSubdomain',
        'topicService',
        'select[name="service_id"]',
        'select[name="parent_topic_id"]',
        'select[name="topic_id"]',
    ],
];

foreach ($contracts as $key => $needles) {
    foreach ($needles as $needle) {
        if (!str_contains(
            $content[$key],
            $needle
        )) {
            throw new RuntimeException(
                'Contract missing: '
                . $key
                . ':'
                . $needle
            );
        }
    }
}

$combined =
    implode(
        "\n",
        $content
    );


foreach ([
    'ticketing_support_routing_rules ADD COLUMN subdomain_id',
    'ticketing_tickets ADD COLUMN support_subdomain_id',
] as $forbiddenSchema) {
    if (str_contains(
        $content['migration'],
        $forbiddenSchema
    )) {
        throw new RuntimeException(
            'Forbidden schema expansion: '
            . $forbiddenSchema
        );
    }
}

echo
    "TICKETING_SUPPORT_SUBDOMAIN_RUNTIME_UI_PASS\n";

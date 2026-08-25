<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateTicketingDomainFoundationTables.php'
    );

$seeder =
    file_get_contents(
        $root
        . '/public_html/system/Database/Seeds/'
        . 'TicketingDomainFoundationSeeder.php'
    );

$migrationRegistry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$seederRegistry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationSeederRegistry.php'
    );

foreach ([
    'ticketing_statuses',
    'ticketing_priorities',
    'ticketing_categories',
    'ticketing_tickets',
    'ticketing_assignments',
    'ticketing_messages',
    'ticketing_attachments',
    'ticketing_events',
] as $table) {

    if (
        !str_contains(
            $migration,
            $table
        )
    ) {
        throw new RuntimeException(
            'Missing ticketing table: '
            . $table
        );
    }
}


foreach ([
    'requester_user_reference',
    'requester_person_reference',
    'requester_organization_reference',
    'assignee_reference',
    'author_user_reference',
    'actor_user_reference',
] as $reference) {

    if (
        !str_contains(
            $migration,
            $reference
        )
    ) {
        throw new RuntimeException(
            'Stable external reference missing: '
            . $reference
        );
    }
}


foreach ([
    'previous_status_code',
    'resulting_status_code',
    'payload_json',
    'occurred_at',
] as $eventColumn) {

    if (
        !str_contains(
            $migration,
            $eventColumn
        )
    ) {
        throw new RuntimeException(
            'Immutable event contract missing: '
            . $eventColumn
        );
    }
}


if (
    str_contains(
        $migration,
        'REFERENCES users'
    )
    || str_contains(
        $migration,
        'REFERENCES persons'
    )
    || str_contains(
        $migration,
        'REFERENCES organizations'
    )
    || str_contains(
        $migration,
        'core.'
    )
) {
    throw new RuntimeException(
        'Cross-database FK/reference detected.'
    );
}


if (
    !str_contains(
        $migrationRegistry,
        "'connection' => 'ticketing.primary'"
    )
    || !str_contains(
        $migrationRegistry,
        'CreateTicketingDomainFoundationTables::class'
    )
) {
    throw new RuntimeException(
        'Ticketing migration registration missing.'
    );
}


if (
    !str_contains(
        $seederRegistry,
        "'connection' => 'ticketing.primary'"
    )
    || !str_contains(
        $seederRegistry,
        'TicketingDomainFoundationSeeder::class'
    )
) {
    throw new RuntimeException(
        'Ticketing seeder registration missing.'
    );
}


foreach ([
    "'new'",
    "'in_progress'",
    "'waiting_requester'",
    "'resolved'",
    "'closed'",
    "'normal'",
    "'urgent'",
    "'TKT-CAT-GENERAL'",
] as $seedContract) {

    if (
        !str_contains(
            $seeder,
            $seedContract
        )
    ) {
        throw new RuntimeException(
            'Ticketing reference data missing: '
            . $seedContract
        );
    }
}

echo "TICKETING_DOMAIN_FOUNDATION_PASS\n";

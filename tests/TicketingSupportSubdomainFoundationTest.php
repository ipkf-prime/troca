<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$migrationPath =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateTicketingSupportSubdomainFoundation.php';

$registryPath =
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';

$migration =
    file_get_contents(
        $migrationPath
    );

$registry =
    file_get_contents(
        $registryPath
    );

if (
    !is_string($migration)
    || !is_string($registry)
) {
    throw new RuntimeException(
        'Foundation source unavailable.'
    );
}

foreach ([
    'ticketing_support_subdomains',
    'subdomain_id BIGINT UNSIGNED',
    'ticketing_support_subdomains_project_id_unique',
    'ticketing_support_services_project_subdomain_sort_index',
    'ticketing_support_services_subdomain_fk',
    'public function down(): void',
] as $needle) {
    if (!str_contains($migration, $needle)) {
        throw new RuntimeException(
            'Foundation contract missing: '
            . $needle
        );
    }
}

foreach ([
    '/FOREIGN\s+KEY\s*\(\s*project_id\s*,\s*subdomain_id\s*\)/s',
    '/REFERENCES\s+ticketing_support_subdomains\s*\(\s*project_id\s*,\s*id\s*\)/s',
] as $pattern) {
    if (preg_match($pattern, $migration) !== 1) {
        throw new RuntimeException(
            'Composite subdomain FK contract missing: '
            . $pattern
        );
    }
}

foreach ([
    'DROP INDEX',
    'DROP FOREIGN KEY',
    'DROP COLUMN',
    'INSERT INTO ticketing_support_subdomains',
] as $forbidden) {
    if (str_contains($migration, $forbidden)) {
        throw new RuntimeException(
            'Destructive or seeded foundation contract found: '
            . $forbidden
        );
    }
}

$supportProject =
    strpos(
        $registry,
        'CreateTicketingSupportProjectFoundation::class'
    );

$subdomain =
    strpos(
        $registry,
        'CreateTicketingSupportSubdomainFoundation::class'
    );

$participant =
    strpos(
        $registry,
        'CreateTicketingParticipantDirectoryFoundation::class'
    );

if (
    $supportProject === false
    || $subdomain === false
    || $participant === false
    || !(
        $supportProject
        < $subdomain
        && $subdomain
        < $participant
    )
) {
    throw new RuntimeException(
        'Migration registry order is invalid.'
    );
}

echo
    "TICKETING_SUPPORT_SUBDOMAIN_FOUNDATION_PASS\n";

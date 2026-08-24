<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$admin = file_get_contents(
    $root . '/public_html/app/Services/AdminPanelService.php'
);

$navigation = file_get_contents(
    $root . '/public_html/app/Services/DynamicAdminNavigationService.php'
);

$migration = file_get_contents(
    $root . '/public_html/system/Database/Migrations/'
    . 'ExposeTicketingNavigation.php'
);

$registry = file_get_contents(
    $root . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach ([
    $admin,
    $navigation,
    $migration,
    $registry,
] as $source) {
    $expect(
        is_string($source),
        'Ticketing navigation source is unreadable.'
    );
}

$expect(
    str_contains(
        $admin,
        "'key' => 'ticketing'"
    )
    && str_contains(
        $admin,
        "'permission' => 'ticketing.ticket.view'"
    ),
    'Ticketing dashboard card contract is incomplete.'
);

$expect(
    str_contains(
        $navigation,
        "'ticketing' => \$urls->ticketingLaunch(\$path)"
    ),
    'Ticketing dynamic navigation URL qualification is missing.'
);

$expect(
    str_contains(
        $migration,
        'class ExposeTicketingNavigation extends Migration'
    ),
    'Ticketing navigation migration must use the Migration contract.'
);

foreach ([
    "'core'",
    "'ticketing'",
    "'sidebar'",
    "'پشتیبانی و تیکتینگ'",
    "'/admin/ticketing'",
    "'ticketing.ticket.view'",
    "'/admin/ticketing/*'",
    'ON DUPLICATE KEY UPDATE',
] as $signal) {
    $expect(
        str_contains($migration, $signal),
        "Missing Ticketing navigation migration marker: {$signal}"
    );
}

$coreStart = strpos($registry, "'core' => [");
$automationStart = strpos($registry, "'automation' => [");

$expect(
    $coreStart !== false
    && $automationStart !== false
    && $automationStart > $coreStart,
    'Core migration registry boundaries are invalid.'
);

$coreBlock = substr(
    $registry,
    $coreStart,
    $automationStart - $coreStart
);

$expect(
    str_contains(
        $coreBlock,
        'ExposeTicketingNavigation::class'
    ),
    'Ticketing navigation migration must run on core.primary.'
);

$ticketingStart = strpos($registry, "'ticketing' => [");

if ($ticketingStart !== false) {
    $ticketingBlock = substr($registry, $ticketingStart);

    $expect(
        !str_contains(
            $ticketingBlock,
            'ExposeTicketingNavigation::class'
        ),
        'Core navigation migration must not run on ticketing.primary.'
    );
}

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i',
        $migration
    ),
    'Ticketing navigation migration must be non-destructive.'
);

$crossDatabaseSqlSource = preg_replace(
    '/\\binformation_schema\\.[a-zA-Z0-9_]+\\b/i',
    'information_schema_metadata',
    $migration
);

$expect(
    !preg_match(
        '/\\b(?:FROM|JOIN|UPDATE|INTO|REFERENCES)\\s+'
        . '[a-zA-Z0-9_]+\\.[a-zA-Z0-9_]+/i',
        (string) $crossDatabaseSqlSource
    ),
    'Ticketing navigation migration must not use application cross-database SQL.'
);

echo "Ticketing navigation integration checks passed.\n";

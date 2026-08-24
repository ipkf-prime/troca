<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$connections = $read(
    'public_html/system/Database/Connections/ConnectionRegistry.php'
);

$migrations = $read(
    'public_html/system/Database/Application/ApplicationMigrationRegistry.php'
);

$seeders = $read(
    'public_html/system/Database/Application/ApplicationSeederRegistry.php'
);

$migrate = $read('public_html/public/migrate.php');
$seed = $read('public_html/public/seed.php');

$expect(
    str_contains(
        $connections,
        "\$this->moduleConfig('ticketing', 'TICKETING')"
    ),
    'Ticketing must use generic module database configuration.'
);

$expect(
    str_contains(
        $connections,
        "'ticketing.primary'"
    ),
    'ticketing.primary must be registered.'
);

$expect(
    str_contains($migrations, "'ticketing' => [")
        && str_contains(
            $migrations,
            "'connection' => 'ticketing.primary'"
        ),
    'Ticketing migration application group is missing.'
);

$expect(
    str_contains($seeders, "'ticketing' => [")
        && str_contains(
            $seeders,
            "'connection' => 'ticketing.primary'"
        ),
    'Ticketing seeder application group is missing.'
);

foreach ([$migrate, $seed] as $endpoint) {
    $expect(
        str_contains(
            $endpoint,
            "['core', 'automation', 'work', 'ticketing']"
        ),
        'Ticketing application must be explicitly allowlisted.'
    );

    $expect(
        str_contains(
            $endpoint,
            "\$application === 'ticketing'"
        ),
        'Ticketing must require a dedicated application connection.'
    );
}

foreach ([
    $connections,
    $migrations,
    $seeders,
    $migrate,
    $seed
] as $source) {
    $expect(
        !str_contains($source, 'ticketing-dev.troca.ir'),
        'Runtime foundation must not hardcode a Ticketing hostname.'
    );
}

/*
 * Cross-database isolation is asserted against the Ticketing-specific
 * foundation contract rather than unrelated legacy SQL already present
 * in shared maintenance endpoints (for example information_schema).
 *
 * At this stage Ticketing may register only:
 *   - its dedicated connection key,
 *   - its application migration/seeder groups,
 *   - maintenance endpoint allowlisting/connection guards.
 *
 * No Ticketing SQL is permitted in these foundation files.
 */
foreach ([
    $connections,
    $migrations,
    $seeders,
    $migrate,
    $seed
] as $source) {
    $ticketingLines = array_filter(
        preg_split('/\R/', $source) ?: [],
        static fn (string $line): bool =>
            stripos($line, 'ticketing') !== false
    );

    $ticketingContract = implode("\n", $ticketingLines);

    $expect(
        !preg_match(
            '/\b(?:FROM|JOIN|UPDATE|INTO|REFERENCES)\s+[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+/i',
            $ticketingContract
        ),
        'Ticketing-specific foundation must not introduce cross-database SQL.'
    );
}

echo "Ticketing application database boundary checks passed.\n";

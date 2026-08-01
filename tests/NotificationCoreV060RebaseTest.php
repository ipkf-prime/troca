<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);
$migrate = $read(
    'public_html/public/migrate.php'
);
$seederRegistry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationSeederRegistry.php'
);
$seed = $read(
    'public_html/public/seed.php'
);
$routeLoader = $read(
    'public_html/system/Routing/RouteLoader.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'CreateNotificationCoreFoundationTables.php'
);
$version = trim($read('public_html/VERSION'));

foreach ([
    'CreateAuthenticationLoginHistoryTable',
    'RepairPersonAddressReferenceDataAndGeography',
    'CreateNotificationCoreFoundationTables',
] as $migrationClass) {
    $expect(
        str_contains($registry, $migrationClass)
        && str_contains($migrate, $migrationClass),
        "Migration not preserved: {$migrationClass}"
    );
}

$expect(
    str_contains(
        $routeLoader,
        'user-profile-hotfix.php'
    )
    && str_contains(
        $routeLoader,
        'account-security.php'
    )
    && str_contains(
        $routeLoader,
        'notifications.php'
    ),
    'Current account/user routes were not preserved.'
);

$expect(
    str_contains(
        $seederRegistry,
        'NotificationCoreSeeder'
    )
    && str_contains(
        $seed,
        'NotificationCoreSeeder'
    ),
    'Notification seeder is not registered.'
);

$expect(
    str_contains(
        $migration,
        'referenceColumnType'
    )
    && str_contains(
        $migration,
        'addForeignKeyIfPossible'
    )
    && !preg_match(
        '/CREATE TABLE.*CONSTRAINT/s',
        substr(
            $migration,
            strpos(
                $migration,
                'private function statements'
            ),
            strpos(
                $migration,
                'private function addForeignKeys'
            ) - strpos(
                $migration,
                'private function statements'
            )
        )
    ),
    'Notification migration is not hardened.'
);

$expect(
    $version === '0.6.0-notification-core-dev',
    'Development version was not updated.'
);

echo "Notification Core v0.6.0 rebase checks passed.\n";

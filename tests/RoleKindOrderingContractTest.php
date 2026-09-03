<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $relative) use ($root): string {
    $content = file_get_contents($root . '/' . $relative);

    if (!is_string($content)) {
        throw new RuntimeException(
            'Cannot read: ' . $relative
        );
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'AddRoleKindSortOrder.php'
);

$seeder = $read(
    'public_html/system/Database/Seeds/'
    . 'AuthRbacSeeder.php'
);

$service = $read(
    'public_html/app/Services/'
    . 'DynamicAccessService.php'
);

$governanceService = $read(
    'public_html/app/Services/'
    . 'DynamicRoleGovernanceService.php'
);

$migrate = $read(
    'public_html/public/migrate.php'
);

$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);

$expect(
    str_contains(
        $migration,
        'class AddRoleKindSortOrder extends Migration'
    ),
    'Role-kind ordering migration class missing.'
);

$expect(
    str_contains(
        $migration,
        'ADD COLUMN sort_order INT NOT NULL DEFAULT 0'
    ),
    'Role-kind sort_order column migration missing.'
);

$expectedCodes = [
    'system_admin',
    'central_admin',
    'province_admin',
    'county_admin',
    'manager',
    'expert',
    'auditor',
    'inspector',
    'support',
    'operator',
    'supplier',
    'customer',
];

$assertOrdered = static function (
    string $source,
    array $codes,
    string $label
) use ($expect): void {
    $last = -1;

    foreach ($codes as $code) {
        $position = strpos(
            $source,
            "'" . $code . "'"
        );

        $expect(
            $position !== false,
            $label . ' missing code: ' . $code
        );

        $expect(
            $position > $last,
            $label . ' order invalid at: ' . $code
        );

        $last = $position;
    }
};

$assertOrdered(
    $migration,
    $expectedCodes,
    'Migration'
);

$seedStart = strpos(
    $seeder,
    'private function seedRoleKinds'
);

$seedEnd = strpos(
    $seeder,
    'private function seedRoles',
    $seedStart === false ? 0 : $seedStart
);

$expect(
    $seedStart !== false
    && $seedEnd !== false
    && $seedEnd > $seedStart,
    'Role-kind seeder method boundaries missing.'
);

$seedMethod = substr(
    $seeder,
    $seedStart,
    $seedEnd - $seedStart
);

$assertOrdered(
    $seedMethod,
    $expectedCodes,
    'Seeder'
);

$expect(
    str_contains(
        $seedMethod,
        'sort_order'
    ),
    'Role-kind seeder does not persist sort_order.'
);

$expect(
    str_contains(
        $service,
        "\$orderBy = 'sort_order, id';"
    ),
    'Dynamic lookup is not using persisted sort_order.'
);

$expect(
    str_contains(
        $governanceService,
        'ORDER BY sort_order, id'
    ),
    'Governance lookup is not using persisted sort_order.'
);

$expect(
    str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\'
        . 'AddRoleKindSortOrder()'
    ),
    'Legacy migration registration missing.'
);

$expect(
    str_contains(
        $registry,
        '\\IPKF\\Database\\Migrations\\'
        . 'AddRoleKindSortOrder::class'
    ),
    'Application migration registration missing.'
);

echo "ROLE_KIND_ORDERING_CONTRACT=PASS\n";

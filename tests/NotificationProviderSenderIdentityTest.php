<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        fwrite(STDERR, "FAIL: cannot read {$path}\n");
        exit(1);
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$service = $read(
    'public_html/app/Services/'
    . 'NotificationProviderManagementService.php'
);
$seeder = $read(
    'public_html/system/Database/Seeds/'
    . 'CommunicationCenterSeeder.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'ExtendEmailProviderSenderIdentity.php'
);
$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);
$migrate = $read('public_html/public/migrate.php');

$expect(
    str_contains(
        $service,
        "'from_name' => 'نام نمایشی فرستنده'"
    ),
    'Email sender-name label is missing.'
);

$expect(
    substr_count($seeder, "'key' => 'from_name'") >= 4,
    'Email provider seed schemas do not include sender name.'
);

foreach ([
    "'gmail_smtp'",
    "'yahoo_smtp'",
    "'microsoft365_smtp'",
    "'smtp'",
    "'from_name'",
] as $marker) {
    $expect(
        str_contains($migration, $marker),
        "Missing sender identity migration marker: {$marker}"
    );
}

$expect(
    str_contains(
        $registry,
        'ExtendEmailProviderSenderIdentity::class'
    )
    && str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\\\'
        . 'ExtendEmailProviderSenderIdentity()'
    ),
    'Email sender identity migration is not registered.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $migration
    ),
    'Destructive SQL is present.'
);

echo "Notification provider sender identity checks passed.\n";

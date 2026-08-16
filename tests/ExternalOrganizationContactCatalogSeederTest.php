<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$path =
    $root
    . '/public_html/system/Database/Seeds/'
    . 'ExternalOrganizationContactCatalogSeeder.php';

$source = file_get_contents($path);

if (!is_string($source)) {
    fwrite(
        STDERR,
        "FAIL: Contact catalog seeder unavailable.\n"
    );

    exit(1);
}

$required = [
    "'phone'",
    "'extension'",
    "'mobile'",
    "'fax'",
    "'email'",
    "'website'",
    "'system'",
    "'فاکس'",
    "'ایمیل'",
    "'سامانه مکاتبات'",
    'SELECT id',
    'INSERT INTO contact_types',
    'UPDATE contact_types',
    "status = 'active'",
];

foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(
            STDERR,
            "FAIL: Missing contact catalog contract: {$needle}\n"
        );

        exit(1);
    }
}

foreach ([
    'DELETE FROM contact_types',
    'TRUNCATE',
    'DROP TABLE',
] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        fwrite(
            STDERR,
            "FAIL: Destructive contact catalog operation found: {$forbidden}\n"
        );

        exit(1);
    }
}

echo "External organization contact catalog seeder checks passed.\n";

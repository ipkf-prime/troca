<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$catalogPath =
    $root
    . '/public_html/resources/ui-content/platform-guides.json';

$migrationPath =
    $root
    . '/public_html/system/Database/Migrations/SeedPlatformGuideCatalog.php';

$registryPath =
    $root
    . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php';


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


$catalog =
    json_decode(
        (string) file_get_contents(
            $catalogPath
        ),
        true
    );


$expect(
    is_array($catalog),
    'Catalog JSON is invalid.'
);

$expect(
    count($catalog) === 53,
    'Catalog must contain exactly 53 records.'
);


$keys = [];
$counts = [
    'guide' => 0,
    'notice' => 0,
    'error' => 0,
];


foreach ($catalog as $item) {

    $expect(
        is_array($item),
        'Catalog row must be an array.'
    );


    $key =
        (string) (
            $item['key']
            ?? ''
        );

    $type =
        (string) (
            $item['content_type']
            ?? ''
        );

    $body =
        trim(
            (string) (
                $item['body']
                ?? ''
            )
        );


    $expect(
        preg_match(
            '/^[a-z0-9][a-z0-9._-]{2,189}$/D',
            $key
        ) === 1,
        'Invalid content key: '
        . $key
    );


    $expect(
        isset(
            $counts[$type]
        ),
        'Invalid content type: '
        . $key
    );


    $expect(
        $body !== '',
        'Empty body: '
        . $key
    );


    $expect(
        !str_contains(
            $body,
            '[DYNAMIC]'
        ),
        'Dynamic placeholder leaked into static catalog: '
        . $key
    );


    $expect(
        ($item['consumer_bound'] ?? null)
        === false,
        'A1 records must not claim consumer binding.'
    );


    $keys[] =
        $key;

    $counts[$type]++;
}


$expect(
    count(
        array_unique(
            $keys
        )
    )
    === 53,
    'Catalog keys must be unique.'
);


$expect(
    $counts['guide'] === 47,
    'Guide count mismatch.'
);

$expect(
    $counts['notice'] === 5,
    'Notice count mismatch.'
);

$expect(
    $counts['error'] === 1,
    'Error count mismatch.'
);


foreach (
    [
        'core.coming-soon.guide.01',
        'ticketing.ticketing-sla-management.guide.01',
        'core.register-verify.guide.03',
        'core.register.guide.02',
    ]
    as $excluded
) {
    $expect(
        !in_array(
            $excluded,
            $keys,
            true
        ),
        'Excluded/deferred key leaked into catalog: '
        . $excluded
    );
}


$migration =
    (string) file_get_contents(
        $migrationPath
    );

$registry =
    (string) file_get_contents(
        $registryPath
    );


$expect(
    str_contains(
        $migration,
        "private const SEED =\n        'g4-c1-a1'"
    ),
    'Seed ownership marker missing.'
);

$expect(
    str_contains(
        $migration,
        "'fine'"
    ),
    'Fine/surface scope contract missing.'
);

$expect(
    str_contains(
        $migration,
        "'surface'"
    ),
    'Surface scope path missing.'
);

$expect(
    str_contains(
        $registry,
        'SeedPlatformGuideCatalog::class'
    ),
    'Migration registry entry missing.'
);


echo "PLATFORM_GUIDE_CATALOG_CONTRACT=PASS\n";
echo "CATALOG_COUNT=53\n";
echo "GUIDE_COUNT=47\n";
echo "NOTICE_COUNT=5\n";
echo "ERROR_COUNT=1\n";
echo "DYNAMIC_GUIDES_DEFERRED=2\n";
echo "FALSE_POSITIVE_GUIDES_EXCLUDED=2\n";
echo "GENERIC_SURFACE_SCOPE=PASS\n";
echo "CONSUMER_BINDING=DEFERRED_TO_G4_C1_A2\n";

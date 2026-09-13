<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$migrationFile =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateTicketingProjectSourceStagingFoundation.php';

$registryFile =
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';

$validatorFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceConfigurationValidator.php';

$builderFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceStagingBatchBuilder.php';

foreach (
    [
        $migrationFile,
        $registryFile,
        $validatorFile,
        $builderFile,
    ]
    as $file
) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable A9 contract file: '
            . $file
        );
    }
}

require_once $validatorFile;
require_once $builderFile;

use App\Services\ProjectSources\ProjectSourceConfigurationValidator;
use App\Services\ProjectSources\ProjectSourceStagingBatchBuilder;

$assert =
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

$validator =
    new ProjectSourceConfigurationValidator();

$config =
    $validator->normalize([
        'code' =>
            'master-catalog',

        'title' =>
            'Master Catalog',

        'driver_code' =>
            'fixture-driver',

        'catalog_code' =>
            'entities',

        'dimension_code' =>
            'organization',

        'credential_reference' =>
            'vault:project-source:1',

        'connector_config' => [
            'base_uri' =>
                'connector-local-name',

            'page_size' =>
                200,
        ],

        'status' =>
            'active',
    ]);

$assert(
    $config['driver_code'] ===
        'fixture-driver',
    'Driver normalization mismatch.'
);

$secretRejected = false;

try {
    $validator->normalize([
        'code' =>
            'bad-source',

        'title' =>
            'Bad',

        'driver_code' =>
            'fixture-driver',

        'catalog_code' =>
            'entities',

        'dimension_code' =>
            'organization',

        'connector_config' => [
            'token' =>
                'plain-secret',
        ],
    ]);
} catch (InvalidArgumentException) {
    $secretRejected = true;
}

$assert(
    $secretRejected,
    'Plain secret material must fail closed.'
);

$page = [
    'items' => [
        [
            'source_reference' =>
                'root-a',

            'title' =>
                'Root A',

            'parent_source_reference' =>
                null,

            'status' =>
                'active',

            'attributes' => [
                'z' => 2,
                'a' => 1,
            ],
        ],
        [
            'source_reference' =>
                'child-a',

            'title' =>
                'Child A',

            'parent_source_reference' =>
                'root-a',

            'status' =>
                'active',

            'attributes' =>
                [],
        ],
    ],

    'next_cursor' =>
        'cursor-2',

    'snapshot_token' =>
        'snapshot-1',
];

$builder =
    new ProjectSourceStagingBatchBuilder();

$batchOne =
    $builder->build(
        $config,
        $page
    );

$pageReordered =
    $page;

$pageReordered['items'][0][
    'attributes'
] = [
    'a' => 1,
    'z' => 2,
];

$batchTwo =
    $builder->build(
        $config,
        $pageReordered
    );

$assert(
    $batchOne['item_count'] === 2,
    'Staging item count mismatch.'
);

$assert(
    $batchOne['rows'][0][
        'payload_hash'
    ] ===
    $batchTwo['rows'][0][
        'payload_hash'
    ],
    'Canonical payload hash must be deterministic.'
);

$assert(
    $batchOne['rows'][1][
        'parent_source_reference'
    ] === 'root-a',
    'Staging parent reference mismatch.'
);

$migration =
    (string) file_get_contents(
        $migrationFile
    );

foreach (
    [
        'ticketing_project_sources',
        'ticketing_project_source_runs',
        'ticketing_project_source_stage_rows',
        'credential_reference',
        'connector_config_json',
        'dimension_code',
        'payload_hash',
        'validation_status',
        'ticketing_support_projects(id)',
    ]
    as $marker
) {
    if (
        !str_contains(
            $migration,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Missing A9 migration marker: '
            . $marker
        );
    }
}

foreach (
    [
        'DROP TABLE',
        'ON DELETE CASCADE',
    ]
    as $forbiddenMigration
) {
    if (
        stripos(
            $migration,
            $forbiddenMigration
        ) !== false
    ) {
        throw new RuntimeException(
            'Destructive A9 migration marker: '
            . $forbiddenMigration
        );
    }
}

$registry =
    (string) file_get_contents(
        $registryFile
    );

$needle =
    '\\IPKF\\Database\\Migrations\\'
    . 'CreateTicketingProjectSourceStagingFoundation'
    . '::class';

$assert(
    substr_count(
        $registry,
        $needle
    ) === 1,
    'A9 migration registry entry must be singular.'
);

$dimensionNeedle =
    '\\IPKF\\Database\\Migrations\\'
    . 'CreateTicketingDynamicScopeDimensionFoundation'
    . '::class';

$assert(
    strpos(
        $registry,
        $dimensionNeedle
    ) <
    strpos(
        $registry,
        $needle
    ),
    'A9 migration must follow dynamic dimension foundation.'
);

$runtime =
    (string) file_get_contents(
        $validatorFile
    )
    . "\n"
    . (string) file_get_contents(
        $builderFile
    )
    . "\n"
    . $migration;

foreach (
    [
        'province',
        'county',
        'company',
    ]
    as $businessSpecific
) {
    if (
        stripos(
            $runtime,
            $businessSpecific
        ) !== false
    ) {
        throw new RuntimeException(
            'Business-specific level leaked into A9: '
            . $businessSpecific
        );
    }
}

if (
    preg_match(
        '/\bNP\b/u',
        $runtime
    ) === 1
) {
    throw new RuntimeException(
        'Adapter identity leaked into generic A9 foundation.'
    );
}

echo "PROJECT_SOURCE_CONFIGURATION_AND_STAGING_CONTRACT=PASS\n";
echo "PROJECT_SOURCE_CONFIGURATION=GENERIC\n";
echo "CREDENTIAL_REFERENCE=INDIRECT_ONLY\n";
echo "PLAIN_SECRET_CONFIG=FAIL_CLOSED\n";
echo "STAGING_BEFORE_CANONICAL_APPLY=PASS\n";
echo "DETERMINISTIC_PAYLOAD_HASH=PASS\n";
echo "PROJECT_OWNERSHIP_FK=PASS\n";
echo "MIGRATION_REGISTRY_ORDER=PASS\n";
echo "DESTRUCTIVE_DOWN=ABSENT\n";
echo "BUSINESS_SPECIFIC_LEVELS=ABSENT\n";
echo "SOURCE_ADAPTER_IDENTITY=ABSENT\n";

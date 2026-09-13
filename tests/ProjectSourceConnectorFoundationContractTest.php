<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$interfaceFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceConnectorInterface.php';

$registryFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceConnectorRegistry.php';

$validatorFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceCatalogPageValidator.php';

foreach (
    [
        $interfaceFile,
        $registryFile,
        $validatorFile,
    ]
    as $file
) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable A8 connector file: '
            . $file
        );
    }
}

require_once $interfaceFile;
require_once $registryFile;
require_once $validatorFile;

use App\Services\ProjectSources\ProjectSourceCatalogPageValidator;
use App\Services\ProjectSources\ProjectSourceConnectorInterface;
use App\Services\ProjectSources\ProjectSourceConnectorRegistry;

final class A8FixtureConnector
    implements ProjectSourceConnectorInterface
{
    public function __construct(
        private string $driver
    ) {
    }

    public function driverCode(): string
    {
        return $this->driver;
    }

    public function capabilities(): array
    {
        return [
            'catalog.read',
            'hierarchy.read',
            'health.read',
        ];
    }

    public function health(): array
    {
        return [
            'ok' => true,
        ];
    }

    public function catalog(
        string $catalogCode,
        ?string $cursor = null,
        int $limit = 200
    ): array {
        if (
            trim($catalogCode) === ''
            || $limit < 1
            || $limit > 500
        ) {
            throw new InvalidArgumentException(
                'Invalid fixture catalog query.'
            );
        }

        return [
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

                    'attributes' =>
                        [],
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
                $cursor,

            'snapshot_token' =>
                'snapshot-1',
        ];
    }
}

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

$registry =
    new ProjectSourceConnectorRegistry([
        new A8FixtureConnector(
            'fixture-a'
        ),
    ]);

$assert(
    $registry->has(
        'fixture-a'
    ),
    'Registered driver missing.'
);

$assert(
    $registry->drivers() === [
        'fixture-a',
    ],
    'Driver registry mismatch.'
);

$validator =
    new ProjectSourceCatalogPageValidator();

$page =
    $validator->normalize(
        $registry
            ->get('fixture-a')
            ->catalog(
                'catalog-a',
                null,
                200
            )
    );

$assert(
    count(
        $page['items']
    ) === 2,
    'Catalog normalization mismatch.'
);

$assert(
    $page['items'][1][
        'parent_source_reference'
    ] === 'root-a',
    'Hierarchy envelope mismatch.'
);

$duplicateRejected = false;

try {
    $validator->normalize([
        'items' => [
            [
                'source_reference' =>
                    'same',

                'title' =>
                    'A',
            ],
            [
                'source_reference' =>
                    'same',

                'title' =>
                    'B',
            ],
        ],
    ]);
} catch (InvalidArgumentException) {
    $duplicateRejected = true;
}

$assert(
    $duplicateRejected,
    'Duplicate source references must fail closed.'
);

$selfParentRejected = false;

try {
    $validator->normalize([
        'items' => [
            [
                'source_reference' =>
                    'self',

                'title' =>
                    'Self',

                'parent_source_reference' =>
                    'self',
            ],
        ],
    ]);
} catch (InvalidArgumentException) {
    $selfParentRejected = true;
}

$assert(
    $selfParentRejected,
    'Self-parent must fail closed.'
);

$duplicateDriverRejected = false;

try {
    $registry->register(
        new A8FixtureConnector(
            'fixture-a'
        )
    );
} catch (LogicException) {
    $duplicateDriverRejected = true;
}

$assert(
    $duplicateDriverRejected,
    'Duplicate driver must fail closed.'
);

$runtime =
    (string) file_get_contents(
        $interfaceFile
    )
    . "\n"
    . (string) file_get_contents(
        $registryFile
    )
    . "\n"
    . (string) file_get_contents(
        $validatorFile
    );

foreach (
    [
        'PDO',
        'curl_',
        'Guzzle',
        'ticketing.project.manage',
    ]
    as $forbidden
) {
    if (
        str_contains(
            $runtime,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'Transport/storage coupling leaked: '
            . $forbidden
        );
    }
}

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
            'Business-specific level leaked: '
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
        'Adapter identity leaked into generic foundation.'
    );
}

echo "PROJECT_SOURCE_CONNECTOR_FOUNDATION_CONTRACT=PASS\n";
echo "CONNECTOR_REGISTRY=PASS\n";
echo "CATALOG_PAGE_VALIDATION=PASS\n";
echo "DUPLICATE_SOURCE_REFERENCE=FAIL_CLOSED\n";
echo "SELF_PARENT=FAIL_CLOSED\n";
echo "DUPLICATE_DRIVER=FAIL_CLOSED\n";
echo "TRANSPORT_AGNOSTIC=PASS\n";
echo "STORAGE_AGNOSTIC=PASS\n";
echo "BUSINESS_SPECIFIC_LEVELS=ABSENT\n";
echo "SOURCE_ADAPTER_IDENTITY=ABSENT\n";

<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


require_once
    $root
    . '/public_html/app/Services/UiContent/'
    . 'UiContentContext.php';

require_once
    $root
    . '/public_html/app/Services/UiContent/'
    . 'UiContentScopeProviderInterface.php';

require_once
    $root
    . '/public_html/app/Services/UiContent/'
    . 'UiContentStoreInterface.php';

require_once
    $root
    . '/public_html/app/Services/UiContent/'
    . 'UiContentEmergencyCatalog.php';

require_once
    $root
    . '/public_html/app/Services/UiContent/'
    . 'UiContentResolver.php';


use App\Services\UiContent\UiContentContext;
use App\Services\UiContent\UiContentEmergencyCatalog;
use App\Services\UiContent\UiContentResolver;
use App\Services\UiContent\UiContentStoreInterface;


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


final class FakeUiContentStore
    implements UiContentStoreInterface
{
    public function __construct(
        private bool $isAvailable,
        private ?array $definitionRow,
        private array $rows = []
    ) {
    }


    public function available(): bool
    {
        return $this->isAvailable;
    }


    public function definition(
        string $contentKey
    ): ?array {

        if (
            !is_array(
                $this->definitionRow
            )
        ) {
            return null;
        }

        if (
            (
                $this->definitionRow[
                    'content_key'
                ]
                ?? null
            )
            !== $contentKey
        ) {
            return null;
        }

        return
            $this->definitionRow;
    }


    public function activeOverrides(
        int $definitionId,
        array $scopeKeys,
        array $locales,
        \DateTimeImmutable $now
    ): array {

        return
            array_values(
                array_filter(
                    $this->rows,
                    static function (
                        array $row
                    ) use (
                        $definitionId,
                        $scopeKeys,
                        $locales
                    ): bool {

                        return
                            (int) (
                                $row[
                                    'definition_id'
                                ]
                                ?? 0
                            )
                            === $definitionId

                            &&
                            in_array(
                                $row[
                                    'scope_key'
                                ]
                                ?? null,
                                $scopeKeys,
                                true
                            )

                            &&
                            in_array(
                                $row[
                                    'locale'
                                ]
                                ?? null,
                                $locales,
                                true
                            );
                    }
                )
            );
    }
}


/*
 * Real current Ticketing hierarchy:
 *
 * global
 * -> module
 * -> project
 * -> portal
 */
$ticketing =
    new UiContentContext(
        'ticketing',
        'fa',
        [
            [
                'type' =>
                    'project',

                'reference' =>
                    'TSP-NEP',
            ],

            [
                'type' =>
                    'portal',

                'reference' =>
                    'TSPT-PORTAL-A',
            ],
        ]
    );


$expectedCascade = [
    'global',
    'module:ticketing',
    'scope:ticketing:project:TSP-NEP',
    'scope:ticketing:project:TSP-NEP:portal:TSPT-PORTAL-A',
];


$assert(
    $ticketing->cascadeScopeKeys()
    === $expectedCascade,
    'Ticketing scope cascade mismatch.'
);

$assert(
    $ticketing->candidateScopeKeys()
    === array_reverse(
        $expectedCascade
    ),
    'Specific-first candidate order mismatch.'
);


/*
 * Future-module proof.
 *
 * Different scope hierarchy; same engine/schema.
 */
$work =
    new UiContentContext(
        'work',
        'fa',
        [
            [
                'type' =>
                    'workspace',

                'reference' =>
                    'WS-001',
            ],

            [
                'type' =>
                    'team',

                'reference' =>
                    'TEAM-02',
            ],
        ]
    );


$assert(
    $work->cascadeScopeKeys()
    === [
        'global',
        'module:work',
        'scope:work:workspace:WS-001',
        'scope:work:workspace:WS-001:team:TEAM-02',
    ],
    'Generic future module scope failed.'
);


$definition = [
    'id' =>
        1,

    'content_key' =>
        'ticketing.attachment.not_found',

    'content_type' =>
        'error',

    'default_locale' =>
        'fa',

    'http_status' =>
        404,
];


$rows = [
    [
        'definition_id' =>
            1,

        'public_reference' =>
            'UIC-OVR-GLOBAL',

        'scope_key' =>
            'global',

        'locale' =>
            'fa',

        'title' =>
            'عنوان عمومی',

        'body' =>
            'متن عمومی',

        'severity_code' =>
            'information',

        'primary_action_code' =>
            'back',

        'primary_action_label' =>
            'بازگشت',

        'visibility_mode' =>
            'show',

        'metadata_json' =>
            '{"global":true}',
    ],

    [
        'definition_id' =>
            1,

        'public_reference' =>
            'UIC-OVR-MODULE',

        'scope_key' =>
            'module:ticketing',

        'locale' =>
            'fa',

        'title' =>
            'عنوان تیکتینگ',

        'body' =>
            null,

        'visibility_mode' =>
            'inherit',

        'metadata_json' =>
            '{"module":true}',
    ],

    [
        'definition_id' =>
            1,

        'public_reference' =>
            'UIC-OVR-PROJECT',

        'scope_key' =>
            'scope:ticketing:project:TSP-NEP',

        'locale' =>
            'fa',

        'title' =>
            null,

        'body' =>
            'متن اختصاصی پروژه',

        'visibility_mode' =>
            'inherit',

        'metadata_json' =>
            '{"project":true}',
    ],

    [
        'definition_id' =>
            1,

        'public_reference' =>
            'UIC-OVR-PORTAL',

        'scope_key' =>
            'scope:ticketing:project:TSP-NEP:portal:TSPT-PORTAL-A',

        'locale' =>
            'fa',

        'title' =>
            null,

        'body' =>
            null,

        'icon_code' =>
            'file-x',

        'visibility_mode' =>
            'hide',

        'metadata_json' =>
            '{"portal":true}',
    ],
];


$resolver =
    new UiContentResolver(
        new FakeUiContentStore(
            true,
            $definition,
            $rows
        ),
        new UiContentEmergencyCatalog()
    );


$result =
    $resolver->resolve(
        'ticketing.attachment.not_found',
        $ticketing,
        404
    );


$assert(
    $result['title']
    === 'عنوان تیکتینگ',
    'Module title inheritance failed.'
);

$assert(
    $result['body']
    === 'متن اختصاصی پروژه',
    'Project body inheritance failed.'
);

$assert(
    $result['icon_code']
    === 'file-x',
    'Portal icon inheritance failed.'
);

$assert(
    $result['visible']
    === false,
    'Portal visibility override failed.'
);

$assert(
    $result[
        'resolved_scope_key'
    ]
    ===
    'scope:ticketing:project:TSP-NEP:portal:TSPT-PORTAL-A',
    'Most-specific scope tracking failed.'
);

$assert(
    (
        $result[
            'metadata'
        ][
            'global'
        ]
        ?? false
    )
    === true

    &&
    (
        $result[
            'metadata'
        ][
            'module'
        ]
        ?? false
    )
    === true

    &&
    (
        $result[
            'metadata'
        ][
            'project'
        ]
        ?? false
    )
    === true

    &&
    (
        $result[
            'metadata'
        ][
            'portal'
        ]
        ?? false
    )
    === true,
    'Sparse metadata inheritance failed.'
);


/*
 * DB/schema unavailable must still produce a safe HTTP
 * presentation through the built-in emergency catalog.
 */
$offline =
    new UiContentResolver(
        new FakeUiContentStore(
            false,
            null
        ),
        new UiContentEmergencyCatalog()
    );


$fallback =
    $offline->resolve(
        'http.404',
        new UiContentContext(
            'core',
            'fa'
        ),
        404
    );


$assert(
    $fallback[
        'available'
    ]
    === true

    &&
    $fallback[
        'http_status'
    ]
    === 404

    &&
    $fallback[
        'source'
    ]
    === 'builtin_emergency',
    'Emergency fallback failed.'
);


/*
 * Schema / source contracts.
 */
$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateDynamicUiContentFoundation.php'
    );

$registry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$moduleCatalog =
    file_get_contents(
        $root
        . '/public_html/app/Services/UiContent/'
        . 'UiContentModuleCatalogService.php'
    );

$scopeProvider =
    file_get_contents(
        $root
        . '/public_html/app/Services/UiContent/'
        . 'UiContentScopeProviderInterface.php'
    );

$resolverSource =
    file_get_contents(
        $root
        . '/public_html/app/Services/UiContent/'
        . 'UiContentResolver.php'
    );


foreach ([
    'ui_content_definitions',
    'ui_content_overrides',
    'scope_type',
    'scope_key',
    'scope_reference',
    'scope_path_json',
    'visibility_mode',
    'primary_action_code',
    'secondary_action_code',
    'starts_at',
    'ends_at',
] as $marker) {

    $assert(
        str_contains(
            (string) $migration,
            $marker
        ),
        'Migration marker missing: '
        . $marker
    );
}


$assert(
    !str_contains(
        (string) $migration,
        'project_reference'
    )
    &&
    !str_contains(
        (string) $migration,
        'portal_reference'
    ),
    'Shared schema coupled to Ticketing-specific columns.'
);


$assert(
    !str_contains(
        (string) $migration,
        'action_url'
    ),
    'Arbitrary database-controlled action URL is forbidden.'
);


$assert(
    substr_count(
        (string) $registry,
        'CreateDynamicUiContentFoundation::class'
    )
    === 1,
    'Migration registry count invalid.'
);


foreach ([
    'ApplicationModuleRegistryService',
    "'core'",
    'source_catalog',
    'runtime_registry',
] as $marker) {

    $assert(
        str_contains(
            (string) $moduleCatalog,
            $marker
        ),
        'Module catalog marker missing: '
        . $marker
    );
}


foreach ([
    'moduleKey()',
    'normalizeScopePath(',
    'scopeCatalog(',
] as $marker) {

    $assert(
        str_contains(
            (string) $scopeProvider,
            $marker
        ),
        'Scope provider contract missing: '
        . $marker
    );
}


$assert(
    str_contains(
        (string) $resolverSource,
        'new UiContentRepository()'
    )
    &&
    str_contains(
        (string) $resolverSource,
        'catch (Throwable)'
    ),
    'Lazy/fail-safe repository resolution contract missing.'
);


echo "DYNAMIC_UI_CONTENT_FOUNDATION_CONTRACT_PASS\n";
echo "CORE_BASE_SCOPE=PASS\n";
echo "GENERIC_HIERARCHICAL_SCOPE=PASS\n";
echo "TICKETING_PROJECT_PORTAL_SCOPE=PASS\n";
echo "FUTURE_MODULE_CUSTOM_SCOPE_NO_SCHEMA_CHANGE=PASS\n";
echo "FIELD_LEVEL_INHERITANCE=PASS\n";
echo "VISIBILITY_OVERRIDE=PASS\n";
echo "EMERGENCY_DB_INDEPENDENT_FALLBACK=PASS\n";
echo "ARBITRARY_ACTION_URL=FORBIDDEN\n";
echo "MIGRATION_REGISTRY=PASS\n";

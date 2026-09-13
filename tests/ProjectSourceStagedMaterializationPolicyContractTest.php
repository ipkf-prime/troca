<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$validationFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceStageValidationPolicy.php';

$plannerFile =
    $root
    . '/public_html/app/Services/ProjectSources/'
    . 'ProjectSourceCanonicalMaterializationPlanner.php';

foreach (
    [
        $validationFile,
        $plannerFile,
    ]
    as $file
) {
    if (!is_readable($file)) {
        throw new RuntimeException(
            'Unreadable A10 contract file: '
            . $file
        );
    }
}

require_once $validationFile;
require_once $plannerFile;

use App\Services\ProjectSources\ProjectSourceStageValidationPolicy;
use App\Services\ProjectSources\ProjectSourceCanonicalMaterializationPlanner;

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

$baseSource = [
    'project_id' =>
        10,

    'code' =>
        'master-catalog',

    'dimension_code' =>
        'organization',
];

$baseDimension = [
    'id' =>
        50,

    'project_id' =>
        10,

    'code' =>
        'organization',

    'status' =>
        'active',

    'source_mode_code' =>
        'external',

    'source_key' =>
        'master-catalog',

    'value_kind_code' =>
        'reference',

    'hierarchy_mode_code' =>
        'tree',
];

$batch = [
    'source_code' =>
        'master-catalog',

    'dimension_code' =>
        'organization',

    'snapshot_token' =>
        'snapshot-100',

    'next_cursor' =>
        null,

    'item_count' =>
        3,

    'rows' => [
        [
            'row_number' =>
                1,

            'source_reference' =>
                'child-b',

            'parent_source_reference' =>
                'root',

            'title' =>
                'Child B',

            'source_status' =>
                'active',

            'attributes_json' =>
                '{}',

            'payload_hash' =>
                str_repeat(
                    'b',
                    64
                ),
        ],
        [
            'row_number' =>
                2,

            'source_reference' =>
                'grandchild',

            'parent_source_reference' =>
                'child-a',

            'title' =>
                'Grandchild',

            'source_status' =>
                'active',

            'attributes_json' =>
                '{}',

            'payload_hash' =>
                str_repeat(
                    'c',
                    64
                ),
        ],
        [
            'row_number' =>
                3,

            'source_reference' =>
                'child-a',

            'parent_source_reference' =>
                'root',

            'title' =>
                'Child A',

            'source_status' =>
                'inactive',

            'attributes_json' =>
                '{}',

            'payload_hash' =>
                str_repeat(
                    'a',
                    64
                ),
        ],
    ],
];

$existing = [
    'root' => [
        'source_reference' =>
            'root',

        'value_reference' =>
            'ROOT-CANONICAL',

        'parent_source_reference' =>
            null,
    ],

    'legacy-sibling' => [
        'source_reference' =>
            'legacy-sibling',

        'value_reference' =>
            'LEGACY-CANONICAL',

        'parent_source_reference' =>
            'root',
    ],
];

$policy =
    new ProjectSourceStageValidationPolicy();

$result =
    $policy->evaluate(
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    );

$assert(
    $result['materializable'] ===
        true,
    'Expected valid staged snapshot.'
);

$planner =
    new ProjectSourceCanonicalMaterializationPlanner(
        $policy
    );

$plan =
    $planner->plan(
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    );

$refs =
    array_column(
        $plan['upserts'],
        'source_reference'
    );

$assert(
    $refs === [
        'child-a',
        'child-b',
        'grandchild',
    ],
    'Parent-first deterministic ordering mismatch.'
);

$assert(
    $plan['deletes'] === [],
    'Omission must never become delete.'
);

$assert(
    $plan[
        'database_write_performed'
    ] === false,
    'A10 planner must not write the database.'
);

$assert(
    $plan[
        'path_rebuild_required'
    ] === true,
    'Tree dimension must request path rebuild.'
);

$assert(
    $plan['upserts'][0]['status']
        === 'inactive',
    'Generic inactive status must be preserved.'
);

foreach (
    $plan['upserts']
    as $upsert
) {
    $assert(
        preg_match(
            '/^[A-Za-z0-9._:-]+$/',
            (string) $upsert[
                'value_reference'
            ]
        ) === 1,
        'Canonical value_reference must be ASCII-safe.'
    );
}

$unicodeBatch = [
    'source_code' =>
        'master-catalog',

    'dimension_code' =>
        'organization',

    'snapshot_token' =>
        'snapshot-unicode',

    'next_cursor' =>
        null,

    'item_count' =>
        1,

    'rows' => [
        [
            'row_number' => 1,
            'source_reference' =>
                'شرکت-۱',
            'parent_source_reference' =>
                'root',
            'title' =>
                'شرکت نمونه',
            'source_status' =>
                'active',
            'attributes_json' =>
                '{}',
            'payload_hash' =>
                str_repeat('d', 64),
        ],
    ],
];

$unicodePlan =
    $planner->plan(
        $baseSource,
        $baseDimension,
        $unicodeBatch,
        $existing
    );

$assert(
    $unicodePlan['upserts'][0][
        'source_reference'
    ] === 'شرکت-۱',
    'Unicode source_reference must be preserved.'
);

$assert(
    preg_match(
        '/^SRCV-[a-f0-9]{64}$/',
        $unicodePlan['upserts'][0][
            'value_reference'
        ]
    ) === 1,
    'Unicode source_reference must map to deterministic ASCII canonical reference.'
);

$adoptBatch = [
    'source_code' =>
        'master-catalog',

    'dimension_code' =>
        'organization',

    'snapshot_token' =>
        'snapshot-existing',

    'next_cursor' =>
        null,

    'item_count' =>
        1,

    'rows' => [
        [
            'row_number' => 1,
            'source_reference' =>
                'legacy-sibling',
            'parent_source_reference' =>
                'root',
            'title' =>
                'Legacy Updated',
            'source_status' =>
                'active',
            'attributes_json' =>
                '{}',
            'payload_hash' =>
                str_repeat('e', 64),
        ],
    ],
];

$adoptPlan =
    $planner->plan(
        $baseSource,
        $baseDimension,
        $adoptBatch,
        $existing
    );

$assert(
    $adoptPlan['upserts'][0][
        'value_reference'
    ] === 'LEGACY-CANONICAL',
    'Existing canonical value_reference must be preserved.'
);

$mustFail =
    static function (
        callable $case,
        string $name
    ) use (
        $assert
    ): void {
        $failed = false;

        try {
            $case();
        } catch (DomainException) {
            $failed = true;
        }

        $assert(
            $failed,
            $name
            . ' must fail closed.'
        );
    };

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $dimension =
            $baseDimension;

        $dimension['project_id'] =
            11;

        $planner->plan(
            $baseSource,
            $dimension,
            $batch,
            $existing
        );
    },
    'Cross-project dimension'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $dimension =
            $baseDimension;

        $dimension[
            'source_mode_code'
        ] = 'managed';

        $planner->plan(
            $baseSource,
            $dimension,
            $batch,
            $existing
        );
    },
    'Managed target dimension'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $dimension =
            $baseDimension;

        $dimension[
            'source_key'
        ] = 'other-source';

        $planner->plan(
            $baseSource,
            $dimension,
            $batch,
            $existing
        );
    },
    'Dimension source binding mismatch'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $incomplete =
            $batch;

        $incomplete['next_cursor'] =
            'cursor-2';

        $planner->plan(
            $baseSource,
            $baseDimension,
            $incomplete,
            $existing
        );
    },
    'Incomplete snapshot'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch
    ): void {
        $missing =
            $batch;

        $missing['rows'][0][
            'parent_source_reference'
        ] = 'missing-parent';

        $planner->plan(
            $baseSource,
            $baseDimension,
            $missing,
            []
        );
    },
    'Missing parent'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $cycle =
            $batch;

        $cycle['rows'] = [
            [
                'row_number' => 1,
                'source_reference' => 'a',
                'parent_source_reference' => 'b',
                'title' => 'A',
                'source_status' => 'active',
                'attributes_json' => '{}',
                'payload_hash' =>
                    str_repeat('a', 64),
            ],
            [
                'row_number' => 2,
                'source_reference' => 'b',
                'parent_source_reference' => 'a',
                'title' => 'B',
                'source_status' => 'active',
                'attributes_json' => '{}',
                'payload_hash' =>
                    str_repeat('b', 64),
            ],
        ];

        $cycle['item_count'] = 2;

        $planner->plan(
            $baseSource,
            $baseDimension,
            $cycle,
            $existing
        );
    },
    'Hierarchy cycle'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $flat =
            $baseDimension;

        $flat[
            'hierarchy_mode_code'
        ] = 'flat';

        $planner->plan(
            $baseSource,
            $flat,
            $batch,
            $existing
        );
    },
    'Flat dimension parent edge'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $duplicate =
            $batch;

        $duplicate['rows'][1][
            'source_reference'
        ] = 'child-b';

        $planner->plan(
            $baseSource,
            $baseDimension,
            $duplicate,
            $existing
        );
    },
    'Duplicate source reference'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $wrongCode =
            $baseDimension;

        $wrongCode['code'] =
            'different-dimension';

        $planner->plan(
            $baseSource,
            $wrongCode,
            $batch,
            $existing
        );
    },
    'Dimension code mismatch'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $invalid =
            $batch;

        $invalid['rows'][0][
            'payload_hash'
        ] = 'not-a-sha256';

        $planner->plan(
            $baseSource,
            $baseDimension,
            $invalid,
            $existing
        );
    },
    'Invalid payload hash'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $invalid =
            $batch;

        $invalid['rows'][0][
            'attributes_json'
        ] = '{bad-json';

        $planner->plan(
            $baseSource,
            $baseDimension,
            $invalid,
            $existing
        );
    },
    'Invalid attributes json'
);

$mustFail(
    static function () use (
        $planner,
        $baseSource,
        $baseDimension,
        $batch,
        $existing
    ): void {
        $invalid =
            $batch;

        $invalid['rows'][0][
            'title'
        ] = str_repeat(
            'x',
            256
        );

        $planner->plan(
            $baseSource,
            $baseDimension,
            $invalid,
            $existing
        );
    },
    'Oversized canonical title'
);

echo "PROJECT_SOURCE_STAGED_MATERIALIZATION_POLICY_CONTRACT=PASS\n";
echo "CROSS_PROJECT_DIMENSION=FAIL_CLOSED\n";
echo "DIMENSION_CODE_MISMATCH=FAIL_CLOSED\n";
echo "MANAGED_DIMENSION_AS_EXTERNAL_TARGET=FAIL_CLOSED\n";
echo "INCOMPLETE_SNAPSHOT=FAIL_CLOSED\n";
echo "MISSING_PARENT=FAIL_CLOSED\n";
echo "HIERARCHY_CYCLE=FAIL_CLOSED\n";
echo "FLAT_DIMENSION_PARENT=FAIL_CLOSED\n";
echo "DUPLICATE_SOURCE_REFERENCE=FAIL_CLOSED\n";
echo "PARENT_FIRST_ORDER=DETERMINISTIC\n";
echo "OMISSION_DELETE=FORBIDDEN\n";
echo "PATH_REBUILD=POST_UPSERT_REQUIRED_FOR_TREE\n";
echo "DATABASE_WRITE=NO\n";
echo "DIMENSION_SOURCE_BINDING=REQUIRED\n";
echo "UNICODE_SOURCE_REFERENCE=PRESERVED\n";
echo "CANONICAL_VALUE_REFERENCE=ASCII_DETERMINISTIC\n";
echo "EXISTING_CANONICAL_VALUE_REFERENCE=PRESERVED\n";
echo "PAYLOAD_HASH_AND_ATTRIBUTES_JSON=VALIDATED\n";
echo "CANONICAL_TITLE_LENGTH=VALIDATED\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$file =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingScopedTopologyAdminEvaluator.php';

if (!is_readable($file)) {
    throw new RuntimeException(
        'A5 evaluator file is unreadable.'
    );
}

require_once $file;

use App\Services\Ticketing\TicketingScopedTopologyAdminEvaluator;

$evaluator =
    new TicketingScopedTopologyAdminEvaluator();

$now =
    new DateTimeImmutable(
        '2026-09-13 04:30:00 UTC'
    );

$scope = [
    'scope_type_code' =>
        'organization',

    'scope_reference' =>
        'root-a',

    'access_mode_code' =>
        'descendants',

    'capabilities_json' =>
        json_encode(
            [
                'topology.view' =>
                    true,

                'topology.create' =>
                    true,

                'topology.update' =>
                    true,

                'topology.delete' =>
                    false,
            ],
            JSON_THROW_ON_ERROR
        ),

    'status' =>
        'active',

    'valid_from' =>
        '2026-01-01 00:00:00',

    'valid_until' =>
        '2026-12-31 23:59:59',
];

$child = [
    'scope_type_code' =>
        'organization',

    'scope_reference' =>
        'child-a',

    'ancestor_scope_references' => [
        'root-a',
    ],
];

$sibling = [
    'scope_type_code' =>
        'organization',

    'scope_reference' =>
        'child-b',

    'ancestor_scope_references' => [
        'root-b',
    ],
];

$otherType = [
    'scope_type_code' =>
        'geography',

    'scope_reference' =>
        'child-a',

    'ancestor_scope_references' => [
        'root-a',
    ],
];

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

$assert(
    $evaluator->allows(
        [$scope],
        'topology.view',
        $child,
        $now
    ),
    'Descendant view must allow.'
);

$assert(
    $evaluator->allows(
        [$scope],
        'topology.create',
        $child,
        $now
    ),
    'Descendant create must allow.'
);

$assert(
    $evaluator->allows(
        [$scope],
        'topology.update',
        $child,
        $now
    ),
    'Descendant update must allow.'
);

$assert(
    !$evaluator->allows(
        [$scope],
        'topology.delete',
        $child,
        $now
    ),
    'Capability deny must remain denied.'
);

$assert(
    !$evaluator->allows(
        [$scope],
        'topology.create',
        $sibling,
        $now
    ),
    'Sibling branch must be denied.'
);

$assert(
    !$evaluator->allows(
        [$scope],
        'topology.create',
        $otherType,
        $now
    ),
    'Cross scope type must be denied.'
);

$exact =
    $scope;

$exact['access_mode_code'] =
    'exact';

$assert(
    !$evaluator->allows(
        [$exact],
        'topology.create',
        $child,
        $now
    ),
    'Exact mode must not include descendants.'
);

$assert(
    $evaluator->allows(
        [$exact],
        'topology.create',
        [
            'scope_type_code' =>
                'organization',

            'scope_reference' =>
                'root-a',

            'ancestor_scope_references' =>
                [],
        ],
        $now
    ),
    'Exact self must allow.'
);

$expired =
    $scope;

$expired['valid_until'] =
    '2025-12-31 23:59:59';

$assert(
    !$evaluator->allows(
        [$expired],
        'topology.view',
        $child,
        $now
    ),
    'Expired scope must deny.'
);

$future =
    $scope;

$future['valid_from'] =
    '2027-01-01 00:00:00';

$assert(
    !$evaluator->allows(
        [$future],
        'topology.view',
        $child,
        $now
    ),
    'Future scope must deny.'
);

$inactive =
    $scope;

$inactive['status'] =
    'inactive';

$assert(
    !$evaluator->allows(
        [$inactive],
        'topology.view',
        $child,
        $now
    ),
    'Inactive scope must deny.'
);

$malformed =
    $scope;

$malformed['capabilities_json'] =
    '{bad-json';

$assert(
    !$evaluator->allows(
        [$malformed],
        'topology.view',
        $child,
        $now
    ),
    'Malformed capabilities must deny.'
);

$malformedValidFrom =
    $scope;

$malformedValidFrom['valid_from'] =
    'not-a-date';

$assert(
    !$evaluator->allows(
        [$malformedValidFrom],
        'topology.view',
        $child,
        $now
    ),
    'Malformed valid_from must deny.'
);

$malformedValidUntil =
    $scope;

$malformedValidUntil['valid_until'] =
    'not-a-date';

$assert(
    !$evaluator->allows(
        [$malformedValidUntil],
        'topology.view',
        $child,
        $now
    ),
    'Malformed valid_until must deny.'
);


$unknownMode =
    $scope;

$unknownMode['access_mode_code'] =
    'wildcard';

$assert(
    !$evaluator->allows(
        [$unknownMode],
        'topology.view',
        $child,
        $now
    ),
    'Unknown access mode must deny.'
);

$assert(
    !$evaluator->allows(
        [$scope],
        'ticketing.project.manage',
        $child,
        $now
    ),
    'Global permission alias must deny.'
);

$assert(
    !$evaluator->allows(
        [$scope],
        'topology.create',
        [
            'scope_type_code' =>
                'organization',

            'scope_reference' =>
                '',

            'ancestor_scope_references' =>
                ['root-a'],
        ],
        $now
    ),
    'Unresolved resource reference must deny.'
);

$secondScope =
    $scope;

$secondScope['scope_reference'] =
    'root-b';

$assert(
    $evaluator->allows(
        [
            $scope,
            $secondScope,
        ],
        'topology.create',
        $sibling,
        $now
    ),
    'Multiple delegated scopes must compose with OR.'
);

$content =
    (string) file_get_contents(
        $file
    );

foreach (
    [
        'ticketing.project.manage',
        'ScopedAuthorizationService',
        'AuthorizationService',
    ]
    as $forbidden
) {
    if (
        str_contains(
            $content,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'Forbidden A5 coupling: '
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
            $content,
            $businessSpecific
        ) !== false
    ) {
        throw new RuntimeException(
            'Business-specific level leaked into generic evaluator: '
            . $businessSpecific
        );
    }
}

if (
    preg_match(
        '/\bNP\b/u',
        $content
    ) === 1
) {
    throw new RuntimeException(
        'NP-specific coupling leaked into generic evaluator.'
    );
}

echo "TICKETING_GENERIC_SCOPED_TOPOLOGY_ADMIN_EVALUATOR=PASS\n";
echo "DESCENDANT_SCOPE_ALLOW=PASS\n";
echo "EXACT_SCOPE_BOUNDARY=PASS\n";
echo "SIBLING_SCOPE_DENY=PASS\n";
echo "CROSS_SCOPE_TYPE_DENY=PASS\n";
echo "CAPABILITY_DENY=PASS\n";
echo "VALIDITY_WINDOW_FAIL_CLOSED=PASS\n";
echo "MALFORMED_CAPABILITIES_FAIL_CLOSED=PASS\n";
echo "UNKNOWN_ACCESS_MODE_FAIL_CLOSED=PASS\n";
echo "MULTI_SCOPE_OR_COMPOSITION=PASS\n";
echo "GLOBAL_PERMISSION_ALIAS=ABSENT\n";
echo "BUSINESS_SPECIFIC_LEVELS=ABSENT\n";
echo "SOURCE_SYSTEM_COUPLING=ABSENT\n";

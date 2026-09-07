<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

require_once
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketingAccessGrantEvaluator.php';

$evaluatorClass =
    'App\\Services\\Ticketing\\'
    . 'TicketingAccessGrantEvaluator';

if (!class_exists($evaluatorClass)) {
    throw new RuntimeException(
        'access_grant_evaluator_not_loadable'
    );
}

$evaluator =
    new $evaluatorClass();


function assertDecision(
    bool $expected,
    bool $actual,
    string $case
): void {
    if ($actual !== $expected) {
        throw new RuntimeException(
            'multi_grant_case_failed:'
            . $case
            . ':expected='
            . ($expected ? 'allow' : 'deny')
            . ':actual='
            . ($actual ? 'allow' : 'deny')
        );
    }

    echo
        'CASE_PASS='
        . $case
        . PHP_EOL;
}


$subject = [
    'dimension-tree' => [
        [
            'value' => 'leaf-a',
            'ancestors' => [
                'root',
                'branch-a',
            ],
        ],
    ],

    'dimension-tags' => [
        'tag-1',
        'tag-2',
    ],
];


/*
 * Multiple dimensions inside one Grant compose with AND.
 */
$grant1 = [
    'status' => 'active',
    'is_unrestricted' => false,

    'dimension_rules' => [
        [
            'dimension' => 'dimension-tree',
            'match_mode_code' => 'any',
            'include_descendants' => true,
            'values' => [
                'branch-a',
            ],
        ],
        [
            'dimension' => 'dimension-tags',
            'match_mode_code' => 'any',
            'include_descendants' => false,
            'values' => [
                'tag-1',
            ],
        ],
    ],

    'resource_rules' => [],
];

assertDecision(
    true,
    $evaluator->matches(
        [$grant1],
        $subject
    ),
    'dimension_and_descendant_match'
);


/*
 * Partial dimension match must fail.
 */
$wrongTagSubject =
    $subject;

$wrongTagSubject['dimension-tags'] = [
    'tag-2',
];

assertDecision(
    false,
    $evaluator->matches(
        [$grant1],
        $wrongTagSubject
    ),
    'dimension_and_rejects_partial_match'
);


/*
 * Multiple selected values inside one rule with ANY semantics.
 */
$anyGrant = [
    'status' => 'active',
    'is_unrestricted' => false,

    'dimension_rules' => [
        [
            'dimension' => 'dimension-tree',
            'match_mode_code' => 'any',
            'include_descendants' => true,
            'values' => [
                'branch-x',
                'branch-a',
                'branch-z',
            ],
        ],
    ],

    'resource_rules' => [],
];

assertDecision(
    true,
    $evaluator->matches(
        [$anyGrant],
        $subject
    ),
    'multi_selected_values_any'
);


/*
 * ALL semantics for a multi-valued dimension.
 */
$allGrant = [
    'status' => 'active',
    'is_unrestricted' => false,

    'dimension_rules' => [
        [
            'dimension' => 'dimension-tags',
            'match_mode_code' => 'all',
            'include_descendants' => false,
            'values' => [
                'tag-1',
                'tag-2',
            ],
        ],
    ],

    'resource_rules' => [],
];

assertDecision(
    true,
    $evaluator->matches(
        [$allGrant],
        $subject
    ),
    'multi_selected_values_all'
);

assertDecision(
    false,
    $evaluator->matches(
        [$allGrant],
        $wrongTagSubject
    ),
    'multi_selected_values_all_reject'
);


/*
 * Multiple separate Grants compose with OR.
 */
$grant2 = [
    'status' => 'active',
    'is_unrestricted' => false,

    'dimension_rules' => [
        [
            'dimension' => 'dimension-tree',
            'match_mode_code' => 'any',
            'include_descendants' => false,
            'values' => [
                'branch-b',
            ],
        ],
        [
            'dimension' => 'dimension-tags',
            'match_mode_code' => 'any',
            'include_descendants' => false,
            'values' => [
                'tag-2',
            ],
        ],
    ],

    'resource_rules' => [],
];

$subject2 = [
    'dimension-tree' => [
        'branch-b',
    ],

    'dimension-tags' => [
        'tag-2',
    ],
];

assertDecision(
    true,
    $evaluator->matches(
        [
            $grant1,
            $grant2,
        ],
        $subject2
    ),
    'multiple_grants_or'
);


/*
 * Generic resource restrictions compose with dimensions using AND.
 */
$resourceGrant = [
    'status' => 'active',
    'is_unrestricted' => false,

    'dimension_rules' => [
        [
            'dimension' => 'dimension-tree',
            'match_mode_code' => 'any',
            'include_descendants' => true,
            'values' => [
                'branch-a',
            ],
        ],
    ],

    'resource_rules' => [
        [
            'resource_type_code' => 'service',
            'match_mode_code' => 'any',
            'values' => [
                'service-a',
                'service-b',
            ],
        ],
        [
            'resource_type_code' => 'topic',
            'match_mode_code' => 'any',
            'values' => [
                'topic-a',
            ],
        ],
    ],
];

assertDecision(
    true,
    $evaluator->matches(
        [$resourceGrant],
        $subject,
        [
            'service' => 'service-b',
            'topic' => 'topic-a',
        ]
    ),
    'dimension_and_resource_match'
);

assertDecision(
    false,
    $evaluator->matches(
        [$resourceGrant],
        $subject,
        [
            'service' => 'service-b',
            'topic' => 'topic-b',
        ]
    ),
    'resource_rule_and_reject'
);


/*
 * Realm is already representable as a generic resource type.
 */
$realmGrant = [
    'status' => 'active',
    'is_unrestricted' => false,

    'dimension_rules' => [],

    'resource_rules' => [
        [
            'resource_type_code' => 'realm',
            'match_mode_code' => 'any',
            'values' => [
                'realm-a',
                'realm-b',
            ],
        ],
    ],
];

assertDecision(
    true,
    $evaluator->matches(
        [$realmGrant],
        [],
        [
            'realm' => 'realm-b',
        ]
    ),
    'future_realm_resource_ready'
);


/*
 * Empty restricted Grant must fail closed.
 */
assertDecision(
    false,
    $evaluator->matches(
        [
            [
                'status' => 'active',
                'is_unrestricted' => false,
                'dimension_rules' => [],
                'resource_rules' => [],
            ],
        ],
        []
    ),
    'empty_restricted_grant_fail_closed'
);


/*
 * Explicit unrestricted Grant authorizes.
 */
assertDecision(
    true,
    $evaluator->matches(
        [
            [
                'status' => 'active',
                'is_unrestricted' => true,
                'dimension_rules' => [],
                'resource_rules' => [],
            ],
        ],
        []
    ),
    'explicit_unrestricted_grant'
);


/*
 * Inactive Grant never authorizes.
 */
assertDecision(
    false,
    $evaluator->matches(
        [
            [
                'status' => 'inactive',
                'is_unrestricted' => true,
                'dimension_rules' => [],
                'resource_rules' => [],
            ],
        ],
        []
    ),
    'inactive_grant_denied'
);


/*
 * Security regression:
 *
 * A restricted Grant containing only inactive rules must NOT become
 * implicitly unrestricted.
 */
assertDecision(
    false,
    $evaluator->matches(
        [
            [
                'status' => 'active',
                'is_unrestricted' => false,

                'dimension_rules' => [
                    [
                        'status' => 'inactive',
                        'dimension' => 'dimension-tags',
                        'match_mode_code' => 'any',
                        'values' => [
                            'tag-1',
                        ],
                    ],
                ],

                'resource_rules' => [],
            ],
        ],
        $subject
    ),
    'inactive_only_rules_fail_closed'
);


/*
 * A malformed rule must fail closed.
 */
assertDecision(
    false,
    $evaluator->matches(
        [
            [
                'status' => 'active',
                'is_unrestricted' => false,
                'dimension_rules' => [
                    'invalid-rule',
                ],
                'resource_rules' => [],
            ],
        ],
        $subject
    ),
    'malformed_rule_fail_closed'
);


echo
    "TICKETING_STAFF_MULTI_GRANT_ACCESS_PASS"
    . PHP_EOL;

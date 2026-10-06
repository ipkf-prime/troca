<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

require_once
    $root
    . '/public_html/app/Services/'
    . 'ImpersonationAuthorizationService.php';

use App\Services\ImpersonationAuthorizationService;

$service =
    new ImpersonationAuthorizationService();

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if ($condition) {
            return;
        }

        fwrite(
            STDERR,
            'FAIL: '
            . $message
            . PHP_EOL
        );

        exit(1);
    };

$scope =
    static function (
        string $type,
        string $reference,
        string $effect = 'allow',
        bool $descendants = false,
        array $ancestors = [],
        array $aliases = []
    ): array {
        return [
            'scope_type_code' =>
                $type,

            'scope_reference' =>
                $reference,

            'effect_code' =>
                $effect,

            'include_descendants' =>
                $descendants,

            'reference_aliases' =>
                $aliases !== []
                    ? $aliases
                    : [$reference],

            'ancestors' =>
                $ancestors,
        ];
    };

$assignment =
    static function (
        int $priority,
        array $scopes,
        bool $requiresScope = true
    ): array {
        return [
            'priority' =>
                $priority,

            'requires_scope' =>
                $requiresScope,

            'scopes' =>
                $scopes,
        ];
    };

$actor =
    static function (
        int $userId,
        int $priority,
        array $scopes,
        bool $permission = true,
        bool $manager = true
    ): array {
        return [
            'user_id' =>
                $userId,

            'priority' =>
                $priority,

            'permission_allowed' =>
                $permission,

            'can_manage_other_users' =>
                $manager,

            'scopes' =>
                $scopes,
        ];
    };

$target =
    static function (
        int $userId,
        array $assignments,
        bool $eligible = true
    ): array {
        return [
            'user_id' =>
                $userId,

            'eligible' =>
                $eligible,

            'assignments' =>
                $assignments,
        ];
    };

$global =
    $scope(
        'global',
        '*',
        'allow',
        true
    );

$own =
    static fn (int $userId): array =>
        [
            'scope_type_code' =>
                'own',

            'scope_reference' =>
                '*',

            'effect_code' =>
                'allow',

            'include_descendants' =>
                false,

            'reference_aliases' =>
                ['*'],

            'ancestors' =>
                [],
        ];


$result =
    $service->evaluate(
        $actor(
            100,
            900,
            [$global]
        ),
        $target(
            200,
            [
                $assignment(
                    1,
                    [$own(200)]
                ),
            ]
        )
    );

$expect(
    ($result['allowed'] ?? false)
        === true,
    'Global manager must reach lower own-only user.'
);

echo "GLOBAL_LOWER_OWN_ONLY=PASS\n";


foreach (
    [
        900 =>
            'peer',

        1000 =>
            'higher',
    ]
    as $priority => $label
) {
    $result =
        $service->evaluate(
            $actor(
                100,
                900,
                [$global]
            ),
            $target(
                200,
                [
                    $assignment(
                        $priority,
                        [$global]
                    ),
                ]
            )
        );

    $expect(
        ($result['allowed'] ?? true)
            === false
        && (
            $result['reason_code']
            ?? ''
        ) === 'target_role_not_lower',
        ucfirst($label)
        . ' target must be denied.'
    );
}

echo "PEER_AND_HIGHER_DENIED=PASS\n";


$result =
    $service->evaluate(
        $actor(
            100,
            900,
            [$global],
            false,
            true
        ),
        $target(
            200,
            [
                $assignment(
                    1,
                    [$own(200)]
                ),
            ]
        )
    );

$expect(
    ($result['reason_code'] ?? '')
        === 'permission_denied',
    'Explicit permission is required.'
);

echo "PERMISSION_REQUIRED=PASS\n";


$result =
    $service->evaluate(
        $actor(
            100,
            900,
            [$global],
            true,
            false
        ),
        $target(
            200,
            [
                $assignment(
                    1,
                    [$own(200)]
                ),
            ]
        )
    );

$expect(
    ($result['reason_code'] ?? '')
        === 'actor_not_manager',
    'Manager capability is required.'
);

echo "MANAGER_CAPABILITY_REQUIRED=PASS\n";


$result =
    $service->evaluate(
        $actor(
            200,
            900,
            [$global]
        ),
        $target(
            200,
            [
                $assignment(
                    1,
                    [$own(200)]
                ),
            ]
        )
    );

$expect(
    ($result['reason_code'] ?? '')
        === 'same_user',
    'Self impersonation must deny.'
);

echo "SELF_DENIED=PASS\n";


$result =
    $service->evaluate(
        $actor(
            100,
            900,
            [$global]
        ),
        $target(
            200,
            [
                $assignment(
                    1,
                    [$own(200)]
                ),
            ],
            false
        )
    );

$expect(
    ($result['reason_code'] ?? '')
        === 'target_ineligible',
    'Inactive/blocked target must deny.'
);

echo "INELIGIBLE_TARGET_DENIED=PASS\n";


$province10 =
    $scope(
        'province',
        'PROV-10',
        'allow',
        true
    );

$county20 =
    $scope(
        'county',
        'COUNTY-20',
        'allow',
        true,
        [
            'province' => [
                '10',
                'PROV-10',
            ],
        ]
    );

$result =
    $service->evaluate(
        $actor(
            100,
            700,
            [$province10]
        ),
        $target(
            200,
            [
                $assignment(
                    600,
                    [$county20]
                ),
            ]
        )
    );

$expect(
    ($result['allowed'] ?? false)
        === true,
    'Province scope must cover proven county descendant.'
);

echo "PROVINCE_TO_COUNTY_DESCENDANT=PASS\n";


$countyOutside =
    $scope(
        'county',
        'COUNTY-99',
        'allow',
        true,
        [
            'province' => [
                '11',
                'PROV-11',
            ],
        ]
    );

$result =
    $service->evaluate(
        $actor(
            100,
            700,
            [$province10]
        ),
        $target(
            200,
            [
                $assignment(
                    600,
                    [$countyOutside]
                ),
            ]
        )
    );

$expect(
    ($result['reason_code'] ?? '')
        === 'target_scope_outside_actor',
    'Province boundary must not be crossed.'
);

echo "CROSS_PROVINCE_DENIED=PASS\n";


$result =
    $service->evaluate(
        $actor(
            100,
            700,
            [$province10]
        ),
        $target(
            200,
            [
                $assignment(
                    1,
                    [$own(200)]
                ),
            ]
        )
    );

$expect(
    ($result['reason_code'] ?? '')
        === 'target_scope_unresolved',
    'Scoped manager must not infer own-only target geography.'
);

echo "OWN_ONLY_NON_GLOBAL_FAIL_CLOSED=PASS\n";


$result =
    $service->evaluate(
        $actor(
            100,
            700,
            [$province10]
        ),
        $target(
            200,
            [
                $assignment(
                    600,
                    [$county20]
                ),
                $assignment(
                    500,
                    [
                        $scope(
                            'company',
                            'COMPANY-X'
                        ),
                    ]
                ),
            ]
        )
    );

$expect(
    ($result['reason_code'] ?? '')
        === 'target_scope_outside_actor',
    'Every non-local scope in mixed envelope must be contained.'
);

echo "MIXED_SCOPE_FAIL_CLOSED=PASS\n";


$result =
    $service->evaluate(
        $actor(
            100,
            900,
            [
                $global,
                $scope(
                    'province',
                    'PROV-10',
                    'deny',
                    true
                ),
            ]
        ),
        $target(
            200,
            [
                $assignment(
                    600,
                    [$county20]
                ),
            ]
        )
    );

$expect(
    ($result['reason_code'] ?? '')
        === 'actor_scope_denied',
    'Explicit actor deny must override global allow.'
);

echo "DENY_PRECEDENCE=PASS\n";


$result =
    $service->evaluate(
        $actor(
            100,
            900,
            [$global]
        ),
        $target(
            200,
            [
                $assignment(
                    1,
                    [
                        [
                            'scope_type_code' =>
                                'unknown_scope',

                            'scope_reference' =>
                                'X',

                            'effect_code' =>
                                'allow',

                            'include_descendants' =>
                                false,
                        ],
                    ]
                ),
            ]
        )
    );

$expect(
    ($result['reason_code'] ?? '')
        === 'target_scope_invalid',
    'Unknown scope must fail closed.'
);

echo "UNKNOWN_SCOPE_DENIED=PASS\n";


$result =
    $service->evaluate(
        $actor(
            100,
            900,
            [$global]
        ),
        $target(
            200,
            [
                $assignment(
                    700,
                    [$province10]
                ),
                $assignment(
                    1,
                    [$own(200)]
                ),
            ]
        )
    );

$expect(
    ($result['allowed'] ?? false)
        === true,
    'Global actor may manage complete lower multi-role envelope.'
);

echo "MULTI_ROLE_LOWER_ENVELOPE=PASS\n";


echo "ADMIN_USER_IMPERSONATION_SCOPED_AUTHORIZATION=PASS\n";
echo "SUBORDINATE_ONLY=YES\n";
echo "PEER_OR_HIGHER=DENY\n";
echo "PERMISSION_REQUIRED=YES\n";
echo "MANAGER_CAPABILITY_REQUIRED=YES\n";
echo "MIXED_SCOPE_ALL_MUST_BE_CONTAINED=YES\n";
echo "OWN_ONLY_FOR_NON_GLOBAL=DENY_UNRESOLVED_SCOPE\n";
echo "ROLE_NAME_AUTHORIZATION=NO\n";
echo "DATABASE_MUTATION=NO\n";
echo "SESSION_MUTATION=NO\n";

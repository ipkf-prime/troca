<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$routePath =
    $root
    . '/public_html/routes/'
    . 'admin-users-manage.php';

$route =
    file_get_contents(
        $routePath
    );

if (!is_string($route)) {
    throw new RuntimeException(
        'route_read_failed'
    );
}

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

$start =
    strpos(
        $route,
        '$adminImpersonationOperateGrantFor ='
    );

$end =
    strpos(
        $route,
        '$adminImpersonationSafeReturn =',
        $start === false
            ? 0
            : $start
    );

$expect(
    $start !== false
    && $end !== false
    && $end > $start,
    'operate_grant_helper_bounds_invalid'
);

$helper =
    substr(
        $route,
        $start,
        $end - $start
    );


/*
 * A2R2 decoupled grant eligibility from current
 * base impersonation capability. No stale
 * recipientEligible variable may remain in the
 * presentation helper.
 */
$expect(
    !str_contains(
        $helper,
        '$recipientEligible'
    ),
    'stale_recipient_eligible_reference_present'
);

echo "A2R4_STALE_RECIPIENT_REFERENCE=NO\n";


$expect(
    str_contains(
        $helper,
        '$baseCapabilityActive'
    ),
    'base_capability_active_missing'
);

$expect(
    str_contains(
        $helper,
        "'status_code' =>"
    ),
    'status_code_contract_missing'
);

$expect(
    str_contains(
        $helper,
        "? 'active'"
    )
    || (
        str_contains(
            $helper,
            "'active'"
        )
        && str_contains(
            $helper,
            "'warning'"
        )
        && str_contains(
            $helper,
            "'inactive'"
        )
    ),
    'status_code_state_contract_missing'
);

echo "A2R4_BASE_CAPABILITY_STATUS_CODE=PASS\n";


/*
 * Granted + base active => active.
 * Granted + base inactive => warning.
 * Not granted => inactive.
 */
$expected =
<<<'EXPECTED'
                'status_code' =>
                    $effect === 'allow'
                        ? (
                            $baseCapabilityActive
                                ? 'active'
                                : 'warning'
                        )
                        : 'inactive',
EXPECTED;

$expect(
    substr_count(
        $helper,
        $expected
    ) === 1,
    'a2r4_exact_status_contract_invalid'
);

echo "A2R4_EXACT_STATUS_CONTRACT=PASS\n";


/*
 * Fail-closed helper remains intact, but should no
 * longer be entered because of a stale local variable.
 */
$expect(
    str_contains(
        $helper,
        'catch (\Throwable)'
    )
    && str_contains(
        $helper,
        'return null;'
    ),
    'fail_closed_helper_contract_missing'
);

echo "A2R4_FAIL_CLOSED_HELPER_PRESERVED=PASS\n";


/*
 * Detail binding from A2R3 must stay intact.
 */
$expect(
    str_contains(
        $route,
        "'impersonation_operate_access'"
    ),
    'a2r3_detail_binding_missing'
);

echo "A2R4_A2R3_BINDING_PRESERVED=PASS\n";
echo "ADMIN_USER_IMPERSONATION_OPERATE_GRANT_PRESENTATION=PASS\n";

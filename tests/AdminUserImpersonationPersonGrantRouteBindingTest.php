<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $value =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'read_failed:'
                . $relative
            );
        }

        return $value;
    };

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

$route =
    $read(
        'public_html/routes/'
        . 'admin-users-manage.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'user-detail.php'
    );


/*
 * Route must create the panel only for Access tab.
 */
$expect(
    str_contains(
        $route,
        "if (\$tab === 'access')"
    ),
    'access_tab_grant_binding_missing'
);

$expect(
    str_contains(
        $route,
        'ImpersonationOperateGrantService'
    ),
    'grant_service_route_binding_missing'
);

$expect(
    str_contains(
        $route,
        'ImpersonationOperateGrantUiService'
    ),
    'grant_ui_route_binding_missing'
);

echo "A2R3_ROUTE_BUILDS_PANEL=PASS\n";


/*
 * Panel is presentation state and must be bound to the
 * page Detail object, not buried inside tab content.
 */
$topLevel =
    strpos(
        $route,
        "\$detail[\n"
        . "                    'impersonation_operate_access'\n"
        . "                ]"
    );

$expect(
    $topLevel !== false,
    'detail_top_level_binding_missing'
);

$expect(
    !str_contains(
        $route,
        "\$detail['content'][\n"
        . "                    'impersonation_operate_access'"
    ),
    'legacy_content_binding_still_present'
);

echo "A2R3_DETAIL_TOP_LEVEL_BINDING=PASS\n";


/*
 * View must consume the exact same contract.
 */
$expect(
    str_contains(
        $view,
        "\$detail[\n"
        . "                'impersonation_operate_access'\n"
        . "            ]"
    ),
    'view_detail_binding_missing'
);

$expect(
    !str_contains(
        $view,
        "\$content[\n"
        . "                'impersonation_operate_access'\n"
        . "            ]"
    ),
    'view_legacy_content_binding_still_present'
);

echo "A2R3_VIEW_CONSUMES_DETAIL_BINDING=PASS\n";


/*
 * Sensitive endpoint remains unchanged.
 */
$expect(
    str_contains(
        $route,
        '/admin/users/{id}/impersonation-operate-access'
    ),
    'grant_revoke_endpoint_missing'
);

echo "A2R3_SENSITIVE_ENDPOINT_PRESERVED=PASS\n";
echo "ADMIN_USER_IMPERSONATION_PERSON_GRANT_ROUTE_BINDING=PASS\n";

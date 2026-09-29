<?php

declare(strict_types=1);

$root =
    dirname(__DIR__)
    . '/public_html';

$dynamic =
    file_get_contents(
        $root
        . '/app/Services/DynamicAdminNavigationService.php'
    );

$panel =
    file_get_contents(
        $root
        . '/app/Services/AdminPanelService.php'
    );

$modules =
    file_get_contents(
        $root
        . '/app/Services/ApplicationModuleRegistryService.php'
    );

$reconciler =
    file_get_contents(
        $root
        . '/scripts/apply-core-navigation-parity.php'
    );

$checks = [
    'runtime module gate' =>
        str_contains(
            $dynamic,
            'CORE_NAVIGATION_MODULE_RUNTIME_GATE_V1'
        ),

    'hub/sidebar child parity' =>
        str_contains(
            $panel,
            'CORE_HUB_CARDS_FROM_SIDEBAR_CHILDREN_V1'
        )
        &&
        str_contains(
            $panel,
            "->children("
        ),

    'independent module navigation sync' =>
        str_contains(
            $modules,
            'MODULE_NAVIGATION_RECONCILIATION_ISOLATED_V1'
        ),

    'communications dashboard card' =>
        str_contains(
            $reconciler,
            "item_key = 'communications'"
        )
        &&
        str_contains(
            $reconciler,
            'dashboard_enabled = 1'
        ),

    'users hierarchy seed' =>
        str_contains(
            $reconciler,
            "'users-list'"
        ),

    'organization hierarchy seed' =>
        str_contains(
            $reconciler,
            "'organization-affiliations'"
        ),

    'system hierarchy seed' =>
        str_contains(
            $reconciler,
            "'system-scheduler'"
        )
        &&
        str_contains(
            $reconciler,
            "'core-feature-settings'"
        ),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        fwrite(
            STDERR,
            "FAIL: {$name}\n"
        );

        exit(1);
    }
}

echo
    "CORE_NAVIGATION_SURFACE_PARITY_REGRESSION_PASS\n";

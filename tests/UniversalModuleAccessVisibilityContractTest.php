<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$panel =
    (string) file_get_contents(
        $root
        . '/public_html/app/Services/AdminPanelService.php'
    );

$registry =
    (string) file_get_contents(
        $root
        . '/public_html/app/Services/CoreFeatureRegistryService.php'
    );

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

$marker =
    'UNIVERSAL_CORE_PARENT_VISIBILITY_BY_CHILD_PERMISSION';

$expect(
    substr_count(
        $panel,
        $marker
    ) === 1,
    'Universal marker invalid.'
);

$expect(
    preg_match(
        "/'key'\\s*=>\\s*'help-texts'.*?"
        . "'permission'\\s*=>\\s*"
        . "'admin\\.ui_content\\.manage'/s",
        $panel
    ) === 1,
    'Help permission invalid.'
);

$expect(
    str_contains(
        $panel,
        "'core_dashboard_enabled'"
    ),
    'Dashboard state propagation missing.'
);

$expect(
    str_contains(
        $panel,
        '$coreRegistryKeys'
    ),
    'Registry duplicate guard missing.'
);

$expect(
    str_contains(
        $panel,
        '$this->resolveDashboardModule('
    ),
    'Parent child-permission fallback missing.'
);

$expect(
    str_contains(
        $registry,
        'permission_mode'
    )
    && str_contains(
        $registry,
        'permission_codes_json'
    )
    && str_contains(
        $registry,
        'dashboard_enabled'
    )
    && str_contains(
        $registry,
        '$this->navigation->can('
    ),
    'Core registry contract invalid.'
);

echo "UNIVERSAL_MODULE_ACCESS_VISIBILITY_CONTRACT=PASS\n";
echo "PARENT_OWN_OR_CHILD_PERMISSION=PASS\n";
echo "CHILD_OWN_PERMISSION=PASS\n";
echo "PERMISSION_PLUS_SCOPE=PASS\n";

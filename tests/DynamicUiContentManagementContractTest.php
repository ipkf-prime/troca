<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

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

$paths = [
    'service' =>
        $root
        . '/public_html/app/Services/UiContent/'
        . 'UiContentManagementService.php',

    'routes' =>
        $root
        . '/public_html/routes/'
        . 'system-help-texts.php',

    'view' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'help-texts.php',

    'rbac' =>
        $root
        . '/public_html/app/Services/'
        . 'AdminNavigationRbacService.php',

    'loader' =>
        $root
        . '/public_html/system/Routing/'
        . 'RouteLoader.php',

    'migration' =>
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateDynamicUiContentFoundation.php',

    'presenter' =>
        $root
        . '/public_html/app/Services/UiContent/'
        . 'UiContentHttpPresenter.php',
];

$source = [];

foreach ($paths as $key => $path) {
    $expect(
        is_readable($path),
        'C2 required file missing: '
        . $key
    );

    $value =
        file_get_contents(
            $path
        );

    $expect(
        is_string($value),
        'C2 file unreadable: '
        . $key
    );

    $source[$key] =
        $value;
}

foreach (
    [
        'ui_content_definitions',
        'ui_content_overrides',
        'public function saveDefinition(',
        'public function saveOverride(',
        'SELECT GET_LOCK(?, 5)',
        'beginTransaction()',
        'FOR UPDATE',
        'UiContentContext',
        'UiContentModuleCatalogService',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['service'],
            $marker
        ),
        'C2 service marker missing: '
        . $marker
    );
}

foreach (
    [
        "'back'",
        "'home'",
        "'admin_home'",
        "'module_home'",
    ]
    as $action
) {
    $expect(
        str_contains(
            $source['service'],
            $action
        ),
        'Finite action missing: '
        . $action
    );
}

$expect(
    !str_contains(
        $source['service'],
        'action_url'
    ),
    'Arbitrary action URL must not be managed.'
);

$expect(
    !str_contains(
        $source['service'],
        'DELETE FROM'
    ),
    'C2 must not physically delete UI content.'
);

$expect(
    !str_contains(
        $source['service'],
        'NotificationGatewayService'
    ),
    'UI presentation content must stay separate from outbound notifications.'
);

foreach (
    [
        '/admin/system/help-texts',
        '/admin/system/help-texts/definition/save',
        '/admin/system/help-texts/override/save',
    ]
    as $route
) {
    $expect(
        str_contains(
            $source['routes'],
            $route
        ),
        'C2 route missing: '
        . $route
    );

    $expect(
        str_contains(
            $source['rbac'],
            "'" . $route . "'"
        ),
        'C2 RBAC route missing: '
        . $route
    );
}

$expect(
    str_contains(
        $source['routes'],
        'new \IPKF\Security\Csrf()'
    ),
    'C2 CSRF contract missing.'
);

foreach (
    [
        'مدیریت راهنما، اعلان و خطا',
        'تعریف محتوای جدید',
        'محدوده تخصصی',
        'عدم نمایش',
        'ساختار فنی محدوده تخصصی',
        'اطلاعات تکمیلی فنی',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $source['view'],
            $marker
        ),
        'C2 UI marker missing: '
        . $marker
    );
}

$expect(
    str_contains(
        $source['loader'],
        'routes/system-help-texts.php'
    ),
    'C2 source route loader registration missing.'
);

$expect(
    str_contains(
        $source['migration'],
        'ui_content_definitions'
    )
    && str_contains(
        $source['migration'],
        'ui_content_overrides'
    ),
    'C1 schema foundation missing.'
);

$expect(
    !str_contains(
        $source['migration'],
        'action_url'
    ),
    'C1 arbitrary URL prohibition regressed.'
);

foreach (
    [
        "'back'",
        "'home'",
        "'admin_home'",
        "'module_home'",
    ]
    as $action
) {
    $expect(
        str_contains(
            $source['presenter'],
            $action
        ),
        'Presenter action contract missing: '
        . $action
    );
}

echo "DYNAMIC_UI_CONTENT_MANAGEMENT_CONTRACT=PASS\n";
echo "GUIDE_MANAGEMENT=PASS\n";
echo "NOTICE_MANAGEMENT=PASS\n";
echo "ERROR_MANAGEMENT=PASS\n";
echo "GENERIC_SCOPE_MANAGEMENT=PASS\n";
echo "FINITE_ACTION_ALLOWLIST=PASS\n";
echo "ARBITRARY_ACTION_URL=FORBIDDEN\n";
echo "PHYSICAL_DELETE=FORBIDDEN\n";
echo "OUTBOUND_NOTIFICATION_COUPLING=FORBIDDEN\n";

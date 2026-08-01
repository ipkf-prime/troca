<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$repository = $read(
    'public_html/app/Repositories/'
    . 'AdminUserManagementRepository.php'
);
$adminView = $read(
    'public_html/resources/views/admin/'
    . 'admin-user-form.php'
);
$selfView = $read(
    'public_html/resources/views/admin/'
    . 'self-profile-edit.php'
);
$diagnostic = $read(
    'public_html/scripts/check-address-cascade.php'
);

$expect(
    str_contains(
        $repository,
        "'province_location_id' =>"
    )
    && str_contains(
        $repository,
        "'county_location_id' =>"
    ),
    'Dynamic geography parent keys are incomplete.'
);

$expect(
    str_contains(
        $repository,
        "relation_types.code ="
    )
    && str_contains(
        $repository,
        "'administrative_parent'"
    )
    && str_contains(
        $repository,
        'relations.is_primary = 1'
    ),
    'Geography graph does not use the primary '
    . 'administrative hierarchy.'
);

$expect(
    str_contains(
        $repository,
        'roles.priority ASC'
    )
    && !str_contains(
        $repository,
        "CASE WHEN roles.code = 'user'"
    ),
    'Role initial order does not follow '
    . 'database priority.'
);

$expect(
    !str_contains(
        $adminView,
        "sortRoles('code')"
    )
    && str_contains(
        $adminView,
        'Initial order is roles.priority'
    ),
    'Client-side code sorting still overrides '
    . 'database order.'
);

foreach ([$adminView, $selfView] as $view) {
    $expect(
        str_contains(
            $view,
            'buildLocationCascade'
        )
        && str_contains(
            $view,
            'select.replaceChildren'
        )
        && str_contains(
            $view,
            'item.provinceId === provinceId'
        ),
        'Cascading province/county/city filtering '
        . 'is incomplete.'
    );
}

$expect(
    str_contains(
        $diagnostic,
        'administrative_parent'
    )
    && str_contains(
        $diagnostic,
        'counties_with_province_parent'
    ),
    'Address cascade diagnostic is incomplete.'
);

echo "Cascading geography and role order checks passed.\n";

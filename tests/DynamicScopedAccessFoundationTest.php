<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $relative
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $relative
    );

    if (!is_string($content)) {
        throw new RuntimeException(
            'Cannot read: ' . $relative
        );
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'CreateDynamicScopedAccessFoundation.php'
);

$service = $read(
    'public_html/app/Services/'
    . 'DynamicAccessService.php'
);

$scoped = $read(
    'public_html/app/Services/'
    . 'ScopedAuthorizationService.php'
);

$routes = $read(
    'public_html/routes/web.php'
);

$accessView = $read(
    'public_html/resources/views/admin/'
    . 'access-control.php'
);

$roleView = $read(
    'public_html/resources/views/admin/'
    . 'access-role-create.php'
);

$scopeView = $read(
    'public_html/resources/views/admin/'
    . 'access-scope-editor.php'
);

$migrate = $read(
    'public_html/public/migrate.php'
);

$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);

foreach ([
    'access_scope_types',
    'access_constraint_types',
    'role_scope_policies',
    'role_identity_requirements',
    'role_assignment_scopes',
    'role_assignment_constraints',
    'access.scopes.manage',
    "['province','استان'",
    "['county','شهرستان'",
    "['company','شرکت'",
    "['project','پروژه'",
    "['own','فقط داده‌های خود کاربر'",
    "['assigned','فقط موارد تخصیص‌یافته'",
] as $marker) {
    $expect(
        str_contains($migration, $marker),
        'Migration marker missing: '
        . $marker
    );
}

foreach ([
    'public function roleBuilder(',
    'public function createRole(',
    'public function scopeEditor(',
    'public function saveAssignmentPolicy(',
    'public function assignmentPolicy(',
    'public function assignmentForUser(',
    'custom_role_created',
    'assignment_scope_policy_updated',
    '/^custom_[a-z][a-z0-9_]{2,60}$/',
] as $marker) {
    $expect(
        str_contains($service, $marker),
        'Dynamic access service marker missing: '
        . $marker
    );
}

foreach ([
    'public function decide(',
    'public function hasPermissionInContext(',
    'legacy_assignment_without_scope_policy',
    'scope_denied',
    'constraint_denied',
    'include_descendants',
] as $marker) {
    $expect(
        str_contains($scoped, $marker),
        'Scoped authorization marker missing: '
        . $marker
    );
}

foreach ([
    '/admin/access-control/roles/create',
    '/admin/access-control/scopes',
    'DYNAMIC_SCOPED_ACCESS_FOUNDATION_V1',
] as $marker) {
    $expect(
        str_contains($routes, $marker),
        'Route marker missing: '
        . $marker
    );
}

$expect(
    str_contains(
        $accessView,
        'DYNAMIC_SCOPED_ACCESS_ENTRY_V1'
    )
    && str_contains(
        $accessView,
        'ایجاد نقش جدید'
    )
    && str_contains(
        $accessView,
        'حوزه و محدودیت انتساب‌ها'
    ),
    'Access-control entry points missing.'
);

foreach ([
    'scope_types[]',
    'identity_fields[]',
    'permissions[]',
    'name="code"',
    "'custom_'",
] as $marker) {
    $expect(
        str_contains($roleView, $marker),
        'Role builder marker missing: '
        . $marker
    );
}

foreach ([
    'data-scope-row',
    'include_descendants',
    'data-constraint-row',
    'data-add-scope',
    'data-add-constraint',
] as $marker) {
    $expect(
        str_contains($scopeView, $marker),
        'Scope editor marker missing: '
        . $marker
    );
}

$expect(
    str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\'
        . 'CreateDynamicScopedAccessFoundation()'
    ),
    'Legacy migration registration missing.'
);

$expect(
    str_contains(
        $registry,
        '\\IPKF\\Database\\Migrations\\'
        . 'CreateDynamicScopedAccessFoundation::class'
    ),
    'Application migration registration missing.'
);

echo "DYNAMIC_SCOPED_ACCESS_FOUNDATION_TEST=PASS\n";

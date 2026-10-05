<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $path
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $path
    );

    if (!is_string($content)) {
        fwrite(
            STDERR,
            "Unable to read: {$path}\n"
        );
        exit(1);
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );
        exit(1);
    }
};

$repository = $read(
    'public_html/app/Repositories/'
    . 'AdminUserManagementRepository.php'
);

$service = $read(
    'public_html/app/Services/'
    . 'AdminUserManagementService.php'
);

$view = $read(
    'public_html/resources/views/admin/'
    . 'admin-user-form.php'
);

$expect(
    str_contains($repository, 'role_kinds')
    && str_contains($repository, 'role_areas'),
    'RBAC classifications are missing.'
);

$expect(
    str_contains($repository, 'role_kind_code')
    && str_contains($repository, 'role_area_code'),
    'Role metadata is incomplete.'
);

$expect(
    str_contains($service, "'role_kinds'")
    && str_contains($service, "'role_areas'"),
    'Access metadata is not returned.'
);

$expect(
    str_contains($view, 'data-user-tab="account"')
    && str_contains($view, 'data-user-tab="contact"')
    && str_contains($view, 'data-user-tab="access"'),
    'Tab-based user editor is missing.'
);

$expect(
    str_contains($view, 'خلاصه نقش و دسترسی')
    && str_contains(
        $view,
        'این بخش فقط وضعیت فعلی را نمایش می‌دهد.'
    )
    && str_contains(
        $view,
        '/admin/access-control'
    ),
    'Read-only access presentation is incomplete.'
);

$expect(
    str_contains(
        $view,
        'user-access-summary-grid'
    )
    && str_contains(
        $view,
        'user-access-summary-role'
    )
    && str_contains(
        $view,
        'data-role-lifecycle='
    ),
    'Role/access summary metadata is incomplete.'
);

$expect(
    !str_contains(
        $view,
        'name="role_ids[]"'
    )
    && !str_contains(
        $view,
        'data-role-search'
    )
    && !str_contains(
        $view,
        'data-role-summary'
    )
    && !str_contains(
        $view,
        'data-role-sort'
    ),
    'Legacy inline role management remains.'
);

$expect(
    str_contains(
        $view,
        "addEventListener('invalid'"
    )
    && str_contains(
        $view,
        'activate('
    ),
    'Invalid fields must activate their tab.'
);

$expect(
    str_contains(
        $view,
        'user-editor__head'
    )
    && !str_contains(
        $view,
        'admin-module-hub--blue'
    ),
    'Compact user editor header contract is missing.'
);

$expect(
    str_contains(
        $view,
        'core.admin-user-form.guide.01'
    )
    && str_contains(
        $view,
        'core.admin-user-form.guide.02'
    )
    && str_contains(
        $view,
        'UiContentInlineGuide::titleHtml'
    )
    && str_contains(
        $view,
        'UiContentInlineGuide::bodyHtml'
    ),
    'Dynamic access guidance is incomplete.'
);

echo "Admin user form polish checks passed.\n";

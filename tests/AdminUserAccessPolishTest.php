<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'admin-user-form.php'
);

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

$expect(
    is_string($view),
    'Admin user form is not readable.'
);

$expect(
    str_contains(
        $view,
        'خلاصه نقش و دسترسی'
    )
    && str_contains(
        $view,
        'این بخش فقط وضعیت فعلی را نمایش می‌دهد.'
    )
    && str_contains(
        $view,
        'تغییر نقش، حوزه و مجوز از مرکز کنترل دسترسی انجام می‌شود.'
    ),
    'Read-only access summary guidance is incomplete.'
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
    'Structured access summary is missing.'
);

$expect(
    str_contains(
        $view,
        '/admin/access-control'
    )
    && str_contains(
        $view,
        "'?tab=users'"
    )
    && str_contains(
        $view,
        "'&user_id='"
    )
    && str_contains(
        $view,
        'مدیریت نقش و دسترسی این کاربر'
    ),
    'User-specific Access Control delegation is incomplete.'
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
    'Dynamic Access Control guidance is incomplete.'
);

$expect(
    str_contains(
        $view,
        'data-role-count'
    )
    && str_contains(
        $view,
        'count($selectedRoleIds)'
    ),
    'Read-only role count presentation is missing.'
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
    )
    && !str_contains(
        $view,
        'formnovalidate>ذخیره نقش‌ها و دسترسی‌ها'
    ),
    'Legacy inline role/access mutation controls remain.'
);

echo "Admin user access polish checks passed.\n";

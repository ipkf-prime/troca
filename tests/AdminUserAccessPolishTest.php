<?php

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
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expect(
    str_contains($view, 'فیلتر نقش‌ها')
    && str_contains($view, 'نقش‌های کاربر'),
    'Access sections do not have clear titles.'
);

$expect(
    str_contains($view, 'عنوان نقش')
    && str_contains($view, 'نوع دسترسی')
    && str_contains($view, 'حوزه دسترسی'),
    'Role list column headings are missing.'
);

$expect(
    str_contains($view, 'role-table__head')
    && str_contains($view, 'role-table__body'),
    'Structured role table is missing.'
);

$expect(
    str_contains($view, 'access-card + .access-card')
    && str_contains($view, 'margin-top: .75rem'),
    'Access sections do not have sufficient visual separation.'
);

$expect(
    str_contains($view, 'data-role-summary')
    && str_contains($view, 'انتخاب‌های فعلی'),
    'Selected-role summary is missing.'
);

$expect(
    str_contains(
        $view,
        "querySelectorAll('[data-role-count]')"
    ),
    'Multiple role counters are not synchronized.'
);

$expect(
    !str_contains(
        $view,
        '<div class="role-list"'
    ),
    'Old compact role list wrapper remains.'
);

echo "Admin user access polish checks passed.\n";

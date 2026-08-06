<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root
    . '/public_html/resources/views/admin/'
    . 'access-control.php';
$content = file_get_contents($path);

if (!is_string($content)) {
    fwrite(
        STDERR,
        "FAIL: access-control.php is not readable.\n"
    );
    exit(1);
}

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
    str_contains($content, 'data-acl-role-picker')
    && str_contains($content, 'data-acl-role-button')
    && str_contains($content, 'data-acl-role-panel'),
    'Single-role workbench controls are missing.'
);

$expect(
    str_contains($content, 'data-acl-module-picker')
    && str_contains($content, 'data-acl-module-button')
    && str_contains($content, 'data-acl-module-panel'),
    'Single-module permission controls are missing.'
);

$expect(
    str_contains($content, 'class="acl-native"')
    && str_contains($content, 'class="acl-checkbox"')
    && str_contains($content, 'class="acl-radio"')
    && str_contains($content, 'height: 15px;')
    && str_contains($content, 'width: 15px;'),
    'Minimal checkbox and radio controls are incomplete.'
);

$expect(
    str_contains($content, '@media (max-width: 1180px)')
    && str_contains($content, '@media (max-width: 760px)')
    && str_contains($content, '@media (max-width: 520px)')
    && str_contains($content, '.acl-audit-table td::before'),
    'Responsive desktop, tablet, mobile, or audit behavior is incomplete.'
);

$expect(
    str_contains($content, 'data-acl-tech-toggle')
    && str_contains($content, 'data-acl-dirty')
    && str_contains($content, 'sessionStorage.setItem')
    && str_contains($content, 'Intl.NumberFormat(\'fa-IR\')'),
    'Technical details, unsaved state, or Persian counters are incomplete.'
);

$expect(
    !str_contains($content, '<details class="acl-role"'),
    'Legacy all-roles-open layout is still present.'
);

echo "Access control responsive UI checks passed.\n";

<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$repository = $read('public_html/app/Repositories/AdminUserManagementRepository.php');
$service = $read('public_html/app/Services/AdminUserManagementService.php');
$view = $read('public_html/resources/views/admin/admin-user-form.php');

$expect(str_contains($repository, 'role_kinds') && str_contains($repository, 'role_areas'), 'RBAC classifications are missing.');
$expect(str_contains($repository, 'role_kind_code') && str_contains($repository, 'role_area_code'), 'Role metadata is incomplete.');
$expect(str_contains($service, "'role_kinds'") && str_contains($service, "'role_areas'"), 'Access options are not returned.');
$expect(str_contains($service, "'access_kind'") && str_contains($service, "'access_area'"), 'Access filter state is missing.');
$expect(str_contains($view, 'data-user-tab="account"') && str_contains($view, 'data-user-tab="access"'), 'Tab-based UI is missing.');
$expect(str_contains($view, 'نوع سطح دسترسی') && str_contains($view, 'حوزه دسترسی'), 'Access type controls are missing.');
$expect(str_contains($view, 'data-role-search') && str_contains($view, 'data-role-summary'), 'Compact role management is missing.');
$expect(str_contains($view, "addEventListener('invalid'") && str_contains($view, 'activate('), 'Invalid fields must activate their tab.');
$expect(str_contains($view, 'user-editor__head') && !str_contains($view, 'admin-module-hub--blue'), 'Oversized header remains.');
$expect(str_contains($view, 'مجوزهای ریزدانه') && str_contains($view, 'مرحله بعد'), 'Permission extension point is missing.');

echo "Admin user form polish checks passed.\n";

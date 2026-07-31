<?php
$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path);
$expect = static function(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};
$repository = $read('public_html/app/Repositories/AdminUserManagementRepository.php');
$service = $read('public_html/app/Services/AdminUserManagementService.php');
$completion = $read('public_html/app/Services/AdminUserDetailCompletionService.php');
$form = $read('public_html/resources/views/admin/admin-user-form.php');
$routes = $read('public_html/routes/admin-users-manage.php');
$expect(str_contains($repository, 'person_contacts') && str_contains($repository, 'person_addresses') && str_contains($repository, 'person_profiles'), 'Person contact/address/profile persistence is missing.');
$expect(str_contains($repository, 'syncPrimaryContacts') && str_contains($repository, 'syncPrimaryAddress') && str_contains($repository, 'syncPersonProfile'), 'Extended user persistence is incomplete.');
$expect(str_contains($service, "'national_code'") && str_contains($service, "'birth_place'") && str_contains($service, "'postal_code'"), 'Extended form validation is incomplete.');
$expect(str_contains($form, 'data-user-tab="account"') && str_contains($form, 'data-user-tab="contact"') && str_contains($form, 'data-user-tab="access"'), 'Three-tab responsive form is missing.');
$expect(str_contains($form, 'نشانی کامل') && str_contains($form, 'عنوان ایمیل') && str_contains($form, 'کد ملی'), 'Required profile fields are not exposed.');
$expect(str_contains($form, 'grid-template-columns:repeat(3') && str_contains($form, '@media (max-width:760px)'), 'Responsive alignment rules are missing.');
$expect(str_contains($completion, "tab !== 'contacts'") && str_contains($completion, 'detailFallback'), 'Detail fallback for account contacts is missing.');
$expect(str_contains($routes, "'/admin/users/{id}/contacts'") && str_contains($routes, 'AdminUserDetailCompletionService'), 'Detail route override is missing.');
$expect(!preg_match('/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $repository), 'Destructive SQL is present.');
echo "Admin user complete profile checks passed.\n";

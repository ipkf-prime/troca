<?php

$root = dirname(__DIR__);
$setup = file_get_contents($root . '/public_html/resources/views/admin/organization-setup.php');
$appointments = file_get_contents($root . '/public_html/resources/views/admin/appointments.php');
$automation = file_get_contents($root . '/public_html/resources/views/admin/automation-correspondence-form.php');
$service = file_get_contents($root . '/public_html/app/Services/Organization/OrganizationSetupService.php');
$css = file_get_contents($root . '/public_html/public/assets/admin/css/admin.css');

$checks = [
    'organization records returned' => str_contains($service, "'records' => ["),
    'organization records table' => str_contains($setup, 'آخرین سازمان‌های ثبت‌شده'),
    'unit records table' => str_contains($setup, 'آخرین واحدهای سازمانی ثبت‌شده'),
    'position records table' => str_contains($setup, 'آخرین پست‌های سازمانی تعریف‌شده'),
    'identity records table' => str_contains($setup, 'آخرین اتصال‌های حساب کاربری و شخص'),
    'responsive record table' => str_contains($css, '.admin-record-table td::before'),
    'visually separated records' => str_contains($css, '.admin-records-block::before') && str_contains($appointments, 'admin-records-section'),
    'proportional appointment search' => str_contains($css, '.admin-appointment-search .admin-users-search__row') && str_contains($css, 'minmax(260px, 1fr) auto'),
    'proportional automation filters' => str_contains($automation, 'automation-draft-tabs') && str_contains($css, '.automation-filter-grid__search'),
    'semantic warning' => str_contains($setup, 'admin-alert--warning') && str_contains($css, '.admin-alert--warning'),
    'compact chart action' => str_contains($setup, 'admin-module-hub__back') && str_contains($appointments, 'admin-module-hub__back'),
    'structured automation form' => substr_count($automation, 'automation-form-section') >= 3,
    'tabbed automation draft' => substr_count($automation, 'data-draft-tab=') === 4 && substr_count($automation, 'data-draft-panel=') === 4,
    'tab validation routing' => str_contains($automation, "form.addEventListener('invalid'") && str_contains($automation, "closest('[data-draft-panel]')"),
    'responsive automation parties' => str_contains($automation, 'data-automation-party'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAILED: {$label}\n");
        exit(1);
    }
}

echo "Organizational minimal sellable UI test passed.\n";

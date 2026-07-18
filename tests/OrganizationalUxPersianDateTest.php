<?php

require_once __DIR__ . '/../public_html/app/Support/PersianDate.php';

use App\Support\PersianDate;

assert(PersianDate::toGregorianDate('۱۴۰۵/۰۴/۲۷') === '2026-07-18');
assert(PersianDate::fromGregorianDate('2026-07-18', false) === '1405/04/27');

$root = dirname(__DIR__);
$views = implode("\n", [
    file_get_contents($root . '/public_html/resources/views/admin/appointments.php'),
    file_get_contents($root . '/public_html/resources/views/admin/automation-correspondence-form.php'),
    file_get_contents($root . '/public_html/resources/views/admin/automation-correspondences.php'),
]);
$javascript = file_get_contents($root . '/public_html/public/assets/admin/js/admin.js');
$css = file_get_contents($root . '/public_html/public/assets/admin/css/admin.css');

$checks = [
    'no native Gregorian date input' => !str_contains($views, 'type="date"') && !str_contains($views, "type='date'"),
    'no Gregorian UI explanation' => !str_contains($views, 'میلادی'),
    'Persian datepicker hooks' => substr_count($views, 'data-persian-datepicker') >= 5,
    'Persian calendar runtime' => str_contains($javascript, 'admin-persian-calendar') && str_contains($javascript, 'monthNames'),
    'Persian calendar styles' => str_contains($css, '.admin-persian-calendar'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAILED: {$label}\n");
        exit(1);
    }
}

echo "Organizational Persian date UX test passed.\n";

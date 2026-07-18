<?php

$root = dirname(__DIR__);
$view = file_get_contents($root . '/public_html/resources/views/admin/organization-setup.php');
$routes = file_get_contents($root . '/public_html/routes/web.php');
$css = file_get_contents($root . '/public_html/public/assets/admin/css/admin.css');

$checks = [
    'five tabs' => substr_count($view, 'class="admin-setup-tab"') === 5,
    'semantic success' => str_contains($view, 'admin-alert--success'),
    'semantic danger' => str_contains($view, 'admin-alert--danger'),
    'active tab session' => str_contains($routes, 'admin_organization_setup_tab'),
    'mobile tabs' => str_contains($css, '.admin-setup-tabs') && str_contains($css, 'grid-template-columns: repeat(2'),
    'saved records below steps' => substr_count($view, 'admin-records-block') >= 4,
    'compact chart action' => str_contains($view, 'admin-module-hub__back'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAILED: {$label}\n");
        exit(1);
    }
}

echo "Organization setup tabs UI test passed.\n";

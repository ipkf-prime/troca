<?php
$view = file_get_contents(__DIR__ . '/../public_html/resources/views/admin/organization-setup.php');
$routes = file_get_contents(__DIR__ . '/../public_html/routes/web.php');
$checks = [
    'five tabs' => substr_count($view, 'class="admin-setup-tab"') === 5,
    'semantic success' => str_contains($view, 'admin-alert--success'),
    'semantic danger' => str_contains($view, 'admin-alert--danger'),
    'active tab session' => str_contains($routes, 'admin_organization_setup_tab'),
    'mobile tabs' => str_contains($view, '@media(max-width:520px)'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) { fwrite(STDERR, "FAILED: {$label}\n"); exit(1); }
}
echo "Organization setup tabs UI test passed.\n";

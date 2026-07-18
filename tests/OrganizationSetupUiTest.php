<?php
$root = dirname(__DIR__);
$required = [
    'public_html/app/Services/Organization/OrganizationSetupService.php',
    'public_html/resources/views/admin/organization-setup.php',
];
foreach ($required as $path) { if (!is_file($root.'/'.$path)) { fwrite(STDERR,"Missing: {$path}\n"); exit(1); } }
$routes=file_get_contents($root.'/public_html/routes/web.php');
$nav=file_get_contents($root.'/public_html/app/Services/AdminNavigationRbacService.php');
foreach (['/admin/organization-setup','organization_setup_operational_ui_available'] as $token) { if (strpos($routes,$token)===false) { fwrite(STDERR,"Missing route token: {$token}\n"); exit(1); } }
if (strpos($nav,"'organizations.manage'")===false) { fwrite(STDERR,"Missing navigation permission\n"); exit(1); }
echo "Organization setup UI structural test passed.\n";

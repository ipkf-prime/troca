<?php

$path = __DIR__ . '/../public_html/system/Database/Seeds/AuthRbacSeeder.php';
$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "Unable to read AuthRbacSeeder.php\n");
    exit(1);
}

$start = strpos($source, "'system_admin' => [");
$end = $start === false ? false : strpos($source, "'province_admin' => [", $start);

if ($start === false || $end === false) {
    fwrite(STDERR, "Unable to locate the system_admin permission block\n");
    exit(1);
}

$systemAdminBlock = substr($source, $start, $end - $start);
$restrictedPermissions = [
    'access.manage',
    'admin.settings.manage',
    'admin.pages.manage',
    'admin.theme.manage',
    'admin.navigation.debug',
];
$operationalPermissions = [
    'users.manage',
    'org_units.manage',
    'positions.manage',
    'user_org_assignments.manage',
    'admin.reports.view',
];
$failures = [];

foreach ($restrictedPermissions as $permission) {
    if (str_contains($systemAdminBlock, "'{$permission}'")) {
        $failures[] = "Restricted permission remains assigned to system_admin: {$permission}";
    }

    if (!str_contains($source, "'{$permission}',")) {
        $failures[] = "Restricted permission is missing from boundary cleanup: {$permission}";
    }
}

foreach ($operationalPermissions as $permission) {
    if (!str_contains($systemAdminBlock, "'{$permission}'")) {
        $failures[] = "Operational permission was removed from system_admin: {$permission}";
    }
}

$boundaryChecks = [
    'boundary method call' => '$this->enforceSystemAdminPermissionBoundary();',
    'role lookup' => "\$this->idFor('roles', 'system_admin')",
    'persisted grant cleanup' => 'DELETE rp',
    'permission join' => 'INNER JOIN permissions p ON p.id = rp.permission_id',
];

foreach ($boundaryChecks as $label => $needle) {
    if (!str_contains($source, $needle)) {
        $failures[] = "Missing {$label}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "System admin permission boundary test passed.\n");

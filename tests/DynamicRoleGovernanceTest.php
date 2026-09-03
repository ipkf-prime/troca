<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn (string $relative): string =>
    (string) file_get_contents($root . '/public_html/' . $relative);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$service = $read('app/Services/DynamicRoleGovernanceService.php');
$migration = $read('system/Database/Migrations/SeedExistingDynamicRoleGovernance.php');
$routes = $read('routes/web.php');
$roleView = $read('resources/views/admin/access-role-create.php');
$scopeView = $read('resources/views/admin/access-scope-editor.php');

foreach ([
    'access_permission_ceiling_exceeded',
    'roles.priority < ?',
    'role_governance_updated',
    'replaceScopePolicies',
    'replaceIdentityRequirements',
] as $marker) {
    $expect(str_contains($service, $marker), 'service marker: ' . $marker);
}

foreach ([
    'class SeedExistingDynamicRoleGovernance extends Migration',
    'public function up(): void',
    'public function down(): void',
    '$this->db->beginTransaction()',
    "'super_admin' =>",
    "'system_admin' =>",
    "'ticketing_staff' =>",
    "'user' =>",
    "permissions.code = 'access.roles.manage'",
] as $marker) {
    $expect(str_contains($migration, $marker), 'migration marker: ' . $marker);
}

foreach ([
    '/admin/access-control/roles',
    '/admin/access-control/roles/update',
    'DynamicRoleGovernanceService',
    'role_notice_code',
    'scope_notice_code',
] as $marker) {
    $expect(str_contains($routes, $marker), 'route marker: ' . $marker);
}

$expect(!str_contains($roleView, 'عملیات انجام نشد:'), 'raw role error exposed');
$expect(!str_contains($scopeView, 'عملیات انجام نشد:'), 'raw scope error exposed');
$expect(str_contains($scopeView, 'data-reference-select'), 'dynamic reference selector missing');
$expect(str_contains($scopeView, 'data-constraint-dependent'), 'constraint visibility missing');

echo "DYNAMIC_ROLE_GOVERNANCE_CONTRACT=PASS\n";

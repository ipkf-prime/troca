<?php

declare(strict_types=1);

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use IPKF\Database\Connections\ConnectionResolver;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

$confirmation = $argv[1] ?? '';
if ($confirmation !== '--confirm=CREATE-WORK-QA-USERS') {
    fwrite(
        STDERR,
        "Usage:\n"
        . "php scripts/create-work-qa-users.php "
        . "--confirm=CREATE-WORK-QA-USERS\n"
    );
    exit(2);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

$accounts = [
    [
        'role_code' => 'qa_work_owner',
        'role_title' => 'QA Work - مالک پروژه',
        'name' => 'مالک آزمون مدیریت کار',
        'username' => 'qa-work-owner',
        'email' => 'qa-work-owner@qa.troca.ir',
        'permissions' => [
            'work.project.view',
            'work.project.manage',
            'work.item.view',
            'work.item.create',
            'work.item.update',
            'work.item.assign',
            'work.audit.view',
        ],
    ],
    [
        'role_code' => 'qa_work_manager',
        'role_title' => 'QA Work - مدیر پروژه',
        'name' => 'مدیر آزمون مدیریت کار',
        'username' => 'qa-work-manager',
        'email' => 'qa-work-manager@qa.troca.ir',
        'permissions' => [
            'work.project.view',
            'work.project.manage',
            'work.item.view',
            'work.item.create',
            'work.item.update',
            'work.item.assign',
            'work.audit.view',
        ],
    ],
    [
        'role_code' => 'qa_work_member',
        'role_title' => 'QA Work - عضو پروژه',
        'name' => 'عضو آزمون مدیریت کار',
        'username' => 'qa-work-member',
        'email' => 'qa-work-member@qa.troca.ir',
        'permissions' => [
            'work.project.view',
            'work.item.view',
            'work.item.create',
            'work.item.update',
        ],
    ],
    [
        'role_code' => 'qa_work_observer',
        'role_title' => 'QA Work - ناظر پروژه',
        'name' => 'ناظر آزمون مدیریت کار',
        'username' => 'qa-work-observer',
        'email' => 'qa-work-observer@qa.troca.ir',
        'permissions' => [
            'work.project.view',
            'work.item.view',
        ],
    ],
    [
        'role_code' => 'qa_work_outsider',
        'role_title' => 'QA Work - خارج از پروژه',
        'name' => 'کاربر آزمون خارج پروژه',
        'username' => 'qa-work-outsider',
        'email' => 'qa-work-outsider@qa.troca.ir',
        'permissions' => [
            'work.project.view',
            'work.item.view',
        ],
    ],
];

$commonPermissions = [
    'admin.dashboard.view',
    'account.profile.view',
    'account.security.view',
    'account.password.change',
    'account.theme.manage',
    'access.manage',
    'auth.login_token.issue',
];

$connections = new ConnectionResolver();
$core = $connections->resolve('core.primary');
$users = new UserRepository();
$roles = new RoleRepository();

$areaStatement = $core->prepare("
    SELECT id FROM role_areas WHERE code = 'global' LIMIT 1
");
$areaStatement->execute();
$globalAreaId = $areaStatement->fetchColumn();

$kindStatement = $core->prepare("
    SELECT id FROM role_kinds WHERE code = 'customer' LIMIT 1
");
$kindStatement->execute();
$customerKindId = $kindStatement->fetchColumn();

if ($globalAreaId === false || $customerKindId === false) {
    throw new RuntimeException(
        'Core RBAC lookup data is missing. Run the Core seed first.'
    );
}

$roleUpsert = $core->prepare("
    INSERT INTO roles (
        code,
        title,
        role_area_id,
        role_kind_id,
        priority,
        is_system,
        is_active,
        is_editable,
        is_deletable,
        can_manage_other_users,
        requires_center,
        created_at,
        updated_at
    )
    VALUES (?, ?, ?, ?, 50, 0, 1, 0, 0, 0, 0,
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        role_area_id = VALUES(role_area_id),
        role_kind_id = VALUES(role_kind_id),
        priority = VALUES(priority),
        is_active = 1,
        is_editable = 0,
        is_deletable = 0,
        can_manage_other_users = 0,
        requires_center = 0,
        updated_at = CURRENT_TIMESTAMP
");

$roleIdStatement = $core->prepare("
    SELECT id FROM roles WHERE code = ? LIMIT 1
");

$permissionIdsStatement = $core->prepare("
    SELECT id, code
    FROM permissions
    WHERE code IN (%s)
      AND is_active = 1
");

$deleteRolePermissions = $core->prepare("
    DELETE FROM role_permissions
    WHERE role_id = ?
");

$insertRolePermission = $core->prepare("
    INSERT IGNORE INTO role_permissions (
        role_id,
        permission_id,
        created_at
    )
    VALUES (?, ?, CURRENT_TIMESTAMP)
");

$baseRole = $roles->findByCode('user');
if ($baseRole === null) {
    throw new RuntimeException(
        'Base role "user" does not exist. Run the Core seed first.'
    );
}

$created = [];

foreach ($accounts as $account) {
    $permissionCodes = array_values(
        array_unique(
            array_merge(
                $commonPermissions,
                $account['permissions']
            )
        )
    );

    if (in_array('work.project.admin', $permissionCodes, true)) {
        throw new RuntimeException(
            'QA roles must never receive work.project.admin.'
        );
    }

    $roleUpsert->execute([
        $account['role_code'],
        $account['role_title'],
        (int) $globalAreaId,
        (int) $customerKindId,
    ]);

    $roleIdStatement->execute([$account['role_code']]);
    $roleId = $roleIdStatement->fetchColumn();

    if ($roleId === false) {
        throw new RuntimeException(
            'Role creation failed: ' . $account['role_code']
        );
    }

    $placeholders = implode(
        ',',
        array_fill(0, count($permissionCodes), '?')
    );

    $statement = $core->prepare(
        sprintf(
            "
            SELECT id, code
            FROM permissions
            WHERE code IN (%s)
              AND is_active = 1
            ",
            $placeholders
        )
    );
    $statement->execute($permissionCodes);

    $permissionMap = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $permission) {
        $permissionMap[
            (string) $permission['code']
        ] = (int) $permission['id'];
    }

    $missingPermissions = array_values(
        array_diff(
            $permissionCodes,
            array_keys($permissionMap)
        )
    );

    if ($missingPermissions !== []) {
        throw new RuntimeException(
            'Missing permissions for '
            . $account['role_code']
            . ': '
            . implode(', ', $missingPermissions)
        );
    }

    $deleteRolePermissions->execute([(int) $roleId]);

    foreach ($permissionCodes as $permissionCode) {
        $insertRolePermission->execute([
            (int) $roleId,
            $permissionMap[$permissionCode],
        ]);
    }

    $password = securePassword();

    $user = $users->createOrUpdateAdminFromEnv([
        'name' => $account['name'],
        'username' => $account['username'],
        'email' => $account['email'],
        'mobile' => '',
        'password' => $password,
    ]);

    if ($user === null) {
        throw new RuntimeException(
            'User creation failed: ' . $account['email']
        );
    }

    $userId = (int) $user['id'];

    $roles->assignRoleToUser(
        $userId,
        (int) $baseRole['id']
    );
    $roles->assignRoleToUser(
        $userId,
        (int) $roleId
    );

    $created[] = [
        'name' => $account['name'],
        'email' => $account['email'],
        'username' => $account['username'],
        'password' => $password,
        'role' => $account['role_title'],
        'user_id' => $userId,
    ];
}

$credentialDirectory = BASE_PATH . '/storage/private/qa';
if (
    !is_dir($credentialDirectory)
    && !mkdir($credentialDirectory, 0700, true)
    && !is_dir($credentialDirectory)
) {
    throw new RuntimeException(
        'Could not create credential directory.'
    );
}

$credentialPath = $credentialDirectory
    . '/work-v0.5.1-users.txt';

$lines = [
    'IPKF Work v0.5.1 QA users',
    'Generated UTC: ' . gmdate('Y-m-d H:i:s'),
    '',
    'After login, switch the active role to the matching QA Work role.',
    '',
];

foreach ($created as $account) {
    $lines[] = 'Name: ' . $account['name'];
    $lines[] = 'Email: ' . $account['email'];
    $lines[] = 'Username: ' . $account['username'];
    $lines[] = 'Password: ' . $account['password'];
    $lines[] = 'Role: ' . $account['role'];
    $lines[] = 'User ID (technical only): ' . $account['user_id'];
    $lines[] = str_repeat('-', 50);
}

file_put_contents(
    $credentialPath,
    implode(PHP_EOL, $lines) . PHP_EOL,
    LOCK_EX
);
chmod($credentialDirectory, 0700);
chmod($credentialPath, 0600);

echo "WORK QA USERS CREATED\n";
echo "count=" . count($created) . PHP_EOL;
echo "credentials=" . $credentialPath . PHP_EOL;
echo "work_project_admin_assigned=no\n";
echo "next=cat " . $credentialPath . PHP_EOL;
echo "security=delete the credentials file after copying it\n";

function securePassword(): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $symbols = '!@#$%*-_+=';
    $all = $upper . $lower . $digits . $symbols;

    $characters = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $symbols[random_int(0, strlen($symbols) - 1)],
    ];

    while (count($characters) < 18) {
        $characters[] = $all[
            random_int(0, strlen($all) - 1)
        ];
    }

    for ($index = count($characters) - 1; $index > 0; $index--) {
        $swap = random_int(0, $index);
        [$characters[$index], $characters[$swap]] = [
            $characters[$swap],
            $characters[$index],
        ];
    }

    return implode('', $characters);
}

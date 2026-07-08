<?php

namespace IPKF\Database\Seeds;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use IPKF\Support\Env;

class AuthRbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoleAreas();
        $this->seedRoleKinds();
        $this->seedRoles();
        $this->seedPermissions();
        $this->assignSuperAdminPermissions();
        $this->seedAdminUser();
    }

    private function seedRoleAreas(): void
    {
        $areas = [
            ['global', 'کشوری', 10],
            ['national', 'ملی یا اتحادیه مرکزی', 20],
            ['province', 'استانی', 30],
            ['county', 'شهرستانی', 40],
            ['district', 'بخش', 50],
            ['village', 'دهستان', 60],
            ['company', 'شرکت', 70],
            ['warehouse', 'انبار', 80],
            ['center', 'مرکز', 90],
        ];

        $statement = $this->db->prepare("
            INSERT INTO role_areas (code, title, sort_order, is_active, created_at, updated_at)
            VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                sort_order = VALUES(sort_order),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($areas as $area) {
            $statement->execute($area);
        }
    }

    private function seedRoleKinds(): void
    {
        $kinds = [
            ['system_admin', 'مدیر سامانه'],
            ['central_admin', 'ادمین مرکزی'],
            ['province_admin', 'ادمین استان'],
            ['county_admin', 'ادمین شهرستان'],
            ['manager', 'مدیر'],
            ['expert', 'کارشناس'],
            ['auditor', 'حسابرسی'],
            ['inspector', 'بازرس'],
            ['support', 'پشتیبان'],
            ['operator', 'اپراتور'],
            ['customer', 'مشتری'],
            ['supplier', 'تأمین‌کننده'],
        ];

        $statement = $this->db->prepare("
            INSERT INTO role_kinds (code, title, is_active, created_at, updated_at)
            VALUES (?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($kinds as $kind) {
            $statement->execute($kind);
        }
    }

    private function seedRoles(): void
    {
        $roles = [
            ['super_admin', 'مدیر کل سامانه', 'global', 'system_admin', true, false, false, true, false],
            ['system_admin', 'مدیر سامانه', 'global', 'system_admin', true, false, false, true, true],
            ['central_admin', 'ادمین مرکزی', 'national', 'central_admin', false, true, true, true, true],
            ['province_admin', 'ادمین استان', 'province', 'province_admin', false, true, true, true, true],
            ['county_admin', 'ادمین شهرستان', 'county', 'county_admin', false, true, true, false, true],
            ['company_admin', 'ادمین شرکت', 'company', 'manager', false, true, true, false, true],
            ['warehouse_manager', 'مدیر انبار', 'warehouse', 'manager', false, true, true, false, false],
            ['operator', 'اپراتور', 'center', 'operator', false, true, true, false, false],
            ['user', 'کاربر', 'global', 'customer', false, true, true, false, false],
        ];

        $statement = $this->db->prepare("
            INSERT INTO roles (
                code, title, role_area_id, role_kind_id, is_system, is_active,
                is_editable, is_deletable, can_manage_other_users, requires_center,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                role_area_id = VALUES(role_area_id),
                role_kind_id = VALUES(role_kind_id),
                is_system = VALUES(is_system),
                is_active = VALUES(is_active),
                is_editable = VALUES(is_editable),
                is_deletable = VALUES(is_deletable),
                can_manage_other_users = VALUES(can_manage_other_users),
                requires_center = VALUES(requires_center),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($roles as $role) {
            [$code, $title, $areaCode, $kindCode, $system, $editable, $deletable, $manageUsers, $requiresCenter] = $role;

            $statement->execute([
                $code,
                $title,
                $this->idFor('role_areas', $areaCode),
                $this->idFor('role_kinds', $kindCode),
                $system ? 1 : 0,
                $editable ? 1 : 0,
                $deletable ? 1 : 0,
                $manageUsers ? 1 : 0,
                $requiresCenter ? 1 : 0,
            ]);
        }
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['users.view', 'users', 'users', 'view', 'View users'],
            ['users.create', 'users', 'users', 'create', 'Create users'],
            ['users.update', 'users', 'users', 'update', 'Update users'],
            ['users.delete', 'users', 'users', 'delete', 'Delete users'],
            ['roles.view', 'auth', 'roles', 'view', 'View roles'],
            ['roles.create', 'auth', 'roles', 'create', 'Create roles'],
            ['roles.update', 'auth', 'roles', 'update', 'Update roles'],
            ['roles.delete', 'auth', 'roles', 'delete', 'Delete roles'],
            ['permissions.view', 'auth', 'permissions', 'view', 'View permissions'],
            ['permissions.assign', 'auth', 'permissions', 'assign', 'Assign permissions'],
            ['organizations.view', 'organizations', 'organizations', 'view', 'View organizations'],
            ['organizations.update', 'organizations', 'organizations', 'update', 'Update organizations'],
            ['system.diagnostics.view', 'system', 'diagnostics', 'view', 'View diagnostics'],
            ['system.installer.view', 'system', 'installer', 'view', 'View installer'],
        ];

        $statement = $this->db->prepare("
            INSERT INTO permissions (code, module, resource, action, title, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                resource = VALUES(resource),
                action = VALUES(action),
                title = VALUES(title),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($permissions as $permission) {
            $statement->execute($permission);
        }
    }

    private function assignSuperAdminPermissions(): void
    {
        $roleId = $this->idFor('roles', 'super_admin');

        if ($roleId === null) {
            return;
        }

        $permissions = $this->db->query("SELECT id FROM permissions")->fetchAll();
        $statement = $this->db->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");

        foreach ($permissions as $permission) {
            $statement->execute([$roleId, (int) $permission['id']]);
        }
    }

    private function idFor(string $table, string $code): ?int
    {
        $statement = $this->db->prepare("SELECT id FROM {$table} WHERE code = ? LIMIT 1");
        $statement->execute([$code]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function seedAdminUser(): void
    {
        $email = trim((string) Env::get('ADMIN_EMAIL', ''));
        $password = (string) Env::get('ADMIN_PASSWORD', '');

        if ($email === '' || $password === '' || $password === 'change-me-securely') {
            return;
        }

        $users = new UserRepository();
        $roles = new RoleRepository();
        $admin = $users->createOrUpdateAdminFromEnv([
            'name' => Env::get('ADMIN_NAME', 'Super Admin'),
            'username' => Env::get('ADMIN_USERNAME', 'admin'),
            'email' => $email,
            'mobile' => Env::get('ADMIN_MOBILE', ''),
            'password' => $password,
        ]);

        $role = $roles->findByCode('super_admin');

        if ($admin !== null && $role !== null) {
            $roles->assignRoleToUser((int) $admin['id'], (int) $role['id'], 'global', null, true);
        }
    }
}

<?php

namespace IPKF\Database\Seeds;

class AutomationCorrespondencePermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        ['automation.correspondence.view', 'view', 'مشاهده مکاتبات'],
        ['automation.correspondence.create', 'create', 'ایجاد پیش نویس مکاتبه'],
        ['automation.correspondence.edit_draft', 'edit_draft', 'ویرایش پیش نویس مکاتبه'],
        ['automation.correspondence.register', 'register', 'ثبت رسمی مکاتبه'],
        ['automation.correspondence.route', 'route', 'ارجاع مکاتبه'],
        ['automation.correspondence.cartable.view', 'cartable_view', 'مشاهده کارتابل مکاتبات'],
        ['automation.correspondence.close', 'close', 'بستن مکاتبه'],
        ['automation.registry.manage', 'manage_registry', 'مدیریت دفترهای ثبت'],
        ['automation.audit.view', 'view_audit', 'مشاهده تاریخچه مکاتبات'],
    ];

    public function run(): void
    {
        if (!$this->tableExists('roles')
            || !$this->tableExists('permissions')
            || !$this->tableExists('role_permissions')
        ) {
            return;
        }

        $this->seedPermissions();
        $this->assignSuperAdminPermissions();
    }

    private function seedPermissions(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO permissions (
                code, module, resource, action, title, is_active, created_at, updated_at
            ) VALUES (?, 'automation', ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE code = code
        ");

        foreach (self::PERMISSIONS as [$code, $action, $title]) {
            $resource = str_contains($code, '.registry.')
                ? 'registry'
                : (str_contains($code, '.audit.') ? 'audit' : 'correspondence');
            $statement->execute([$code, $resource, $action, $title]);
        }
    }

    private function assignSuperAdminPermissions(): void
    {
        $roleStatement = $this->db->prepare("SELECT id FROM roles WHERE code = 'super_admin' LIMIT 1");
        $roleStatement->execute();
        $roleId = $roleStatement->fetchColumn();

        if ($roleId === false) {
            return;
        }

        $permissionStatement = $this->db->prepare('SELECT id FROM permissions WHERE code = ? LIMIT 1');
        $assignmentStatement = $this->db->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");

        foreach (self::PERMISSIONS as [$code]) {
            $permissionStatement->execute([$code]);
            $permissionId = $permissionStatement->fetchColumn();

            if ($permissionId !== false) {
                $assignmentStatement->execute([(int) $roleId, (int) $permissionId]);
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }
}

<?php

namespace IPKF\Database\Seeds;

class OrganizationalIdentityPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        ['organizations.manage', 'organizations', 'manage', 'مدیریت سازمان‌ها'],
        ['org_units.manage', 'org_units', 'manage', 'مدیریت واحدهای سازمانی'],
        ['positions.manage', 'positions', 'manage', 'مدیریت پست‌های سازمانی'],
        ['appointments.manage', 'appointments', 'manage', 'مدیریت انتصاب‌ها'],
        ['appointments.assign', 'appointments', 'assign', 'انتصاب اشخاص به پست'],
        ['organizational_context.switch', 'organizational_context', 'switch', 'تغییر جایگاه فعال'],
        ['signatures.view', 'signatures', 'view', 'مشاهده امضاها'],
        ['signatures.manage', 'signatures', 'manage', 'مدیریت امضاها'],
        ['signature_authorizations.manage', 'signature_authorizations', 'manage', 'مدیریت مجوزهای امضا'],
    ];

    public function run(): void
    {
        if (!$this->tableExists('permissions') || !$this->tableExists('roles') || !$this->tableExists('role_permissions')) {
            return;
        }

        $insert = $this->db->prepare("INSERT INTO permissions (code,module,resource,action,title,is_active,created_at,updated_at) VALUES (?, 'core', ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE title = VALUES(title), is_active = 1, updated_at = CURRENT_TIMESTAMP");
        foreach (self::PERMISSIONS as [$code,$resource,$action,$title]) {
            $insert->execute([$code,$resource,$action,$title]);
        }

        $role = $this->db->query("SELECT id FROM roles WHERE code = 'super_admin' AND is_active = 1 LIMIT 1")->fetchColumn();
        if ($role === false) {
            return;
        }

        $find = $this->db->prepare('SELECT id FROM permissions WHERE code = ? LIMIT 1');
        $assign = $this->db->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
        foreach (self::PERMISSIONS as [$code]) {
            $find->execute([$code]);
            $permissionId = $find->fetchColumn();
            if ($permissionId !== false) {
                $assign->execute([(int)$role, (int)$permissionId]);
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $statement->execute([$table]);
        return (int)$statement->fetchColumn() > 0;
    }
}

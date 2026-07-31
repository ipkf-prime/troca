<?php

namespace IPKF\Database\Seeds;

class WorkManagementPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        ['work.project.view', 'project', 'view', 'مشاهده پروژه‌ها و Workها'],
        ['work.project.manage', 'project', 'manage', 'مدیریت پروژه‌ها و Workها'],
        ['work.project.admin', 'project', 'admin', 'دسترسی مدیریتی سراسری به همه پروژه‌های Work'],
        ['work.item.view', 'item', 'view', 'مشاهده تسک‌ها'],
        ['work.item.create', 'item', 'create', 'ایجاد تسک'],
        ['work.item.update', 'item', 'update', 'ویرایش و تغییر وضعیت تسک'],
        ['work.item.assign', 'item', 'assign', 'تخصیص مسئول تسک'],
        ['work.audit.view', 'audit', 'view', 'مشاهده تاریخچه تغییرات Work'],
        ['work.settings.view', 'settings', 'view', 'مشاهده تنظیمات مدیریت کار'],
        ['work.settings.manage', 'settings', 'manage', 'مدیریت تعاریف و تنظیمات Work'],
    ];

    public function run(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO permissions (code, module, resource, action, title, is_active, created_at, updated_at)
            VALUES (?, 'work', ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE title = VALUES(title), is_active = 1, updated_at = CURRENT_TIMESTAMP
        ");
        foreach (self::PERMISSIONS as [$code, $resource, $action, $title]) {
            $statement->execute([$code, $resource, $action, $title]);
        }

        $roleId = $this->db->query("SELECT id FROM roles WHERE code = 'super_admin' LIMIT 1")->fetchColumn();
        if ($roleId === false) {
            return;
        }

        $assign = $this->db->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
            SELECT ?, id, CURRENT_TIMESTAMP FROM permissions WHERE code = ?
        ");
        foreach (self::PERMISSIONS as [$code]) {
            $assign->execute([(int) $roleId, $code]);
        }
    }
}

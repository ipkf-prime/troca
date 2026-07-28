<?php

namespace IPKF\Database\Seeds;

class WorkManagementFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->db->exec("
            INSERT INTO work_projects
                (public_reference, code, title, description, status, created_at, updated_at)
            VALUES
                (UUID(), 'ipkf-management', 'IPKF Management', 'پروژه اصلی مدیریت توسعه و عملیات IPKF', 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), updated_at = UTC_TIMESTAMP()
        ");

        $projectId = (int) $this->db
            ->query("SELECT id FROM work_projects WHERE code = 'ipkf-management' LIMIT 1")
            ->fetchColumn();

        $statement = $this->db->prepare("
            INSERT INTO work_items
                (public_reference, project_id, title, description, status, progress_percent, created_at, updated_at)
            SELECT UUID(), ?, 'IPKF Work Management', 'Work اصلی مدیریت پروژه‌ها، ماژول‌ها و تسک‌ها', 'active', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP()
            WHERE NOT EXISTS (
                SELECT 1 FROM work_items WHERE project_id = ? AND title = 'IPKF Work Management'
            )
        ");
        $statement->execute([$projectId, $projectId]);
    }
}

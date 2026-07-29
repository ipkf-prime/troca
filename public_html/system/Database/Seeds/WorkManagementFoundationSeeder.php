<?php

namespace IPKF\Database\Seeds;

class WorkManagementFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStatuses();
        $projectId = $this->seedProject();
        $this->seedRootWork($projectId);
    }

    private function seedStatuses(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO work_statuses
                (code, title, category, color, sort_order, is_closed, is_system, is_active, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                category = VALUES(category),
                color = VALUES(color),
                sort_order = VALUES(sort_order),
                is_closed = VALUES(is_closed),
                is_system = 1,
                is_active = 1,
                updated_at = UTC_TIMESTAMP()
        ");

        foreach ($this->statuses() as $status) {
            $statement->execute($status);
        }
    }

    private function seedProject(): int
    {
        $statement = $this->db->prepare("
            INSERT INTO work_projects
                (public_reference, code, title, description, status_code, visibility_code, created_by_user_reference, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, 'active', 'private', 'system:seed', UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                status_code = VALUES(status_code),
                visibility_code = VALUES(visibility_code),
                updated_at = UTC_TIMESTAMP()
        ");
        $statement->execute([
            'WRK-PRJ-IPKF-MGMT',
            'ipkf-management',
            'IPKF Management',
            $this->fa('&#x067E;&#x0631;&#x0648;&#x0698;&#x0647; &#x0627;&#x0635;&#x0644;&#x06CC; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x062A;&#x0648;&#x0633;&#x0639;&#x0647; &#x0648; &#x0639;&#x0645;&#x0644;&#x06CC;&#x0627;&#x062A; IPKF'),
        ]);

        return (int) $this->db
            ->query("SELECT id FROM work_projects WHERE code = 'ipkf-management' LIMIT 1")
            ->fetchColumn();
    }

    private function seedRootWork(int $projectId): void
    {
        $statusId = (int) $this->db
            ->query("SELECT id FROM work_statuses WHERE code = 'in_progress' LIMIT 1")
            ->fetchColumn();

        $statement = $this->db->prepare("
            INSERT INTO work_items
                (public_reference, project_id, parent_id, status_id, item_type, sequence_number, title, description, priority_code, progress_percent, sort_order, created_by_user_reference, created_at, updated_at)
            VALUES
                (?, ?, NULL, ?, 'work', 1, ?, ?, 'normal', 0, 10, 'system:seed', UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                status_id = VALUES(status_id),
                updated_at = UTC_TIMESTAMP()
        ");
        $statement->execute([
            'WRK-ITEM-IPKF-ROOT',
            $projectId,
            $statusId,
            'IPKF Work Management',
            $this->fa('Work &#x0627;&#x0635;&#x0644;&#x06CC; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x067E;&#x0631;&#x0648;&#x0698;&#x0647;&#x200C;&#x0647;&#x0627;&#x060C; &#x0645;&#x0627;&#x0698;&#x0648;&#x0644;&#x200C;&#x0647;&#x0627; &#x0648; &#x062A;&#x0633;&#x06A9;&#x200C;&#x0647;&#x0627;'),
        ]);
    }

    private function statuses(): array
    {
        return [
            ['backlog', $this->fa('&#x0635;&#x0641; &#x0627;&#x0646;&#x062A;&#x0638;&#x0627;&#x0631;'), 'open', '#64748b', 10, 0],
            ['planned', $this->fa('&#x0628;&#x0631;&#x0646;&#x0627;&#x0645;&#x0647;&#x200C;&#x0631;&#x06CC;&#x0632;&#x06CC;'), 'open', '#2563eb', 20, 0],
            ['in_progress', $this->fa('&#x062F;&#x0631; &#x062D;&#x0627;&#x0644; &#x0627;&#x0646;&#x062C;&#x0627;&#x0645;'), 'open', '#16a34a', 30, 0],
            ['blocked', $this->fa('&#x0645;&#x062A;&#x0648;&#x0642;&#x0641;'), 'blocked', '#dc2626', 40, 0],
            ['review', $this->fa('&#x0628;&#x0627;&#x0632;&#x0628;&#x06CC;&#x0646;&#x06CC;'), 'open', '#7c3aed', 50, 0],
            ['done', $this->fa('&#x0627;&#x0646;&#x062C;&#x0627;&#x0645;&#x200C;&#x0634;&#x062F;&#x0647;'), 'closed', '#0f766e', 60, 1],
            ['cancelled', $this->fa('&#x0644;&#x063A;&#x0648;&#x0634;&#x062F;&#x0647;'), 'closed', '#6b7280', 70, 1],
        ];
    }

    private function fa(string $entities): string
    {
        return html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

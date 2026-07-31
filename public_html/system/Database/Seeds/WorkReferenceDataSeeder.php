<?php

namespace IPKF\Database\Seeds;

class WorkReferenceDataSeeder extends Seeder
{
    private const GROUPS = [
        ['project_status', 'وضعیت‌های پروژه', 'وضعیت‌های قابل انتخاب برای پروژه‌ها', 'dynamic', 10],
        ['project_visibility', 'سطوح دسترسی پروژه', 'سطوح ساختاری مشاهده پروژه', 'fixed', 20],
        ['item_priority', 'اولویت‌های کار', 'اولویت‌های قابل انتخاب برای کارها و تسک‌ها', 'dynamic', 30],
        ['item_type', 'انواع آیتم', 'انواع ساختاری کار، نقطه عطف، تسک و زیرتسک', 'structural', 40],
    ];

    private const ITEMS = [
        'project_status' => [
            ['active', 'فعال', 'Active', '#16a34a', 10, 1],
            ['paused', 'متوقف', 'Paused', '#f59e0b', 20, 1],
            ['completed', 'تکمیل‌شده', 'Completed', '#0f766e', 30, 1],
            ['cancelled', 'لغوشده', 'Cancelled', '#6b7280', 40, 1],
        ],
        'project_visibility' => [
            ['private', 'خصوصی', 'Private', '#64748b', 10, 1],
            ['members', 'اعضای پروژه', 'Project members', '#2563eb', 20, 1],
            ['organization', 'سازمانی', 'Organization', '#7c3aed', 30, 1],
            ['public', 'عمومی', 'Public', '#16a34a', 40, 1],
        ],
        'item_priority' => [
            ['low', 'کم', 'Low', '#64748b', 10, 1],
            ['normal', 'عادی', 'Normal', '#2563eb', 20, 1],
            ['high', 'زیاد', 'High', '#f59e0b', 30, 1],
            ['urgent', 'فوری', 'Urgent', '#dc2626', 40, 1],
        ],
        'item_type' => [
            ['work', 'کار', 'Work', '#0f766e', 10, 1],
            ['milestone', 'نقطه عطف', 'Milestone', '#7c3aed', 20, 1],
            ['task', 'تسک', 'Task', '#2563eb', 30, 1],
            ['subtask', 'زیرتسک', 'Subtask', '#64748b', 40, 1],
        ],
    ];

    public function run(): void
    {
        $groupStatement = $this->db->prepare("
            INSERT IGNORE INTO module_reference_groups
                (module_code, code, title, description, management_mode,
                 sort_order, is_active, created_at, updated_at)
            VALUES ('work', ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");

        foreach (self::GROUPS as $group) {
            $groupStatement->execute($group);
        }

        $findGroup = $this->db->prepare("
            SELECT id, management_mode
            FROM module_reference_groups
            WHERE module_code = 'work' AND code = ?
            LIMIT 1
        ");

        $itemStatement = $this->db->prepare("
            INSERT IGNORE INTO module_reference_items
                (group_id, code, title_fa, title_en, color, sort_order,
                 is_active, is_system, is_locked, metadata_json,
                 created_by_user_reference, updated_by_user_reference,
                 created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, NULL,
                    'system:seed', 'system:seed', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");

        foreach (self::ITEMS as $groupCode => $items) {
            $findGroup->execute([$groupCode]);
            $group = $findGroup->fetch(\PDO::FETCH_ASSOC);
            if (!$group) {
                continue;
            }

            $locked = (string) $group['management_mode'] === 'structural' ? 1 : 0;

            foreach ($items as [$code, $titleFa, $titleEn, $color, $sortOrder]) {
                $itemStatement->execute([
                    (int) $group['id'],
                    $code,
                    $titleFa,
                    $titleEn,
                    $color,
                    $sortOrder,
                    $locked,
                ]);
            }
        }
    }
}

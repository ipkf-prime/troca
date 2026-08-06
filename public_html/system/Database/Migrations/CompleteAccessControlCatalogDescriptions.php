<?php

namespace IPKF\Database\Migrations;

class CompleteAccessControlCatalogDescriptions extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        $descriptions = [
            'automation.audit.view' =>
                'مشاهده سوابق ثبت، ارجاع و تغییرات مکاتبات اداری.',
            'notifications.routing.manage' =>
                'مدیریت قواعد انتخاب کانال، سرویس‌دهنده و مسیر ارسال اعلان‌ها.',
            'organizations.manage' =>
                'ایجاد، ویرایش و مدیریت سازمان‌های سامانه.',
            'positions.manage' =>
                'ایجاد، ویرایش و مدیریت پست‌ها و سمت‌های سازمانی.',
            'org_units.manage' =>
                'ایجاد، ویرایش و مدیریت ساختار و واحدهای سازمانی.',
            'work.item.view' =>
                'مشاهده فهرست و جزئیات تسک‌های مدیریت کار.',
            'work.item.create' =>
                'ایجاد تسک جدید در پروژه‌های مدیریت کار.',
            'work.item.update' =>
                'ویرایش مشخصات و تغییر وضعیت تسک‌ها.',
            'work.item.assign' =>
                'تعیین یا تغییر مسئول اجرای تسک‌ها.',
            'work.settings.view' =>
                'مشاهده تعاریف و تنظیمات ماژول مدیریت کار.',
        ];

        $statement = $this->db->prepare("
            UPDATE permissions
            SET description = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = ?
              AND (
                    description IS NULL
                    OR description = ''
              )
        ");

        foreach ($descriptions as $code => $description) {
            $statement->execute([
                $description,
                $code,
            ]);
        }
    }

    public function down(): void
    {
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

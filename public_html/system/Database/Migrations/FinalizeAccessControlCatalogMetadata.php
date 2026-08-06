<?php

namespace IPKF\Database\Migrations;

class FinalizeAccessControlCatalogMetadata extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        $items = [
            'automation.correspondence.cartable.view' => [
                'مکاتبات اداری',
                'مشاهده کارتابل و مکاتبات ارجاع‌شده به کاربر.',
            ],
            'automation.correspondence.close' => [
                'مکاتبات اداری',
                'بستن گردش مکاتبه پس از پایان فرایند.',
            ],
            'automation.correspondence.create' => [
                'مکاتبات اداری',
                'ایجاد پیش‌نویس مکاتبه جدید.',
            ],
            'automation.correspondence.edit_draft' => [
                'مکاتبات اداری',
                'ویرایش پیش‌نویس مکاتبه پیش از ثبت رسمی.',
            ],
            'automation.correspondence.register' => [
                'مکاتبات اداری',
                'ثبت رسمی مکاتبه در دفتر مربوط.',
            ],
            'automation.correspondence.route' => [
                'مکاتبات اداری',
                'ارجاع مکاتبه به کاربر یا واحد سازمانی.',
            ],
            'automation.correspondence.view' => [
                'مکاتبات اداری',
                'مشاهده فهرست و جزئیات مکاتبات اداری.',
            ],
            'automation.registry.manage' => [
                'دفاتر ثبت مکاتبات',
                'مدیریت دفترهای ثبت، شماره‌گذاری و قواعد دبیرخانه.',
            ],
            'messages.admin.manage' => [
                'پیام‌رسان داخلی',
                'مدیریت کامل پیام‌های داخلی در سطح سامانه.',
            ],
            'messages.admin.view' => [
                'پیام‌رسان داخلی',
                'مشاهده مدیریتی پیام‌های داخلی کاربران.',
            ],
            'messages.reply' => [
                'پیام‌رسان داخلی',
                'پاسخ به پیام‌های دریافت‌شده در کارتابل داخلی.',
            ],
            'messages.send' => [
                'پیام‌رسان داخلی',
                'ارسال پیام داخلی به کاربران سامانه.',
            ],
            'messages.view' => [
                'پیام‌رسان داخلی',
                'مشاهده کارتابل پیام‌های داخلی.',
            ],
            'notifications.send.manage' => [
                'ارسال اعلان',
                'ارسال مستقیم اعلان از کانال‌های فعال سامانه.',
            ],
            'notifications.preferences.self' => [
                'ترجیحات اعلان',
                'مدیریت کانال‌ها و ترجیحات دریافت اعلان شخصی.',
            ],
            'notifications.providers.manage' => [
                'سرویس‌دهندگان اعلان',
                'مدیریت سرویس‌دهندگان ایمیل، پیام کوتاه و پیام‌رسان.',
            ],
            'notifications.reports.view' => [
                'گزارش‌های اعلان',
                'مشاهده گزارش ارسال و تحویل اعلان‌ها.',
            ],
            'appointments.assign' => [
                'انتصاب‌ها',
                'انتصاب اشخاص به پست‌ها و جایگاه‌های سازمانی.',
            ],
            'appointments.manage' => [
                'انتصاب‌ها',
                'مدیریت انتصاب‌ها و سوابق جایگاه‌های سازمانی.',
            ],
            'organizational_context.switch' => [
                'جایگاه سازمانی فعال',
                'تغییر جایگاه سازمانی فعال کاربر در نشست جاری.',
            ],
            'signature_authorizations.manage' => [
                'مجوزهای امضا',
                'مدیریت حدود اختیار و مجوزهای امضای سازمانی.',
            ],
            'signatures.manage' => [
                'امضاها',
                'ثبت، ویرایش و مدیریت امضاهای سازمانی.',
            ],
            'signatures.view' => [
                'امضاها',
                'مشاهده امضاهای ثبت‌شده و وضعیت آن‌ها.',
            ],
            'notifications.deliveries.view' => [
                'گزارش تحویل اعلان',
                'مشاهده وضعیت و جزئیات تحویل اعلان‌ها.',
            ],
            'notifications.manage' => [
                'اعلان‌ها',
                'مدیریت زیرساخت و تنظیمات عمومی اعلان‌ها.',
            ],
            'notifications.view' => [
                'اعلان‌ها',
                'مشاهده اعلان‌های شخصی کاربر.',
            ],
            'notifications.templates.manage' => [
                'قالب‌های اعلان',
                'مدیریت قالب‌های محتوایی اعلان‌ها.',
            ],
        ];

        $statement = $this->db->prepare("
            UPDATE permissions
            SET display_group = ?,
                description = CASE
                    WHEN description IS NULL OR description = ''
                        THEN ?
                    ELSE description
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = ?
        ");

        foreach ($items as $code => $definition) {
            $statement->execute([
                $definition[0],
                $definition[1],
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

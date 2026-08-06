<?php

namespace IPKF\Database\Migrations;

class CompleteAccessControlPersianLocalization extends Migration
{
    public function up(): void
    {
        $this->localizePermissionCatalog();
        $this->completeDisplayGroups();
    }

    public function down(): void
    {
    }

    private function localizePermissionCatalog(): void
    {
        $items = [
            'account.profile.view' => [
                'مشاهده پروفایل حساب کاربری',
                'دسترسی به صفحه پروفایل و اطلاعات شخصی حساب.',
                'پروفایل',
            ],
            'account.security.view' => [
                'مشاهده امنیت حساب کاربری',
                'مشاهده تنظیمات امنیتی و نشست‌های حساب.',
                'امنیت حساب',
            ],
            'account.password.change' => [
                'تغییر گذرواژه حساب کاربری',
                'تغییر گذرواژه حساب کاربری جاری.',
                'امنیت حساب',
            ],
            'account.theme.manage' => [
                'مدیریت ظاهر شخصی پنل',
                'انتخاب و تنظیم پوسته و ظاهر شخصی پنل مدیریت.',
                'ظاهر پنل',
            ],
            'admin.theme.manage' => [
                'مدیریت ظاهر عمومی پنل',
                'مدیریت پوسته و تنظیمات ظاهری عمومی پنل مدیریت.',
                'ظاهر پنل',
            ],
            'admin.dashboard.view' => [
                'مشاهده داشبورد مدیریت',
                'دسترسی به داشبورد اصلی پنل مدیریت.',
                'داشبورد',
            ],
            'access.manage' => [
                'مدیریت نقش فعال و دسترسی‌ها',
                'مدیریت نقش فعال و دسترسی‌های مدیریتی حساب.',
                'دسترسی',
            ],
            'admin.settings.manage' => [
                'مدیریت تنظیمات سامانه',
                'تغییر تنظیمات مدیریتی سامانه.',
                'تنظیمات',
            ],
            'admin.pages.manage' => [
                'مدیریت صفحات پنل',
                'مدیریت صفحات و بخش‌های پنل مدیریت.',
                'صفحات پنل',
            ],
            'admin.reports.view' => [
                'مشاهده گزارش‌های مدیریتی',
                'دسترسی به گزارش‌های مدیریتی سامانه.',
                'گزارش‌ها',
            ],
            'admin.navigation.debug' => [
                'عیب‌یابی منوهای مدیریتی',
                'مشاهده اطلاعات فنی منوهای پویا برای عیب‌یابی.',
                'عیب‌یابی',
            ],
            'admin.route.debug' => [
                'عیب‌یابی مسیرهای مدیریتی',
                'مشاهده اطلاعات فنی مسیرها و قواعد دسترسی برای عیب‌یابی.',
                'عیب‌یابی',
            ],
            'roles.view' => [
                'مشاهده نقش‌ها',
                'مشاهده فهرست نقش‌های سامانه.',
                'نقش‌ها',
            ],
            'roles.create' => [
                'ایجاد نقش',
                'ایجاد نقش جدید در سامانه.',
                'نقش‌ها',
            ],
            'roles.update' => [
                'ویرایش نقش‌ها',
                'ویرایش مشخصات نقش‌های سامانه.',
                'نقش‌ها',
            ],
            'roles.delete' => [
                'حذف نقش‌ها',
                'حذف نقش‌های مجاز سامانه.',
                'نقش‌ها',
            ],
            'permissions.view' => [
                'مشاهده مجوزها',
                'مشاهده کاتالوگ مجوزهای سامانه.',
                'مجوزها',
            ],
            'permissions.assign' => [
                'تخصیص مجوزها',
                'تخصیص یا لغو مجوزهای نقش‌ها.',
                'مجوزها',
            ],
            'auth.login_token.issue' => [
                'صدور توکن ورود',
                'صدور توکن ورود یک‌بارمصرف یا موقت.',
                'ورود و نشست',
            ],
            'org_units.view' => [
                'مشاهده واحدهای سازمانی',
                'مشاهده ساختار و واحدهای سازمانی.',
                'واحدهای سازمانی',
            ],
            'positions.view' => [
                'مشاهده سمت‌های سازمانی',
                'مشاهده سمت‌ها و جایگاه‌های سازمانی.',
                'سمت‌ها',
            ],
            'user_org_assignments.manage' => [
                'مدیریت انتساب کاربران به سازمان',
                'مدیریت عضویت و انتساب کاربران به واحدهای سازمانی.',
                'انتساب کاربران',
            ],
            'organizations.view' => [
                'مشاهده سازمان‌ها',
                'مشاهده فهرست و اطلاعات سازمان‌ها.',
                'سازمان‌ها',
            ],
            'organizations.update' => [
                'ویرایش سازمان‌ها',
                'ویرایش مشخصات سازمان‌ها.',
                'سازمان‌ها',
            ],
            'support.view' => [
                'مشاهده بخش پشتیبانی',
                'دسترسی به امکانات و اطلاعات بخش پشتیبانی.',
                'پشتیبانی',
            ],
            'system.diagnostics.view' => [
                'مشاهده وضعیت تشخیصی سامانه',
                'مشاهده گزارش‌های سلامت و اطلاعات تشخیصی سامانه.',
                'پایش و عیب‌یابی',
            ],
            'system.installer.view' => [
                'مشاهده نصب‌کننده سامانه',
                'دسترسی به وضعیت و ابزار نصب سامانه.',
                'نصب و راه‌اندازی',
            ],
            'users.view' => [
                'مشاهده کاربران',
                'مشاهده فهرست و اطلاعات کاربران.',
                'کاربران',
            ],
            'users.create' => [
                'ایجاد کاربر',
                'ایجاد حساب کاربری جدید.',
                'کاربران',
            ],
            'users.update' => [
                'ویرایش کاربران',
                'ویرایش اطلاعات حساب‌های کاربری.',
                'کاربران',
            ],
            'users.delete' => [
                'حذف کاربران',
                'حذف یا غیرفعال‌سازی کاربران مجاز.',
                'کاربران',
            ],
            'users.manage' => [
                'مدیریت کاربران',
                'مدیریت کامل حساب‌های کاربری.',
                'کاربران',
            ],
            'work.project.view' => [
                'مشاهده پروژه‌ها و کارها',
                'مشاهده پروژه‌ها و کارهای ثبت‌شده.',
                'پروژه‌ها',
            ],
            'work.project.manage' => [
                'مدیریت پروژه‌ها و کارها',
                'ایجاد و ویرایش پروژه‌ها و کارهای زیرمجموعه.',
                'پروژه‌ها',
            ],
            'work.audit.view' => [
                'مشاهده تاریخچه تغییرات مدیریت کار',
                'مشاهده رویدادها و تغییرات ماژول مدیریت کار.',
                'ممیزی و تاریخچه',
            ],
            'work.settings.manage' => [
                'مدیریت تعاریف و تنظیمات مدیریت کار',
                'مدیریت تنظیمات و داده‌های پایه ماژول مدیریت کار.',
                'تنظیمات',
            ],
            'work.project.admin' => [
                'دسترسی مدیریتی سراسری به پروژه‌ها',
                'مدیریت همه پروژه‌ها و کارها بدون محدودیت مالکیت.',
                'پروژه‌ها',
            ],
        ];

        $statement = $this->db->prepare("
            UPDATE permissions
            SET title = ?,
                description = ?,
                display_group = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = ?
        ");

        foreach ($items as $code => $definition) {
            $statement->execute([
                $definition[0],
                $definition[1],
                $definition[2],
                $code,
            ]);
        }
    }

    private function completeDisplayGroups(): void
    {
        $groups = [
            'profile' => 'پروفایل',
            'security' => 'امنیت حساب',
            'password' => 'امنیت حساب',
            'theme' => 'ظاهر پنل',
            'dashboard' => 'داشبورد',
            'access' => 'دسترسی',
            'settings' => 'تنظیمات',
            'pages' => 'صفحات پنل',
            'reports' => 'گزارش‌ها',
            'navigation' => 'منوها',
            'routes' => 'مسیرها',
            'roles' => 'نقش‌ها',
            'permissions' => 'مجوزها',
            'login_tokens' => 'ورود و نشست',
            'org_units' => 'واحدهای سازمانی',
            'positions' => 'سمت‌ها',
            'user_org_assignments' => 'انتساب کاربران',
            'organizations' => 'سازمان‌ها',
            'support' => 'پشتیبانی',
            'diagnostics' => 'پایش و عیب‌یابی',
            'installer' => 'نصب و راه‌اندازی',
            'users' => 'کاربران',
            'audit' => 'ممیزی و تاریخچه',
            'item' => 'اقلام کار',
            'project' => 'پروژه‌ها',
            'providers' => 'سرویس‌دهندگان',
            'routing' => 'مسیریابی',
            'preferences' => 'ترجیحات',
            'send' => 'ارسال اعلان',
            'notification_send' => 'ارسال اعلان',
            'notification_recipients' => 'گیرندگان اعلان',
            'notification_manual_targets' => 'مقصدهای دستی',
            'notification_approval' => 'تأیید اعلان',
            'messages' => 'پیام‌ها',
            'general' => 'عمومی',
        ];

        $statement = $this->db->prepare("
            UPDATE permissions
            SET display_group = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE resource = ?
              AND (
                    display_group IS NULL
                    OR display_group = ''
                    OR display_group = resource
              )
        ");

        foreach ($groups as $resource => $title) {
            $statement->execute([$title, $resource]);
        }
    }
}

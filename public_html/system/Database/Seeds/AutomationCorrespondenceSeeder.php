<?php

namespace IPKF\Database\Seeds;

class AutomationCorrespondenceSeeder extends Seeder
{
    private const DOMAINS = [
        'correspondence_direction' => 'جهت مکاتبه',
        'correspondence_status' => 'وضعیت مکاتبه',
        'correspondence_priority' => 'اولویت مکاتبه',
        'correspondence_confidentiality' => 'طبقه‌بندی محرمانگی',
        'correspondence_channel' => 'روش دریافت مکاتبه',
        'correspondence_dispatch_channel' => 'روش ارسال خارج از کارتابل',
        'correspondence_dispatch_status' => 'وضعیت ارسال خارج از کارتابل',
        'correspondence_dispatch_followup_type' => 'نوع پیگیری ارسال',
        'correspondence_dispatch_followup_status' => 'وضعیت پیگیری ارسال',
        'correspondence_party_role' => 'نقش طرف مکاتبه',
        'correspondence_party_kind' => 'نوع طرف مکاتبه',
        'registry_book_scope' => 'دامنه دفتر ثبت',
        'registration_role' => 'نقش ثبت',
        'registration_status' => 'وضعیت ثبت',
        'correspondence_relation_type' => 'نوع ارتباط مکاتبات',
        'referral_requested_action' => 'اقدام درخواستی ارجاع',
        'referral_status' => 'وضعیت ارجاع',
        'correspondence_event_type' => 'نوع رویداد مکاتبه',
        'attachment_role' => 'نقش پیوست',
        'file_scan_status' => 'وضعیت بررسی فایل',
    ];

    private const VALUES = [
        'correspondence_direction' => [
            ['incoming', 'وارده'],
            ['outgoing', 'صادره'],
            ['internal', 'داخلی'],
        ],
        'correspondence_status' => [
            ['draft', 'پیش‌نویس'],
            ['registered', 'ثبت‌شده'],
            ['received', 'دریافت‌شده'],
            ['dispatched', 'ارسال‌شده'],
            ['in_progress', 'در حال اقدام'],
            ['closed', 'بسته‌شده'],
            ['cancelled', 'لغوشده'],
        ],
        'correspondence_priority' => [
            ['low', 'کم'],
            ['normal', 'عادی'],
            ['high', 'زیاد'],
            ['urgent', 'فوری'],
        ],
        'correspondence_confidentiality' => [
            ['normal', 'عادی'],
            ['confidential', 'محرمانه'],
            ['secret', 'سری'],
            ['top_secret', 'به‌کلی سری'],
        ],
        'correspondence_channel' => [
            ['manual', 'ثبت دستی'],
            ['postal', 'پست'],
            ['courier', 'پیک'],
            ['hand_delivery', 'تحویل دستی'],
            ['fax', 'فاکس'],
            ['email', 'ایمیل'],
            ['system', 'سامانه'],
        ],
        'correspondence_dispatch_channel' => [
            ['postal', 'پست'],
            ['courier', 'پیک'],
            ['hand_delivery', 'تحویل دستی'],
            ['fax', 'فاکس'],
            ['email', 'ایمیل'],
            ['system', 'سامانه'],
        ],
        'correspondence_dispatch_status' => [
            ['prepared', 'آماده ارسال'],
            ['pending', 'در انتظار ارسال'],
            ['queued', 'در صف ارسال'],
            ['dispatched', 'ارسال‌شده'],
            ['delivered', 'تحویل‌شده'],
            ['failed', 'ناموفق'],
            ['cancelled', 'لغوشده'],
        ],
        'correspondence_dispatch_followup_type' => [
            ['destination_registration', 'اخذ شماره ثبت مقصد'],
            ['delivery_confirmation', 'تأیید تحویل'],
            ['phone_followup', 'پیگیری تلفنی'],
        ],
        'correspondence_dispatch_followup_status' => [
            ['pending', 'در انتظار پیگیری'],
            ['completed', 'تکمیل‌شده'],
            ['cancelled', 'لغوشده'],
        ],
        'correspondence_party_role' => [
            ['sender', 'فرستنده'],
            ['primary_recipient', 'گیرنده اصلی'],
            ['cc', 'رونوشت'],
            ['bcc', 'رونوشت مخفی'],
            ['external_correspondent', 'طرف مکاتبه بیرونی'],
        ],
        'correspondence_party_kind' => [
            ['person', 'شخص'],
            ['organization', 'سازمان'],
            ['org_unit', 'واحد سازمانی'],
            ['external', 'طرف بیرونی'],
        ],
        'registry_book_scope' => [
            ['incoming', 'دفتر وارده'],
            ['outgoing', 'دفتر صادره'],
            ['internal', 'دفتر مکاتبات داخلی'],
            ['general', 'دفتر عمومی'],
        ],
        'registration_role' => [
            ['official', 'ثبت رسمی'],
            ['secondary', 'ثبت ثانویه'],
        ],
        'registration_status' => [
            ['active', 'فعال'],
            ['cancelled', 'باطل‌شده'],
        ],
        'correspondence_relation_type' => [
            ['reply_to', 'پاسخ به'],
            ['follow_up', 'پیرو'],
            ['continuation', 'ادامه'],
            ['replacement', 'جایگزین'],
            ['related', 'مرتبط'],
            ['cancellation_reference', 'مرجع ابطال'],
        ],
        'referral_requested_action' => [
            ['review', 'بررسی'],
            ['action', 'اقدام'],
            ['reply', 'تهیه پاسخ'],
            ['approve', 'تأیید'],
            ['sign', 'امضا'],
            ['archive', 'بایگانی'],
        ],
        'referral_status' => [
            ['pending', 'در انتظار'],
            ['seen', 'مشاهده‌شده'],
            ['claimed', 'پذیرفته‌شده'],
            ['in_progress', 'در حال اقدام'],
            ['returned', 'بازگردانده‌شده'],
            ['completed', 'تکمیل‌شده'],
            ['cancelled', 'لغوشده'],
        ],
        'correspondence_event_type' => [
            ['created', 'ایجاد'],
            ['revised', 'ویرایش نسخه'],
            ['registered', 'ثبت رسمی'],
            ['received', 'دریافت'],
            ['dispatched', 'ارسال'],
            ['referred', 'ارجاع'],
            ['seen', 'مشاهده'],
            ['claimed', 'پذیرش'],
            ['returned', 'بازگشت'],
            ['completed', 'تکمیل'],
            ['closed', 'بستن'],
            ['cancelled', 'لغو'],
            ['relation_added', 'افزودن ارتباط'],
            ['attachment_linked', 'پیوند پیوست'],
        ],
        'attachment_role' => [
            ['main', 'فایل اصلی'],
            ['enclosure', 'پیوست'],
            ['supporting', 'مدرک پشتیبان'],
            ['scan', 'تصویر اسکن‌شده'],
        ],
        'file_scan_status' => [
            ['pending', 'در انتظار بررسی'],
            ['clean', 'سالم'],
            ['infected', 'آلوده'],
            ['failed', 'خطا در بررسی'],
            ['not_required', 'نیازمند بررسی نیست'],
        ],
    ];

    private const PERMISSIONS = [
        ['automation.correspondence.view', 'view', 'مشاهده مکاتبات'],
        ['automation.correspondence.create', 'create', 'ایجاد پیش‌نویس مکاتبه'],
        ['automation.correspondence.edit_draft', 'edit_draft', 'ویرایش پیش‌نویس مکاتبه'],
        ['automation.correspondence.register', 'register', 'ثبت رسمی مکاتبه'],
        ['automation.correspondence.dispatch', 'dispatch', 'ثبت ارسال مکاتبه صادره'],
        ['automation.correspondence.route', 'route', 'ارجاع مکاتبه'],
        ['automation.correspondence.cartable.view', 'cartable_view', 'مشاهده کارتابل مکاتبات'],
        ['automation.correspondence.close', 'close', 'بستن مکاتبه'],
        ['automation.registry.manage', 'manage_registry', 'مدیریت دفترهای ثبت'],
        ['automation.audit.view', 'view_audit', 'مشاهده تاریخچه مکاتبات'],
    ];

    public function run(): void
    {
        if (!$this->tableExists('lookup_domains') || !$this->tableExists('lookup_values')) {
            return;
        }

        $this->seedLookupDomains();
        $this->seedLookupValues();
        $this->retireObsoleteValues();
    }

    private function seedLookupDomains(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO lookup_domains (
                code, title, description, is_system, status, created_at, updated_at
            ) VALUES (?, ?, NULL, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                is_system = 1,
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach (self::DOMAINS as $code => $title) {
            $statement->execute([$code, $title]);
        }
    }

    private function seedLookupValues(): void
    {
        $domainStatement = $this->db->prepare('SELECT id FROM lookup_domains WHERE code = ? LIMIT 1');
        $valueStatement = $this->db->prepare("
            INSERT INTO lookup_values (
                domain_id, code, title, description, sort_order, status, metadata_json,
                created_at, updated_at
            ) VALUES (?, ?, ?, NULL, ?, 'active', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                sort_order = VALUES(sort_order),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach (self::VALUES as $domainCode => $values) {
            $domainStatement->execute([$domainCode]);
            $domainId = $domainStatement->fetchColumn();

            if ($domainId === false) {
                continue;
            }

            foreach ($values as $index => [$code, $title]) {
                $valueStatement->execute([(int) $domainId, $code, $title, ($index + 1) * 10]);
            }
        }
    }


    private function retireObsoleteValues(): void
    {
        $statement = $this->db->prepare("
            UPDATE lookup_values AS lv
            INNER JOIN lookup_domains AS ld
                ON ld.id = lv.domain_id
            SET
                lv.status = 'inactive',
                lv.updated_at = CURRENT_TIMESTAMP
            WHERE ld.code = 'correspondence_channel'
              AND lv.code = 'internal'
        ");

        $statement->execute();
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

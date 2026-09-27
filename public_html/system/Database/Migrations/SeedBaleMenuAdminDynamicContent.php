<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

/**
 * B7-A129-R2
 *
 * Seeds administrator-editable Bale menu management UI copy into the existing
 * Dynamic UI Content foundation. Runtime consumers never use these Persian
 * defaults directly; they resolve the active DB override through UiContentInlineGuide.
 * Existing administrator-managed rows are preserved verbatim.
 */
final class SeedBaleMenuAdminDynamicContent extends Migration
{
    private const SEED = 'b7-a129-r2';
    private const MODULE = 'core';
    private const SURFACE = 'bale-menu-admin';

    public function up(): void
    {
        $items = $this->items();
        if ($items === []) throw new RuntimeException('Bale menu content seed is empty.');

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                $definitionId = $this->ensureDefinition($item);
                $this->ensureOverride($definitionId, $item);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function down(): void
    {
        /* Non-destructive: once seeded, copy is administrator-managed content. */
    }

    /** @return list<array{key:string,type:string,title:string,body:string}> */
    private function items(): array
    {
        return [
            // Existing dynamic notices / errors used by the page.
            ['key'=>'core.bale-menu.notice.published','type'=>'notice','title'=>'انتشار انجام شد','body'=>'نسخه جدید منو با موفقیت منتشر شد.'],
            ['key'=>'core.bale-menu.notice.unchanged','type'=>'notice','title'=>'بدون تغییر','body'=>'تغییری برای انتشار وجود ندارد.'],
            ['key'=>'core.bale-menu.notice.bot_registered','type'=>'notice','title'=>'بات ثبت شد','body'=>'بات آماده‌شده با موفقیت به مدیریت منوها افزوده شد.'],
            ['key'=>'core.bale-menu.error.invalid_csrf','type'=>'error','title'=>'نشست نامعتبر','body'=>'نشست امنیتی معتبر نیست. صفحه را تازه‌سازی و دوباره تلاش کنید.'],
            ['key'=>'core.bale-menu.error.save_failed','type'=>'error','title'=>'ذخیره انجام نشد','body'=>'ذخیره تغییرات انجام نشد. دوباره تلاش کنید.'],
            ['key'=>'core.bale-menu.error.catalog_read','type'=>'error','title'=>'خواندن کاتالوگ ناموفق بود','body'=>'کاتالوگ فعال بات قابل خواندن نیست.'],
            ['key'=>'core.bale-menu.error.label_conflict','type'=>'error','title'=>'تعارض عنوان دکمه','body'=>'این عنوان با یکی از عنوان‌های تاریخی دکمه‌ها تعارض دارد و نمی‌تواند به مقصد دیگری اختصاص یابد.'],
            ['key'=>'core.bale-menu.error.button_invalid','type'=>'error','title'=>'دکمه نامعتبر است','body'=>'اطلاعات دکمه‌ها کامل یا معتبر نیست.'],
            ['key'=>'core.bale-menu.error.text_invalid','type'=>'error','title'=>'متن نامعتبر است','body'=>'متن واردشده خالی، بیش از حد مجاز یا خارج از قرارداد محتوایی است.'],
            ['key'=>'core.bale-menu.error.publish_busy','type'=>'error','title'=>'انتشار در حال انجام است','body'=>'هم‌اکنون یک انتشار دیگر در حال انجام است. کمی بعد دوباره تلاش کنید.'],
            ['key'=>'core.bale-menu.error.catalog_not_ready','type'=>'error','title'=>'کاتالوگ آماده نیست','body'=>'بات انتخاب‌شده هنوز کاتالوگ فعال و معتبر مستقلی ندارد.'],
            ['key'=>'core.bale-menu.error.stale_form','type'=>'error','title'=>'فرم قدیمی است','body'=>'نسخه فعال از زمان باز شدن فرم تغییر کرده است. صفحه را تازه‌سازی کنید.'],
            ['key'=>'core.bale-menu.error.identity_changed','type'=>'error','title'=>'هویت دکمه تغییر کرده است','body'=>'هویت فنی دکمه‌ها قابل تغییر نیست. فقط متن و ترتیب قابل ویرایش است.'],
            ['key'=>'core.bale-menu.error.mode_invalid','type'=>'error','title'=>'نوع نمایش نامعتبر است','body'=>'نوع نمایش انتخاب‌شده معتبر نیست.'],
            ['key'=>'core.bale-menu.error.columns_invalid','type'=>'error','title'=>'تعداد ستون نامعتبر است','body'=>'تعداد دکمه در هر سطر خارج از محدوده مجاز است.'],
            ['key'=>'core.bale-menu.error.rows_invalid','type'=>'error','title'=>'چیدمان سطرها نامعتبر است','body'=>'چیدمان سطرهای دکمه‌ها معتبر نیست یا با قرارداد منو سازگار نیست.'],
            ['key'=>'core.bale-menu.error.registration_invalid','type'=>'error','title'=>'اطلاعات بات نامعتبر است','body'=>'کلید یا نام محیط اجرایی بات معتبر نیست.'],
            ['key'=>'core.bale-menu.error.key_exists','type'=>'error','title'=>'کلید بات تکراری است','body'=>'این کلید بات قبلاً ثبت شده است.'],
            ['key'=>'core.bale-menu.error.registry_limit','type'=>'error','title'=>'ظرفیت ثبت بات تکمیل است','body'=>'تعداد بات‌های قابل ثبت به سقف مجاز رسیده است.'],
            ['key'=>'core.bale-menu.error.not_provisioned','type'=>'error','title'=>'بات آماده نشده است','body'=>'محیط اجرایی معرفی‌شده هنوز به‌صورت معتبر آماده نشده است.'],
            ['key'=>'core.bale-menu.error.already_registered','type'=>'error','title'=>'بات قبلاً ثبت شده است','body'=>'این بات پیش از این در مدیریت منوها ثبت شده است.'],
            ['key'=>'core.bale-menu.error.identity_conflict','type'=>'error','title'=>'تعارض هویت بات','body'=>'هویت این بات با یکی از بات‌های ثبت‌شده تعارض دارد.'],
            ['key'=>'core.bale-menu.error.not_dev','type'=>'error','title'=>'محیط Dev معتبر نیست','body'=>'فقط محیط اجرایی Dev مستقل قابل ثبت در این بخش است.'],
            ['key'=>'core.bale-menu.error.registration_busy','type'=>'error','title'=>'ثبت بات در حال انجام است','body'=>'هم‌اکنون عملیات ثبت دیگری در حال انجام است. کمی بعد دوباره تلاش کنید.'],
            ['key'=>'core.bale-menu.error.registry_changed','type'=>'error','title'=>'فهرست بات‌ها تغییر کرده است','body'=>'فهرست بات‌ها هم‌زمان تغییر کرده است. صفحه را تازه‌سازی و دوباره تلاش کنید.'],

            // Existing dynamic guidance already consumed by the view.
            ['key'=>'core.bale-menu.guide.overview','type'=>'guide','title'=>'راهنمای مدیریت منوی بله','body'=>'متن منو، عنوان دکمه‌ها و متن‌های پاسخ بات از محتوای مدیریت‌شده خوانده می‌شوند و هویت فنی کلیدها ثابت می‌ماند.'],
            ['key'=>'core.bale-menu.guide.button_order','type'=>'guide','title'=>'ترتیب دکمه‌ها','body'=>'ترتیب دکمه‌ها را با عدد مشخص کنید. تغییر ترتیب، مقصد فنی دکمه را تغییر نمی‌دهد.'],
            ['key'=>'core.bale-menu.guide.managed_content','type'=>'guide','title'=>'متن‌های مدیریت‌شده','body'=>'متن‌های پاسخ و پیام‌های بات از همین بخش قابل ویرایش‌اند و همراه نسخه منو منتشر می‌شوند.'],
            ['key'=>'core.bale-menu.guide.editor_scope','type'=>'guide','title'=>'محدوده ویرایش','body'=>'فقط متن‌های قابل‌نمایش و ترتیب مجاز قابل ویرایش‌اند؛ کلید، مقصد و قرارداد فنی ثابت می‌مانند.'],
            ['key'=>'core.bale-menu.guide.preview','type'=>'guide','title'=>'پیش‌نمایش','body'=>'پیش‌نمایش زیر، چیدمان و عنوان‌های نسخه در حال ویرایش را نشان می‌دهد.'],
            ['key'=>'core.bale-menu.guide.publish_scope','type'=>'guide','title'=>'دامنه انتشار','body'=>'انتشار، یک نسخه جدید و تغییرناپذیر برای همان بات ایجاد می‌کند و نسخه‌های تاریخی حفظ می‌شوند.'],
            ['key'=>'core.bale-menu.guide.register','type'=>'guide','title'=>'ثبت بات آماده‌شده','body'=>'فقط باتی را ثبت کنید که محیط Dev مستقل و کاتالوگ فعال معتبر آن از قبل آماده شده باشد.'],
            ['key'=>'core.bale-menu.guide.transport_privacy','type'=>'guide','title'=>'حریم اطلاعات اتصال','body'=>'این صفحه توکن بات را نمی‌خواند و اطلاعات محرمانه اتصال را نمایش نمی‌دهد.'],
            ['key'=>'core.bale-menu.guide.webhook_pending','type'=>'guide','title'=>'دریافت پیام','body'=>'تنظیمات دریافت پیام و وب‌هوک باید از مسیر کنترل‌شده زیرساخت همان بات انجام شود.'],
            ['key'=>'core.bale-menu.guide.registration_scope','type'=>'guide','title'=>'دامنه ثبت','body'=>'ثبت در این صفحه فقط بات آماده‌شده را به فهرست مدیریت اضافه می‌کند و سرویس جدیدی Provision نمی‌کند.'],

            // Admin UI labels. These are runtime-resolved, administrator-editable copy.
            ['key'=>'core.bale-menu.ui.page_title','type'=>'guide','title'=>'عنوان صفحه','body'=>'مدیریت منوهای بله'],
            ['key'=>'core.bale-menu.ui.tabs_aria','type'=>'guide','title'=>'برچسب دسترس‌پذیری تب‌ها','body'=>'بخش‌های مدیریت بات'],
            ['key'=>'core.bale-menu.ui.tab.bots','type'=>'guide','title'=>'تب بات‌ها','body'=>'بات‌ها'],
            ['key'=>'core.bale-menu.ui.tab.menus','type'=>'guide','title'=>'تب منوها','body'=>'منوها'],
            ['key'=>'core.bale-menu.ui.tab.transport','type'=>'guide','title'=>'تب دریافت پیام','body'=>'دریافت پیام و وب‌هوک'],
            ['key'=>'core.bale-menu.ui.tab.messages','type'=>'guide','title'=>'تب راهنماها','body'=>'راهنماها و اعلان‌ها'],
            ['key'=>'core.bale-menu.ui.bot_prefix','type'=>'guide','title'=>'پیشوند بات','body'=>'بات:'],
            ['key'=>'core.bale-menu.ui.revision_prefix','type'=>'guide','title'=>'پیشوند نسخه','body'=>'نسخه:'],
            ['key'=>'core.bale-menu.ui.menu_select','type'=>'guide','title'=>'انتخاب منو','body'=>'انتخاب منو'],
            ['key'=>'core.bale-menu.ui.menu_edit_aria','type'=>'guide','title'=>'برچسب ویرایش منو','body'=>'ویرایش منو'],
            ['key'=>'core.bale-menu.ui.columns_label','type'=>'guide','title'=>'تعداد دکمه در سطر','body'=>'تعداد دکمه در هر سطر'],
            ['key'=>'core.bale-menu.ui.display_mode_label','type'=>'guide','title'=>'نوع نمایش','body'=>'نوع نمایش'],
            ['key'=>'core.bale-menu.ui.display_mode.inline','type'=>'guide','title'=>'نمایش شیشه‌ای','body'=>'شیشه‌ای، زیر پیام'],
            ['key'=>'core.bale-menu.ui.display_mode.reply','type'=>'guide','title'=>'منوی پایین صفحه','body'=>'منوی پایین صفحه'],
            ['key'=>'core.bale-menu.ui.menu_text_label','type'=>'guide','title'=>'متن پیام منو','body'=>'متن پیام منو'],
            ['key'=>'core.bale-menu.ui.buttons_heading','type'=>'guide','title'=>'دکمه‌ها','body'=>'دکمه‌ها'],
            ['key'=>'core.bale-menu.ui.button_title_label','type'=>'guide','title'=>'عنوان دکمه','body'=>'عنوان دکمه'],
            ['key'=>'core.bale-menu.ui.order_label','type'=>'guide','title'=>'ترتیب','body'=>'ترتیب'],
            ['key'=>'core.bale-menu.ui.preview_aria','type'=>'guide','title'=>'پیش‌نمایش منو','body'=>'پیش‌نمایش منو'],
            ['key'=>'core.bale-menu.ui.publish_button','type'=>'guide','title'=>'انتشار نسخه','body'=>'انتشار نسخه جدید منو'],
            ['key'=>'core.bale-menu.ui.bots_aria','type'=>'guide','title'=>'بات‌های تعریف‌شده','body'=>'بات‌های تعریف‌شده'],
            ['key'=>'core.bale-menu.ui.registered_bots_heading','type'=>'guide','title'=>'بات‌های ثبت‌شده','body'=>'بات‌های ثبت‌شده'],
            ['key'=>'core.bale-menu.ui.manage_menu','type'=>'guide','title'=>'مدیریت منو','body'=>'مدیریت منو'],
            ['key'=>'core.bale-menu.ui.add_bot_heading','type'=>'guide','title'=>'افزودن بات','body'=>'افزودن بات آماده‌شده'],
            ['key'=>'core.bale-menu.ui.bot_key_label','type'=>'guide','title'=>'کلید یکتای بات','body'=>'کلید یکتای بات'],
            ['key'=>'core.bale-menu.ui.bot_key_placeholder','type'=>'guide','title'=>'نمونه کلید بات','body'=>'support-bot'],
            ['key'=>'core.bale-menu.ui.site_slug_label','type'=>'guide','title'=>'نام پوشه Dev','body'=>'نام پوشه محیط اجرایی Dev'],
            ['key'=>'core.bale-menu.ui.site_slug_placeholder','type'=>'guide','title'=>'نمونه محیط Dev','body'=>'supportbot-dev.example.ir'],
            ['key'=>'core.bale-menu.ui.register_bot_button','type'=>'guide','title'=>'ثبت بات','body'=>'بررسی و ثبت بات آماده‌شده'],
            ['key'=>'core.bale-menu.ui.transport_aria','type'=>'guide','title'=>'دریافت پیام و وب‌هوک','body'=>'دریافت پیام و وب‌هوک'],
            ['key'=>'core.bale-menu.ui.transport_heading','type'=>'guide','title'=>'دریافت پیام و وب‌هوک','body'=>'دریافت پیام و وب‌هوک'],
            ['key'=>'core.bale-menu.ui.selected_bot_prefix','type'=>'guide','title'=>'بات انتخاب‌شده','body'=>'بات انتخاب‌شده:'],
            ['key'=>'core.bale-menu.ui.messages_aria','type'=>'guide','title'=>'راهنماها و اعلان‌ها','body'=>'راهنماها و اعلان‌ها'],
            ['key'=>'core.bale-menu.ui.messages_heading','type'=>'guide','title'=>'راهنماها و اعلان‌ها','body'=>'راهنماها، اعلان‌ها، هشدارها و خطاها'],
            ['key'=>'core.bale-menu.ui.open_content_management','type'=>'guide','title'=>'مدیریت راهنما و اعلان','body'=>'بازکردن مدیریت راهنما و اعلان‌ها'],
        ];
    }

    private function ensureDefinition(array $item): int
    {
        $key = (string) $item['key'];
        $type = (string) $item['type'];
        if (!in_array($type, ['guide','notice','error'], true)) throw new RuntimeException('Unsupported content type: ' . $key);

        $query = $this->db->prepare(
            'SELECT id, content_type FROM ui_content_definitions WHERE content_key = ? LIMIT 1 FOR UPDATE'
        );
        $query->execute([$key]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            if ((string)($row['content_type'] ?? '') !== $type) throw new RuntimeException('Definition type mismatch: ' . $key);
            return (int) $row['id'];
        }

        $reference = 'UICD-' . strtoupper(substr(hash('sha256', 'definition|' . $key), 0, 24));
        $metadata = [
            'seed'=>self::SEED,
            'module'=>self::MODULE,
            'surface'=>self::SURFACE,
            'render_mode'=>'body',
            'consumer_bound'=>true,
            'runtime_dynamic'=>true,
        ];
        $insert = $this->db->prepare(
            'INSERT INTO ui_content_definitions '
            . '(public_reference, content_key, content_type, default_locale, http_status, description, metadata_json, is_active) '
            . 'VALUES (?, ?, ?, \'fa\', NULL, ?, ?, 1)'
        );
        $insert->execute([
            $reference,$key,$type,(string)$item['title'],
            json_encode($metadata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ]);
        $id=(int)$this->db->lastInsertId();
        if ($id < 1) {
            $query->execute([$key]);
            $id=(int)$query->fetchColumn();
        }
        if ($id < 1) throw new RuntimeException('Unable to create definition: ' . $key);
        return $id;
    }

    private function ensureOverride(int $definitionId, array $item): void
    {
        $key=(string)$item['key'];
        $scopeKey='scope:' . self::MODULE . ':surface:' . self::SURFACE;
        $query=$this->db->prepare(
            "SELECT id FROM ui_content_overrides WHERE definition_id = ? AND scope_key = ? AND locale = 'fa' LIMIT 1 FOR UPDATE"
        );
        $query->execute([$definitionId,$scopeKey]);
        if ($query->fetch(PDO::FETCH_ASSOC)) return; // Preserve administrator-managed content verbatim.

        $reference='UICO-' . strtoupper(substr(hash('sha256','override|' . $key . '|' . $scopeKey . '|fa'),0,24));
        $scopePath=[['type'=>'surface','reference'=>self::SURFACE]];
        $metadata=[
            'seed'=>self::SEED,
            'consumer_bound'=>true,
            'render_mode'=>'body',
            'runtime_dynamic'=>true,
        ];
        $insert=$this->db->prepare(
            "INSERT INTO ui_content_overrides
             (public_reference, definition_id, scope_type, scope_key, module_key, scope_reference, scope_path_json,
              locale, title, body, icon_code, severity_code, layout_variant, visibility_mode,
              primary_action_code, primary_action_label, secondary_action_code, secondary_action_label,
              metadata_json, is_active)
             VALUES (?, ?, 'fine', ?, ?, ?, ?, 'fa', ?, ?, NULL, NULL, NULL, 'show', NULL, NULL, NULL, NULL, ?, 1)"
        );
        $insert->execute([
            $reference,$definitionId,$scopeKey,self::MODULE,self::SURFACE,
            json_encode($scopePath,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            (string)$item['title'],(string)$item['body'],
            json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ]);
    }
}

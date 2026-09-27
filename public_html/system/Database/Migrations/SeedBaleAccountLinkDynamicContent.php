<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

/**
 * B7-A148
 *
 * Seeds administrator-editable Bale Account-Link copy.
 * Runtime consumers resolve these values through UiContentInlineGuide.
 * Existing administrator-managed overrides are never overwritten.
 */
final class SeedBaleAccountLinkDynamicContent extends Migration
{
    private const SEED = 'b7-a148';
    private const MODULE = 'core';
    private const SURFACE = 'bale-account-link';

    public function up(): void
    {
        $items = $this->items();

        if ($items === []) {
            throw new RuntimeException(
                'Bale account-link dynamic content seed is empty.'
            );
        }

        $this->db->beginTransaction();

        try {
            foreach ($items as $item) {
                $definitionId = $this->ensureDefinition($item);
                $this->ensureOverride($definitionId, $item);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function down(): void
    {
        /*
         * Non-destructive.
         * Once seeded, copy is administrator-managed content.
         */
    }

    /**
     * @return list<array{
     *   key:string,
     *   title:string,
     *   body:string
     * }>
     */
    private function items(): array
    {
        return [
            [
                'key' => 'core.bale-account-link.ui.page_title',
                'title' => 'عنوان اتصال حساب بله',
                'body' => 'اتصال حساب بله',
            ],
            [
                'key' => 'core.bale-account-link.ui.status_intro',
                'title' => 'توضیح وضعیت اتصال',
                'body' => 'وضعیت اتصال حساب شما به بات‌های تعریف‌شده در IPKF',
            ],
            [
                'key' => 'core.bale-account-link.error.csrf',
                'title' => 'خطای اعتبار درخواست',
                'body' => 'اعتبار درخواست منقضی شده است. صفحه را تازه‌سازی کنید.',
            ],
            [
                'key' => 'core.bale-account-link.error.invalid',
                'title' => 'بات نامعتبر',
                'body' => 'بات انتخاب‌شده معتبر یا در دسترس نیست.',
            ],
            [
                'key' => 'core.bale-account-link.error.rate_limited',
                'title' => 'محدودیت درخواست',
                'body' => 'برای درخواست دوباره کمی صبر کنید.',
            ],
            [
                'key' => 'core.bale-account-link.error.connected',
                'title' => 'اتصال موجود',
                'body' => 'حساب شما قبلاً به این بات متصل شده است.',
            ],
            [
                'key' => 'core.bale-account-link.error.failed',
                'title' => 'شروع اتصال ناموفق',
                'body' => 'شروع اتصال انجام نشد. دوباره تلاش کنید.',
            ],
            [
                'key' => 'core.bale-account-link.notice.request_created',
                'title' => 'درخواست اتصال',
                'body' => 'درخواست اتصال ثبت شد.',
            ],
            [
                'key' => 'core.bale-account-link.ui.confirm_instruction',
                'title' => 'راهنمای تأیید',
                'body' => 'برای تأیید هویت بله، لینک زیر را در همین دستگاه باز کنید.',
            ],
            [
                'key' => 'core.bale-account-link.ui.link_expiry',
                'title' => 'اعتبار لینک',
                'body' => 'این لینک یک‌بارمصرف است و ده دقیقه اعتبار دارد.',
            ],
            [
                'key' => 'core.bale-account-link.ui.confirm_button',
                'title' => 'دکمه تأیید',
                'body' => 'تأیید در بله',
            ],
            [
                'key' => 'core.bale-account-link.ui.pending_notice',
                'title' => 'وضعیت در انتظار',
                'body' => 'تا پیش از دریافت و تأیید پیام واقعی از همین بات، وضعیت حساب «متصل نیست» باقی می‌ماند.',
            ],
            [
                'key' => 'core.bale-account-link.error.connection_unavailable',
                'title' => 'اطلاعات اتصال ناموجود',
                'body' => 'اطلاعات اتصال بله فعلاً در دسترس نیست.',
            ],
            [
                'key' => 'core.bale-account-link.error.no_active_bot',
                'title' => 'بات اتصال موجود نیست',
                'body' => 'هنوز بات فعالی با قابلیت اتصال حساب کاربری در IPKF پیکربندی نشده است.',
            ],
            [
                'key' => 'core.bale-account-link.ui.default_bot_name',
                'title' => 'نام پیش‌فرض بات',
                'body' => 'بات بله',
            ],
            [
                'key' => 'core.bale-account-link.ui.status_label',
                'title' => 'برچسب وضعیت حساب',
                'body' => 'وضعیت حساب:',
            ],
            [
                'key' => 'core.bale-account-link.ui.connected',
                'title' => 'وضعیت متصل',
                'body' => 'متصل',
            ],
            [
                'key' => 'core.bale-account-link.ui.not_connected',
                'title' => 'وضعیت متصل نیست',
                'body' => 'متصل نیست',
            ],
            [
                'key' => 'core.bale-account-link.ui.verified_at',
                'title' => 'زمان تأیید',
                'body' => 'زمان تأیید:',
            ],
            [
                'key' => 'core.bale-account-link.ui.connect_button',
                'title' => 'دکمه اتصال',
                'body' => 'اتصال به این بات',
            ],
            [
                'key' => 'core.bale-account-link.ui.managed_notice',
                'title' => 'راهنمای مدیریت اتصال',
                'body' => 'مدیریت اتصال این بات از همین بخش انجام می‌شود.',
            ],
            [
                'key' => 'core.bale-account-link.ui.link_token_label',
                'title' => 'برچسب کد اتصال',
                'body' => 'کد اتصال دریافت‌شده از بات بله',
            ],
            [
                'key' => 'core.bale-account-link.ui.consent_label',
                'title' => 'رضایت اتصال',
                'body' => 'اتصال حساب IPKF من به حساب بات بله را تأیید می‌کنم.',
            ],
            [
                'key' => 'core.bale-account-link.ui.submit_button',
                'title' => 'دکمه ثبت اتصال',
                'body' => 'ثبت درخواست اتصال',
            ],
            [
                'key' => 'core.bale-account-link.ui.browser_page_title',
                'title' => 'عنوان صفحه تأیید',
                'body' => 'اتصال حساب IPKF به بله',
            ],
            [
                'key' => 'core.bale-account-link.error.auth_required',
                'title' => 'احراز هویت لازم است',
                'body' => 'برای اتصال حساب، دوباره وارد IPKF شوید، احراز هویت دومرحله‌ای را تکمیل کنید و ظرف پنج دقیقه به این صفحه برگردید.',
            ],
            [
                'key' => 'core.bale-account-link.ui.browser_intro',
                'title' => 'راهنمای ورود کد',
                'body' => 'کد اتصال را از گفت‌وگوی خصوصی بات وارد کنید. ثبت درخواست به معنی تکمیل اتصال نیست.',
            ],
            [
                'key' => 'core.bale-account-link.error.invalid_token',
                'title' => 'کد اتصال نامعتبر',
                'body' => 'کد اتصال یا تأیید کاربر معتبر نیست.',
            ],
            [
                'key' => 'core.bale-account-link.error.service_inactive',
                'title' => 'خدمت غیرفعال',
                'body' => 'خدمت اتصال حساب هنوز فعال نشده است.',
            ],
            [
                'key' => 'core.bale-account-link.error.submit_failed',
                'title' => 'ثبت درخواست ناموفق',
                'body' => 'درخواست ثبت نشد؛ نشست و اعتبار کد اتصال را بررسی کنید.',
            ],
            [
                'key' => 'core.bale-account-link.notice.queued',
                'title' => 'درخواست در صف',
                'body' => 'درخواست تأیید شما در صف خصوصی ثبت شد. اتصال حساب پس از دریافت و تأیید توسط بات نهایی می‌شود.',
            ],
            [
                'key' => 'core.bale-account-link.error.temporarily_unavailable',
                'title' => 'ثبت موقتاً ناممکن',
                'body' => 'ثبت درخواست در حال حاضر ممکن نیست.',
            ],
            [
                'key' => 'core.bale-account-link.ui.service_title_prefix',
                'title' => 'پیشوند عنوان خدمت',
                'body' => 'دسترسی به خدمات — ',
            ],
            [
                'key' => 'core.bale-account-link.ui.account_button',
                'title' => 'دکمه حساب',
                'body' => 'مدیریت اتصال حساب بله',
            ],
            [
                'key' => 'core.bale-account-link.ui.account_nav',
                'title' => 'ناوبری حساب',
                'body' => 'اتصال پیام‌رسان‌ها',
            ],
        ];
    }

    /**
     * @param array{key:string,title:string,body:string} $item
     */
    private function ensureDefinition(array $item): int
    {
        $key = $item['key'];

        $query = $this->db->prepare(
            'SELECT id, content_type '
            . 'FROM ui_content_definitions '
            . 'WHERE content_key = ? '
            . 'LIMIT 1 FOR UPDATE'
        );
        $query->execute([$key]);

        $row = $query->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            if ((string) ($row['content_type'] ?? '') !== 'guide') {
                throw new RuntimeException(
                    'Definition type mismatch: ' . $key
                );
            }

            return (int) $row['id'];
        }

        $reference =
            'UICD-'
            . strtoupper(
                substr(
                    hash(
                        'sha256',
                        'definition|' . $key
                    ),
                    0,
                    24
                )
            );

        $metadata = [
            'seed' => self::SEED,
            'module' => self::MODULE,
            'surface' => self::SURFACE,
            'render_mode' => 'body',
            'consumer_bound' => true,
            'runtime_dynamic' => true,
        ];

        $insert = $this->db->prepare(
            'INSERT INTO ui_content_definitions '
            . '(public_reference, content_key, content_type, '
            . 'default_locale, http_status, description, '
            . 'metadata_json, is_active) '
            . 'VALUES (?, ?, \'guide\', \'fa\', NULL, ?, ?, 1)'
        );

        $insert->execute([
            $reference,
            $key,
            $item['title'],
            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);

        $id = (int) $this->db->lastInsertId();

        if ($id < 1) {
            $query->execute([$key]);
            $id = (int) $query->fetchColumn();
        }

        if ($id < 1) {
            throw new RuntimeException(
                'Unable to create definition: ' . $key
            );
        }

        return $id;
    }

    /**
     * @param array{key:string,title:string,body:string} $item
     */
    private function ensureOverride(
        int $definitionId,
        array $item
    ): void {
        $key = $item['key'];

        $scopeKey =
            'scope:'
            . self::MODULE
            . ':surface:'
            . self::SURFACE;

        $query = $this->db->prepare(
            "SELECT id "
            . "FROM ui_content_overrides "
            . "WHERE definition_id = ? "
            . "AND scope_key = ? "
            . "AND locale = 'fa' "
            . "LIMIT 1 FOR UPDATE"
        );

        $query->execute([
            $definitionId,
            $scopeKey,
        ]);

        if ($query->fetch(PDO::FETCH_ASSOC)) {
            /*
             * Preserve administrator-managed content verbatim.
             */
            return;
        }

        $reference =
            'UICO-'
            . strtoupper(
                substr(
                    hash(
                        'sha256',
                        'override|'
                        . $key
                        . '|'
                        . $scopeKey
                        . '|fa'
                    ),
                    0,
                    24
                )
            );

        $scopePath = [[
            'type' => 'surface',
            'reference' => self::SURFACE,
        ]];

        $metadata = [
            'seed' => self::SEED,
            'consumer_bound' => true,
            'render_mode' => 'body',
            'runtime_dynamic' => true,
        ];

        $insert = $this->db->prepare(
            "INSERT INTO ui_content_overrides "
            . "(public_reference, definition_id, "
            . "scope_type, scope_key, module_key, "
            . "scope_reference, scope_path_json, "
            . "locale, title, body, icon_code, "
            . "severity_code, layout_variant, "
            . "visibility_mode, primary_action_code, "
            . "primary_action_label, secondary_action_code, "
            . "secondary_action_label, metadata_json, "
            . "is_active) "
            . "VALUES (?, ?, 'fine', ?, ?, ?, ?, 'fa', "
            . "?, ?, NULL, NULL, NULL, 'show', "
            . "NULL, NULL, NULL, NULL, ?, 1)"
        );

        $insert->execute([
            $reference,
            $definitionId,
            $scopeKey,
            self::MODULE,
            self::SURFACE,
            json_encode(
                $scopePath,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            $item['title'],
            $item['body'],
            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }
}

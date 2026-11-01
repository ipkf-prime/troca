<?php
/** @var array $page */
$safir = is_array($page['safir_campaign'] ?? null) ? $page['safir_campaign'] : [];
$safirSettings = $safir['settings'] ?? [];
$safirCampaign = $safir['campaign'] ?? null;
$safirRows = is_array($safirCampaign['rows'] ?? null) ? $safirCampaign['rows'] : [];
$safirCounts = $safir['counts'] ?? [];
$safirCsrf = (new \IPKF\Security\Csrf())->token();
$safirStatusMessages = [
    'safir_imported' => 'فایل اکسل بررسی شد. اطلاعات را پیش از ارسال کنترل کنید.',
    'safir_settings_saved' => 'تنظیمات قالب ذخیره شد؛ پیش‌نمایش قبلی پاک شد.',
    'safir_reset' => 'کمپین جاری پاک شد.',
    'safir_test_sent' => 'وب‌سرویس، ارسال آزمایشی را با شناسه پیام تأیید کرد.',
    'safir_test_failed' => 'ارسال آزمایشی توسط سرویس رد شد؛ گزارش را بررسی کنید.',
    'safir_test_unknown' => 'وضعیت تست نامشخص است؛ قبل از تکرار، پنل سفیر را بررسی کنید.',
    'safir_batch_completed' => 'دسته ارسال پردازش شد؛ گزارش زیر را بررسی کنید.',
    'safir_send_disabled' => 'ارسال واقعی در تنظیمات سرور غیرفعال است.',
    'safir_key_missing' => 'کلید جدید سرویس در تنظیمات امن سرور ثبت نشده است.',
    'safir_test_allowlist_missing' => 'شماره آزمایشی مجاز در سرور تنظیم نشده است.',
    'safir_no_ready_rows' => 'هیچ گیرنده معتبری برای تست وجود ندارد.',
    'safir_confirmation_required' => 'تأیید صریح عملیات الزامی است.',
    'safir_campaign_missing' => 'ابتدا اکسل مخاطبان را بارگذاری کنید.',
    'safir_template_changed' => 'قالب تغییر کرده است. اکسل را دوباره بارگذاری کنید.',
    'safir_settings_invalid' => 'شناسه بازو، شناسه قالب یا متن قالب معتبر نیست.',
    'safir_invalid_upload' => 'فایل معتبر XLSX با حجم حداکثر ۲ مگابایت انتخاب کنید.',
    'safir_xlsx_extension_missing' => 'افزونه‌های PHP ZIP و SimpleXML روی سرور لازم است.',
    'safir_xlsx_invalid' => 'ساختار فایل اکسل معتبر نیست.',
    'safir_xlsx_too_large' => 'حجم محتوای اکسل بیش از حد مجاز است.',
    'safir_xlsx_too_many_rows' => 'در این نسخه حداکثر ۵۰۰ ردیف قابل بررسی است.',
    'safir_xlsx_sheet_missing' => 'برگه نخست اکسل قابل خواندن نیست.',
    'safir_xlsx_header_missing' => 'چهار عنوان ستون موردنیاز در سطر اول پیدا نشد.',
    'safir_xlsx_empty' => 'اکسل ردیف مخاطب ندارد.',
    'safir_xlsx_formulas_forbidden' => 'فایل دارای فرمول است؛ مقادیر را به متن ساده تبدیل کنید.',
    'safir_storage_unavailable' => 'ذخیره امن تنظیمات امکان‌پذیر نشد.',
    'safir_export_failed' => 'گزارش قابل دریافت نیست.',
    'safir_invalid_csrf' => 'نشست یا توکن امنیتی منقضی شده است. صفحه را تازه‌سازی کنید.',
    'safir_operation_failed' => 'عملیات انجام نشد. گزارش خطا را بررسی کنید.',
];
?>
<section class="provider-management-card" dir="rtl">
    <?php if (isset($safirStatusMessages[$status])): ?>
        <div class="admin-alert <?= in_array($status, ['safir_imported','safir_settings_saved','safir_reset','safir_test_sent','safir_batch_completed'], true) ? 'admin-alert--success' : 'admin-alert--danger' ?>">
            <?= admin_h($safirStatusMessages[$status]) ?>
        </div>
    <?php endif; ?>
    <h3>دعوت‌نامه مدیرعامل‌ها در بله (سفیر)</h3>
    <p class="communication-muted">
        این بخش مستقل از بازوی پیام‌رسان فعلی IPKF است. فایل اکسل را وارد کنید، مخاطبان و متن را بررسی کنید، سپس تست و ارسال را تأیید کنید.
        متن ثابت پیام باید در پنل سفیر بله تأیید شده باشد.
    </p>
    <div class="communication-actions" style="display:flex;flex-wrap:wrap;gap:1rem">
        <span>وضعیت ارسال:
            <strong><?= !empty($safir['send_enabled']) ? 'فعال با مجوز سرور' : 'غیرفعال (حالت امن)' ?></strong>
        </span>
        <span>کلید محرمانه:
            <strong><?= !empty($safir['credential_ready']) ? 'در سرور موجود' : 'تنظیم نشده' ?></strong>
        </span>
        <span>شماره تست:
            <strong dir="ltr"><?= admin_h((string) ($safir['test_phone_masked'] ?? '')) ?></strong>
        </span>
    </div>
</section>
<section class="provider-management-card" dir="rtl">
    <h3>۱. تنظیمات قالب دعوت‌نامه</h3>
    <form method="post" class="communication-form" action="/admin/communications/settings/safir">
        <input type="hidden" name="_token" value="<?= admin_h($safirCsrf) ?>">
        <input type="hidden" name="action" value="save_settings">
        <label><span>شناسه بازو</span><input dir="ltr" required name="bot_id" value="<?= admin_h($safirSettings['bot_id'] ?? '') ?>"></label>
        <label><span>شناسه قالب خدماتی</span><input dir="ltr" required name="template_id" value="<?= admin_h($safirSettings['template_id'] ?? '') ?>"></label>
        <label class="communication-form__wide">
            <span>متن تأییدشده برای پیش‌نمایش (متغیرهای {{name}} و {{sherkat}})</span>
            <textarea name="body" rows="7" style="width:100%" required><?= admin_h($safirSettings['body'] ?? '') ?></textarea>
        </label>
        <div class="communication-actions communication-form__wide">
            <button class="admin-button" type="submit">ذخیره تنظیمات قالب</button>
        </div>
    </form>
    <small class="communication-muted">کلید API در این صفحه دریافت یا نمایش داده نمی‌شود و باید فقط در محیط سرور نگهداری شود. تغییر قالب کمپین فعلی را پاک می‌کند.</small>
</section>
<section class="provider-management-card" dir="rtl">
    <h3>۲. ورود فایل Excel</h3>
    <form class="communication-form" action="/admin/communications/settings/safir" method="post" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= admin_h($safirCsrf) ?>">
        <input type="hidden" name="action" value="import">
        <label class="communication-form__wide">
            <span>فایل XLSX شامل چهار ستون نام، نام خانوادگی، نام شرکت و شماره همراه مدیرعامل</span>
            <input type="file" name="recipients" required accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
        </label>
        <div class="communication-actions communication-form__wide">
            <button class="admin-button" type="submit">بارگذاری و پیش‌نمایش (بدون ارسال)</button>
        </div>
    </form>
</section>
<?php if (is_array($safirCampaign)): ?>
    <section class="provider-management-card" dir="rtl">
        <h3>۳. بررسی مخاطبان و پیام‌ها</h3>
        <p class="communication-muted">
            شناسه کمپین: <code dir="ltr"><?= admin_h($safirCampaign['reference'] ?? '') ?></code>
            <span style="margin-inline-start:1rem">آماده: <b><?= (int) ($safirCounts['ready'] ?? 0) ?></b></span>
            <span>پذیرفته‌شده: <b><?= (int) ($safirCounts['sent'] ?? 0) ?></b></span>
            <span>ناموفق: <b><?= (int) ($safirCounts['failed'] ?? 0) ?></b></span>
            <span>نامشخص: <b><?= (int) ($safirCounts['unknown'] ?? 0) ?></b></span>
            <span>حذف‌شده: <b><?= (int) ($safirCounts['skipped'] ?? 0) ?></b></span>
        </p>
        <div style="max-height:480px;overflow:auto">
            <table class="admin-table" style="width:100%;min-width:850px">
                <thead>
                <tr>
                    <th>ردیف</th><th>مدیرعامل</th><th>شرکت</th><th>شماره همراه</th><th>وضعیت</th><th>خطا / شناسه پیام</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($safirRows as $safirRow): ?>
                    <tr>
                        <td><?= (int) $safirRow['row'] ?></td>
                        <td><?= admin_h($safirRow['name']) ?></td>
                        <td><?= admin_h($safirRow['company']) ?></td>
                        <td dir="ltr"><?= admin_h($safirRow['phone']) ?></td>
                        <td><?= admin_h($safirRow['status']) ?></td>
                        <td><?= admin_h($safirRow['error'] ?: $safirRow['message_id']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
            $preview = null;
            foreach ($safirRows as $candidate) {
                if ($candidate['status'] !== 'skipped') { $preview = $candidate; break; }
            }
        ?>
        <?php if (is_array($preview)): ?>
            <h4>نمونه پیام واقعی از قالب</h4>
            <div style="white-space:pre-wrap;border:1px solid var(--admin-border,#dde2e9);padding:1rem;border-radius:12px;line-height:2">
                <?= admin_h($preview['preview']) ?>
            </div>
        <?php endif; ?>
        <div class="communication-actions" style="display:flex;flex-wrap:wrap;gap:1rem;margin-top:1rem">
            <a class="admin-button" href="/admin/communications/settings/safir/export">دریافت گزارش CSV برای Excel</a>
            <form method="post" action="/admin/communications/settings/safir">
                <input type="hidden" name="_token" value="<?= admin_h($safirCsrf) ?>">
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="admin-button">پاک‌کردن کمپین</button>
            </form>
        </div>
    </section>
    <section class="provider-management-card" dir="rtl">
        <h3>۴. ارسال آزمایشی و ارسال گروهی</h3>
        <?php if (empty($safir['send_enabled'])): ?>
            <p class="communication-muted">ارسال تا فعال‌سازی مجزا و امن در تنظیمات محیط سرور قفل است. بخش واردکردن اکسل و گزارش بدون ارسال کار می‌کند.</p>
        <?php else: ?>
            <p>شماره آزمایشی ثابت و مجاز سرور: <b><?= admin_h($safir['test_phone_masked'] ?? '') ?></b>. پیام با اطلاعات اولین ردیف معتبر ارسال خواهد شد.</p>
            <form method="post" action="/admin/communications/settings/safir" class="communication-form">
                <input type="hidden" name="_token" value="<?= admin_h($safirCsrf) ?>">
                <input type="hidden" name="action" value="test">
                <label class="communication-form__wide"><input type="checkbox" name="confirm_test" value="yes" required> تأیید ارسال یک پیام واقعی آزمایشی به شماره مجاز</label>
                <button class="admin-button" type="submit">ارسال آزمایشی</button>
                <p class="communication-muted">نتیجه تست: <?= admin_h($safirCampaign['test_status'] ?? 'انجام نشده') ?>، شناسه: <?= admin_h($safirCampaign['test_message_id'] ?? '') ?></p>
            </form>
            <?php if (($safirCampaign['test_status'] ?? '') === 'sent' && (int) ($safirCounts['ready'] ?? 0) > 0): ?>
                <form id="safir-dispatch-form" method="post" action="/admin/communications/settings/safir" class="communication-form">
                    <input type="hidden" name="_token" value="<?= admin_h($safirCsrf) ?>">
                    <input type="hidden" name="action" value="dispatch">
                    <input type="hidden" name="reference" value="<?= admin_h($safirCampaign['reference']) ?>">
                    <input type="hidden" name="confirmation" value="<?= admin_h('SEND ' . $safirCampaign['reference']) ?>">
                    <label class="communication-form__wide">
                        <input type="checkbox" name="dispatch_approved" value="yes" required>
                        شماره‌ها و محتوای پیام را بررسی کردم و ارسال گروهی به مخاطبان مجاز را تأیید می‌کنم.
                    </label>
                    <button class="admin-button" type="submit">شروع / ادامه ارسال گروهی</button>
                    <p class="communication-muted" id="safir-progress" aria-live="polite">پیام‌ها در دسته‌های حداکثر ۳تایی، با توقف خودکار هنگام خطا ارسال می‌شوند. خروج از صفحه روند را متوقف می‌کند.</p>
                </form>
                <script>
                (() => {
                    const form = document.getElementById('safir-dispatch-form');
                    if (!form || !window.fetch) return;
                    form.addEventListener('submit', async (event) => {
                        event.preventDefault();
                        if (!form.reportValidity() || !window.confirm('ارسال واقعی به تمام شماره‌های آماده آغاز شود؟')) return;
                        const button = form.querySelector('button[type="submit"]');
                        const progress = document.getElementById('safir-progress');
                        button.disabled = true;
                        let total = 0;
                        try {
                            while (true) {
                                const data = new FormData(form);
                                data.set('ajax', '1');
                                const response = await fetch(form.action, {
                                    method: 'POST',
                                    body: data,
                                    credentials: 'same-origin',
                                    headers: { 'Accept': 'application/json' }
                                });
                                const result = await response.json();
                                if (!response.ok || !result.ok) {
                                    progress.textContent = 'ارسال متوقف شد: ' + (result.code || 'network_error');
                                    break;
                                }
                                total = Number(result.counts.sent || 0);
                                progress.textContent = 'پذیرفته‌شده: ' + total + ' | باقیمانده: ' + result.counts.ready;
                                if (result.halted || !Number(result.counts.ready)) {
                                    if (result.halted) progress.textContent += ' | خطا یا وضعیت نامشخص؛ ارسال متوقف شد.';
                                    break;
                                }
                                await new Promise((resolve) => setTimeout(resolve, 2000));
                            }
                        } catch (_) {
                            progress.textContent = 'ارتباط با سامانه قطع شد؛ وضعیت موارد نامشخص را قبل از ادامه بررسی کنید.';
                        }
                        window.location.reload();
                    });
                })();
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

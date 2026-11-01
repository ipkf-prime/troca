<?php
/** @var array $page */
$safir = is_array($page['safir_campaign'] ?? null) ? $page['safir_campaign'] : [];
$templates = is_array($safir['templates'] ?? null) ? $safir['templates'] : [];
$campaign = $safir['campaign'] ?? null;
$rows = is_array($campaign['rows'] ?? null) ? $campaign['rows'] : [];
$counts = $safir['counts'] ?? [];
$csrf = (new \IPKF\Security\Csrf())->token();
$statusMessages = [
    'safir_template_saved' => 'قالب سفیر ذخیره شد. برای ارسال، قالب باید در پنل سفیر تأیید شده باشد.',
    'safir_template_status_saved' => 'وضعیت قالب تغییر کرد.',
    'safir_imported' => 'اکسل خوانده شد. پیش‌نمایش و وضعیت مخاطبان را بررسی کنید.',
    'safir_reset' => 'کمپین جاری پاک شد.',
    'safir_test_sent' => 'API پیام آزمایشی را پذیرفت؛ دریافت پیام را در بله بررسی کنید.',
    'safir_test_failed' => 'سفیر ارسال آزمایشی را رد کرد.',
    'safir_test_unknown' => 'وضعیت ارسال آزمایشی نامشخص است؛ از ارسال مجدد خودکار خودداری کنید.',
    'safir_batch_completed' => 'یک دسته ارسال پردازش شد؛ گزارش را بررسی کنید.',
    'safir_send_disabled' => 'ارسال واقعی در تنظیمات امن سرور غیرفعال است.',
    'safir_key_missing' => 'کلید API سفیر در تنظیمات سرور وجود ندارد.',
    'safir_test_allowlist_missing' => 'شماره مجاز تست تنظیم نشده است.',
    'safir_confirmation_required' => 'تأیید صریح عملیات الزامی است.',
    'safir_campaign_missing' => 'ابتدا اکسل مخاطبان را بارگذاری کنید.',
    'safir_template_changed' => 'قالب تغییر کرده؛ قبل از ارسال، اکسل را دوباره بررسی کنید.',
    'safir_template_unavailable' => 'قالب غیرفعال است یا تأییدش در سفیر ثبت نشده است.',
    'safir_template_invalid' => 'عنوان، شناسه قالب، بازو، ستون شماره یا متن معتبر نیست.',
    'safir_template_mapping_invalid' => 'نگاشت یکی از متغیرها به ستون اکسل معتبر نیست.',
    'safir_template_variables_invalid' => 'نام متغیرها معتبر نیست. نمونه: {{tracking_code}}',
    'safir_catalog_missing' => 'فهرست اولیه قالب‌ها موجود نیست.',
    'safir_catalog_path_missing' => 'مسیر پایدار ذخیره قالب‌ها در سرور تنظیم نشده است.',
    'safir_storage_unavailable' => 'ذخیره امن قالب‌ها ممکن نشد.',
    'safir_xlsx_mapping_missing' => 'یک یا چند ستون موردنیاز قالب در اکسل پیدا نشد.',
    'safir_xlsx_duplicate_header' => 'عنوان ستون تکراری در ردیف اول اکسل وجود دارد.',
    'safir_xlsx_header_missing' => 'عنوان ستون‌ها در ردیف نخست اکسل پیدا نشد.',
    'safir_xlsx_empty' => 'فایل اکسل مخاطب ندارد.',
    'safir_invalid_upload' => 'فایل XLSX معتبر با حجم مجاز انتخاب کنید.',
    'safir_xlsx_extension_missing' => 'افزونه‌های ZIP و SimpleXML در PHP نیاز است.',
    'safir_xlsx_formulas_forbidden' => 'فرمول در اکسل مجاز نیست؛ مقادیر را به متن تبدیل کنید.',
    'safir_xlsx_too_large' => 'فایل اکسل بیش از سقف مجاز این سرور است.',
    'safir_xlsx_too_many_rows' => 'تعداد ردیف‌های این فایل بیش از سقف ورود است.',
    'safir_operation_failed' => 'عملیات با خطا مواجه شد.',
    'safir_invalid_csrf' => 'نشست منقضی شده؛ صفحه را تازه‌سازی کنید.',
];
$successes = ['safir_template_saved','safir_template_status_saved','safir_imported','safir_reset','safir_test_sent','safir_batch_completed'];
?>
<section class="provider-management-card" dir="rtl">
    <h3>مدیریت قالب‌ها و ارسال گروهی سفیر بله</h3>
    <p class="communication-muted">برای هر نوع پیام، یک قالب مجزا با شناسه تأییدشده سفیر، متن و متغیرهای دلخواه تعریف کنید. نام متغیرهای داخل <code dir="ltr">{{...}}</code> مستقیماً از متن تشخیص داده می‌شوند؛ هیچ کلید متغیری در کد ارسال ثابت نیست.</p>
    <?php if (isset($statusMessages[$status])): ?>
        <div class="admin-alert admin-alert--<?= in_array($status, $successes, true) ? 'success' : 'danger' ?>">
            <?= admin_h($statusMessages[$status]) ?>
        </div>
    <?php endif; ?>
    <div class="communication-actions" style="display:flex;flex-wrap:wrap;gap:1rem">
        <span>ارسال واقعی: <strong><?= !empty($safir['send_enabled']) ? 'فعال با مجوز سرور' : 'غیرفعال (حالت امن)' ?></strong></span>
        <span>کلید سفیر: <strong><?= !empty($safir['credential_ready']) ? 'در سرور تنظیم شده' : 'تنظیم نشده' ?></strong></span>
        <span>شماره تست: <strong><?= admin_h((string) ($safir['test_phone_masked'] ?? '')) ?></strong></span>
    </div>
</section>

<section class="provider-management-card" dir="rtl">
    <h3>۱. مدیریت قالب‌های خدماتی</h3>
    <p class="communication-muted">ابتدا متن و متغیرها را در پنل سفیر بله ثبت و تأیید کنید؛ سپس شناسه قالب و نگاشت متغیرها به اکسل را اینجا تعریف کنید. تغییر متن در IPKF به‌تنهایی قالب مورد تأیید سفیر را عوض نمی‌کند.</p>

    <?php foreach ($templates as $tpl): ?>
        <details style="border:1px solid var(--admin-border,#e1e5ea);border-radius:12px;padding:1rem;margin:0.7rem 0">
            <summary style="cursor:pointer;font-weight:600">
                <?= admin_h($tpl['title'] ?? '') ?>
                <small dir="ltr">[<?= admin_h($tpl['code'] ?? '') ?> — <?= admin_h($tpl['template_id'] ?? '') ?>]</small>
                <small><?= !empty($tpl['active']) ? 'فعال' : 'غیرفعال' ?> / <?= !empty($tpl['provider_approved']) ? 'تأییدشده در سفیر (اعلام مدیر)' : 'تأییدنشده' ?></small>
            </summary>
            <form method="post" action="/admin/communications/settings/safir" class="communication-form" data-safir-template-form>
                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                <input type="hidden" name="action" value="save_template">
                <label><span>کد داخلی قالب (غیرقابل تغییر)</span>
                    <input name="code" readonly dir="ltr" value="<?= admin_h($tpl['code']) ?>"></label>
                <label><span>عنوان نمایشی</span>
                    <input name="title" required value="<?= admin_h($tpl['title']) ?>"></label>
                <label><span>شناسه بازو (Bot ID)</span>
                    <input name="bot_id" required dir="ltr" value="<?= admin_h($tpl['bot_id']) ?>"></label>
                <label><span>شناسه قالب تأییدشده (Template ID)</span>
                    <input name="template_id" required dir="ltr" value="<?= admin_h($tpl['template_id']) ?>"></label>
                <label><span>ستون شماره مقصد در اکسل</span>
                    <input name="phone_column" required value="<?= admin_h($tpl['phone_column']) ?>"></label>
                <label><span>نوع پیام در سفیر</span><input name="message_type" value="<?= admin_h($tpl['message_type'] ?? '') ?>" placeholder="خدماتی"></label>
                <label><span>تعرفه هر پیام (ریال)</span><input name="unit_price_irr" dir="ltr" inputmode="numeric" value="<?= admin_h($tpl['unit_price_irr'] ?? '') ?>"></label>
                <label><span>تاریخ ثبت در پنل سفیر</span><input name="provider_created_at" dir="ltr" value="<?= admin_h($tpl['provider_created_at'] ?? '') ?>"></label>
                <label class="communication-form__wide"><span>متن قالب و متغیرها</span>
                    <textarea rows="7" name="body" required style="width:100%" data-safir-body><?= admin_h($tpl['body']) ?></textarea></label>
                <div class="communication-form__wide" data-safir-mappings>
                    <?php foreach (($tpl['mappings'] ?? []) as $variable => $columnNames): ?>
                        <label><span><code dir="ltr">{{<?= admin_h($variable) ?>}}</code> ← ستون(های) اکسل؛ برای ترکیب با + جدا کنید</span>
                            <input dir="auto" name="mappings[<?= admin_h($variable) ?>]" data-safir-map="<?= admin_h($variable) ?>" value="<?= admin_h(implode(' + ', $columnNames)) ?>"></label>
                    <?php endforeach; ?>
                </div>
                <label class="communication-form__wide">
                    <input type="checkbox" name="provider_approved" value="1" <?= !empty($tpl['provider_approved']) ? 'checked' : '' ?>>
                    تأیید می‌کنم شناسه و متن این قالب در پنل سفیر ثبت و تأیید شده است.
                </label>
                <div class="communication-actions communication-form__wide"><button class="admin-button" type="submit">ذخیره تغییرات قالب</button></div>
            </form>
            <form method="post" action="/admin/communications/settings/safir">
                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                <input type="hidden" name="action" value="toggle_template">
                <input type="hidden" name="code" value="<?= admin_h($tpl['code']) ?>">
                <input type="hidden" name="active" value="<?= !empty($tpl['active']) ? '0' : '1' ?>">
                <button type="submit" class="admin-button"><?= !empty($tpl['active']) ? 'غیرفعال‌کردن قالب' : 'فعال‌کردن قالب' ?></button>
            </form>
        </details>
    <?php endforeach; ?>

    <details style="border:1px solid var(--admin-border,#e1e5ea);border-radius:12px;padding:1rem;margin:0.7rem 0">
        <summary style="cursor:pointer;font-weight:600">+ تعریف قالب جدید</summary>
        <form method="post" action="/admin/communications/settings/safir" class="communication-form" data-safir-template-form>
            <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
            <input type="hidden" name="action" value="save_template">
            <label><span>کد داخلی یکتا (انگلیسی)</span><input name="code" required dir="ltr" placeholder="payment_reminder"></label>
            <label><span>عنوان قالب</span><input name="title" required placeholder="یادآوری پرداخت"></label>
            <label><span>شناسه بازو (Bot ID)</span><input name="bot_id" required dir="ltr"></label>
            <label><span>شناسه قالب تأییدشده در سفیر</span><input name="template_id" required dir="ltr"></label>
            <label><span>نام ستون شماره در اکسل</span><input name="phone_column" required value="شماره همراه"></label>
            <label><span>نوع پیام در سفیر</span><input name="message_type" placeholder="خدماتی"></label>
            <label><span>تعرفه هر پیام (ریال)</span><input name="unit_price_irr" dir="ltr" inputmode="numeric"></label>
            <label><span>تاریخ ثبت در پنل سفیر</span><input name="provider_created_at" dir="ltr" placeholder="۱۴۰۵/۰۸/۰۹"></label>
            <label class="communication-form__wide">
                <span>متن تأییدشده قالب؛ هر متغیر را مانند <code dir="ltr">{{tracking_code}}</code> بنویسید</span>
                <textarea name="body" data-safir-body required rows="6" style="width:100%"></textarea>
            </label>
            <div class="communication-form__wide" data-safir-mappings></div>
            <label class="communication-form__wide">
                <input type="checkbox" name="provider_approved" value="1">
                شناسه و متن این قالب قبلاً توسط سفیر بله تأیید شده است.
            </label>
            <div class="communication-actions communication-form__wide"><button type="submit" class="admin-button">ثبت قالب جدید</button></div>
        </form>
    </details>
</section>

<section class="provider-management-card" dir="rtl">
    <h3>۲. انتخاب قالب و ورود اکسل</h3>
    <form method="post" enctype="multipart/form-data" action="/admin/communications/settings/safir" class="communication-form">
        <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
        <input type="hidden" name="action" value="import">
        <label><span>قالب پیام</span>
            <select name="template_code" required>
                <option value="">یک قالب انتخاب کنید</option>
                <?php foreach ($templates as $tpl): ?>
                    <?php if (empty($tpl['active'])) continue; ?>
                    <option value="<?= admin_h($tpl['code']) ?>"><?= admin_h($tpl['title']) ?>
                        (<?= admin_h($tpl['template_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>فایل مخاطبان</span>
            <input type="file" name="recipients" accept=".xlsx" required>
        </label>
        <p class="communication-muted communication-form__wide">ستون‌های اکسل مطابق نگاشت هر قالب خوانده می‌شوند. نام ستون‌ها باید در ردیف اول باشد؛ داده ناقص، شماره تکراری و فرمول کنترل می‌شود.</p>
        <div class="communication-actions communication-form__wide">
            <button class="admin-button" type="submit">بارگذاری و پیش‌نمایش، بدون ارسال</button>
        </div>
    </form>
</section>

<?php if (is_array($campaign)): ?>
    <section class="provider-management-card" dir="rtl">
        <h3>۳. پیش‌نمایش: <?= admin_h($campaign['template_title'] ?? $campaign['template_code']) ?></h3>
        <p class="communication-muted">شناسه کمپین: <code dir="ltr"><?= admin_h($campaign['reference']) ?></code> |
            آماده: <?= (int) ($counts['ready'] ?? 0) ?> |
            پذیرفته‌شده: <?= (int) ($counts['sent'] ?? 0) ?> |
            ناموفق: <?= (int) ($counts['failed'] ?? 0) ?> |
            نامشخص: <?= (int) ($counts['unknown'] ?? 0) ?> |
            کنارگذاشته‌شده: <?= (int) ($counts['skipped'] ?? 0) ?>
        </p>
        <div style="max-height:440px;overflow:auto">
            <table class="admin-table" style="width:100%;min-width:700px">
                <thead><tr><th>ردیف</th><th>شماره مقصد</th>
                    <?php foreach (($campaign['template_variables'] ?? []) as $var): ?>
                        <th><code dir="ltr"><?= admin_h($var) ?></code></th>
                    <?php endforeach; ?>
                    <th>وضعیت</th><th>پیام سرویس</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $item): ?>
                    <tr><td><?= (int) $item['row'] ?></td><td dir="ltr"><?= admin_h($item['phone']) ?></td>
                        <?php foreach (($campaign['template_variables'] ?? []) as $var): ?>
                            <td><?= admin_h($item['fields'][$var] ?? '') ?></td>
                        <?php endforeach; ?>
                        <td><?= admin_h($item['status']) ?></td>
                        <td><?= admin_h($item['error'] ?: $item['message_id']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php foreach ($rows as $item): if (($item['status'] ?? '') !== 'skipped'): ?>
            <h4>پیش‌نمایش نمونه پیام شخصی‌سازی‌شده</h4>
            <div style="white-space:pre-wrap;border:1px solid var(--admin-border,#dde2e9);padding:1rem;border-radius:12px;line-height:2"><?= admin_h($item['preview']) ?></div>
            <?php break; endif; endforeach; ?>
        <div class="communication-actions" style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:1rem">
            <a class="admin-button" href="/admin/communications/settings/safir/export">دریافت گزارش CSV (Excel)</a>
            <form method="post" action="/admin/communications/settings/safir">
                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="admin-button">پاک‌کردن کمپین</button>
            </form>
        </div>
    </section>
    <section class="provider-management-card" dir="rtl">
        <h3>۴. ارسال آزمایشی و گروهی</h3>
        <?php if (empty($safir['send_enabled'])): ?>
            <p class="communication-muted">ارسال در محیط فعلی قفل است. می‌توانید قالب و اکسل را بررسی کنید ولی درخواست API ارسال نمی‌شود.</p>
        <?php else: ?>
            <p>شماره تست مجاز سرور: <?= admin_h($safir['test_phone_masked']) ?>.</p>
            <form method="post" action="/admin/communications/settings/safir" class="communication-form">
                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                <input type="hidden" name="action" value="test">
                <label class="communication-form__wide"><input type="checkbox" name="confirm_test" value="yes" required>
                    ارسال یک پیام واقعی آزمایشی با قالب انتخاب‌شده به شماره تست را تأیید می‌کنم.
                </label>
                <button class="admin-button" type="submit">تست سفیر بله</button>
                <p class="communication-muted">نتیجه تست: <?= admin_h($campaign['test_status'] ?: 'هنوز انجام نشده') ?></p>
            </form>
            <?php if (($campaign['test_status'] ?? '') === 'sent' && (int) ($counts['ready'] ?? 0) > 0): ?>
                <form id="safir-dispatch-form" method="post" action="/admin/communications/settings/safir" class="communication-form">
                    <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                    <input type="hidden" name="action" value="dispatch">
                    <input type="hidden" name="reference" value="<?= admin_h($campaign['reference']) ?>">
                    <input type="hidden" name="confirmation" value="<?= admin_h('SEND ' . $campaign['reference']) ?>">
                    <label class="communication-form__wide">
                        <input type="checkbox" name="dispatch_approved" value="yes" required>
                        قالب تأییدشده، فهرست گیرندگان و متن‌ها را کنترل کرده‌ام و ارسال واقعی را تأیید می‌کنم.
                    </label>
                    <button type="submit" class="admin-button">شروع / ادامه ارسال گروهی</button>
                    <p id="safir-progress" class="communication-muted" aria-live="polite">ارسال در دسته‌های محدود انجام می‌شود؛ خطا یا پاسخ نامشخص ادامه خودکار را متوقف می‌کند.</p>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
<script>
(() => {
    document.querySelectorAll('[data-safir-template-form]').forEach((form) => {
        const body = form.querySelector('[data-safir-body]');
        const box = form.querySelector('[data-safir-mappings]');
        if (!body || !box) return;
        function synchronize() {
            const found = [...new Set([...body.value.matchAll(/\{\{([A-Za-z][A-Za-z0-9_]*)\}\}/g)].map(x => x[1]))];
            const old = {};
            box.querySelectorAll('input[data-safir-map]').forEach((input) => { old[input.dataset.safirMap] = input.value; });
            box.replaceChildren();
            found.forEach((key) => {
                const label = document.createElement('label');
                const span = document.createElement('span');
                span.textContent = '{{' + key + '}} — نام ستون یا چند ستون با +';
                const input = document.createElement('input');
                input.name = 'mappings[' + key + ']';
                input.dataset.safirMap = key;
                input.required = true;
                input.value = old[key] || '';
                label.append(span, input);
                box.append(label);
            });
        }
        body.addEventListener('input', synchronize);
        synchronize();
    });
    const form = document.getElementById('safir-dispatch-form');
    if (!form || !window.fetch) return;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity() || !confirm('ارسال واقعی به مخاطبان آماده انجام شود؟')) return;
        const button = form.querySelector('button[type=submit]');
        const progress = document.getElementById('safir-progress');
        button.disabled = true;
        try {
            while (true) {
                const data = new FormData(form);
                data.set('ajax', '1');
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    progress.textContent = 'متوقف شد: ' + (result.code || 'network_error');
                    break;
                }
                progress.textContent = 'پذیرفته‌شده: ' + result.counts.sent + ' | باقیمانده: ' + result.counts.ready;
                if (result.halted || !Number(result.counts.ready)) {
                    if (result.halted) progress.textContent += ' | خطا یا پاسخ نامشخص؛ ارسال متوقف شد.';
                    break;
                }
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        } catch (_) {
            progress.textContent = 'ارتباط قطع شده؛ قبل از ادامه، وضعیت ارسال‌ها را بررسی کنید.';
        }
        window.location.reload();
    });
})();
</script>

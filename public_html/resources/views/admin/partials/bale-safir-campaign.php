<?php
/** Safir: dedicated catalog list + tabbed create/edit + existing safe preview. */
$safir = is_array($page['safir_campaign'] ?? null) ? $page['safir_campaign'] : [];
$templates = is_array($safir['templates'] ?? null) ? $safir['templates'] : [];
$campaign = $safir['campaign'] ?? null;
$rows = is_array($campaign['rows'] ?? null) ? $campaign['rows'] : [];
$counts = $safir['counts'] ?? [];
$csrf = (new \IPKF\Security\Csrf())->token();
$status = (string) ($status ?? '');
$mode = (string) ($page['safir_mode'] ?? 'list');
$editTemplate = is_array($safir['edit_template'] ?? null) ? $safir['edit_template'] : null;
if (in_array($mode, ['edit', 'test'], true) && !$editTemplate) { $mode = 'list'; }
if (!in_array($mode, ['create', 'edit', 'test'], true)) { $mode = 'list'; }
$tpl = in_array($mode, ['edit', 'test'], true) ? $editTemplate : [];
$variableRows = [];
foreach ((array) ($tpl['mappings'] ?? []) as $key => $columns) {
    $variableRows[] = [
        'key_en' => (string) $key,
        'label_fa' => (string) ($tpl['variable_labels'][$key] ?? implode(' و ', (array) $columns)),
        'excel_column' => implode(' + ', (array) $columns),
    ];
}
$statusMessages = [
    'safir_template_saved' => 'قالب با موفقیت ذخیره شد.',
    'safir_template_status_saved' => 'وضعیت قالب تغییر کرد.',
    'safir_imported' => 'اکسل خوانده شد؛ پیش‌نمایش و وضعیت مخاطبان را بررسی کنید.',
    'safir_reset' => 'فهرست پیش‌نمایش پاک شد.',
    'safir_manual_test_disabled' => 'ارسال آزمایشی واقعی هنوز در سرور مجاز نشده است. پیش‌نمایش دستی در دسترس است.',
    'safir_test_phone_not_configured' => 'شماره مقصد آزمایشی در تنظیمات امن سرور ثبت نشده یا معتبر نیست.',
    'safir_test_fields_invalid' => 'تمام متغیرهای قالب را با مقدار معتبر وارد کنید.',
    'safir_test_audit_unavailable' => 'دفتر ثبت امن تلاش‌های تست آماده نیست.',
    'safir_test_nonce_invalid' => 'شناسه درخواست تست معتبر نیست؛ صفحه را تازه‌سازی کنید.',
    'safir_test_already_attempted' => 'این درخواست آزمون قبلاً ثبت شده است و برای جلوگیری از ارسال تکراری دوباره ارسال نمی‌شود.',
    'safir_manual_test_sent' => 'ارسال آزمایشی به شماره مجاز توسط سرویس تأیید شد.',
    'safir_manual_test_failed' => 'سرویس ارسال آزمایشی را ناموفق اعلام کرد.',
    'safir_manual_test_unknown' => 'وضعیت ارسال مشخص نیست؛ درخواست را بدون بررسی دوباره تکرار نکنید.',
    'safir_report_too_many_records' => 'تعداد گزارش‌ها از ظرفیت مجاز بیشتر شده است؛ وضعیت ذخیره‌سازی را بررسی کنید.',

    'safir_template_invalid' => 'شناسه بازو، عنوان، کد، شماره ستون یا متن قالب معتبر نیست.',
    'safir_template_mapping_invalid' => 'عنوان، کلید یا نام ستون یکی از متغیرها نامعتبر یا تکراری است.',
    'safir_template_variables_invalid' => 'تعداد یا قالب نام متغیرها معتبر نیست؛ حداکثر ۱۰۰ متغیر.',
    'safir_template_variables_mismatch' => 'کلیدهای متغیرها باید دقیقاً با کلیدهای داخل متن پیام یکسان باشند.',
    'safir_template_unavailable' => 'قالب انتخاب‌شده فعال یا تأییدشده نیست.',
    'safir_xlsx_mapping_missing' => 'ستون‌های تعریف‌شده در نگاشت قالب در اکسل پیدا نشدند.',
    'safir_xlsx_duplicate_header' => 'عنوان ستون تکراری در ردیف نخست اکسل وجود دارد.',
    'safir_xlsx_header_missing' => 'عنوان ستون‌های اکسل در ردیف اول نیست.',
    'safir_xlsx_empty' => 'فایل اکسل مخاطبی ندارد.',
    'safir_invalid_upload' => 'فایل XLSX معتبر با حجم مجاز انتخاب کنید.',
    'safir_xlsx_formulas_forbidden' => 'فرمول در اکسل مجاز نیست؛ سلول‌ها را به مقدار تبدیل کنید.',
    'safir_xlsx_too_large' => 'حجم فایل اکسل بیش از سقف مجاز است.',
    'safir_xlsx_too_many_rows' => 'تعداد ردیف‌های اکسل بیش از سقف مجاز است.',
    'safir_operation_failed' => 'عملیات با خطا مواجه شد.',
    'safir_invalid_csrf' => 'نشست منقضی شده؛ صفحه را تازه‌سازی کنید.',
    'safir_send_disabled' => 'ارسال واقعی توسط سرور غیرفعال شده است.',
    'safir_pilot_list_not_allowed' => 'آزمایش گروهی فقط با ۲ یا ۳ شماره منحصربه‌فرد که دقیقاً در فهرست امن سرور مجاز شده‌اند اجرا می‌شود؛ اکسل را اصلاح و دوباره بارگذاری کنید.',
    'safir_campaign_missing' => 'ابتدا فایل اکسل مخاطبان را وارد کنید.',
    'safir_template_changed' => 'قالب تغییر کرده است؛ اکسل را دوباره بارگذاری کنید.',
    'safir_export_failed' => 'گزارش CSV قابل دریافت نیست.',
];
$successes = ['safir_template_saved','safir_template_status_saved','safir_imported','safir_reset','safir_manual_test_sent'];
?>
<style>
/* Safir UI 2.1 — local, scoped, RTL and responsive; no external assets. */
.safir-shell{direction:rtl;display:grid;gap:14px;color:var(--admin-text,#22363b)}
.safir-shell *{box-sizing:border-box}
.safir-card{min-width:0;padding:20px 22px;background:var(--admin-card-bg,#fff);border:1px solid var(--admin-border,#dfe8e2);border-radius:14px}
.safir-card--summary{padding-bottom:15px}
.safir-head{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.safir-head h2,.safir-head h3,.safir-card h3{margin:0;font-weight:750;line-height:1.6}
.safir-head h2{font-size:1.15rem}
.safir-head h3,.safir-card h3{font-size:1rem}
.safir-caption{margin:5px 0 0;color:var(--admin-muted,#647785);font-size:.82rem;line-height:1.85}
.safir-count{font-size:.78rem;color:var(--admin-muted,#637a86)}
.safir-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.safir-actions form{margin:0;display:inline-flex}
.safir-soft,.safir-primary{appearance:none;display:inline-flex;justify-content:center;align-items:center;gap:6px;min-height:37px;border-radius:9px;padding:7px 13px;font:inherit;font-size:.84rem;font-weight:650;line-height:1.5;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background-color .15s,border-color .15s,box-shadow .15s}
.safir-soft{border:1px solid var(--admin-border,#dbe6df);color:var(--admin-text,#32464d);background:var(--admin-card-bg,#fff)}
.safir-soft:hover{background:#f2f8f4;border-color:#bdd7c7}
.safir-primary{border:1px solid #218654;background:#218654;color:#fff}
.safir-primary:hover{background:#197448;border-color:#197448;color:#fff}
.safir-soft:focus-visible,.safir-primary:focus-visible,.safir-shell input:focus-visible,.safir-shell select:focus-visible,.safir-shell textarea:focus-visible,.safir-form-tabs button:focus-visible{outline:2px solid #258f61;outline-offset:2px}
.safir-soft:disabled,.safir-primary:disabled{opacity:.55;cursor:not-allowed}
.safir-soft--small{min-height:33px;padding:5px 10px;font-size:.8rem}
.safir-soft--muted{color:#647580}
.safir-status{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:13px;padding-top:12px;border-top:1px solid var(--admin-border,#e7eee9)}
.safir-status-item{display:inline-flex;gap:6px;align-items:center;min-height:27px;padding:3px 10px;background:#f7faf8;border:1px solid var(--admin-border,#e2eae5);border-radius:99px;color:var(--admin-muted,#687b83);font-size:.76rem}
.safir-status-item strong{font-weight:700;color:var(--admin-text,#30474c)}
.safir-status-item--off strong{color:#24774f}
.safir-table-scroll{overflow-x:auto;margin-top:11px}
.safir-table{width:100%;min-width:670px;border-collapse:collapse;text-align:right}
.safir-table th,.safir-table td{padding:11px 12px;border-bottom:1px solid var(--admin-border,#e9efeb);vertical-align:middle}
.safir-table th{color:var(--admin-muted,#607481);font-size:.79rem;font-weight:700;background:#f8fbf9}
.safir-table th:first-child{border-radius:0 9px 9px 0}
.safir-table th:last-child{border-radius:9px 0 0 9px}
.safir-table td{font-size:.88rem}
.safir-table td strong{font-weight:700}
.safir-table td[dir="ltr"]{text-align:right;font-variant-numeric:tabular-nums}
.safir-table tr:last-child td{border-bottom:0}
.safir-table td:last-child{min-width:180px}
.safir-badge{display:inline-block;padding:3px 10px;border-radius:99px;background:#e9f5ed;color:#237c4b;font-size:.76rem;font-weight:650}
.safir-badge--off{background:#f1f3f4;color:#61727b}
.safir-upload-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px;margin-top:15px;align-items:end}
.safir-upload-form label,.safir-field{display:grid;gap:7px;min-width:0;color:var(--admin-text,#394b52);font-size:.85rem;font-weight:600}
.safir-field-title{font-size:.82rem;font-weight:650}
.safir-shell select,.safir-fields input:not([type="checkbox"]),.safir-variable-row input,.safir-wide-label textarea{width:100%;min-width:0;min-height:42px;padding:9px 12px;border:1px solid var(--admin-border,#d9e5dd);border-radius:9px;font:inherit;font-weight:450;font-size:.86rem;color:var(--admin-text,#243840);background:var(--admin-card-bg,#fff);box-shadow:none}
.safir-shell input::placeholder,.safir-shell textarea::placeholder{color:#95a4aa}
.safir-shell select{cursor:pointer}
.safir-file-field{position:relative;min-width:0}
.safir-file-shell{display:flex;align-items:center;min-height:42px;gap:12px;padding:5px 7px 5px 12px;border:1px solid var(--admin-border,#d9e5dd);border-radius:9px;background:var(--admin-card-bg,#fff)}
.safir-file-button{display:inline-flex;align-items:center;min-height:30px;padding:4px 12px;border:1px solid #cbded2;border-radius:7px;background:#f3f8f4;color:#2d7252;font-size:.82rem;white-space:nowrap}
.safir-file-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--admin-muted,#788992);font-size:.8rem;min-width:0;direction:rtl}
.safir-file-field:focus-within .safir-file-shell{outline:2px solid #258f61;outline-offset:2px}
.safir-file-input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.safir-upload-footer{grid-column:1/-1;display:flex;justify-content:flex-start;align-items:center;gap:12px;flex-wrap:wrap;margin-top:1px}
.safir-upload-footer .safir-primary{min-height:40px}
.safir-form-tabs{display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--admin-border,#e0eae3);padding-bottom:9px;margin:17px 0 20px}
.safir-form-tabs button{appearance:none;padding:9px 14px;border:1px solid transparent;border-radius:8px;background:transparent;color:var(--admin-muted,#607983);font:inherit;font-size:.84rem;font-weight:650;cursor:pointer}
.safir-form-tabs button:hover{background:#f4f9f6}
.safir-form-tabs button.is-active{background:#eaf5ee;color:#217d50}
.safir-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.safir-fields label,.safir-wide-label{display:grid;gap:7px;font-size:.85rem;font-weight:600}
.safir-fields .safir-full{grid-column:1/-1}
.safir-checkbox{display:inline-flex;align-items:center;gap:9px;color:var(--admin-text,#30434b);font-size:.85rem;font-weight:500;line-height:1.7}
.safir-checkbox input{accent-color:#278e5d;width:16px;height:16px;flex:none}
.safir-variable-table{width:100%;min-width:680px;border-collapse:separate;border-spacing:0 8px}
.safir-variable-table td{padding:3px 5px;vertical-align:middle}
.safir-variable-table th{text-align:right;padding:7px;font-size:.81rem;color:var(--admin-muted,#667d87)}
.safir-variable-table td:last-child{min-width:170px}
.safir-variable-table .safir-actions{flex-wrap:nowrap}
.safir-variable-row input{min-height:40px}
.safir-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}
.safir-form-panel[hidden],.safir-form-footer [hidden]{display:none!important}
.safir-wide-label textarea{min-height:190px;resize:vertical;line-height:2}
.safir-form-footer{border-top:1px solid var(--admin-border,#e0e9e3);padding-top:16px;display:flex;align-items:center;gap:9px;justify-content:flex-end;margin-top:20px}
.safir-shell .admin-alert{margin-top:12px}
@media(max-width:760px){
 .safir-card{padding:15px}
 .safir-head{align-items:flex-start}
 .safir-upload-form,.safir-fields{grid-template-columns:minmax(0,1fr)}
 .safir-upload-footer{grid-column:1}
 .safir-status{gap:6px}
 .safir-table th,.safir-table td{padding:9px 8px}
 .safir-form-tabs button{padding:8px 10px}
}
@media(prefers-reduced-motion:reduce){.safir-soft,.safir-primary{transition:none}}
</style>

<div class="safir-shell">
    <section class="safir-card safir-card--summary">
        <div class="safir-head">
            <div>
                <h2>مدیریت قالب‌های سفیر بله</h2>
                <p class="safir-caption">تعریف قالب، نگاشت ستون‌های اکسل و پیش‌نمایش پیام‌ها</p>
            </div>
            <div class="safir-actions">
                <?php if ($mode === 'list'): ?>
                    <a class="safir-primary" href="/admin/communications/safir?mode=create">+ قالب جدید</a>
                <?php else: ?>
                    <a class="safir-soft" href="/admin/communications/safir">بازگشت به فهرست</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="safir-status">
            <span class="safir-status-item <?= empty($safir['send_enabled']) ? 'safir-status-item--off' : '' ?>">ارسال واقعی: <strong><?= empty($safir['send_enabled']) ? 'غیرفعال' : 'فعال' ?></strong></span>
            <span class="safir-status-item">کلید API: <strong><?= empty($safir['credential_ready']) ? 'تنظیم‌نشده' : 'تنظیم‌شده' ?></strong></span>
            <span class="safir-status-item">شماره تست: <strong><?= admin_h((string) ($safir['test_phone_masked'] ?: 'تنظیم‌نشده')) ?></strong></span>
        </div>
        <?php if (isset($statusMessages[$status])): ?>
            <div class="admin-alert admin-alert--<?= in_array($status, $successes, true) ? 'success' : 'danger' ?>">
                <?= admin_h($statusMessages[$status]) ?>
            </div>
        <?php elseif ($status !== '' && str_starts_with($status, 'safir_')): ?>
            <div class="admin-alert admin-alert--danger">عملیات انجام نشد: <?= admin_h($status) ?></div>
        <?php endif; ?>
    </section>

    <?php if ($mode === 'list'): ?>
        <section class="safir-card">
            <div class="safir-head">
                <h3>فهرست قالب‌های پیام</h3>
                <span class="safir-count"><?= count($templates) ?> قالب</span>
            </div>
            <div class="safir-table-scroll">
                <table class="safir-table">
                    <thead><tr>
                        <th>عنوان قالب</th><th>کد انگلیسی</th><th>شناسه قالب سفیر</th><th>متغیرها</th><th>وضعیت</th><th>عملیات</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($templates as $item): ?>
                        <tr>
                            <td><strong><?= admin_h($item['title'] ?? '') ?></strong></td>
                            <td dir="ltr"><?= admin_h($item['code'] ?? '') ?></td>
                            <td dir="ltr"><?= admin_h($item['template_id'] ?? '') ?></td>
                            <td><?= count((array) ($item['mappings'] ?? [])) ?></td>
                            <td>
                                <span class="safir-badge <?= empty($item['active']) ? 'safir-badge--off' : '' ?>">
                                    <?= empty($item['active']) ? 'غیرفعال' : 'فعال' ?>
                                </span>
                            </td>
                            <td>
                                <div class="safir-actions">
                                    <a class="safir-soft safir-soft--small" href="/admin/communications/safir?edit=<?= rawurlencode((string) $item['code']) ?>">ویرایش</a>
                                    <?php if (!empty($item['active'])): ?>
                                        <a class="safir-soft safir-soft--small" href="/admin/communications/safir/template.xlsx?template_code=<?= rawurlencode((string) $item['code']) ?>">دانلود اکسل خام</a>
                                    <?php endif; ?>
                                    <a class="safir-soft safir-soft--small" href="/admin/communications/safir?test=<?= rawurlencode((string) $item['code']) ?>">تست پیام</a>
                                    <form method="post" action="/admin/communications/settings/safir">
                                        <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                                        <input type="hidden" name="action" value="toggle_template">
                                        <input type="hidden" name="code" value="<?= admin_h($item['code']) ?>">
                                        <input type="hidden" name="active" value="<?= empty($item['active']) ? '1' : '0' ?>">
                                        <button type="submit" class="safir-soft safir-soft--small safir-soft--muted"><?= empty($item['active']) ? 'فعال‌سازی' : 'غیرفعال‌سازی' ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$templates): ?>
                        <tr><td colspan="6">هنوز قالبی ثبت نشده است. «قالب جدید» را انتخاب کنید.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="safir-card">
            <h3>پیش‌نمایش ارسال از فایل اکسل</h3>
            <p class="safir-caption">نام ستون‌های اکسل باید با نگاشت قالب مطابقت داشته باشد. این مرحله هیچ پیامی ارسال نمی‌کند.</p>
            <form method="post" enctype="multipart/form-data" action="/admin/communications/settings/safir" class="safir-upload-form">
                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                <input type="hidden" name="action" value="import">
                <label class="safir-field"><span class="safir-field-title">قالب پیام</span><select name="template_code" data-safir-template-select required>
                    <option value="">قالب را انتخاب کنید</option>
                    <?php foreach ($templates as $item): if (!empty($item['active'])): ?>
                        <option value="<?= admin_h($item['code']) ?>"><?= admin_h($item['title']) ?></option>
                    <?php endif; endforeach; ?>
                </select></label>
                <label class="safir-field safir-file-field"><span class="safir-field-title">فایل اکسل مخاطبان</span><span class="safir-file-shell"><span class="safir-file-button">انتخاب فایل</span><span class="safir-file-name" data-safir-file-name>فایلی انتخاب نشده است</span></span><input class="safir-file-input" data-safir-file-input type="file" name="recipients" accept=".xlsx" required aria-label="انتخاب فایل اکسل مخاطبان"></label>
                <div class="safir-upload-footer">
                    <button class="safir-primary" type="submit">بارگذاری و پیش‌نمایش</button>
                    <a class="safir-soft" data-safir-skeleton-link hidden aria-label="دانلود اکسل خام بر اساس ستون‌های قالب انتخاب‌شده">دریافت فایل خام همین قالب (.xlsx)</a>
                </div>
            </form>
        </section>

    <?php elseif ($mode === 'test'): ?>
        <section class="safir-card">
            <div class="safir-head">
                <div>
                    <h3>تست قالب: <?= admin_h((string) ($tpl['title'] ?? '')) ?></h3>
                    <p class="safir-caption">مقادیر متغیرها را وارد کنید و متن نهایی را پیش‌نمایش بگیرید. مقصد ارسال آزمایشی به‌طور خودکار از تنظیمات امن سرور خوانده می‌شود و قابل تغییر در این فرم نیست.</p>
                </div>
                <span class="safir-badge <?= empty($safir['manual_test_enabled']) ? 'safir-badge--off' : '' ?>">
                    <?= empty($safir['manual_test_enabled']) ? 'ارسال آزمایشی غیرفعال' : 'ارسال آزمایشی مجاز' ?>
                </span>
            </div>
            <form method="post" action="/admin/communications/settings/safir" data-safir-manual-test>
                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                <input type="hidden" name="action" value="manual_test">
                <input type="hidden" name="template_code" value="<?= admin_h((string) ($tpl['code'] ?? '')) ?>">
                <input type="hidden" name="test_nonce" value="<?= bin2hex(random_bytes(16)) ?>">
                <textarea data-safir-test-body hidden readonly><?= admin_h((string) ($tpl['body'] ?? '')) ?></textarea>
                <div class="safir-fields" style="margin-top:16px">
                    <?php foreach ((array) ($tpl['mappings'] ?? []) as $variable => $mappedColumns): ?>
                        <label>
                            <span><?= admin_h((string) ($tpl['variable_labels'][$variable] ?? implode(' و ', (array) $mappedColumns))) ?>
                                <small dir="ltr">(<?= admin_h((string) $variable) ?>)</small>
                            </span>
                            <input type="text" maxlength="500" required
                                name="test_fields[<?= admin_h((string) $variable) ?>]"
                                data-safir-test-value data-safir-test-key="<?= admin_h((string) $variable) ?>"
                                placeholder="مقدار آزمایشی <?= admin_h((string) $variable) ?>">
                        </label>
                    <?php endforeach; ?>
                    <div class="safir-field">
                        <span class="safir-field-title">مقصد ثابت ارسال آزمایشی</span>
                        <div class="safir-card" style="min-height:42px;padding:10px 12px;display:flex;align-items:center;justify-content:space-between;gap:8px">
                            <strong dir="ltr"><?= admin_h((string) ($safir['test_phone_masked'] ?? 'تنظیم‌نشده')) ?></strong>
                            <small class="safir-caption">فقط خواندنی؛ از تنظیمات سرور</small>
                        </div>
                    </div>
                </div>
                <div class="safir-card safir-test-preview" style="margin-top:16px;white-space:pre-wrap;line-height:2" data-safir-test-preview aria-live="polite">برای مشاهده متن نهایی، «پیش‌نمایش پیام» را انتخاب کنید.</div>
                <div class="safir-form-footer">
                    <a class="safir-soft" href="/admin/communications/safir">بازگشت</a>
                    <button class="safir-soft" type="button" data-safir-test-preview-button>پیش‌نمایش پیام</button>
                    <label class="safir-checkbox">
                        <input type="checkbox" name="confirm_manual_test" value="yes" required
                            <?= empty($safir['manual_test_enabled']) ? 'disabled' : '' ?>>
                        ارسال واقعی یک پیام به شماره آزمایشی ثابت سرور را تأیید می‌کنم.
                    </label>
                    <button class="safir-primary" type="submit"
                        <?= empty($safir['manual_test_enabled']) ? 'disabled' : '' ?>>ارسال آزمایشی</button>
                    <a class="safir-soft" href="/admin/communications/safir?edit=<?= rawurlencode((string) ($tpl['code'] ?? '')) ?>&amp;tab=report">گزارش تست‌های این قالب</a>
                </div>
            </form>
            <?php if (empty($safir['manual_test_enabled'])): ?>
                <p class="safir-caption">ارسال آزمایشی واقعی در Production قفل است؛ فعال‌سازی آن فقط با مجوز مستقل و ثبت تلاش‌ها در فضای خصوصی سرور انجام می‌شود. دکمه پیش‌نمایش بدون ارسال کار می‌کند.</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="safir-card">
            <h3><?= $mode === 'edit' ? 'ویرایش قالب' : 'تعریف قالب جدید' ?></h3>
            <p class="safir-caption">مشخصات قالب و متغیرها را در تب‌های زیر تنظیم کنید.</p>
            <form method="post" action="/admin/communications/settings/safir" data-safir-editor>
                <input type="hidden" name="_token" value="<?= admin_h($csrf) ?>">
                <input type="hidden" name="action" value="save_template">
                <div class="safir-form-tabs" role="tablist" aria-label="ویرایش قالب">
                    <button type="button" role="tab" class="is-active" data-safir-tab="identity" aria-selected="true">مشخصات قالب</button>
                    <button type="button" role="tab" data-safir-tab="variables" aria-selected="false">متغیرها</button>
                    <button type="button" role="tab" data-safir-tab="message" aria-selected="false">متن پیام</button>
                    <?php if ($mode === 'edit'): ?>
                        <button type="button" role="tab" data-safir-tab="report" aria-selected="false">گزارش تست‌ها</button>
                    <?php endif; ?>
                </div>
                <div class="safir-form-panel" data-safir-panel="identity" role="tabpanel">
                    <div class="safir-fields">
                        <label><span>کد انگلیسی یکتا</span>
                            <input name="code" required maxlength="63" dir="ltr" pattern="[a-z][a-z0-9._-]{1,63}" value="<?= admin_h($tpl['code'] ?? '') ?>" <?= $mode === 'edit' ? 'readonly' : '' ?> placeholder="invite"></label>
                        <label><span>عنوان فارسی قالب</span>
                            <input name="title" required maxlength="160" value="<?= admin_h($tpl['title'] ?? '') ?>" placeholder="دعوت به عضویت"></label>
                        <label><span>شناسه بازو (Bot ID)</span>
                            <input name="bot_id" required dir="ltr" inputmode="numeric" value="<?= admin_h($tpl['bot_id'] ?? '') ?>"></label>
                        <label><span>شناسه قالب تأییدشده سفیر</span>
                            <input name="template_id" required dir="ltr" value="<?= admin_h($tpl['template_id'] ?? '') ?>"></label>
                        <label><span>نام ستون شماره همراه در اکسل</span>
                            <input name="phone_column" required value="<?= admin_h($tpl['phone_column'] ?? 'شماره همراه') ?>"></label>
                        <label class="safir-full">
                            <span class="safir-checkbox"><input type="checkbox" name="provider_approved" value="1" <?= !empty($tpl['provider_approved']) ? 'checked' : '' ?>> متن و شناسه قالب در پنل سفیر تأیید شده است.</span>
                        </label>
                    </div>
                </div>
                <div class="safir-form-panel" data-safir-panel="variables" role="tabpanel" hidden>
                    <p class="safir-caption">برای هر متغیر عنوان فارسی، کلید انگلیسی و ستون اکسل را مشخص کنید. ترکیب چند ستون با علامت + امکان‌پذیر است.</p>
                    <div class="safir-table-scroll">
                        <table class="safir-variable-table">
                            <thead><tr><th>عنوان فارسی</th><th>کلید انگلیسی</th><th>ستون متناظر اکسل</th><th>عملیات</th></tr></thead>
                            <tbody data-safir-variables>
                                <?php foreach ($variableRows as $i => $variable): ?>
                                    <tr class="safir-variable-row" data-safir-variable>
                                        <td><input required maxlength="120" name="variable_items[<?= $i ?>][label_fa]" value="<?= admin_h($variable['label_fa']) ?>" placeholder="نام کامل"></td>
                                        <td><input required maxlength="64" dir="ltr" pattern="[a-zA-Z_][a-zA-Z0-9_]*" data-safir-key name="variable_items[<?= $i ?>][key_en]" value="<?= admin_h($variable['key_en']) ?>" placeholder="name"></td>
                                        <td><input required maxlength="120" name="variable_items[<?= $i ?>][excel_column]" value="<?= admin_h($variable['excel_column']) ?>" placeholder="نام + نام خانوادگی"></td>
                                        <td><div class="safir-actions">
                                            <button class="safir-soft safir-soft--small" type="button" data-safir-insert>درج در متن</button>
                                            <button class="safir-soft safir-soft--small safir-soft--muted" type="button" data-safir-remove>حذف</button>
                                        </div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="safir-toolbar">
                        <button class="safir-soft" type="button" data-safir-add>+ افزودن متغیر</button>
                        <span class="safir-caption"><span data-safir-count><?= count($variableRows) ?></span> از حداکثر ۱۰۰ متغیر</span>
                    </div>
                </div>
                <div class="safir-form-panel" data-safir-panel="message" role="tabpanel" hidden>
                    <label class="safir-wide-label">
                        <span>متن مورد تأیید سفیر</span>
                        <textarea name="body" data-safir-body required rows="8" maxlength="4000" placeholder="سلام {{name}} ..."><?= admin_h($tpl['body'] ?? '') ?></textarea>
                    </label>
                    <p class="safir-caption">کلیدهای متغیر باید دقیقاً مطابق ردیف‌های تب متغیرها باشند. تغییر متن در IPKF به‌معنی تأیید خودکار آن در سفیر نیست.</p>
                    <div data-safir-sample class="safir-card" style="white-space:pre-wrap;margin-top:10px">پیش‌نمایش متن در این بخش نمایش داده می‌شود.</div>
                </div>
                <?php if ($mode === 'edit'): ?>
                    <div class="safir-form-panel" data-safir-panel="report" role="tabpanel" hidden>
                        <div class="safir-head">
                            <div>
                                <h4>گزارش ارسال‌های آزمایشی همین قالب</h4>
                                <p class="safir-caption">آخرین تلاش‌های ثبت‌شده برای این قالب؛ بدون نمایش کلید API، متن پیام یا شماره کامل</p>
                            </div>
                            <a class="safir-soft" href="/admin/communications/safir/test-report.csv?template_code=<?= rawurlencode((string) ($tpl['code'] ?? '')) ?>">دانلود گزارش CSV همین قالب</a>
                        </div>
                        <div class="safir-table-scroll">
                            <table class="safir-table">
                                <thead><tr>
                                    <th>زمان UTC</th><th>شماره تست</th><th>وضعیت</th><th>کد HTTP</th><th>کد خطا</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ((array) ($safir['manual_test_history'] ?? []) as $report): ?>
                                    <tr>
                                        <td dir="ltr"><?= admin_h((string) $report['time']) ?></td>
                                        <td dir="ltr"><?= admin_h((string) $report['phone_masked']) ?></td>
                                        <td><?= admin_h((string) $report['status']) ?></td>
                                        <td><?= (int) $report['http_code'] ?></td>
                                        <td><?= (int) $report['error_code'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($safir['manual_test_history'])): ?>
                                    <tr><td colspan="5">هنوز تستی برای این قالب ثبت نشده است.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="safir-form-footer">
                    <a class="safir-soft" href="/admin/communications/safir">انصراف</a>
                    <button class="safir-primary" type="submit" data-safir-save-button>ذخیره قالب</button>
                </div>
            </form>
        </section>
    <?php endif; ?>
<?php if (is_array($campaign)): ?>
    <section class="provider-management-card" dir="rtl">
        <h3>پیش‌نمایش: <?= admin_h($campaign['template_title'] ?? $campaign['template_code']) ?></h3>
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
                <button type="submit" class="safir-soft safir-soft--muted">پاک‌کردن پیش‌نمایش</button>
            </form>
        </div>
    </section>
    <section class="provider-management-card" dir="rtl">
        <h3>۴. ارسال آزمایشی و گروهی</h3>
        <?php if (empty($safir['send_enabled'])): ?>
            <p class="communication-muted">ارسال در محیط فعلی قفل است. می‌توانید قالب و اکسل را بررسی کنید ولی درخواست API ارسال نمی‌شود.</p>
        <?php else: ?>
            <?php if (!empty($safir['pilot_mode'])): ?>
                <p class="communication-muted" style="font-weight:700;color:#8a561c">حالت آزمایش گروهی محدود فعال است: حداکثر ۳ شماره منحصربه‌فرد، فقط شماره‌های از پیش تأییدشده در سرور. ارسال گروهی عمومی همچنان غیرفعال است. فهرست اکسل کامل قبل از هر ارسال مجدداً بررسی می‌شود.</p>
            <?php endif; ?>
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
                    <button type="submit" class="admin-button"><?= !empty($safir['pilot_mode']) ? 'شروع تست گروهی محدود (۲ تا ۳ مخاطب)' : 'شروع / ادامه ارسال گروهی' ?></button>
                    <p id="safir-progress" class="communication-muted" aria-live="polite">ارسال در دسته‌های محدود انجام می‌شود؛ خطا یا پاسخ نامشخص ادامه خودکار را متوقف می‌کند.</p>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

</div>
<script>
(() => {
    // The only source for the workbook columns is the selected saved template.
    const templateSelect = document.querySelector('[data-safir-template-select]');
    const skeletonLink = document.querySelector('[data-safir-skeleton-link]');
    if (templateSelect && skeletonLink) {
        const updateSkeletonLink = () => {
            const code = templateSelect.value.trim();
            skeletonLink.hidden = code === '';
            if (code) {
                skeletonLink.href = '/admin/communications/safir/template.xlsx?template_code=' + encodeURIComponent(code);
            } else {
                skeletonLink.removeAttribute('href');
            }
        };
        templateSelect.addEventListener('change', updateSkeletonLink);
        updateSkeletonLink();
    }
    document.querySelectorAll('[data-safir-file-input]').forEach(input => {
        const name = input.closest('.safir-file-field')?.querySelector('[data-safir-file-name]');
        if (!name) return;
        input.addEventListener('change', () => {
            name.textContent = input.files?.[0]?.name || 'فایلی انتخاب نشده است';
        });
    });
    const manual = document.querySelector('[data-safir-manual-test]');
    if (manual) {
        const source = manual.querySelector('[data-safir-test-body]');
        const box = manual.querySelector('[data-safir-test-preview]');
        const preview = () => {
            const fields = new Map();
            let complete = true;
            manual.querySelectorAll('[data-safir-test-value]').forEach(input => {
                const value = input.value.trim();
                if (!value) complete = false;
                fields.set(input.dataset.safirTestKey, value);
            });
            if (!complete) {
                // No fabricated text is ever inserted into the message preview.
                box.textContent = 'برای نمایش متن نهایی، ابتدا تمام متغیرهای قالب را تکمیل کنید.';
                return;
            }
            box.textContent = source.value.replace(/\{\{([A-Za-z_][A-Za-z0-9_]*)\}\}/g,
                (match, key) => fields.has(key) ? fields.get(key) : match);
        };
        manual.querySelector('[data-safir-test-preview-button]').addEventListener('click', preview);
        manual.querySelectorAll('[data-safir-test-value]').forEach(input =>
            input.addEventListener('input', preview));
        preview();
    }
    const form = document.querySelector('[data-safir-editor]');
    if (form) {
        const tabButtons = Array.from(form.querySelectorAll('[data-safir-tab]'));
        const panels = Array.from(form.querySelectorAll('[data-safir-panel]'));
        const items = form.querySelector('[data-safir-variables]');
        const body = form.querySelector('[data-safir-body]');
        const count = form.querySelector('[data-safir-count]');
        const add = form.querySelector('[data-safir-add]');
        const preview = form.querySelector('[data-safir-sample]');
        const saveButton = form.querySelector('[data-safir-save-button]');
        let nextIndex = items.querySelectorAll('[data-safir-variable]').length;
        function selectTab(name) {
            tabButtons.forEach(button => {
                const active = button.dataset.safirTab === name;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach(panel => { panel.hidden = panel.dataset.safirPanel !== name; });
            if (saveButton) saveButton.hidden = name === 'report';
        }
        tabButtons.forEach(button => button.addEventListener('click', () => selectTab(button.dataset.safirTab)));
        const requestedTab = new URLSearchParams(window.location.search).get('tab');
        selectTab(tabButtons.some(button => button.dataset.safirTab === requestedTab) ? requestedTab : 'identity');
        function updateCount() {
            const n = items.querySelectorAll('[data-safir-variable]').length;
            count.textContent = String(n);
            add.disabled = n >= 100;
        }
        function addVariable() {
            if (items.querySelectorAll('[data-safir-variable]').length >= 100) return;
            const index = nextIndex++;
            const tr = document.createElement('tr');
            tr.className = 'safir-variable-row';
            tr.dataset.safirVariable = '';
            const fields = [
                ['label_fa', 'عنوان فارسی', false],
                ['key_en', 'key_en', true],
                ['excel_column', 'نام ستون اکسل', false]
            ];
            fields.forEach(field => {
                const td = document.createElement('td');
                const input = document.createElement('input');
                input.name = 'variable_items[' + index + '][' + field[0] + ']';
                input.placeholder = field[1];
                input.required = true;
                input.maxLength = field[0] === 'key_en' ? 64 : 120;
                if (field[2]) {
                    input.dir = 'ltr';
                    input.pattern = '[a-zA-Z_][a-zA-Z0-9_]*';
                    input.dataset.safirKey = '';
                }
                td.append(input);
                tr.append(td);
            });
            const buttons = document.createElement('td');
            const wrap = document.createElement('div');
            wrap.className = 'safir-actions';
            for (const entry of [['درج در متن', 'safirInsert'], ['حذف', 'safirRemove']]) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'safir-soft';
                button.textContent = entry[0];
                if (entry[1] === 'safirInsert') button.dataset.safirInsert = '';
                else button.dataset.safirRemove = '';
                wrap.append(button);
            }
            buttons.append(wrap);
            tr.append(buttons);
            items.append(tr);
            updateCount();
            tr.querySelector('input').focus();
        }
        add.addEventListener('click', addVariable);
        items.addEventListener('click', event => {
            const button = event.target.closest('button');
            const tr = event.target.closest('[data-safir-variable]');
            if (!button || !tr) return;
            if (button.hasAttribute('data-safir-remove')) {
                tr.remove();
                updateCount();
                return;
            }
            if (button.hasAttribute('data-safir-insert')) {
                const key = (tr.querySelector('[data-safir-key]')?.value || '').trim();
                if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(key)) {
                    selectTab('variables');
                    tr.querySelector('[data-safir-key]')?.focus();
                    return;
                }
                body.value += (body.value && !body.value.endsWith(' ') ? ' ' : '') + '{{' + key + '}}';
                refreshPreview();
                selectTab('message');
                body.focus();
            }
        });
        function refreshPreview() {
            const mapping = new Map();
            items.querySelectorAll('[data-safir-variable]').forEach(row => {
                const key = (row.querySelector('[data-safir-key]')?.value || '').trim();
                const label = row.querySelector('input[name$="[label_fa]"]')?.value || '';
                if (key) mapping.set(key, '[' + label + ']');
            });
            preview.textContent = body.value.replace(/\{\{([A-Za-z_][A-Za-z0-9_]*)\}\}/g, (_, key) => mapping.get(key) || '{{' + key + '}}') || 'متن پیام را وارد کنید.';
        }
        body.addEventListener('input', refreshPreview);
        items.addEventListener('input', refreshPreview);
        refreshPreview();
        updateCount();
        form.addEventListener('invalid', event => {
            const panel = event.target.closest('[data-safir-panel]');
            if (panel) selectTab(panel.dataset.safirPanel);
        }, true);
        form.addEventListener('submit', event => {
            const rows = Array.from(items.querySelectorAll('[data-safir-variable]'));
            if (rows.length < 1 || rows.length > 100) {
                event.preventDefault();
                selectTab('variables');
                alert('برای قالب باید بین ۱ تا ۱۰۰ متغیر تعریف شود.');
                return;
            }
            const keys = rows.map(row => (row.querySelector('[data-safir-key]')?.value || '').trim());
            const unique = new Set(keys);
            const textKeys = new Set(Array.from(body.value.matchAll(/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/g), match => match[1]));
            if (unique.size !== keys.length || textKeys.size !== unique.size || keys.some(key => !textKeys.has(key))) {
                event.preventDefault();
                selectTab('variables');
                alert('کلیدهای هر ردیف باید یکتا و عیناً در متن قالب استفاده شده باشند.');
                return;
            }
            const invalid = Array.from(form.querySelectorAll('[required]')).find(input => !input.checkValidity());
            if (invalid) {
                event.preventDefault();
                const panel = invalid.closest('[data-safir-panel]');
                if (panel) selectTab(panel.dataset.safirPanel);
                invalid.reportValidity();
            }
        });
    }
    const dispatchForm = document.getElementById('safir-dispatch-form');
    if (!dispatchForm || !window.fetch) return;
    dispatchForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!dispatchForm.reportValidity() || !confirm('ارسال واقعی به مخاطبان آماده انجام شود؟')) return;
        const button = dispatchForm.querySelector('button[type=submit]');
        const progress = document.getElementById('safir-progress');
        button.disabled = true;
        try {
            while (true) {
                const data = new FormData(dispatchForm);
                data.set('ajax', '1');
                const response = await fetch(dispatchForm.action, {
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

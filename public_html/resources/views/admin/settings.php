<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}
$registry = $registry ?? ['available' => false, 'items' => [], 'catalog' => []];
$status = (string) ($status ?? '');
$catalog = $registry['catalog'] ?? [];
$catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
$registeredJson = json_encode($registry['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb"><a href="/admin/dashboard">داشبورد</a><span>/</span><span>تنظیمات ماژول‌ها</span></nav>

<?php if ($status === 'saved'): ?><div class="admin-alert admin-alert--success">تنظیمات ماژول ذخیره شد.</div><?php endif; ?>
<?php if ($status === 'invalid'): ?><div class="admin-alert admin-alert--danger"><?= admin_h((string) ($error ?? 'اطلاعات معتبر نیست.')) ?></div><?php endif; ?>
<?php if (!$registry['available']): ?><div class="admin-alert admin-alert--warning">جدول رجیستری موجود نیست؛ Migration را اجرا کنید.</div><?php endif; ?>

<section class="admin-section">
    <div class="admin-panel-heading">
        <div>
            <h3>زیرساخت عمومی فایل</h3>
            <p>مدیریت مسیر پیوست‌های خصوصی و موتور بررسی بدافزار برای ماژول‌های سامانه</p>
        </div>
        <a
            class="admin-button admin-button--soft"
            href="/admin/settings/file-infrastructure"
        >
            تنظیمات فایل و آنتی‌ویروس
        </a>
    </div>
</section>

<section class="admin-section admin-tab-workspace">
    <nav class="admin-tabs" data-admin-tabs role="tablist" aria-label="تنظیمات ماژول">
        <button class="admin-tab is-active" type="button" data-admin-tab="general">عمومی</button>
        <button class="admin-tab" type="button" data-admin-tab="access">دامنه و ورود</button>
        <button class="admin-tab" type="button" data-admin-tab="runtime">نمایش و دسترسی</button>
        <button class="admin-tab" type="button" data-admin-tab="database">دیتابیس</button>
        <button class="admin-tab" type="button" data-admin-tab="registered">ماژول‌های ثبت‌شده <small><?= admin_h(\App\Support\AdminFormat::digits(count($registry['items'] ?? []))) ?></small></button>
    </nav>

    <div class="admin-module-context" data-module-context>
        <span>ماژول جاری</span>
        <strong data-module-context-name>یک ماژول انتخاب کنید</strong>
        <code dir="ltr" data-module-context-key>—</code>
    </div>

    <form method="post" action="/admin/settings/modules" data-module-registry-form>
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">

        <section class="admin-tab-panel is-active" data-admin-tab-panel="general">
            <div class="admin-panel-heading"><div><h3>انتخاب ماژول</h3><p>ماژول نصب‌شده را انتخاب کنید؛ مقادیر پایه خودکار تکمیل می‌شوند.</p></div></div>
            <div class="admin-form-grid">
                <label><span>ماژول</span><select name="catalog_key" required data-module-select><option value="">انتخاب ماژول</option><?php foreach ($catalog as $key => $module): ?><option value="<?= admin_h($key) ?>"><?= admin_h($module['name']) ?></option><?php endforeach; ?><option value="custom">ماژول سفارشی</option></select></label>
                <label><span>نام نمایشی</span><input name="display_name" required data-module-field="name"></label>
                <label><span>کلید ماژول</span><input name="module_key" required readonly dir="ltr" pattern="[a-z][a-z0-9_-]{1,99}" data-module-field="key"></label>
                <label class="admin-field--compact"><span>ترتیب نمایش</span><input type="number" name="sort_order" value="10" min="0"></label>
                <label class="admin-check-field admin-module-toggle"><input type="checkbox" name="is_active" value="1" checked><span>ماژول فعال باشد</span></label>
            </div>
        </section>

        <section class="admin-tab-panel" data-admin-tab-panel="access" hidden>
            <div class="admin-panel-heading"><div><h3>دامنه و ورود یکپارچه</h3><p>نشانی اجرای ماژول و مسیر بازگشت احراز هویت</p></div></div>
            <div class="admin-form-grid">
                <label><span>آدرس ماژول</span><input type="url" name="base_url" required dir="ltr" placeholder="https://module-dev.troca.ir" data-module-field="base_url"></label>
                <label><span>Callback ورود یکپارچه</span><input type="url" name="sso_callback_url" dir="ltr" placeholder="https://module-dev.troca.ir/auth/module-sso/callback" data-module-field="callback_url"></label>
            </div>
        </section>

        <section class="admin-tab-panel" data-admin-tab-panel="runtime" hidden>
            <div class="admin-panel-heading">
                <div>
                    <h3>نمایش، مسیر و دسترسی</h3>
                    <p>
                        تنظیمات کارت داشبورد، سایدبار و مجوز پایه ماژول
                        از این بخش کنترل می‌شود.
                    </p>
                </div>
            </div>

            <div class="admin-form-grid admin-module-runtime-grid">

                <label>
                    <span>مسیر اصلی ماژول</span>
                    <input
                        name="route_path"
                        dir="ltr"
                        placeholder="/admin/module"
                        data-module-field="route_path"
                    >
                </label>

                <label>
                    <span>کلید دسترسی پایه</span>
                    <input
                        name="permission_key"
                        dir="ltr"
                        placeholder="module.view"
                        data-module-field="permission_key"
                    >
                </label>

                <label>
                    <span>آیکن</span>
                    <input
                        name="icon_code"
                        dir="ltr"
                        value="apps"
                        data-module-field="icon_code"
                    >
                </label>

                <label class="admin-module-color-field">
                    <span>رنگ کارت داشبورد</span>

                    <div class="admin-module-color-control">
                        <input
                            type="color"
                            name="color_code"
                            value="#2563eb"
                            aria-label="انتخاب رنگ کارت"
                            data-module-field="color_code"
                            data-module-color-picker
                        >

                        <input
                            type="text"
                            value="#2563EB"
                            maxlength="7"
                            dir="ltr"
                            spellcheck="false"
                            autocomplete="off"
                            aria-label="کد HEX رنگ کارت"
                            data-module-color-hex
                        >
                    </div>

                    <small class="admin-field-help">
                        رنگ انتخاب‌شده مستقیماً روی کارت داشبورد اعمال می‌شود.
                    </small>
                </label>

                <label class="admin-module-runtime-description">
                    <span>توضیح کارت داشبورد</span>
                    <textarea
                        name="dashboard_description"
                        rows="3"
                        data-module-field="dashboard_description"
                    ></textarea>
                </label>

                <div class="admin-module-runtime-toggles">

                    <label class="admin-check-field admin-module-toggle">
                        <input
                            type="checkbox"
                            name="dashboard_enabled"
                            value="1"
                            checked
                            data-module-dashboard-enabled
                        >
                        <span>نمایش در داشبورد</span>
                    </label>

                    <label class="admin-check-field admin-module-toggle">
                        <input
                            type="checkbox"
                            name="sidebar_enabled"
                            value="1"
                            checked
                            data-module-sidebar-enabled
                        >
                        <span>نمایش در سایدبار</span>
                    </label>

                </div>

            </div>
        </section>

        <section class="admin-tab-panel" data-admin-tab-panel="database" hidden>
            <div class="admin-panel-heading"><div><h3>اتصال دیتابیس</h3><p>مشخصات فنی اینجا ثبت می‌شود؛ مقدار واقعی رمز فقط در ENV باقی می‌ماند.</p></div></div>
            <div class="admin-form-grid admin-module-database-grid">
                <label><span>نام اتصال</span><input name="database_connection_name" dir="ltr" data-module-field="connection"></label>
                <label><span>میزبان</span><input name="database_host" value="localhost" dir="ltr"></label>
                <label><span>پورت</span><input type="number" name="database_port" value="3306" min="1" max="65535"></label>
                <label><span>نام دیتابیس</span><input name="database_name" dir="ltr" data-module-field="database"></label>
                <label><span>نام کاربری دیتابیس</span><input name="database_username" dir="ltr" autocomplete="off" data-module-field="username"></label>
                <label><span>Charset</span><select name="database_charset" dir="ltr" data-module-field="charset"><option value="utf8mb4">utf8mb4</option></select></label>
                <label><span>SSL Mode</span><input name="database_ssl_mode" dir="ltr" placeholder="اختیاری" data-module-field="ssl_mode"></label>
                <label><span>Timeout (ثانیه)</span><input type="number" name="connection_timeout" value="5" min="1" max="60" data-module-field="timeout"></label>
                <label><span>حالت اجرا</span><select name="runtime_mode" dir="ltr" data-module-field="runtime_mode"><option value="fallback">fallback</option><option value="provisioning">provisioning</option><option value="dedicated">dedicated</option></select></label>
                <label><span>کلید رمز در ENV</span><input type="text" readonly dir="ltr" data-module-field="secret" tabindex="-1"></label>
                <label><span>رمز جدید دیتابیس</span><input type="password" name="database_password" dir="ltr" autocomplete="new-password" data-module-password placeholder="برای حفظ رمز فعلی خالی بگذارید"></label>
                <label><span>وضعیت رمز در ENV</span><input type="text" value="تنظیم نشده" readonly dir="ltr" class="admin-secret-status" data-module-secret-status></label>
            </div>
        </section>

        <section class="admin-tab-panel" data-admin-tab-panel="registered" hidden>
            <div class="admin-panel-heading"><div><h3>ماژول‌های ثبت‌شده</h3><p>فهرست اتصال‌های فعال و غیرفعال سامانه</p></div></div>
            <div class="admin-record-table-wrap"><table class="admin-table admin-record-table"><thead><tr><th>ماژول</th><th>آدرس</th><th>اتصال</th><th>وضعیت</th></tr></thead><tbody>
            <?php foreach ($registry['items'] as $item): ?><tr><td data-label="ماژول"><strong><?= admin_h($item['display_name']) ?></strong><small dir="ltr"><?= admin_h($item['module_key']) ?></small></td><td data-label="آدرس" dir="ltr"><?= admin_h($item['base_url']) ?></td><td data-label="اتصال" dir="ltr"><?= admin_h($item['database_connection_name'] ?? '—') ?></td><td data-label="وضعیت"><span class="admin-status-badge admin-status-badge--<?= (int) $item['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int) $item['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></span></td></tr><?php endforeach; ?>
            <?php if (($registry['items'] ?? []) === []): ?><tr><td colspan="4"><div class="admin-empty-state admin-empty-state--compact">هنوز ماژولی ثبت نشده است.</div></td></tr><?php endif; ?>
            </tbody></table></div>
        </section>

        <div class="admin-form-actions" data-module-save-actions hidden><button class="admin-button" type="submit" <?= !$registry['available'] ? 'disabled' : '' ?>>ذخیره تنظیمات</button></div>
    </form>
</section>

<script type="application/json" id="module-catalog-data"><?= $catalogJson ?></script>
<script type="application/json" id="registered-modules-data"><?= $registeredJson ?></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-module-registry-form]');
    const select = document.querySelector('[data-module-select]');
    if (!form || !select) return;
    const catalog = JSON.parse(document.getElementById('module-catalog-data')?.textContent || '{}');
    const registeredItems = JSON.parse(document.getElementById('registered-modules-data')?.textContent || '[]');
    const registered = Object.fromEntries(registeredItems.map((item) => [item.module_key, item]));
    const context = document.querySelector('[data-module-context]');
    const contextName = document.querySelector('[data-module-context-name]');
    const contextKey = document.querySelector('[data-module-context-key]');
    const actions = document.querySelector('[data-module-save-actions]');
    const nameInput = form.querySelector('[data-module-field="name"]');
    const keyInput = form.querySelector('[data-module-field="key"]');
    const secretStatusInput = form.querySelector('[data-module-secret-status]');
    const passwordInput = form.querySelector('[data-module-password]');
    const colorPicker = form.querySelector('[data-module-color-picker]');
    const colorHexInput = form.querySelector('[data-module-color-hex]');

    const legacyModuleColors = {
        blue: '#2563eb',
        teal: '#0f766e',
        cyan: '#0891b2',
        purple: '#7c3aed',
        violet: '#6d28d9',
        fuchsia: '#c026d3',
        indigo: '#4f46e5',
        amber: '#d97706',
        orange: '#f97316',
        rose: '#e11d48',
        green: '#16a34a'
    };

    const normalizeModuleColor = function (value) {
        const raw = String(value || '').trim().toLowerCase();

        if (/^#[0-9a-f]{6}$/.test(raw)) {
            return raw;
        }

        return legacyModuleColors[raw] || '#2563eb';
    };

    const syncModuleColorText = function () {
        if (!colorPicker || !colorHexInput) return;

        colorHexInput.value =
            String(colorPicker.value || '#2563eb').toUpperCase();
    };

    const applyModuleColorText = function () {
        if (!colorPicker || !colorHexInput) return;

        const raw = String(colorHexInput.value || '').trim();

        if (/^#[0-9A-Fa-f]{6}$/.test(raw)) {
            colorPicker.value = raw.toLowerCase();
            colorHexInput.value = raw.toUpperCase();
            return;
        }

        colorHexInput.value =
            String(colorPicker.value || '#2563eb').toUpperCase();
    };
    const refreshContext = function () {
        const selected = select.value !== '';
        if (actions) actions.hidden = !selected;
        if (contextName) contextName.textContent = selected ? (nameInput?.value || 'ماژول سفارشی') : 'یک ماژول انتخاب کنید';
        if (contextKey) contextKey.textContent = selected ? (keyInput?.value || 'custom') : '—';
    };
    const loadSelectedModule = function () {
        const key = select.value;

        if (keyInput) {
            keyInput.readOnly =
                key !== 'custom';
        }

        const module = catalog[key] || {};
        const saved = registered[key] || {};
        const values = {
            key: key === 'custom' ? '' : key,
            name: saved.display_name || module.name || '',
            base_url: saved.base_url || module.base_url || '',
            callback_url: saved.sso_callback_url || module.callback_url || '',
            connection: saved.database_connection_name || module.connection || '',
            database: saved.database_name || module.database || '',
            username: saved.database_username || module.username || '',
            charset: saved.database_charset || module.charset || 'utf8mb4',
            ssl_mode: saved.database_ssl_mode || module.ssl_mode || '',
            timeout: saved.connection_timeout || module.timeout || 5,
            runtime_mode: saved.runtime_mode || module.runtime_mode || 'fallback',

            route_path:
                saved.route_path
                || module.route_path
                || (
                    key && key !== 'custom'
                        ? '/admin/' + key
                        : ''
                ),

            permission_key:
                saved.permission_key
                || module.permission_key
                || (
                    key && key !== 'custom'
                        ? key + '.view'
                        : ''
                ),

            icon_code:
                saved.icon_code
                || module.icon_code
                || 'apps',

            color_code:
                normalizeModuleColor(
                    saved.color_code
                    || module.color_code
                    || '#2563eb'
                ),

            dashboard_description:
                saved.dashboard_description
                || module.dashboard_description
                || '',

            secret: module.secret || ''
        };
        Object.entries(values).forEach(([field, value]) => { const input = form.querySelector('[data-module-field="' + field + '"]'); if (input) input.value = value; });
        syncModuleColorText();
        const hostInput = form.querySelector('[name="database_host"]');
        const portInput = form.querySelector('[name="database_port"]');
        const orderInput = form.querySelector('[name="sort_order"]');
        const activeInput = form.querySelector('[name="is_active"]');
        if (hostInput) hostInput.value = saved.database_host || module.host || 'localhost';
        if (portInput) portInput.value = saved.database_port || module.port || 3306;
        if (orderInput) orderInput.value = saved.sort_order ?? 10;
        if (activeInput) activeInput.checked = !saved.module_key || Number(saved.is_active) === 1;

        const dashboardEnabled =
            form.querySelector(
                '[data-module-dashboard-enabled]'
            );

        const sidebarEnabled =
            form.querySelector(
                '[data-module-sidebar-enabled]'
            );

        if (dashboardEnabled) {
            dashboardEnabled.checked =
                !saved.module_key
                || Number(
                    saved.dashboard_enabled ?? 1
                ) === 1;
        }

        if (sidebarEnabled) {
            sidebarEnabled.checked =
                !saved.module_key
                || Number(
                    saved.sidebar_enabled ?? 1
                ) === 1;
        }

        if (passwordInput) passwordInput.value = '';
        if (secretStatusInput) {
            secretStatusInput.value = module.secret_configured ? '********' : 'تنظیم نشده';
            secretStatusInput.classList.toggle('is-configured', Boolean(module.secret_configured));
        }
        refreshContext();
    };
    colorPicker?.addEventListener(
        'input',
        syncModuleColorText
    );

    colorHexInput?.addEventListener(
        'change',
        applyModuleColorText
    );

    colorHexInput?.addEventListener(
        'blur',
        applyModuleColorText
    );

    select.addEventListener('change', loadSelectedModule);
    nameInput?.addEventListener('input', refreshContext);
    keyInput?.addEventListener('input', refreshContext);
    document.querySelectorAll('[data-admin-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            if (actions) actions.hidden = tab.getAttribute('data-admin-tab') === 'registered' || select.value === '';
        });
    });
    const firstRegisteredKey = registeredItems[0]?.module_key || '';
    if (firstRegisteredKey && select.querySelector('option[value="' + CSS.escape(firstRegisteredKey) + '"]')) {
        select.value = firstRegisteredKey;
        loadSelectedModule();
    }
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';

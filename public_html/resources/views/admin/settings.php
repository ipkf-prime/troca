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

<section class="admin-section admin-tab-workspace">
    <nav class="admin-tabs" data-admin-tabs role="tablist" aria-label="تنظیمات ماژول">
        <button class="admin-tab is-active" type="button" data-admin-tab="general">عمومی</button>
        <button class="admin-tab" type="button" data-admin-tab="access">دامنه و ورود</button>
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
                <label><span>کلید ماژول</span><input name="module_key" required dir="ltr" pattern="[a-z][a-z0-9_-]{1,99}" data-module-field="key"></label>
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

        <section class="admin-tab-panel" data-admin-tab-panel="database" hidden>
            <div class="admin-panel-heading"><div><h3>اتصال دیتابیس</h3><p>مشخصات اتصال ثبت می‌شود؛ رمز عبور فقط از Secret خوانده خواهد شد.</p></div></div>
            <div class="admin-form-grid">
                <label><span>نام اتصال</span><input name="database_connection_name" dir="ltr" data-module-field="connection"></label>
                <label><span>میزبان</span><input name="database_host" value="localhost" dir="ltr"></label>
                <label class="admin-field--compact"><span>پورت</span><input type="number" name="database_port" value="3306" min="1" max="65535"></label>
                <label><span>نام دیتابیس</span><input name="database_name" dir="ltr" data-module-field="database"></label>
                <label><span>Secret Reference</span><input name="secret_reference" dir="ltr" data-module-field="secret"></label>
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
    const refreshContext = function () {
        const selected = select.value !== '';
        if (actions) actions.hidden = !selected;
        if (contextName) contextName.textContent = selected ? (nameInput?.value || 'ماژول سفارشی') : 'یک ماژول انتخاب کنید';
        if (contextKey) contextKey.textContent = selected ? (keyInput?.value || 'custom') : '—';
    };
    const loadSelectedModule = function () {
        const key = select.value;
        const module = catalog[key] || {};
        const saved = registered[key] || {};
        const values = {
            key: key === 'custom' ? '' : key,
            name: saved.display_name || module.name || '',
            base_url: saved.base_url || module.base_url || '',
            callback_url: saved.sso_callback_url || module.callback_url || '',
            connection: saved.database_connection_name || module.connection || '',
            database: saved.database_name || module.database || '',
            secret: saved.secret_reference || module.secret || ''
        };
        Object.entries(values).forEach(([field, value]) => { const input = form.querySelector('[data-module-field="' + field + '"]'); if (input) input.value = value; });
        const hostInput = form.querySelector('[name="database_host"]');
        const portInput = form.querySelector('[name="database_port"]');
        const orderInput = form.querySelector('[name="sort_order"]');
        const activeInput = form.querySelector('[name="is_active"]');
        if (hostInput) hostInput.value = saved.database_host || 'localhost';
        if (portInput) portInput.value = saved.database_port || 3306;
        if (orderInput) orderInput.value = saved.sort_order ?? 10;
        if (activeInput) activeInput.checked = !saved.module_key || Number(saved.is_active) === 1;
        refreshContext();
    };
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

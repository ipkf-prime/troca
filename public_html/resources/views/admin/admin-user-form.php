<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$page = $page ?? [];
$form = $page['form'] ?? [];
$roles = $page['roles'] ?? [];
$roleKinds = $page['role_kinds'] ?? [];
$roleAreas = $page['role_areas'] ?? [];
$statusOptions = $page['status_options'] ?? [];
$errors = $errors ?? [];
$isEdit = !empty($page['is_edit']);
$userId = (int) ($form['id'] ?? 0);
$formAction = $isEdit ? '/admin/users/' . $userId : '/admin/users';
$selectedRoleIds = array_map('intval', is_array($form['role_ids'] ?? null) ? $form['role_ids'] : []);
$status = (string) ($status ?? '');
$requestedTab = trim((string) ($_GET['tab'] ?? ''));
$activeTab = in_array($requestedTab, ['account', 'access'], true) ? $requestedTab : 'account';

ob_start();
?>
<style>
.user-editor{display:grid;gap:.7rem}.user-editor__head{align-items:center;background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:.9rem;display:flex;justify-content:space-between;min-height:4rem;padding:.65rem .8rem}.user-editor__title{align-items:center;display:flex;gap:.6rem}.user-editor__icon{align-items:center;background:var(--admin-primary-soft);border-radius:.65rem;color:var(--admin-primary);display:inline-flex;height:2.45rem;justify-content:center;width:2.45rem}.user-editor__head h2{font-size:1.02rem;margin:0}.user-editor__head p{color:var(--admin-text-muted);font-size:.72rem;margin:.1rem 0 0}.user-editor__tabs{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:.78rem;display:flex;gap:.25rem;padding:.28rem}.user-editor__tab{appearance:none;background:transparent;border:0;border-radius:.58rem;color:var(--admin-text-muted);cursor:pointer;font:inherit;font-size:.78rem;font-weight:800;min-height:2.3rem;padding:.35rem .8rem}.user-editor__tab[aria-selected=true]{background:var(--admin-primary-soft);color:var(--admin-primary)}.user-editor__panel{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:.9rem;padding:.8rem}.user-editor__panel[hidden]{display:none}.user-block+.user-block{border-top:1px solid var(--admin-border);margin-top:.8rem;padding-top:.8rem}.user-block__head{margin-bottom:.55rem}.user-block__head h3{font-size:.84rem;margin:0}.user-block__head p{color:var(--admin-text-muted);font-size:.68rem;margin:.08rem 0 0}.user-grid{display:grid;gap:.6rem;grid-template-columns:repeat(2,minmax(0,1fr))}.user-grid--3{grid-template-columns:repeat(3,minmax(0,1fr))}.user-field{min-width:0}.user-field--wide{grid-column:1/-1}.user-field>span{display:block;font-size:.72rem;font-weight:800;margin-bottom:.22rem}.user-field>small{color:var(--admin-text-muted);display:block;font-size:.63rem;margin-top:.18rem}.user-field input,.user-field select{min-height:2.35rem}.user-checks{display:flex;flex-wrap:wrap;gap:.45rem}.user-check{align-items:center;background:var(--admin-surface-muted);border:1px solid var(--admin-border);border-radius:.62rem;display:flex;font-size:.7rem;gap:.35rem;min-height:2.25rem;padding:.3rem .55rem}.user-check input{height:1rem;margin:0;width:1rem}.access-tools{align-items:end;display:grid;gap:.5rem;grid-template-columns:repeat(3,minmax(0,1fr))}.access-summary{align-items:center;background:var(--admin-primary-soft);border:1px solid color-mix(in srgb,var(--admin-primary) 24%,transparent);border-radius:.66rem;display:flex;flex-wrap:wrap;gap:.4rem;justify-content:space-between;margin-top:.6rem;min-height:2.45rem;padding:.4rem .6rem}.access-summary__chips{display:flex;flex-wrap:wrap;gap:.28rem}.access-chip{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:999px;font-size:.64rem;font-weight:800;padding:.22rem .45rem}.role-list{border:1px solid var(--admin-border);border-radius:.7rem;margin-top:.6rem;max-height:22rem;overflow:auto}.role-row{align-items:center;border-top:1px solid var(--admin-border);cursor:pointer;display:grid;gap:.5rem;grid-template-columns:1.1rem minmax(9rem,1.2fr) minmax(7rem,.7fr) minmax(7rem,.7fr);min-height:2.9rem;padding:.4rem .6rem}.role-row:first-child{border-top:0}.role-row:hover{background:var(--admin-surface-muted)}.role-row.is-selected{background:color-mix(in srgb,var(--admin-primary-soft) 70%,var(--admin-surface))}.role-row[hidden]{display:none}.role-row input{height:1rem;margin:0;width:1rem}.role-row strong,.role-row small{display:block}.role-row strong{font-size:.74rem}.role-row small{color:var(--admin-text-muted);direction:ltr;font-size:.61rem}.role-row__meta{color:var(--admin-text-muted);font-size:.66rem}.role-empty{color:var(--admin-text-muted);display:none;font-size:.72rem;padding:1rem;text-align:center}.role-empty.is-visible{display:block}.permission-next{align-items:center;background:var(--admin-surface-muted);border:1px dashed var(--admin-border);border-radius:.7rem;display:flex;gap:.7rem;justify-content:space-between;margin-top:.65rem;padding:.55rem .65rem}.permission-next strong{font-size:.72rem}.permission-next small{color:var(--admin-text-muted);display:block;font-size:.64rem;margin-top:.1rem}.user-actions{background:color-mix(in srgb,var(--admin-surface) 93%,transparent);border:1px solid var(--admin-border);border-radius:.75rem;bottom:.5rem;box-shadow:0 8px 24px rgb(15 23 42 / .08);display:flex;gap:.4rem;justify-content:space-between;margin-top:.7rem;padding:.45rem;position:sticky;z-index:20}.user-actions>div{display:flex;flex-wrap:wrap;gap:.4rem}@media(max-width:900px){.access-tools,.user-grid--3{grid-template-columns:1fr 1fr}.access-tools .user-field:last-child{grid-column:1/-1}.role-row{grid-template-columns:1.1rem minmax(0,1fr)}.role-row__meta{display:none}}@media(max-width:640px){.user-editor__head p{display:none}.user-editor__tabs{display:grid;grid-template-columns:1fr 1fr}.user-grid,.user-grid--3,.access-tools{grid-template-columns:1fr}.access-tools .user-field:last-child,.user-field--wide{grid-column:auto}.user-actions{display:grid}.permission-next{align-items:stretch;display:grid}}
</style>

<div class="user-editor" data-user-editor data-active-tab="<?= admin_h($activeTab) ?>">
    <nav class="admin-breadcrumb" aria-label="breadcrumb">
        <a href="/admin/dashboard">داشبورد</a><span>/</span>
        <a href="/admin/modules/users">مدیریت کاربران</a><span>/</span>
        <a href="/admin/users">کاربران</a><span>/</span>
        <span><?= $isEdit ? 'ویرایش کاربر' : 'ایجاد کاربر' ?></span>
    </nav>

    <header class="user-editor__head">
        <div class="user-editor__title">
            <span class="user-editor__icon"><?= \App\Support\AdminIcon::html('users') ?></span>
            <div><h2><?= $isEdit ? 'ویرایش کاربر' : 'ایجاد کاربر جدید' ?></h2><p>اطلاعات حساب و دسترسی‌ها را در دو بخش مدیریت کنید.</p></div>
        </div>
        <a class="admin-button admin-button--soft admin-button--compact" href="/admin/users">بازگشت</a>
    </header>

    <?php if ($status === 'saved'): ?><div class="admin-alert admin-alert--success">تغییرات با موفقیت ذخیره شد.</div><?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="admin-alert admin-alert--danger" role="alert"><strong>ذخیره انجام نشد.</strong><ul><?php foreach ($errors as $error): ?><li><?= admin_h($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="user-editor__tabs" role="tablist">
        <button class="user-editor__tab" type="button" role="tab" data-user-tab="account" aria-selected="<?= $activeTab === 'account' ? 'true' : 'false' ?>">حساب و هویت</button>
        <button class="user-editor__tab" type="button" role="tab" data-user-tab="access" aria-selected="<?= $activeTab === 'access' ? 'true' : 'false' ?>">نقش و دسترسی (<span data-role-count><?= count($selectedRoleIds) ?></span>)</button>
    </div>

    <form method="post" action="<?= admin_h($formAction) ?>">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">

        <section class="user-editor__panel" data-user-panel="account" <?= $activeTab === 'account' ? '' : 'hidden' ?>>
            <div class="user-block">
                <div class="user-block__head"><h3>مشخصات پایه</h3><p>نام نمایشی و شناسه ورود</p></div>
                <div class="user-grid user-grid--3">
                    <label class="user-field"><span>نام</span><input name="first_name" value="<?= admin_h($form['first_name'] ?? '') ?>" maxlength="100" autocomplete="given-name" required autofocus></label>
                    <label class="user-field"><span>نام خانوادگی</span><input name="last_name" value="<?= admin_h($form['last_name'] ?? '') ?>" maxlength="100" autocomplete="family-name" required></label>
                    <label class="user-field"><span>نام کاربری</span><input name="username" value="<?= admin_h($form['username'] ?? '') ?>" maxlength="32" pattern="[a-z][a-z0-9_]{1,30}[a-z0-9]" autocomplete="username" dir="ltr" required><small>حروف انگلیسی، عدد و زیرخط</small></label>
                </div>
            </div>

            <div class="user-block">
                <div class="user-block__head"><h3>تماس و وضعیت</h3><p>حداقل ایمیل یا شماره موبایل الزامی است.</p></div>
                <div class="user-grid user-grid--3">
                    <label class="user-field"><span>ایمیل</span><input type="email" name="email" value="<?= admin_h($form['email'] ?? '') ?>" maxlength="190" autocomplete="email" dir="ltr"></label>
                    <label class="user-field"><span>شماره موبایل</span><input name="mobile" value="<?= admin_h($form['mobile'] ?? '') ?>" maxlength="15" inputmode="tel" autocomplete="tel" placeholder="09123456789" dir="ltr"></label>
                    <label class="user-field"><span>وضعیت حساب</span><select name="status" required><?php foreach ($statusOptions as $code => $label): ?><option value="<?= admin_h($code) ?>"<?= (string) ($form['status'] ?? 'active') === (string) $code ? ' selected' : '' ?>><?= admin_h($label) ?></option><?php endforeach; ?></select></label>
                    <div class="user-checks user-field--wide">
                        <label class="user-check"><input type="hidden" name="email_verified" value="0"><input type="checkbox" name="email_verified" value="1"<?= !empty($form['email_verified']) ? ' checked' : '' ?>> ایمیل تأیید شده</label>
                        <label class="user-check"><input type="hidden" name="mobile_verified" value="0"><input type="checkbox" name="mobile_verified" value="1"<?= !empty($form['mobile_verified']) ? ' checked' : '' ?>> موبایل تأیید شده</label>
                    </div>
                </div>
            </div>

            <div class="user-block">
                <div class="user-block__head"><h3>رمز عبور</h3><p><?= $isEdit ? 'برای حفظ رمز فعلی، هر دو کادر را خالی بگذارید.' : 'رمز اولیه حداقل ۱۰ کاراکتر باشد.' ?></p></div>
                <div class="user-grid">
                    <label class="user-field"><span><?= $isEdit ? 'رمز عبور جدید' : 'رمز عبور اولیه' ?></span><input type="password" name="password" minlength="10" maxlength="200" autocomplete="new-password"<?= $isEdit ? '' : ' required' ?>></label>
                    <label class="user-field"><span>تکرار رمز عبور</span><input type="password" name="password_confirmation" minlength="10" maxlength="200" autocomplete="new-password"<?= $isEdit ? '' : ' required' ?>></label>
                </div>
            </div>
        </section>

        <section class="user-editor__panel" data-user-panel="access" <?= $activeTab === 'access' ? '' : 'hidden' ?>>
            <div class="user-block">
                <div class="user-block__head"><h3>نوع و حوزه سطح دسترسی</h3><p>این طبقه‌بندی مستقیماً از ساختار RBAC خوانده می‌شود.</p></div>
                <div class="access-tools">
                    <label class="user-field"><span>نوع سطح دسترسی</span><select name="access_kind" data-kind-filter><?php foreach ($roleKinds as $kind): ?><option value="<?= admin_h($kind['code'] ?? 'all') ?>"<?= (string) ($form['access_kind'] ?? 'all') === (string) ($kind['code'] ?? 'all') ? ' selected' : '' ?>><?= admin_h($kind['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>حوزه دسترسی</span><select name="access_area" data-area-filter><?php foreach ($roleAreas as $area): ?><option value="<?= admin_h($area['code'] ?? 'all') ?>"<?= (string) ($form['access_area'] ?? 'all') === (string) ($area['code'] ?? 'all') ? ' selected' : '' ?>><?= admin_h($area['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>جست‌وجوی نقش</span><input type="search" name="role_search" value="<?= admin_h($form['role_search'] ?? '') ?>" maxlength="80" placeholder="عنوان یا کد نقش" data-role-search></label>
                </div>

                <div class="access-summary"><strong style="font-size:.7rem">نقش‌های انتخاب‌شده</strong><div class="access-summary__chips" data-role-summary></div><span style="color:var(--admin-text-muted);font-size:.68rem" data-role-summary-empty>فقط نقش پایه کاربر فعال است.</span></div>

                <div class="role-list" data-role-list>
                    <?php foreach ($roles as $role): ?>
                        <?php
                        $roleId = (int) ($role['id'] ?? 0);
                        $roleCode = (string) ($role['code'] ?? '');
                        $roleTitle = (string) ($role['title'] ?? '');
                        $isBase = $roleCode === 'user';
                        $selected = $isBase || in_array($roleId, $selectedRoleIds, true);
                        ?>
                        <label class="role-row<?= $selected ? ' is-selected' : '' ?>" data-role-row data-kind="<?= admin_h($role['role_kind_code'] ?? 'uncategorized') ?>" data-area="<?= admin_h($role['role_area_code'] ?? 'global') ?>" data-search="<?= admin_h(strtolower($roleTitle . ' ' . $roleCode)) ?>">
                            <input type="checkbox" name="role_ids[]" value="<?= $roleId ?>" data-role-checkbox data-title="<?= admin_h($roleTitle) ?>"<?= $selected ? ' checked' : '' ?><?= $isBase ? ' disabled' : '' ?>>
                            <?php if ($isBase): ?><input type="hidden" name="role_ids[]" value="<?= $roleId ?>"><?php endif; ?>
                            <span><strong><?= admin_h($roleTitle) ?></strong><small><?= admin_h($roleCode) ?></small></span>
                            <span class="role-row__meta"><?= admin_h($role['role_kind_title'] ?? 'سایر') ?></span>
                            <span class="role-row__meta"><?= admin_h($role['role_area_title'] ?? 'سراسری') ?></span>
                        </label>
                    <?php endforeach; ?>
                    <div class="role-empty" data-role-empty>نقشی مطابق فیلتر پیدا نشد.</div>
                </div>

                <div class="permission-next"><div><strong>مجوزهای ریزدانه</strong><small>مدیریت مستقیم Permissionها در مرحله توسعه دسترسی‌ها به همین تب اضافه می‌شود.</small></div><span class="admin-pill">مرحله بعد</span></div>
            </div>
        </section>

        <footer class="user-actions">
            <div><button class="admin-button" type="submit"><?= $isEdit ? 'ذخیره تغییرات' : 'ایجاد کاربر' ?></button><a class="admin-button admin-button--soft" href="/admin/users">انصراف</a></div>
            <div><?php if ($isEdit): ?><a class="admin-button admin-button--soft" href="<?= admin_h('/admin/users/' . $userId) ?>">مشاهده جزئیات</a><?php endif; ?></div>
        </footer>
    </form>
</div>

<script>
(() => {
    const root = document.querySelector('[data-user-editor]');
    if (!root) return;
    const tabs = [...root.querySelectorAll('[data-user-tab]')];
    const panels = [...root.querySelectorAll('[data-user-panel]')];
    const activate = name => {
        tabs.forEach(t => t.setAttribute('aria-selected', t.dataset.userTab === name ? 'true' : 'false'));
        panels.forEach(p => p.hidden = p.dataset.userPanel !== name);
        const url = new URL(location.href); url.searchParams.set('tab', name); history.replaceState({}, '', url);
    };
    tabs.forEach(t => t.addEventListener('click', () => activate(t.dataset.userTab)));
    root.querySelector('form')?.addEventListener('invalid', e => { const p = e.target.closest('[data-user-panel]'); if (p) activate(p.dataset.userPanel); }, true);

    const kind = root.querySelector('[data-kind-filter]');
    const area = root.querySelector('[data-area-filter]');
    const search = root.querySelector('[data-role-search]');
    const rows = [...root.querySelectorAll('[data-role-row]')];
    const empty = root.querySelector('[data-role-empty]');
    const summary = root.querySelector('[data-role-summary]');
    const summaryEmpty = root.querySelector('[data-role-summary-empty]');
    const count = root.querySelector('[data-role-count]');
    const norm = value => String(value || '').trim().toLocaleLowerCase('fa');

    const filter = () => {
        let visible = 0;
        rows.forEach(row => {
            const ok = (kind.value === 'all' || row.dataset.kind === kind.value)
                && (area.value === 'all' || row.dataset.area === area.value)
                && (norm(search.value) === '' || norm(row.dataset.search).includes(norm(search.value)));
            row.hidden = !ok; if (ok) visible++;
        });
        empty.classList.toggle('is-visible', visible === 0);
    };
    const selection = () => {
        const checked = [...root.querySelectorAll('[data-role-checkbox]:checked')];
        rows.forEach(row => row.classList.toggle('is-selected', Boolean(row.querySelector('[data-role-checkbox]')?.checked)));
        summary.textContent = '';
        checked.filter(c => !c.disabled).forEach(c => { const chip = document.createElement('span'); chip.className = 'access-chip'; chip.textContent = c.dataset.title || ''; summary.appendChild(chip); });
        summaryEmpty.hidden = checked.some(c => !c.disabled);
        count.textContent = String(checked.length);
    };
    kind?.addEventListener('change', filter); area?.addEventListener('change', filter); search?.addEventListener('input', filter);
    rows.forEach(row => row.querySelector('[data-role-checkbox]')?.addEventListener('change', selection));
    filter(); selection();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

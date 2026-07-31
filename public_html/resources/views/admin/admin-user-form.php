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
$personTypes = $page['person_types'] ?? [];
$provinces = $page['provinces'] ?? [];
$counties = $page['counties'] ?? [];
$cities = $page['cities'] ?? [];
$addressTypes = $page['address_types'] ?? [];
$statusOptions = $page['status_options'] ?? [];
$errors = $errors ?? [];
$isEdit = !empty($page['is_edit']);
$userId = (int) ($form['id'] ?? 0);
$formAction = $isEdit ? '/admin/users/' . $userId : '/admin/users';
$selectedRoleIds = array_map('intval', is_array($form['role_ids'] ?? null) ? $form['role_ids'] : []);
$baseRoleId = 0;
foreach ($roles as $roleDefinition) {
    if ((string) ($roleDefinition['code'] ?? '') === 'user') {
        $baseRoleId = (int) ($roleDefinition['id'] ?? 0);
        break;
    }
}
if (
    $baseRoleId > 0
    && !in_array($baseRoleId, $selectedRoleIds, true)
) {
    $selectedRoleIds[] = $baseRoleId;
}
$status = (string) ($status ?? '');
$requestedTab = trim((string) ($_GET['tab'] ?? ''));

$contactErrorKeys = ['email', 'mobile', 'contact', 'province_id', 'county_id', 'city_id', 'address_type_id', 'postal_code', 'address_line'];
$accessErrorKeys = ['role_ids', 'access_kind', 'access_area', 'permissions'];
$activeTab = in_array($requestedTab, ['account', 'contact', 'access'], true) ? $requestedTab : 'account';
foreach (array_keys($errors) as $key) {
    if (in_array($key, $contactErrorKeys, true)) {
        $activeTab = 'contact';
    }
    if (in_array($key, $accessErrorKeys, true)) {
        $activeTab = 'access';
    }
}

ob_start();
?>
<style>
.user-editor { display:grid; gap:.85rem; }
.user-editor__head, .user-editor__tabs, .user-block, .access-card, .user-actions { background:var(--admin-surface); border:1px solid var(--admin-border); border-radius:.9rem; }
.user-editor__head { align-items:center; display:flex; gap:.8rem; justify-content:space-between; min-height:4rem; padding:.7rem .9rem; }
.user-editor__title { align-items:center; display:flex; gap:.65rem; min-width:0; }
.user-editor__icon { align-items:center; background:var(--admin-primary-soft); border:1px solid color-mix(in srgb,var(--admin-primary) 22%,transparent); border-radius:.65rem; color:var(--admin-primary); display:inline-flex; flex:0 0 auto; height:2.45rem; justify-content:center; width:2.45rem; }
.user-editor__head h2 { font-size:1.02rem; line-height:1.55; margin:0; }
.user-editor__head p { color:var(--admin-text-muted); font-size:.72rem; line-height:1.7; margin:.08rem 0 0; }
.user-editor__tabs { display:flex; gap:.25rem; padding:.3rem; overflow-x:auto; }
.user-editor__tab { align-items:center; appearance:none; background:transparent; border:0; border-radius:.58rem; color:var(--admin-text-muted); cursor:pointer; display:inline-flex; flex:0 0 auto; font:inherit; font-size:.78rem; font-weight:800; gap:.35rem; justify-content:center; min-height:2.35rem; padding:.38rem .85rem; }
.user-editor__tab[aria-selected="true"] { background:var(--admin-primary-soft); color:var(--admin-primary); }
.user-editor__tab-count { align-items:center; background:var(--admin-surface); border:1px solid var(--admin-border); border-radius:999px; display:inline-flex; font-size:.65rem; height:1.3rem; justify-content:center; min-width:1.3rem; padding-inline:.25rem; }
.user-editor__panel[hidden] { display:none; }
.user-block, .access-card { padding:.9rem; }
.user-block + .user-block, .access-card + .access-card { margin-top: .75rem; }
.user-block__head, .access-card__head { align-items:flex-start; display:flex; gap:.8rem; justify-content:space-between; margin-bottom:.7rem; }
.user-block__head h3, .access-card__head h3 { font-size:.86rem; line-height:1.55; margin:0; }
.user-block__head p, .access-card__head p { color:var(--admin-text-muted); font-size:.69rem; line-height:1.75; margin:.1rem 0 0; }
.user-grid { align-items:start; display:grid; gap:.75rem; grid-template-columns:repeat(3,minmax(0,1fr)); }
.user-grid--2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
.user-field { display:grid; grid-template-rows:auto minmax(2.48rem,auto) auto; min-width:0; }
.user-field--wide { grid-column:1/-1; }
.user-field > span { color:var(--admin-text); display:block; font-size:.74rem; font-weight:800; margin-bottom:.26rem; }
.user-field > small { color:var(--admin-text-muted); display:block; font-size:.64rem; line-height:1.7; margin-top:.2rem; }
.user-field input, .user-field select, .user-field textarea { min-height:2.48rem; width:100%; }
.user-field textarea { min-height:6.5rem; resize:vertical; }
.user-checks { display:flex; flex-wrap:wrap; gap:.45rem; }
.user-check { align-items:center; background:var(--admin-surface-muted); border:1px solid var(--admin-border); border-radius:.62rem; display:flex; font-size:.71rem; gap:.38rem; min-height:2.3rem; padding:.32rem .58rem; }
.user-check input { height:1rem; margin:0; width:1rem; }
.access-tools { align-items:end; display:grid; gap:.65rem; grid-template-columns:repeat(3,minmax(0,1fr)); }
.access-summary { align-items:center; background:var(--admin-primary-soft); border:1px solid color-mix(in srgb,var(--admin-primary) 24%,transparent); border-radius:.72rem; display:grid; gap:.55rem; grid-template-columns:auto minmax(0,1fr); min-height:3rem; padding:.55rem .7rem; }
.access-summary__title { font-size:.73rem; font-weight:800; white-space:nowrap; }
.access-summary__content, .access-summary__chips { align-items:center; display:flex; flex-wrap:wrap; gap:.35rem; }
.access-chip { background:var(--admin-surface); border:1px solid var(--admin-border); border-radius:999px; font-size:.66rem; font-weight:800; padding:.25rem .5rem; }
.access-summary__empty { color:var(--admin-text-muted); font-size:.69rem; }
.role-table { border:1px solid var(--admin-border); border-radius:.75rem; overflow:hidden; }
.role-table__head, .role-row { align-items:center; display:grid; gap:.55rem; grid-template-columns:1.25rem minmax(9rem,1.15fr) minmax(6.5rem,.65fr) minmax(7.5rem,.75fr) minmax(7.5rem,.75fr) minmax(10rem,1fr); }
.role-table__head { background:var(--admin-surface-muted); border-bottom:1px solid var(--admin-border); color:var(--admin-text-muted); font-size:.68rem; font-weight:800; min-height:2.55rem; padding:.42rem .7rem; }
.role-table__body { max-height:24rem; overflow:auto; }
.role-row { border-top:1px solid var(--admin-border); cursor:pointer; min-height:3.25rem; padding:.48rem .7rem; }
.role-row:first-child { border-top:0; }
.role-row:hover { background:var(--admin-surface-muted); }
.role-row.is-selected { background:color-mix(in srgb,var(--admin-primary-soft) 72%,var(--admin-surface)); }
.role-row[hidden], .role-empty { display:none; }
.role-row input { height:1rem; margin:0; width:1rem; }
.role-row__identity strong { display:block; font-size:.77rem; }
.role-row__code { background:var(--admin-surface); border:1px solid var(--admin-border); border-radius:.45rem; color:var(--admin-text-muted); direction:ltr; display:inline-flex; font-size:.63rem; justify-content:flex-start; max-width:100%; overflow:hidden; padding:.22rem .38rem; text-overflow:ellipsis; white-space:nowrap; }
.role-row__meta { color:var(--admin-text); font-size:.69rem; min-width:0; }
.role-row__meta small { color:var(--admin-text-muted); display:none; font-size:.62rem; margin-bottom:.12rem; }
.role-row__scope { overflow-wrap:anywhere; }
.role-row.is-base { box-shadow:inset -3px 0 0 var(--admin-primary); }
.role-row__badge { background:var(--admin-primary-soft); border-radius:999px; color:var(--admin-primary); display:inline-flex; font-size:.6rem; margin-top:.18rem; padding:.12rem .35rem; }
.role-empty.is-visible { display:block; color:var(--admin-text-muted); font-size:.74rem; padding:1.1rem; text-align:center; }
.permission-next { align-items:center; background:var(--admin-surface-muted); border:1px dashed var(--admin-border); border-radius:.75rem; display:flex; gap:.75rem; justify-content:space-between; padding:.65rem .75rem; }
.permission-next strong { font-size:.74rem; }
.permission-next small { color:var(--admin-text-muted); display:block; font-size:.65rem; line-height:1.75; margin-top:.1rem; }
.user-actions { bottom:.5rem; box-shadow:0 8px 24px rgb(15 23 42/.08); display:flex; gap:.45rem; justify-content:space-between; margin-top:.8rem; padding:.5rem; position:sticky; z-index:20; }
.user-actions > div { display:flex; flex-wrap:wrap; gap:.4rem; }
@media (max-width:1100px) { .user-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .access-tools { grid-template-columns:1fr 1fr; } .access-tools .user-field:last-child { grid-column:1/-1; } }
@media (max-width:1050px) { .role-table__head { display:none; } .role-row { align-items:start; grid-template-columns:1.25rem minmax(0,1fr) minmax(0,1fr); } .role-row__identity { grid-column:2/-1; } .role-row__code { grid-column:2; } .role-row__meta { background:var(--admin-surface); border:1px solid var(--admin-border); border-radius:.55rem; min-height:2.8rem; padding:.4rem .5rem; } .role-row__meta small { display:block; } }
@media (max-width:760px) { .user-grid,.user-grid--2,.access-tools,.access-summary { grid-template-columns:1fr; } .user-field--wide,.access-tools .user-field:last-child { grid-column:auto; } .role-row { grid-template-columns:1.25rem minmax(0,1fr); } .role-row__identity,.role-row__code,.role-row__meta { grid-column:2; } .user-editor__head p { display:none; } .user-actions,.permission-next { display:grid; } }
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
            <div><h2><?= $isEdit ? 'ویرایش کاربر' : 'ایجاد کاربر جدید' ?></h2><p>هویت، اطلاعات تماس، نشانی و دسترسی‌ها را کامل ثبت کنید.</p></div>
        </div>
        <a class="admin-button admin-button--soft admin-button--compact" href="/admin/users">بازگشت</a>
    </header>

    <?php if ($status === 'saved'): ?><div class="admin-alert admin-alert--success">تغییرات با موفقیت ذخیره شد.</div><?php endif; ?>
    <?php if ($errors !== []): ?><div class="admin-alert admin-alert--danger" role="alert"><strong>ذخیره انجام نشد.</strong><ul><?php foreach ($errors as $error): ?><li><?= admin_h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="user-editor__tabs" role="tablist">
        <button class="user-editor__tab" type="button" role="tab" data-user-tab="account" aria-selected="<?= $activeTab === 'account' ? 'true' : 'false' ?>">حساب و هویت</button>
        <button class="user-editor__tab" type="button" role="tab" data-user-tab="contact" aria-selected="<?= $activeTab === 'contact' ? 'true' : 'false' ?>">تماس و نشانی</button>
        <button class="user-editor__tab" type="button" role="tab" data-user-tab="access" aria-selected="<?= $activeTab === 'access' ? 'true' : 'false' ?>">نقش و دسترسی <span class="user-editor__tab-count" data-role-count><?= count($selectedRoleIds) ?></span></button>
    </div>

    <form method="post" action="<?= admin_h($formAction) ?>">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">

        <section class="user-editor__panel" data-user-panel="account" <?= $activeTab === 'account' ? '' : 'hidden' ?>>
            <div class="user-block">
                <div class="user-block__head"><div><h3>اطلاعات هویتی</h3><p>مشخصات پایه پرونده شخص و اطلاعات شناسنامه‌ای.</p></div></div>
                <div class="user-grid">
                    <label class="user-field"><span>نوع شخص</span><select name="person_type" required><?php foreach ($personTypes as $option): ?><option value="<?= admin_h($option['code'] ?? '') ?>" <?= (string)($form['person_type'] ?? 'individual') === (string)($option['code'] ?? '') ? 'selected' : '' ?>><?= admin_h($option['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>نام</span><input name="first_name" value="<?= admin_h($form['first_name'] ?? '') ?>" maxlength="100" required autofocus></label>
                    <label class="user-field"><span>نام خانوادگی</span><input name="last_name" value="<?= admin_h($form['last_name'] ?? '') ?>" maxlength="100" required></label>
                    <label class="user-field"><span>کد ملی</span><input name="national_code" value="<?= admin_h($form['national_code'] ?? '') ?>" maxlength="10" inputmode="numeric" dir="ltr"></label>
                    <label class="user-field"><span>نام پدر</span><input name="father_name" value="<?= admin_h($form['father_name'] ?? '') ?>" maxlength="100"></label>
                    <label class="user-field"><span>تاریخ تولد</span><input type="date" name="birth_date" value="<?= admin_h($form['birth_date'] ?? '') ?>" dir="ltr"></label>
                    <label class="user-field"><span>محل تولد</span><input name="birth_place" value="<?= admin_h($form['birth_place'] ?? '') ?>" maxlength="150"></label>
                    <label class="user-field"><span>شماره شناسنامه</span><input name="identity_number" value="<?= admin_h($form['identity_number'] ?? '') ?>" maxlength="50" dir="ltr"></label>
                    <label class="user-field"><span>سریال شناسنامه</span><input name="identity_serial" value="<?= admin_h($form['identity_serial'] ?? '') ?>" maxlength="50" dir="ltr"></label>
                </div>
            </div>

            <div class="user-block">
                <div class="user-block__head"><div><h3>حساب ورود</h3><p>شناسه ورود، وضعیت، ایمیل، موبایل و رمز عبور.</p></div></div>
                <div class="user-grid">
                    <label class="user-field"><span>نام کاربری</span><input name="username" value="<?= admin_h($form['username'] ?? '') ?>" maxlength="32" pattern="[a-z][a-z0-9_]{1,30}[a-z0-9]" dir="ltr" required><small>حروف انگلیسی، عدد و زیرخط</small></label>
                    <label class="user-field"><span>وضعیت حساب</span><select name="status" required><?php foreach ($statusOptions as $code => $label): ?><option value="<?= admin_h($code) ?>" <?= (string)($form['status'] ?? 'active') === (string)$code ? 'selected' : '' ?>><?= admin_h($label) ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>ایمیل</span><input type="email" name="email" value="<?= admin_h($form['email'] ?? '') ?>" maxlength="190" dir="ltr"></label>
                    <label class="user-field"><span>شماره موبایل</span><input name="mobile" value="<?= admin_h($form['mobile'] ?? '') ?>" maxlength="15" inputmode="tel" placeholder="09123456789" dir="ltr"></label>
                    <label class="user-field"><span><?= $isEdit ? 'رمز عبور جدید' : 'رمز عبور اولیه' ?></span><input type="password" name="password" minlength="10" maxlength="200" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>><small><?= $isEdit ? 'برای حفظ رمز فعلی خالی بگذارید.' : 'حداقل ۱۰ کاراکتر' ?></small></label>
                    <label class="user-field"><span>تکرار رمز عبور</span><input type="password" name="password_confirmation" minlength="10" maxlength="200" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>></label>
                    <div class="user-checks user-field--wide">
                        <label class="user-check"><input type="hidden" name="email_verified" value="0"><input type="checkbox" name="email_verified" value="1" <?= !empty($form['email_verified']) ? 'checked' : '' ?>>ایمیل تأیید شده</label>
                        <label class="user-check"><input type="hidden" name="mobile_verified" value="0"><input type="checkbox" name="mobile_verified" value="1" <?= !empty($form['mobile_verified']) ? 'checked' : '' ?>>موبایل تأیید شده</label>
                    </div>
                </div>
            </div>
        </section>

        <section class="user-editor__panel" data-user-panel="contact" <?= $activeTab === 'contact' ? '' : 'hidden' ?>>
            <div class="user-block">
                <div class="user-block__head"><div><h3>راه‌های تماس</h3><p>ایمیل و موبایل حساب هم‌زمان در پرونده تماس شخص ثبت می‌شوند.</p></div></div>
                <div class="user-grid user-grid--2">
                    <label class="user-field"><span>عنوان ایمیل</span><input name="contact_email_label" value="<?= admin_h($form['contact_email_label'] ?? 'ایمیل اصلی') ?>" maxlength="100"></label>
                    <label class="user-field"><span>عنوان موبایل</span><input name="contact_mobile_label" value="<?= admin_h($form['contact_mobile_label'] ?? 'موبایل اصلی') ?>" maxlength="100"></label>
                </div>
            </div>

            <div class="user-block">
                <div class="user-block__head"><div><h3>نشانی اصلی</h3><p>موقعیت و نشانی کامل در پرونده شخص ذخیره می‌شود.</p></div></div>
                <div class="user-grid">
                    <label class="user-field"><span>نوع نشانی</span><select name="address_type_id"><option value="0">انتخاب نشده</option><?php foreach ($addressTypes as $option): ?><option value="<?= (int)($option['id'] ?? 0) ?>" <?= (int)($form['address_type_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : '' ?>><?= admin_h($option['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>استان</span><select name="province_id" data-province><option value="0">انتخاب نشده</option><?php foreach ($provinces as $option): ?><option value="<?= (int)($option['id'] ?? 0) ?>" <?= (int)($form['province_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : '' ?>><?= admin_h($option['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>شهرستان</span><select name="county_id" data-county><option value="0">انتخاب نشده</option><?php foreach ($counties as $option): ?><option value="<?= (int)($option['id'] ?? 0) ?>" data-province-id="<?= (int)($option['province_id'] ?? 0) ?>" <?= (int)($form['county_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : '' ?>><?= admin_h($option['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>شهر</span><select name="city_id" data-city><option value="0">انتخاب نشده</option><?php foreach ($cities as $option): ?><option value="<?= (int)($option['id'] ?? 0) ?>" data-province-id="<?= (int)($option['province_id'] ?? 0) ?>" data-county-id="<?= (int)($option['county_id'] ?? 0) ?>" <?= (int)($form['city_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : '' ?>><?= admin_h($option['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>ناحیه یا محله</span><input name="district" value="<?= admin_h($form['district'] ?? '') ?>" maxlength="150"></label>
                    <label class="user-field"><span>کد پستی</span><input name="postal_code" value="<?= admin_h($form['postal_code'] ?? '') ?>" maxlength="10" inputmode="numeric" dir="ltr"></label>
                    <label class="user-field user-field--wide"><span>نشانی کامل</span><textarea name="address_line" maxlength="500"><?= admin_h($form['address_line'] ?? '') ?></textarea></label>
                </div>
            </div>
        </section>

        <section class="user-editor__panel" data-user-panel="access" <?= $activeTab === 'access' ? '' : 'hidden' ?>>
            <section class="access-card">
                <div class="access-card__head"><div><h3>فیلتر نقش‌ها</h3><p>نقش‌ها را بر اساس نوع سطح دسترسی، حوزه و عنوان محدود کنید.</p></div></div>
                <div class="access-tools">
                    <label class="user-field"><span>نوع سطح دسترسی</span><select name="access_kind" data-kind-filter><?php foreach ($roleKinds as $kind): ?><option value="<?= admin_h($kind['code'] ?? 'all') ?>" <?= (string)($form['access_kind'] ?? 'all') === (string)($kind['code'] ?? 'all') ? 'selected' : '' ?>><?= admin_h($kind['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>حوزه دسترسی</span><select name="access_area" data-area-filter><?php foreach ($roleAreas as $area): ?><option value="<?= admin_h($area['code'] ?? 'all') ?>" <?= (string)($form['access_area'] ?? 'all') === (string)($area['code'] ?? 'all') ? 'selected' : '' ?>><?= admin_h($area['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>جست‌وجوی نقش</span><input type="search" name="role_search" value="<?= admin_h($form['role_search'] ?? '') ?>" maxlength="80" placeholder="عنوان یا کد نقش" data-role-search></label>
                </div>
            </section>

            <section class="access-card">
                <div class="access-card__head"><div><h3>نقش‌های کاربر</h3><p>نقش پایه «کاربر» همیشه فعال است.</p></div><span class="admin-pill"><span data-role-count><?= count($selectedRoleIds) ?></span> نقش فعال</span></div>
                <div class="access-summary"><span class="access-summary__title">انتخاب‌های فعلی</span><div class="access-summary__content"><div class="access-summary__chips" data-role-summary></div><span class="access-summary__empty" data-role-summary-empty>فقط نقش پایه کاربر فعال است.</span></div></div>
                <div class="role-table" style="margin-top:.75rem">
                    <div class="role-table__head">
                        <span>انتخاب</span>
                        <?php foreach ([
                            'title' => 'عنوان نقش',
                            'code' => 'کد نقش',
                            'kind' => 'نوع دسترسی',
                            'area' => 'حوزه دسترسی',
                            'scope' => 'مرجع حوزه',
                        ] as $roleSortKey => $roleSortLabel): ?>
                            <button
                                type="button"
                                class="admin-sort-link"
                                data-role-sort="<?= admin_h($roleSortKey) ?>"
                            >
                                <?= admin_h($roleSortLabel) ?>
                                <span class="admin-sort-link__indicator">↕</span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="role-table__body" data-role-list>
                        <?php foreach ($roles as $role): ?>
                            <?php
                            $roleId = (int) ($role['id'] ?? 0);
                            $roleCode = (string) ($role['code'] ?? '');
                            $roleTitle = (string) ($role['title'] ?? '');
                            $kindTitle = (string) (
                                $role['role_kind_title'] ?? 'سایر'
                            );
                            $areaTitle = (string) (
                                $role['role_area_title'] ?? 'سراسری'
                            );
                            $areaCode = (string) (
                                $role['role_area_code'] ?? 'global'
                            );
                            $scopeReference = $areaCode === 'global'
                                ? 'کل سامانه'
                                : 'هنگام انتصاب تعیین می‌شود';
                            $isBase = $roleCode === 'user';
                            $selected = $isBase
                                || in_array(
                                    $roleId,
                                    $selectedRoleIds,
                                    true
                                );
                            ?>
                            <label
                                class="role-row<?= $selected
                                    ? ' is-selected'
                                    : '' ?><?= $isBase
                                    ? ' is-base'
                                    : '' ?>"
                                data-role-row
                                data-kind="<?= admin_h(
                                    $role['role_kind_code']
                                    ?? 'uncategorized'
                                ) ?>"
                                data-area="<?= admin_h($areaCode) ?>"
                                data-search="<?= admin_h(
                                    strtolower(
                                        $roleTitle . ' ' . $roleCode
                                    )
                                ) ?>"
                                data-sort-title="<?= admin_h($roleTitle) ?>"
                                data-sort-code="<?= admin_h($roleCode) ?>"
                                data-sort-kind="<?= admin_h($kindTitle) ?>"
                                data-sort-area="<?= admin_h($areaTitle) ?>"
                                data-sort-scope="<?= admin_h(
                                    $scopeReference
                                ) ?>"
                            >
                                <input
                                    type="checkbox"
                                    name="role_ids[]"
                                    value="<?= $roleId ?>"
                                    data-role-checkbox
                                    data-title="<?= admin_h($roleTitle) ?>"
                                    <?= $selected ? ' checked' : '' ?>
                                    <?= $isBase ? ' disabled' : '' ?>
                                >

                                <?php if ($isBase): ?>
                                    <input
                                        type="hidden"
                                        name="role_ids[]"
                                        value="<?= $roleId ?>"
                                    >
                                <?php endif; ?>

                                <span class="role-row__identity">
                                    <strong><?= admin_h($roleTitle) ?></strong>
                                    <?php if ($isBase): ?>
                                        <span class="role-row__badge">
                                            نقش پیش‌فرض
                                        </span>
                                    <?php endif; ?>
                                </span>

                                <code class="role-row__code">
                                    <?= admin_h($roleCode) ?>
                                </code>

                                <span class="role-row__meta">
                                    <small>نوع دسترسی</small>
                                    <?= admin_h($kindTitle) ?>
                                </span>

                                <span class="role-row__meta">
                                    <small>حوزه دسترسی</small>
                                    <?= admin_h($areaTitle) ?>
                                </span>

                                <span class="role-row__meta role-row__scope">
                                    <small>مرجع حوزه</small>
                                    <?= admin_h($scopeReference) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>

                        <div class="role-empty" data-role-empty>
                            نقشی مطابق فیلتر پیدا نشد.
                        </div>
                    </div>
                </div>
            </section>

            <section class="access-card"><div class="permission-next"><div><strong>مجوزهای ریزدانه</strong><small>مدیریت مستقیم Permissionها در مرحله توسعه دسترسی‌ها به همین تب اضافه می‌شود.</small></div><span class="admin-pill">مرحله بعد</span></div></section>
        </section>

        <footer class="user-actions"><div><button class="admin-button" type="submit"><?= $isEdit ? 'ذخیره تغییرات' : 'ایجاد کاربر' ?></button><a class="admin-button admin-button--soft" href="/admin/users">انصراف</a></div><div><?php if($isEdit):?><a class="admin-button admin-button--soft" href="<?= admin_h('/admin/users/'.$userId) ?>">مشاهده جزئیات</a><?php endif;?></div></footer>
    </form>
</div>

<script>
(() => {
    const root=document.querySelector('[data-user-editor]'); if(!root)return;
    const tabs=[...root.querySelectorAll('[data-user-tab]')];
    const panels=[...root.querySelectorAll('[data-user-panel]')];
    const activate=name=>{tabs.forEach(tab=>tab.setAttribute('aria-selected',tab.dataset.userTab===name?'true':'false'));panels.forEach(panel=>panel.hidden=panel.dataset.userPanel!==name);root.dataset.activeTab=name;};
    tabs.forEach(tab=>tab.addEventListener('click',()=>activate(tab.dataset.userTab)));
    root.querySelector('form')?.addEventListener('invalid',event=>{const panel=event.target.closest('[data-user-panel]');if(panel)activate(panel.dataset.userPanel);},true);

    const province=root.querySelector('[data-province]');
    const county=root.querySelector('[data-county]');
    const city=root.querySelector('[data-city]');
    const refreshLocations=()=>{
        const provinceId=province?.value||'0'; const countyId=county?.value||'0';
        [...(county?.options||[])].forEach(option=>{if(option.value==='0')return;const match=provinceId==='0'||option.dataset.provinceId==='0'||option.dataset.provinceId===provinceId;option.hidden=!match;});
        [...(city?.options||[])].forEach(option=>{if(option.value==='0')return;const provinceMatch=provinceId==='0'||option.dataset.provinceId==='0'||option.dataset.provinceId===provinceId;const countyMatch=countyId==='0'||option.dataset.countyId==='0'||option.dataset.countyId===countyId;option.hidden=!(provinceMatch&&countyMatch);});
    };
    province?.addEventListener('change',()=>{if(county)county.value='0';if(city)city.value='0';refreshLocations();});
    county?.addEventListener('change',()=>{if(city)city.value='0';refreshLocations();});
    refreshLocations();

    const kind=root.querySelector('[data-kind-filter]'); const area=root.querySelector('[data-area-filter]'); const search=root.querySelector('[data-role-search]'); const rows=[...root.querySelectorAll('[data-role-row]')]; const empty=root.querySelector('[data-role-empty]'); const summary=root.querySelector('[data-role-summary]'); const summaryEmpty=root.querySelector('[data-role-summary-empty]'); const counts=[...root.querySelectorAll('[data-role-count]')];
    const normalize=value=>String(value||'').trim().toLocaleLowerCase('fa');
    const filter=()=>{let visible=0;rows.forEach(row=>{const show=(kind?.value==='all'||row.dataset.kind===kind?.value)&&(area?.value==='all'||row.dataset.area===area?.value)&&(!normalize(search?.value)||normalize(row.dataset.search).includes(normalize(search?.value)));row.hidden=!show;if(show)visible++;});empty?.classList.toggle('is-visible',visible===0);};
    const selection=()=>{const checked=[...root.querySelectorAll('[data-role-checkbox]:checked')];rows.forEach(row=>row.classList.toggle('is-selected',Boolean(row.querySelector('[data-role-checkbox]')?.checked)));if(summary){summary.textContent='';checked.filter(item=>!item.disabled).forEach(item=>{const chip=document.createElement('span');chip.className='access-chip';chip.textContent=item.dataset.title||'';summary.appendChild(chip);});}if(summaryEmpty)summaryEmpty.hidden=checked.filter(item=>!item.disabled).length>0;counts.forEach(count=>count.textContent=String(checked.length));};
    const roleList=root.querySelector('[data-role-list]');
    let roleSort={key:'title',dir:'asc'};
    const sortRoles=key=>{
        roleSort={
            key,
            dir:roleSort.key===key&&roleSort.dir==='asc'
                ?'desc'
                :'asc'
        };
        const dataKey=`sort${key.charAt(0).toUpperCase()+key.slice(1)}`;
        const sorted=[...rows].sort((a,b)=>{
            const left=normalize(a.dataset[dataKey]);
            const right=normalize(b.dataset[dataKey]);
            const compared=left.localeCompare(right,'fa');
            return roleSort.dir==='asc'?compared:-compared;
        });
        sorted.forEach(row=>roleList?.insertBefore(row,empty));
        root.querySelectorAll('[data-role-sort]').forEach(button=>{
            const indicator=button.querySelector('.admin-sort-link__indicator');
            if(indicator){
                indicator.textContent=button.dataset.roleSort===key
                    ?(roleSort.dir==='asc'?'↑':'↓')
                    :'↕';
            }
        });
    };
    root.querySelectorAll('[data-role-sort]').forEach(button=>
        button.addEventListener(
            'click',
            ()=>sortRoles(button.dataset.roleSort||'title')
        )
    );
    kind?.addEventListener('change',filter);
    area?.addEventListener('change',filter);
    search?.addEventListener('input',filter);
    rows.forEach(row=>
        row.querySelector('[data-role-checkbox]')
            ?.addEventListener('change',selection)
    );
    sortRoles('title');
    filter();
    selection();
})();
</script>
<?php
$content=ob_get_clean();
require __DIR__.'/layout.php';

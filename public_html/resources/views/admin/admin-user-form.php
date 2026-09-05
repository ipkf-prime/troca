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
$addressRecords = is_array(
    $form['address_records'] ?? null
)
    ? array_values($form['address_records'])
    : [];
$statusOptions = $page['status_options'] ?? [];
$errors = $errors ?? [];
$isEdit = !empty($page['is_edit']);
$userId = (int) ($form['id'] ?? 0);
$formAction = $isEdit ? '/admin/users/' . $userId : '/admin/users';
$selectedRoleIds = array_map('intval', is_array($form['role_ids'] ?? null) ? $form['role_ids'] : []);

$roleStates = is_array(
    $page['role_states'] ?? null
)
    ? $page['role_states']
    : [];

$roleStateByRoleId = [];

foreach ($roleStates as $roleState) {
    $stateRoleId =
        (int) (
            $roleState['role_id']
            ?? 0
        );

    if ($stateRoleId > 0) {
        $roleStateByRoleId[
            $stateRoleId
        ] = $roleState;
    }
}

$roleLifecycleLabels = [
    'active' =>
        'فعال',

    'pending_identity' =>
        'در انتظار تکمیل هویت',

    'pending_scope' =>
        'در انتظار تعیین حوزه',

    'pending_identity_scope' =>
        'در انتظار تکمیل هویت و حوزه',

    'revoked' =>
        'لغوشده',
];

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
$verification = trim(
    (string) ($_GET['verification'] ?? '')
);
$verificationParts = array_values(array_filter(
    explode(',', $verification)
));
$verificationMessages = [];
foreach ($verificationParts as $part) {
    [$field, $state] = array_pad(
        explode(':', $part, 2),
        2,
        ''
    );
    $fieldLabel = $field === 'email'
        ? 'ایمیل'
        : ($field === 'mobile' ? 'موبایل' : 'شناسه');
    $verificationMessages[] = match ($state) {
        'sent' => "کد تأیید {$fieldLabel} ارسال شد.",
        'dev_token_exposed' =>
            "کد تأیید {$fieldLabel} در حالت توسعه ایجاد شد.",
        'not_configured' =>
            "ذخیره انجام شد؛ سرویس ارسال کد {$fieldLabel} پیکربندی نشده است.",
        'rate_limited' =>
            "ذخیره انجام شد؛ محدودیت ارسال کد {$fieldLabel} فعال است.",
        default =>
            "ذخیره انجام شد؛ ارسال کد تأیید {$fieldLabel} ناموفق بود.",
    };
}
$requestedTab = trim((string) ($_GET['tab'] ?? ''));

if ($status === 'roles_saved') {
    $verificationMessages[] = 'نقش‌ها و دسترسی‌های کاربر ذخیره شد.';
}

$contactErrorKeys = [
    'email',
    'mobile',
    'contact',
    'geography',
    'province_location_id',
    'county_location_id',
    'city_location_id',
    'address_type_id',
    'postal_code',
    'address_line',
];
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
.user-editor select,.user-editor select option,.user-editor select optgroup { font-family:"Vazirmatn","Tahoma","Segoe UI",sans-serif!important; }
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
.role-table__head, .role-row { align-items:center; display:grid; gap:.5rem; grid-template-columns:1.25rem minmax(9rem,1.2fr) minmax(7.4rem,.72fr) minmax(7.8rem,.78fr) minmax(7.8rem,.78fr) minmax(10rem,1fr); }
.role-table__head .admin-sort-link { align-items:center; background:transparent; border:0; box-shadow:none; color:inherit; display:flex; font:inherit; gap:.25rem; justify-content:flex-start; min-height:2rem; padding:.15rem .1rem; text-align:right; width:100%; }
.role-table__head .admin-sort-link:hover { color:var(--admin-primary); }
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
.user-access-summary-grid { display:grid; gap:.55rem; grid-template-columns:repeat(2,minmax(0,1fr)); }
.user-access-summary-role { align-items:center; background:var(--admin-surface-muted); border:1px solid var(--admin-border); border-radius:.72rem; display:flex; gap:.6rem; justify-content:space-between; min-height:3.2rem; padding:.55rem .65rem; }
.user-access-summary-role strong { display:block; font-size:.76rem; }
.user-access-summary-role code { color:var(--admin-text-muted); display:block; font-size:.62rem; margin-top:.1rem; }
@media (max-width:1100px) { .user-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .access-tools { grid-template-columns:1fr 1fr; } .access-tools .user-field:last-child { grid-column:1/-1; } }
@media (max-width:1050px) { .role-table__head { display:none; } .role-row { align-items:start; grid-template-columns:1.25rem minmax(0,1fr) minmax(0,1fr); } .role-row__identity { grid-column:2/-1; } .role-row__code { grid-column:2; } .role-row__meta { background:var(--admin-surface); border:1px solid var(--admin-border); border-radius:.55rem; min-height:2.8rem; padding:.4rem .5rem; } .role-row__meta small { display:block; } }
@media (max-width:760px) { .user-grid,.user-grid--2,.access-tools,.access-summary,.user-access-summary-grid { grid-template-columns:1fr; } .user-field--wide,.access-tools .user-field:last-child { grid-column:auto; } .role-row { grid-template-columns:1.25rem minmax(0,1fr); } .role-row__identity,.role-row__code,.role-row__meta { grid-column:2; } .user-editor__head p { display:none; } .user-actions,.permission-next { display:grid; } }
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
            <div><h2><?= $isEdit ? 'ویرایش کاربر' : 'ایجاد کاربر جدید' ?></h2><p>هویت، اطلاعات تماس، نشانی و وضعیت حساب را مدیریت کنید.</p></div>
        </div>
        <a class="admin-button admin-button--soft admin-button--compact" href="/admin/users">بازگشت</a>
    </header>

    <?php if (in_array($status, ['saved', 'created'], true)): ?>
        <div class="admin-alert admin-alert--success">
            <?= $status === 'created'
                ? 'کاربر با موفقیت ایجاد شد.'
                : 'تغییرات با موفقیت ذخیره شد.' ?>
        </div>
    <?php endif; ?>
    <?php foreach ($verificationMessages as $verificationMessage): ?>
        <div class="admin-alert">
            <?= admin_h($verificationMessage) ?>
        </div>
    <?php endforeach; ?>
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
                    <label class="user-field"><span>تاریخ تولد شمسی</span><input type="text" name="birth_date_jalali" value="<?= admin_h($form['birth_date_jalali'] ?? '') ?>" inputmode="numeric" placeholder="۱۴۰۰/۰۱/۰۱" dir="ltr"><small>قالب شمسی: سال/ماه/روز</small></label>
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
                        <span class="user-check">
                            ایمیل:
                            <strong><?= !empty($form['email_verified'])
                                ? 'تأیید شده'
                                : 'تأیید نشده' ?></strong>
                        </span>
                        <span class="user-check">
                            موبایل:
                            <strong><?= !empty($form['mobile_verified'])
                                ? 'تأیید شده'
                                : 'تأیید نشده' ?></strong>
                        </span>
                        <small class="admin-muted">
                            با تغییر ایمیل یا موبایل، تأیید قبلی لغو و کد OTP ارسال می‌شود.
                        </small>
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
                <div class="user-block__head"><div><h3>نشانی اصلی</h3><p>انتخاب‌ها از جغرافیای پویای سامانه خوانده و محل دقیق در پرونده شخص ذخیره می‌شود.</p></div></div>
                    <?php if ($provinces === []): ?>
                        <div class="admin-alert admin-alert--danger user-field--wide">
                            داده جغرافیایی فعال پیدا نشد. Migration و داده‌های مرجع جغرافیا را بررسی کنید.
                        </div>
                    <?php endif; ?>
                <div class="user-grid">
                    <label class="user-field"><span>نوع نشانی</span><select name="address_type_id"><option value="0">انتخاب نشده</option><?php foreach ($addressTypes as $option): ?><option value="<?= (int)($option['id'] ?? 0) ?>" <?= (int)($form['address_type_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : '' ?>><?= admin_h($option['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>استان</span><select name="province_location_id" data-province><option value="0">انتخاب نشده</option><?php foreach ($provinces as $option): ?><option value="<?= (int)($option['id'] ?? 0) ?>" <?= (int)($form['province_location_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : '' ?>><?= admin_h($option['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>شهرستان</span><select name="county_location_id" data-county><option value="0">انتخاب نشده</option><?php foreach ($counties as $option): ?><option value="<?= (int)($option['id'] ?? 0) ?>" data-province-id="<?= (int)($option['province_location_id'] ?? 0) ?>" <?= (int)($form['county_location_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : '' ?>><?= admin_h($option['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>شهر</span><select name="city_location_id" data-city><option value="0">انتخاب نشده</option><?php foreach ($cities as $option): ?><option value="<?= (int)($option['id'] ?? 0) ?>" data-province-id="<?= (int)($option['province_location_id'] ?? 0) ?>" data-county-id="<?= (int)($option['county_location_id'] ?? 0) ?>" <?= (int)($form['city_location_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : '' ?>><?= admin_h($option['title'] ?? '') ?></option><?php endforeach; ?></select></label>
                    <label class="user-field"><span>ناحیه یا محله</span><input name="district" value="<?= admin_h($form['district'] ?? '') ?>" maxlength="150"></label>
                    <label class="user-field"><span>کد پستی</span><input name="postal_code" value="<?= admin_h($form['postal_code'] ?? '') ?>" maxlength="10" inputmode="numeric" dir="ltr"></label>
                    <label class="user-field user-field--wide"><span>نشانی کامل</span><textarea name="address_line" maxlength="500"><?= admin_h($form['address_line'] ?? '') ?></textarea></label>
                </div>
            </div>
        </section>

        <section class="user-editor__panel" data-user-panel="access" <?= $activeTab === 'access' ? '' : 'hidden' ?>>
            <section class="access-card">
                <div class="access-card__head">
                    <div>
                        <h3>خلاصه نقش و دسترسی</h3>
                        <p>
                            این بخش فقط وضعیت فعلی را نمایش می‌دهد.
                            تغییر نقش، حوزه و مجوز از مرکز کنترل دسترسی انجام می‌شود.
                        </p>
                    </div>

                    <?php if ($isEdit): ?>
                        <a
                            class="admin-button admin-button--compact"
                            href="<?= admin_h(
                                '/admin/access-control'
                                . '?tab=users'
                                . '&user_id='
                                . $userId
                            ) ?>"
                        >
                            مدیریت نقش و دسترسی این کاربر
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (!$isEdit): ?>
                    <div class="permission-next">
                        <div>
                            <strong>نقش پایه کاربر</strong>
                            <small>
                                حساب جدید فقط با نقش پایه «کاربر» ایجاد می‌شود.
                                پس از ایجاد حساب، نقش‌های تکمیلی را از مرکز کنترل دسترسی اضافه کنید.
                            </small>
                        </div>
                        <span class="admin-pill">
                            خودکار
                        </span>
                    </div>

                <?php else: ?>
                    <div class="user-access-summary-grid">
                        <?php
                        $visibleRoleCount = 0;
                        ?>

                        <?php foreach ($roles as $role): ?>
                            <?php
                            $roleId =
                                (int) (
                                    $role['id']
                                    ?? 0
                                );

                            if (
                                !in_array(
                                    $roleId,
                                    $selectedRoleIds,
                                    true
                                )
                            ) {
                                continue;
                            }

                            $visibleRoleCount++;

                            $roleCode =
                                (string) (
                                    $role['code']
                                    ?? ''
                                );

                            $state =
                                $roleStateByRoleId[
                                    $roleId
                                ] ?? null;

                            $lifecycleCode =
                                is_array($state)
                                    ? (string) (
                                        $state[
                                            'lifecycle_status_code'
                                        ] ?? ''
                                    )
                                    : '';

                            $lifecycleLabel =
                                $roleLifecycleLabels[
                                    $lifecycleCode
                                ] ?? 'ثبت‌شده';
                            ?>

                            <article class="user-access-summary-role">
                                <div>
                                    <strong>
                                        <?= admin_h(
                                            $role['title']
                                            ?? ''
                                        ) ?>
                                    </strong>

                                    <code dir="ltr">
                                        <?= admin_h($roleCode) ?>
                                    </code>
                                </div>

                                <span
                                    class="admin-pill"
                                    data-role-lifecycle="<?= admin_h(
                                        $lifecycleCode
                                    ) ?>"
                                >
                                    <?= admin_h(
                                        $lifecycleLabel
                                    ) ?>
                                </span>
                            </article>
                        <?php endforeach; ?>

                        <?php if ($visibleRoleCount === 0): ?>
                            <div class="admin-empty-state">
                                نقش قابل‌نمایشی برای این حساب ثبت نشده است.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="permission-next" style="margin-top:.75rem">
                        <div>
                            <strong>مرجع تغییرات دسترسی</strong>
                            <small>
                                افزودن یا حذف نقش، تعیین حوزه، محدودیت،
                                نقش پیش‌فرض و Permission فقط در مرکز کنترل دسترسی انجام می‌شود.
                            </small>
                        </div>

                        <a
                            class="admin-button admin-button--soft admin-button--compact"
                            href="<?= admin_h(
                                '/admin/access-control'
                                . '?tab=users'
                                . '&user_id='
                                . $userId
                            ) ?>"
                        >
                            باز کردن مرکز کنترل
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        </section>

        <footer class="user-actions"><div><button class="admin-button" type="submit"><?= $isEdit ? 'ذخیره تغییرات' : 'ایجاد کاربر' ?></button><a class="admin-button admin-button--soft" href="/admin/users">انصراف</a></div><div><?php if($isEdit):?><a class="admin-button admin-button--soft" href="<?= admin_h('/admin/users/'.$userId) ?>">مشاهده جزئیات</a><?php endif;?></div></footer>
    </form>
</div>

<script
    type="application/json"
    data-address-records
><?= json_encode(
    $addressRecords,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?></script>

<script>
(() => {
    const root=document.querySelector('[data-user-editor]'); if(!root)return;
    const tabs=[...root.querySelectorAll('[data-user-tab]')];
    const panels=[...root.querySelectorAll('[data-user-panel]')];
    const activate=name=>{tabs.forEach(tab=>tab.setAttribute('aria-selected',tab.dataset.userTab===name?'true':'false'));panels.forEach(panel=>panel.hidden=panel.dataset.userPanel!==name);root.dataset.activeTab=name;};
    tabs.forEach(tab=>tab.addEventListener('click',()=>activate(tab.dataset.userTab)));
    root.querySelector('form')?.addEventListener('invalid',event=>{const panel=event.target.closest('[data-user-panel]');if(panel)activate(panel.dataset.userPanel);},true);

    const province = root.querySelector('[data-province]');
    const county = root.querySelector('[data-county]');
    const city = root.querySelector('[data-city]');
    const addressType = root.querySelector(
        '[name="address_type_id"]'
    );
    const district = root.querySelector(
        '[name="district"]'
    );
    const postalCode = root.querySelector(
        '[name="postal_code"]'
    );
    const addressLine = root.querySelector(
        '[name="address_line"]'
    );
    const addressRecordsNode = document.querySelector(
        '[data-address-records]'
    );
    let addressRecords = [];

    try {
        addressRecords = JSON.parse(
            addressRecordsNode?.textContent || '[]'
        );
    } catch (error) {
        addressRecords = [];
    }

    const buildLocationCascade = (
        province,
        county,
        city
    ) => {
        if (!province || !county || !city) {
            return null;
        }

        const countyPlaceholder =
            county.options[0]?.cloneNode(true)
            ?? new Option('انتخاب نشده', '0');
        const cityPlaceholder =
            city.options[0]?.cloneNode(true)
            ?? new Option('انتخاب نشده', '0');

        const countyOptions = [
            ...county.options,
        ]
            .filter(option => option.value !== '0')
            .map(option => ({
                value: option.value,
                label: option.textContent ?? '',
                provinceId:
                    option.dataset.provinceId ?? '0',
            }));

        const cityOptions = [
            ...city.options,
        ]
            .filter(option => option.value !== '0')
            .map(option => ({
                value: option.value,
                label: option.textContent ?? '',
                provinceId:
                    option.dataset.provinceId ?? '0',
                countyId:
                    option.dataset.countyId ?? '0',
            }));

        const replaceOptions = (
            select,
            placeholder,
            items,
            selectedValue
        ) => {
            select.replaceChildren(
                placeholder.cloneNode(true)
            );

            items.forEach(item => {
                const option = new Option(
                    item.label,
                    item.value,
                    false,
                    item.value === selectedValue
                );
                option.dataset.provinceId =
                    item.provinceId ?? '0';
                option.dataset.countyId =
                    item.countyId ?? '0';
                select.append(option);
            });

            if (
                selectedValue !== '0'
                && !items.some(
                    item => item.value === selectedValue
                )
            ) {
                select.value = '0';
            }
        };

        const refreshCounties = (
            preserveSelection = true
        ) => {
            const provinceId =
                province.value || '0';
            const selectedCounty = preserveSelection
                ? county.value || '0'
                : '0';

            const filtered = provinceId === '0'
                ? []
                : countyOptions.filter(
                    item =>
                        item.provinceId === provinceId
                );

            countyPlaceholder.textContent =
                provinceId === '0'
                    ? 'ابتدا استان را انتخاب کنید'
                    : (
                        filtered.length > 0
                            ? 'انتخاب نشده'
                            : 'شهرستانی ثبت نشده است'
                    );

            replaceOptions(
                county,
                countyPlaceholder,
                filtered,
                selectedCounty
            );

            county.disabled =
                provinceId === '0'
                || filtered.length === 0;
        };

        const refreshCities = (
            preserveSelection = true
        ) => {
            const provinceId =
                province.value || '0';
            const countyId =
                county.value || '0';
            const selectedCity = preserveSelection
                ? city.value || '0'
                : '0';

            const filtered =
                provinceId === '0'
                || countyId === '0'
                    ? []
                    : cityOptions.filter(
                        item =>
                            item.provinceId
                                === provinceId
                            && item.countyId
                                === countyId
                    );

            cityPlaceholder.textContent =
                countyId === '0'
                    ? 'ابتدا شهرستان را انتخاب کنید'
                    : (
                        filtered.length > 0
                            ? 'انتخاب نشده'
                            : 'شهری ثبت نشده است'
                    );

            replaceOptions(
                city,
                cityPlaceholder,
                filtered,
                selectedCity
            );

            city.disabled =
                countyId === '0'
                || filtered.length === 0;
        };

        province.addEventListener(
            'change',
            () => {
                refreshCounties(false);
                refreshCities(false);
            }
        );

        county.addEventListener(
            'change',
            () => refreshCities(false)
        );

        refreshCounties(true);
        refreshCities(true);

        return {
            setValues(values = {}) {
                province.value = String(
                    values.province_location_id
                    ?? 0
                );
                refreshCounties(false);

                county.value = String(
                    values.county_location_id
                    ?? 0
                );
                refreshCities(false);

                city.value = String(
                    values.city_location_id
                    ?? 0
                );
            },
        };
    };

    const locationCascade = buildLocationCascade(
        province,
        county,
        city
    );

    const loadSelectedAddressType = () => {
        const typeId = Number(
            addressType?.value || 0
        );
        const record = addressRecords.find(
            item => Number(
                item.address_type_id || 0
            ) === typeId
        ) || null;

        locationCascade?.setValues(
            record || {
                province_location_id: 0,
                county_location_id: 0,
                city_location_id: 0,
            }
        );

        if (district) {
            district.value = record?.district || '';
        }

        if (postalCode) {
            postalCode.value =
                record?.postal_code || '';
        }

        if (addressLine) {
            addressLine.value =
                record?.address_line || '';
        }
    };

    addressType?.addEventListener(
        'change',
        loadSelectedAddressType
    );

    // Role assignment is managed exclusively by /admin/access-control.
})();
</script>
<?php
$content=ob_get_clean();
require __DIR__.'/layout.php';

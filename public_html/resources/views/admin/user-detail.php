<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$user = $detail['user'] ?? [];
$workspace = $detail['workspace'] ?? [];
$tabs = $detail['tabs'] ?? [];
$activeTab = (string) ($detail['active_tab'] ?? 'overview');
$tabContent = $detail['content'] ?? [];
$activeTabTitle = '';

foreach ($tabs as $tab) {
    if (($tab['key'] ?? '') === $activeTab) {
        $activeTabTitle = (string) ($tab['title'] ?? '');
        break;
    }
}

$renderFieldList = function (array $fields): void {
    ?>
    <div class="entity-field-grid">
        <?php foreach ($fields as $field): ?>
            <div class="entity-field">
                <span><?= admin_h($field['label'] ?? '') ?></span>
                <strong<?= isset($field['dir']) ? ' dir="' . admin_h($field['dir']) . '"' : '' ?>><?= admin_h($field['value'] ?? '—') ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
};

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/modules/users">مدیریت کاربران</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/users">کاربران</a>
    <span aria-hidden="true">/</span>
    <span><?= admin_h($activeTabTitle !== '' ? $activeTabTitle : 'جزئیات کاربر') ?></span>
</nav>

<?php
$content = $tabContent;
ob_start();
?>

<?php if ($activeTab === 'identity'): ?>
    <?php $identity = $content['identity'] ?? []; ?>
    <section class="entity-section">
        <div class="admin-section__header">
            <div>
                <h2>اطلاعات هویتی</h2>
                <p class="admin-muted">مشخصات پایه شخص بدون نمایش داده‌های حساس خام.</p>
            </div>
        </div>
        <?php $renderFieldList([
            ['label' => 'نام کامل', 'value' => $identity['full_name'] ?? '—'],
            ['label' => 'نام', 'value' => $identity['first_name'] ?? '—'],
            ['label' => 'نام خانوادگی', 'value' => $identity['last_name'] ?? '—'],
            ['label' => 'نوع شخص', 'value' => $identity['person_type'] ?? '—'],
            ['label' => 'کد ملی', 'value' => $identity['national_code'] ?? '—', 'dir' => 'ltr'],
            ['label' => 'نام پدر', 'value' => $identity['father_name'] ?? '—'],
            ['label' => 'تاریخ تولد', 'value' => $identity['birth_date'] ?? '—'],
            ['label' => 'محل تولد', 'value' => $identity['birth_place'] ?? '—'],
            ['label' => 'شماره شناسنامه', 'value' => $identity['identity_number'] ?? '—', 'dir' => 'ltr'],
            ['label' => 'سریال شناسنامه', 'value' => $identity['identity_serial'] ?? '—', 'dir' => 'ltr'],
        ]); ?>
    </section>
<?php elseif ($activeTab === 'contacts'): ?>
    <?php $contacts = $content['contacts'] ?? []; ?>
    <?php $addresses = $content['addresses'] ?? []; ?>
    <section class="entity-section">
        <div class="admin-section__header">
            <div>
                <h2>راه‌های تماس</h2>
                <p class="admin-muted">اطلاعات تماس ثبت‌شده با عنوان‌های معنایی.</p>
            </div>
        </div>
        <?php if ($contacts === []): ?>
            <div class="admin-empty-state">اطلاعات تماسی برای این شخص ثبت نشده است.</div>
        <?php else: ?>
            <div class="entity-card-list">
                <?php foreach ($contacts as $contact): ?>
                    <article class="entity-info-card">
                        <header>
                            <strong><?= admin_h($contact['type'] ?? '—') ?></strong>
                            <span class="admin-status-badge admin-status-badge--<?= admin_h($contact['status']['code'] ?? 'unknown') ?>"><?= admin_h($contact['status']['label'] ?? '—') ?></span>
                        </header>
                        <?php $renderFieldList([
                            ['label' => 'عنوان', 'value' => $contact['label'] ?? '—'],
                            ['label' => 'مقدار', 'value' => $contact['value'] ?? '—', 'dir' => 'ltr'],
                            ['label' => 'اصلی', 'value' => $contact['is_primary'] ?? 'خیر'],
                            ['label' => 'تأیید شده', 'value' => $contact['is_verified'] ?? 'خیر'],
                        ]); ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="entity-section">
        <div class="admin-section__header">
            <div>
                <h2>نشانی‌ها</h2>
                <p class="admin-muted">آدرس‌های ثبت‌شده بدون نمایش شناسه‌های داخلی.</p>
            </div>
        </div>
        <?php if ($addresses === []): ?>
            <div class="admin-empty-state">نشانی برای این شخص ثبت نشده است.</div>
        <?php else: ?>
            <div class="entity-card-list">
                <?php foreach ($addresses as $address): ?>
                    <article class="entity-info-card">
                        <header>
                            <strong><?= admin_h($address['type'] ?? '—') ?></strong>
                            <span class="admin-status-badge admin-status-badge--<?= admin_h($address['status']['code'] ?? 'unknown') ?>"><?= admin_h($address['status']['label'] ?? '—') ?></span>
                        </header>
                        <?php $renderFieldList([
                            ['label' => 'استان', 'value' => $address['province'] ?? '—'],
                            ['label' => 'شهر', 'value' => $address['city'] ?? '—'],
                            ['label' => 'ناحیه/محله', 'value' => $address['district'] ?? '—'],
                            ['label' => 'کد پستی', 'value' => $address['postal_code'] ?? '—', 'dir' => 'ltr'],
                            ['label' => 'اصلی', 'value' => $address['is_primary'] ?? 'خیر'],
                            ['label' => 'نشانی', 'value' => $address['address_line'] ?? '—'],
                        ]); ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php elseif ($activeTab === 'account'): ?>
    <?php $account = $content['account'] ?? []; ?>
    <?php $security = $content['security'] ?? []; ?>
    <section class="entity-section">
        <div class="admin-section__header">
            <div>
                <h2>حساب و امنیت</h2>
                <p class="admin-muted">خلاصه امن وضعیت حساب، احراز هویت و MFA.</p>
            </div>
        </div>
        <?php $renderFieldList([
            ['label' => 'نام کاربری', 'value' => $account['username'] ?? '—', 'dir' => 'ltr'],
            ['label' => 'موبایل', 'value' => $account['mobile'] ?? '—', 'dir' => 'ltr'],
            ['label' => 'ایمیل', 'value' => $account['email'] ?? '—', 'dir' => 'ltr'],
            ['label' => 'وضعیت حساب', 'value' => $account['status']['label'] ?? '—'],
            ['label' => 'تأیید ایمیل', 'value' => $account['email_verified']['label'] ?? '—'],
            ['label' => 'تأیید موبایل', 'value' => $account['mobile_verified']['label'] ?? '—'],
            ['label' => 'آخرین ورود', 'value' => $account['last_login_at'] ?? '—'],
            ['label' => 'تاریخ ایجاد', 'value' => $account['created_at'] ?? '—'],
            ['label' => 'آخرین به‌روزرسانی', 'value' => $account['updated_at'] ?? '—'],
        ]); ?>
    </section>
    <section class="entity-section">
        <div class="admin-section__header">
            <div>
                <h2>خلاصه MFA</h2>
                <p class="admin-muted">رازها، کدها و توکن‌ها در این بخش نمایش داده نمی‌شوند.</p>
            </div>
        </div>
        <div class="admin-mini-grid">
            <article class="admin-card"><span>MFA فعال</span><strong><?= admin_h($security['mfa_enabled'] ?? 'خیر') ?></strong></article>
            <article class="admin-card"><span>TOTP فعال</span><strong><?= admin_h($security['totp_enabled'] ?? 'خیر') ?></strong></article>
            <article class="admin-card"><span>کد بازیابی</span><strong><?= admin_h($security['recovery_codes_available'] ?? 'خیر') ?></strong></article>
            <article class="admin-card"><span>تعداد کدهای بازیابی</span><strong><?= admin_h($security['recovery_codes_count'] ?? '۰') ?></strong></article>
            <article class="admin-card"><span>دستگاه مورد اعتماد</span><strong><?= admin_h($security['trusted_devices_available'] ?? 'خیر') ?></strong></article>
            <article class="admin-card"><span>تعداد دستگاه‌های فعال</span><strong><?= admin_h($security['trusted_devices_count'] ?? '۰') ?></strong></article>
        </div>
    </section>
<?php elseif ($activeTab === 'access'): ?>
    <?php $roles = $content['roles'] ?? []; ?>
    <section class="entity-section">
        <div class="admin-section__header">
            <div>
                <h2>نقش‌ها و دسترسی‌ها</h2>
                <p class="admin-muted">انتساب‌های نقش این کاربر فقط برای مشاهده نمایش داده می‌شود.</p>
            </div>
        </div>
        <?php if ($roles === []): ?>
            <div class="admin-empty-state">نقشی برای این کاربر ثبت نشده است.</div>
        <?php else: ?>
            <div class="admin-table-wrap entity-responsive-table">
                <table class="admin-table admin-user-detail-table">
                    <thead>
                        <tr>
                            <th>نقش</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>محدوده</th>
                            <th>نوع سازمان</th>
                            <th>سطح سازمان</th>
                            <th>شروع</th>
                            <th>پایان</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td>
                                    <strong><?= admin_h($role['role_title'] ?? '—') ?></strong>
                                    <small class="admin-user-detail-secondary" dir="ltr"><?= admin_h($role['role_code'] ?? '—') ?></small>
                                </td>
                                <td><?= admin_h($role['priority'] ?? '—') ?></td>
                                <td><span class="admin-status-badge admin-status-badge--<?= admin_h($role['status']['code'] ?? 'unknown') ?>"><?= admin_h($role['status']['label'] ?? '—') ?></span></td>
                                <td><?= admin_h($role['scope_summary'] ?? '—') ?></td>
                                <td><?= admin_h($role['organization_type_title'] ?? '—') ?></td>
                                <td><?= admin_h($role['organization_level_title'] ?? '—') ?></td>
                                <td><?= admin_h($role['starts_at'] ?? '—') ?></td>
                                <td><?= admin_h($role['ends_at'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="entity-mobile-cards">
                <?php foreach ($roles as $role): ?>
                    <article class="entity-info-card">
                        <header>
                            <strong><?= admin_h($role['role_title'] ?? '—') ?></strong>
                            <span class="admin-status-badge admin-status-badge--<?= admin_h($role['status']['code'] ?? 'unknown') ?>"><?= admin_h($role['status']['label'] ?? '—') ?></span>
                        </header>
                        <?php $renderFieldList([
                            ['label' => 'کد نقش', 'value' => $role['role_code'] ?? '—', 'dir' => 'ltr'],
                            ['label' => 'اولویت', 'value' => $role['priority'] ?? '—'],
                            ['label' => 'محدوده', 'value' => $role['scope_summary'] ?? '—'],
                            ['label' => 'نوع سازمان', 'value' => $role['organization_type_title'] ?? '—'],
                            ['label' => 'سطح سازمان', 'value' => $role['organization_level_title'] ?? '—'],
                            ['label' => 'شروع', 'value' => $role['starts_at'] ?? '—'],
                            ['label' => 'پایان', 'value' => $role['ends_at'] ?? '—'],
                        ]); ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php elseif ($activeTab === 'appointments'): ?>
    <?php $legacy = $content['legacy_organization_assignments'] ?? []; ?>
    <?php $canonical = $content['canonical_organization_appointments'] ?? []; ?>
    <section class="entity-section">
        <div class="admin-section__header">
            <div>
                <h2>انتساب‌های سازمانی داخلی</h2>
                <p class="admin-muted">داده‌های موجود در user_org_assignments بدون ادغام حدسی.</p>
            </div>
        </div>
        <?php if ($legacy === []): ?>
            <div class="admin-empty-state">انتساب سازمانی برای این شخص ثبت نشده است.</div>
        <?php else: ?>
            <div class="entity-card-list">
                <?php foreach ($legacy as $assignment): ?>
                    <article class="entity-info-card">
                        <header>
                            <strong><?= admin_h($assignment['org_unit_title'] ?? '—') ?></strong>
                            <span class="admin-status-badge admin-status-badge--<?= admin_h($assignment['status']['code'] ?? 'unknown') ?>"><?= admin_h($assignment['status']['label'] ?? '—') ?></span>
                        </header>
                        <?php $renderFieldList([
                            ['label' => 'کد واحد', 'value' => $assignment['org_unit_code'] ?? '—', 'dir' => 'ltr'],
                            ['label' => 'سمت', 'value' => $assignment['position_title'] ?? '—'],
                            ['label' => 'کد سمت', 'value' => $assignment['position_code'] ?? '—', 'dir' => 'ltr'],
                            ['label' => 'اصلی', 'value' => $assignment['is_primary'] ?? 'خیر'],
                            ['label' => 'شروع', 'value' => $assignment['started_at'] ?? '—'],
                            ['label' => 'پایان', 'value' => $assignment['ended_at'] ?? '—'],
                        ]); ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <section class="entity-section">
        <div class="admin-section__header">
            <div>
                <h2>انتصاب‌های رسمی سازمان</h2>
                <p class="admin-muted">داده‌های canonical در organization_appointments در صورت وجود.</p>
            </div>
        </div>
        <?php if ($canonical === []): ?>
            <div class="admin-empty-state">انتصاب canonical برای این شخص ثبت نشده است.</div>
        <?php else: ?>
            <div class="entity-card-list">
                <?php foreach ($canonical as $appointment): ?>
                    <article class="entity-info-card">
                        <header>
                            <strong><?= admin_h($appointment['organization_position'] ?? '—') ?></strong>
                            <span class="admin-status-badge admin-status-badge--<?= admin_h($appointment['status']['code'] ?? 'unknown') ?>"><?= admin_h($appointment['status']['label'] ?? '—') ?></span>
                        </header>
                        <?php $renderFieldList([
                            ['label' => 'سازمان', 'value' => $appointment['organization'] ?? '—'],
                            ['label' => 'واحد', 'value' => $appointment['org_unit'] ?? '—'],
                            ['label' => 'سمت پایه', 'value' => $appointment['reusable_position'] ?? '—'],
                            ['label' => 'کد جایگاه', 'value' => $appointment['organization_position_code'] ?? '—', 'dir' => 'ltr'],
                            ['label' => 'نوع انتصاب', 'value' => $appointment['appointment_type'] ?? '—'],
                            ['label' => 'اصلی', 'value' => $appointment['is_primary'] ?? 'خیر'],
                            ['label' => 'سرپرست/acting', 'value' => $appointment['is_acting'] ?? 'خیر'],
                            ['label' => 'شروع', 'value' => $appointment['valid_from'] ?? '—'],
                            ['label' => 'پایان', 'value' => $appointment['valid_to'] ?? '—'],
                            ['label' => 'مرجع انتصاب', 'value' => $appointment['appointment_reference'] ?? '—', 'dir' => 'ltr'],
                        ]); ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <?php $overview = $content['overview'] ?? $user; ?>
    <?php $security = $content['security'] ?? []; ?>
    <section class="entity-section entity-overview">
        <div class="admin-section__header">
            <div>
                <h2>خلاصه کاربر</h2>
                <p class="admin-muted">نمای فشرده از مهم‌ترین اطلاعات، بدون تکرار همه فیلدها.</p>
            </div>
        </div>
        <div class="admin-mini-grid">
            <article class="admin-card"><span>نام نمایشی</span><strong><?= admin_h($overview['display_name'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>نام کاربری</span><strong dir="ltr"><?= admin_h($overview['username'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>وضعیت حساب</span><strong><?= admin_h($overview['status']['label'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>نوع شخص</span><strong><?= admin_h($overview['person_type'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>استان</span><strong><?= admin_h($overview['province'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>شهرستان</span><strong><?= admin_h($overview['county'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>شهر</span><strong><?= admin_h($overview['city'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>نقش‌های فعال</span><strong><?= admin_h($overview['active_role_count_label'] ?? '۰') ?></strong></article>
            <article class="admin-card"><span>واحد اصلی</span><strong><?= admin_h($overview['primary_org_unit'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>MFA</span><strong><?= admin_h($security['mfa_enabled'] ?? 'خیر') ?></strong></article>
            <article class="admin-card"><span>ایجاد</span><strong><?= admin_h($overview['created_at'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>آخرین ورود</span><strong><?= admin_h($overview['last_login_at'] ?? '—') ?></strong></article>
        </div>
    </section>
    <section class="entity-section">
        <div class="admin-section__header">
            <div>
                <h2>نمای دسترسی و سازمان</h2>
                <p class="admin-muted">خلاصه کوتاه؛ جزئیات کامل در تب‌های نقش‌ها و انتصاب‌ها قرار دارد.</p>
            </div>
        </div>
        <div class="admin-mini-grid">
            <article class="admin-card"><span>نقش‌ها</span><strong><?= admin_h($overview['active_role_summary'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>انتساب سازمانی</span><strong><?= admin_h($overview['primary_org_unit'] ?? '—') ?></strong></article>
            <article class="admin-card"><span>کد بازیابی MFA</span><strong><?= admin_h($security['recovery_codes_available'] ?? 'خیر') ?></strong></article>
        </div>
    </section>
<?php endif; ?>

<?php
$workspaceContent = ob_get_clean();
require __DIR__ . '/partials/entity-workspace.php';
$content = ob_get_clean();
require __DIR__ . '/layout.php';

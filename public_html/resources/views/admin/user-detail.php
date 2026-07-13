<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$user = $detail['user'] ?? [];
$roles = $detail['roles'] ?? [];
$orgAssignments = $detail['organization_assignments'] ?? [];
$security = $detail['security'] ?? [];

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/modules/users">مدیریت کاربران</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/users">کاربران</a>
    <span aria-hidden="true">/</span>
    <span>جزئیات کاربر</span>
</nav>

<section class="admin-module-hub admin-module-hub--blue admin-users-heading">
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html('user') ?>
    </div>
    <div>
        <h2>جزئیات کاربر</h2>
        <p>مشاهده اطلاعات هویتی، حساب، دسترسی و ساختار سازمانی</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/users">بازگشت به کاربران</a>
</section>

<section class="admin-section admin-user-detail-summary">
    <div class="admin-user-detail-summary__avatar">
        <img src="<?= admin_h($user['avatar_url'] ?? '/assets/admin/images/avatars/default-avatar.svg') ?>" alt="">
    </div>
    <div class="admin-user-detail-summary__body">
        <span class="admin-muted">کاربر</span>
        <h2><?= admin_h($user['display_name'] ?? '—') ?></h2>
        <p dir="ltr"><?= admin_h($user['username'] ?? '—') ?></p>
    </div>
    <div class="admin-user-detail-summary__meta">
        <span class="admin-status-badge admin-status-badge--<?= admin_h($user['status']['code'] ?? 'unknown') ?>"><?= admin_h($user['status']['label'] ?? '—') ?></span>
        <span class="admin-pill"><?= admin_h($user['active_role_count_label'] ?? '۰') ?> نقش فعال</span>
        <span class="admin-pill admin-pill--muted"><?= admin_h($user['primary_org_unit'] ?? '—') ?></span>
    </div>
</section>

<section class="admin-user-detail-grid">
    <article class="admin-section">
        <div class="admin-section__header">
            <div>
                <h2>اطلاعات هویتی</h2>
                <p class="admin-muted">مشخصات پایه کاربر بدون نمایش داده‌های حساس.</p>
            </div>
        </div>
        <div class="admin-field-list">
            <div><span>نام کامل</span><strong><?= admin_h($user['full_name'] ?? '—') ?></strong></div>
            <div><span>نوع شخص</span><strong><?= admin_h($user['person_type'] ?? '—') ?></strong></div>
            <div><span>نام</span><strong><?= admin_h($user['first_name'] ?? '—') ?></strong></div>
            <div><span>نام خانوادگی</span><strong><?= admin_h($user['last_name'] ?? '—') ?></strong></div>
            <div><span>استان</span><strong><?= admin_h($user['province'] ?? '—') ?></strong></div>
            <div><span>شهر</span><strong><?= admin_h($user['city'] ?? '—') ?></strong></div>
            <div><span>تاریخ ایجاد</span><strong><?= admin_h($user['created_at'] ?? '—') ?></strong></div>
        </div>
    </article>

    <article class="admin-section">
        <div class="admin-section__header">
            <div>
                <h2>اطلاعات حساب</h2>
                <p class="admin-muted">وضعیت حساب و راه‌های ارتباطی ثبت‌شده.</p>
            </div>
        </div>
        <div class="admin-field-list">
            <div><span>نام کاربری</span><strong dir="ltr"><?= admin_h($user['username'] ?? '—') ?></strong></div>
            <div><span>موبایل</span><strong dir="ltr"><?= admin_h($user['mobile'] ?? '—') ?></strong></div>
            <div><span>ایمیل</span><strong dir="ltr"><?= admin_h($user['email'] ?? '—') ?></strong></div>
            <div><span>وضعیت حساب</span><strong><span class="admin-status-badge admin-status-badge--<?= admin_h($user['status']['code'] ?? 'unknown') ?>"><?= admin_h($user['status']['label'] ?? '—') ?></span></strong></div>
            <div><span>تأیید ایمیل</span><strong><span class="admin-status-badge admin-status-badge--<?= admin_h($user['email_verified']['code'] ?? 'unknown') ?>"><?= admin_h($user['email_verified']['label'] ?? '—') ?></span></strong></div>
            <div><span>تأیید موبایل</span><strong><span class="admin-status-badge admin-status-badge--<?= admin_h($user['mobile_verified']['code'] ?? 'unknown') ?>"><?= admin_h($user['mobile_verified']['label'] ?? '—') ?></span></strong></div>
            <div><span>آخرین ورود</span><strong><?= admin_h($user['last_login_at'] ?? '—') ?></strong></div>
            <div><span>آخرین به‌روزرسانی</span><strong><?= admin_h($user['updated_at'] ?? '—') ?></strong></div>
        </div>
    </article>
</section>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>امنیت و MFA</h2>
            <p class="admin-muted">خلاصه امن وضعیت احراز هویت چندمرحله‌ای، بدون نمایش رازها یا کدها.</p>
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

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>نقش‌ها و دسترسی‌ها</h2>
            <p class="admin-muted">انتساب‌های نقش این کاربر به‌صورت فقط خواندنی نمایش داده می‌شود.</p>
        </div>
    </div>
    <?php if ($roles === []): ?>
        <div class="admin-empty-state">نقشی برای این کاربر ثبت نشده است.</div>
    <?php else: ?>
        <div class="admin-table-wrap">
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
    <?php endif; ?>
</section>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>انتساب‌های سازمانی</h2>
            <p class="admin-muted">واحدها و سمت‌های مرتبط با این کاربر، بدون امکان تغییر در این مرحله.</p>
        </div>
    </div>
    <?php if ($orgAssignments === []): ?>
        <div class="admin-empty-state">انتساب سازمانی برای این کاربر ثبت نشده است.</div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table admin-user-detail-table">
                <thead>
                    <tr>
                        <th>واحد سازمانی</th>
                        <th>سمت</th>
                        <th>انتساب اصلی</th>
                        <th>وضعیت</th>
                        <th>شروع</th>
                        <th>پایان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orgAssignments as $assignment): ?>
                        <tr>
                            <td>
                                <strong><?= admin_h($assignment['org_unit_title'] ?? '—') ?></strong>
                                <small class="admin-user-detail-secondary" dir="ltr"><?= admin_h($assignment['org_unit_code'] ?? '—') ?></small>
                            </td>
                            <td>
                                <strong><?= admin_h($assignment['position_title'] ?? '—') ?></strong>
                                <small class="admin-user-detail-secondary" dir="ltr"><?= admin_h($assignment['position_code'] ?? '—') ?></small>
                            </td>
                            <td><?= admin_h($assignment['is_primary'] ?? 'خیر') ?></td>
                            <td><span class="admin-status-badge admin-status-badge--<?= admin_h($assignment['status']['code'] ?? 'unknown') ?>"><?= admin_h($assignment['status']['label'] ?? '—') ?></span></td>
                            <td><?= admin_h($assignment['started_at'] ?? '—') ?></td>
                            <td><?= admin_h($assignment['ended_at'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

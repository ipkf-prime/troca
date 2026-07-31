<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$user = $context['user'] ?? [];
$active = $context['active_assignment'] ?? [];
$mfa = $context['mfa'] ?? [];

$statusLabel = match ((string) ($user['status'] ?? '')) {
    'active' => 'فعال',
    'inactive' => 'غیرفعال',
    'suspended' => 'تعلیق‌شده',
    default => 'نامشخص',
};

ob_start();
?>
<div class="account-shell">
    <?php require __DIR__ . '/partials/account-nav.php'; ?>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h2>نمای کلی حساب</h2>
                <p>
                    اطلاعات اصلی، نقش جاری و وضعیت امنیتی حساب شما
                </p>
            </div>
            <span class="account-badge account-badge--success">
                <?= admin_h($statusLabel) ?>
            </span>
        </div>

        <div class="account-summary">
            <article class="account-stat">
                <span>نام نمایشی</span>
                <strong><?= admin_h($user['name'] ?? '—') ?></strong>
            </article>

            <article class="account-stat">
                <span>نام کاربری</span>
                <strong dir="ltr">
                    <?= admin_h($user['username'] ?? '—') ?>
                </strong>
            </article>

            <article class="account-stat">
                <span>نقش فعال</span>
                <strong>
                    <?= admin_h($active['role_title'] ?? '—') ?>
                </strong>
            </article>
        </div>
    </section>

    <section class="account-card">
        <div class="account-card__head">
            <div>
                <h3>وضعیت حساب</h3>
                <p>
                    اطلاعات تماس و کنترل‌های مهم امنیتی
                </p>
            </div>
        </div>

        <div class="account-list">
            <div class="account-list__row">
                <span>ایمیل</span>
                <strong dir="ltr">
                    <?= admin_h($user['email'] ?? '—') ?>
                </strong>
                <span class="account-badge">
                    شناسه ورود
                </span>
            </div>

            <div class="account-list__row">
                <span>شماره موبایل</span>
                <strong dir="ltr">
                    <?= admin_h($user['mobile'] ?? '—') ?>
                </strong>
                <span class="account-badge">
                    تماس
                </span>
            </div>

            <div class="account-list__row">
                <span>تأیید دومرحله‌ای</span>
                <strong>
                    <?= ($mfa['enabled'] ?? false)
                        ? 'فعال'
                        : 'غیرفعال' ?>
                </strong>
                <span class="account-badge <?= ($mfa['enabled'] ?? false)
                    ? 'account-badge--success'
                    : 'account-badge--danger' ?>">
                    <?= ($mfa['enabled'] ?? false)
                        ? 'محافظت‌شده'
                        : 'نیازمند اقدام' ?>
                </span>
            </div>
        </div>

        <div class="account-actions" style="margin-top:.7rem">
            <a class="admin-button" href="/admin/profile/edit">
                ویرایش هویت و نشانی
            </a>
            <a class="admin-button admin-button--soft" href="/admin/security">
                تنظیمات امنیتی
            </a>
            <a
                class="admin-button admin-button--soft"
                href="/admin/profile/access"
            >
                نقش‌ها و دسترسی‌ها
            </a>
            <a
                class="admin-button admin-button--soft"
                href="/admin/my-theme"
            >
                ظاهر پنل
            </a>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

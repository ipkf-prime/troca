<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$mfa = $context['mfa'] ?? [];
$methods = array_values(array_filter($mfa['methods'] ?? []));

ob_start();
?>
<?php require __DIR__ . '/partials/account-nav.php'; ?>
<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>امنیت و ورود</h2>
            <p class="admin-muted">وضعیت امن نشست جاری و احراز هویت چندمرحله‌ای حساب شما.</p>
        </div>
    </div>
    <div class="admin-mini-grid">
        <article class="admin-card"><span>وضعیت ورود فعلی</span><strong>فعال</strong></article>
        <article class="admin-card"><span>تأیید دومرحله‌ای / TOTP</span><strong><?= ($mfa['enabled'] ?? false) ? 'فعال' : 'غیرفعال' ?></strong></article>
        <article class="admin-card"><span>وضعیت تأیید MFA در نشست</span><strong><?= ($mfa['verified'] ?? false) ? 'تأیید شده' : 'تأیید نشده' ?></strong></article>
    </div>
    <div class="admin-field-list admin-field-list--compact">
        <div><span>روش‌های فعال MFA</span><strong><?= admin_h($methods !== [] ? implode('، ', $methods) : '—') ?></strong></div>
        <div><span>کدهای بازیابی</span><strong><?= ($mfa['recovery_codes_available'] ?? false) ? 'موجود' : 'ناموجود' ?></strong></div>
        <div><span>نقش فعال در نشست</span><strong><?= admin_h($context['active_assignment']['role_title'] ?? '—') ?></strong></div>
    </div>
    <div class="admin-empty-state">مدیریت دستگاه‌های مورد اعتماد و تنظیمات تکمیلی امنیت در فاز بعدی اضافه می‌شود.</div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

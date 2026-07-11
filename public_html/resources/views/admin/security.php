<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

ob_start();
?>
<?php require __DIR__ . '/partials/account-nav.php'; ?>
<section class="admin-section">
    <h2>امنیت و ورود</h2>
    <div class="admin-mini-grid">
        <article class="admin-card"><span>رمز یکبارمصرف</span><strong><?= ($context['mfa']['enabled'] ?? false) ? 'فعال' : 'غیرفعال' ?></strong></article>
        <article class="admin-card"><span>نشست ورود</span><strong>فعال</strong></article>
        <article class="admin-card"><span>نقش فعال</span><strong><?= admin_h($context['active_assignment']['role_title'] ?? '-') ?></strong></article>
    </div>
    <div class="admin-empty-state">مدیریت دستگاه‌های مورد اعتماد و تنظیمات تکمیلی امنیت در فاز بعدی اضافه می‌شود.</div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

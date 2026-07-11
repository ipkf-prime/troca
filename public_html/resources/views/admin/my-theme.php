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
    <h2>پوسته نمایشی من</h2>
    <p class="admin-muted">در این فاز پوسته پنل به صورت سراسری مدیریت می‌شود. تنظیمات اختصاصی کاربر در فاز بعدی اضافه خواهد شد.</p>
    <div class="admin-empty-state">برای تغییر پوسته سراسری به بخش «پوسته پنل» مراجعه کنید.</div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$user = $context['user'] ?? [];

ob_start();
?>
<?php require __DIR__ . '/partials/account-nav.php'; ?>
<section class="admin-section">
    <h2>اطلاعات حساب</h2>
    <p class="admin-muted">در این نسخه، اطلاعات حساب فقط نمایش داده می‌شود. تغییر ایمیل، موبایل و نام کاربری از جریان تایید هویت انجام خواهد شد.</p>
    <div class="admin-field-list">
        <div><span>شناسه کاربر</span><strong><?= admin_h($user['id'] ?? '-') ?></strong></div>
        <div><span>نام کاربری</span><strong><?= admin_h($user['username'] ?? '-') ?></strong></div>
        <div><span>ایمیل</span><strong><?= admin_h($user['email'] ?? '-') ?></strong></div>
        <div><span>موبایل</span><strong><?= admin_h($user['mobile'] ?? '-') ?></strong></div>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

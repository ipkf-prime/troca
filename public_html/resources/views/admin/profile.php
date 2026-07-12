<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$user = $context['user'] ?? [];
$active = $context['active_assignment'] ?? [];

ob_start();
?>
<?php require __DIR__ . '/partials/account-nav.php'; ?>

<section class="admin-section">
    <div class="admin-section__header">
        <div>
            <h2>پروفایل کاربری</h2>
            <p class="admin-muted">نمای کلی اطلاعات کاربر و نقش فعال در سامانه.</p>
        </div>
    </div>
    <div class="admin-mini-grid">
        <article class="admin-card"><span>نام</span><strong><?= admin_h($user['name'] ?? '-') ?></strong></article>
        <article class="admin-card"><span>نام کاربری</span><strong><?= admin_h($user['username'] ?? '-') ?></strong></article>
        <article class="admin-card"><span>نقش فعال</span><strong><?= admin_h($active['role_title'] ?? '-') ?></strong></article>
    </div>
</section>

<section class="admin-section">
    <h2>اطلاعات تماس</h2>
    <div class="admin-field-list">
        <div><span>ایمیل</span><strong><?= admin_h($user['email'] ?? '-') ?></strong></div>
        <div><span>موبایل</span><strong><?= admin_h($user['mobile'] ?? '-') ?></strong></div>
        <div><span>وضعیت حساب</span><strong><?= admin_h($user['status'] ?? '-') ?></strong></div>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/modules/users">مدیریت کاربران</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/users">کاربران</a>
    <span aria-hidden="true">/</span>
    <span>کاربر پیدا نشد</span>
</nav>

<section class="admin-section">
    <div class="admin-empty-state">
        <h2>کاربر پیدا نشد</h2>
        <p>کاربر موردنظر وجود ندارد یا در دسترس نیست.</p>
        <a class="admin-button admin-button--primary" href="/admin/users">بازگشت به کاربران</a>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

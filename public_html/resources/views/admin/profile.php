<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$user = $context['user'];
$active = $context['active_assignment'];
$mfa = $context['mfa'];

ob_start();
?>
<section class="admin-section">
    <h2>اطلاعات کاربر</h2>
    <dl class="admin-profile">
        <dt>نام</dt>
        <dd><?= admin_h($user['name'] ?? '') ?></dd>
        <dt>نام کاربری</dt>
        <dd><?= admin_h($user['username'] ?? '') ?></dd>
        <dt>ایمیل</dt>
        <dd><?= admin_h($user['email'] ?? '') ?></dd>
        <dt>موبایل</dt>
        <dd><?= admin_h($user['mobile'] ?? '') ?></dd>
        <dt>نقش فعال</dt>
        <dd><?= admin_h($active['role_title'] ?? '') ?></dd>
        <dt>وضعیت MFA</dt>
        <dd><?= ($mfa['enabled'] ?? false) ? 'فعال' : 'غیرفعال' ?><?= ($mfa['verified'] ?? false) ? ' / تایید شده' : '' ?></dd>
    </dl>
</section>

<section class="admin-section">
    <h2>تغییرات هویت</h2>
    <p class="admin-muted">ویرایش پروفایل در فاز بعدی به جریان تایید هویت موجود متصل می‌شود.</p>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

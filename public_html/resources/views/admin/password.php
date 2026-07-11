<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$status = $status ?? '';
$error = $error ?? '';

ob_start();
?>
<?php require __DIR__ . '/partials/account-nav.php'; ?>
<?php if ($status === 'updated'): ?>
    <div class="admin-notice">کلمه عبور با موفقیت تغییر کرد.</div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="admin-alert"><?= admin_h($error) ?></div>
<?php endif; ?>
<section class="admin-section">
    <h2>تغییر کلمه عبور</h2>
    <form method="post" action="/admin/password" class="admin-form">
        <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
        <label><span>کلمه عبور فعلی</span><input type="password" name="current_password" autocomplete="current-password" required></label>
        <label><span>کلمه عبور جدید</span><input type="password" name="password" autocomplete="new-password" required></label>
        <label><span>تکرار کلمه عبور جدید</span><input type="password" name="password_confirmation" autocomplete="new-password" required></label>
        <div class="admin-form-actions"><button type="submit">ذخیره کلمه عبور</button></div>
    </form>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

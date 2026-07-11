<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$title = $title ?? 'بازیابی کلمه عبور';
$sent = $sent ?? false;
$identifier = $identifier ?? '';
$themeService = new \App\Services\AdminThemeService();
$theme = $themeService->systemTheme();
$themeAssets = $themeService->assetUrls();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= admin_h($title) ?> | IPKF</title>
    <link rel="stylesheet" href="<?= admin_h($themeAssets['admin_css']) ?>">
    <style id="admin-theme-vars"><?= "\n" . $themeService->cssVariables() . "\n" ?></style>
</head>
<body class="admin-auth-page" data-admin-theme="<?= admin_h($theme['canonical_preset'] ?? $theme['active_preset'] ?? 'official_emerald') ?>" data-admin-theme-source="system">
    <main class="admin-auth">
        <section class="admin-auth__panel">
            <div class="admin-auth__brand">
                <span class="admin-auth__mark">?</span>
                <div>
                    <p class="admin-kicker">بازیابی دسترسی</p>
                    <h1>بازیابی کلمه عبور</h1>
                </div>
            </div>
            <p class="admin-muted">شناسه حساب را وارد کنید. اگر حساب معتبر باشد، راهنمای بازیابی از مسیرهای تنظیم‌شده ارسال می‌شود.</p>

            <?php if ($sent): ?>
                <div class="admin-notice">اگر این حساب در سامانه وجود داشته باشد، راهنمای بازیابی برای آن ارسال می‌شود.</div>
            <?php endif; ?>

            <form method="post" action="/admin/forgot-password" class="admin-form">
                <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                <label>
                    <span>ایمیل، موبایل یا نام کاربری</span>
                    <input name="login" value="<?= admin_h($identifier) ?>" autocomplete="username" data-autofocus="true" autofocus required>
                </label>
                <button type="submit">ارسال راهنمای بازیابی</button>
            </form>

            <div class="admin-auth-links">
                <a href="/admin/login">بازگشت به ورود</a>
                <a href="/">صفحه اصلی</a>
            </div>
        </section>
    </main>
    <script src="<?= admin_h($themeAssets['admin_js']) ?>" defer></script>
</body>
</html>

<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$title = $title ?? 'ورود به پنل مدیریت';
$error = $error ?? null;
$login = $login ?? '';
$themeService = new \App\Services\AdminThemeService();
$theme = $themeService->systemTheme();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= admin_h($title) ?> | IPKF</title>
    <link rel="stylesheet" href="/assets/admin/css/admin.css">
    <style><?= "\n" . $themeService->cssVariables() . "\n" ?></style>
</head>
<body class="admin-auth-page">
    <main class="admin-auth">
        <section class="admin-auth__panel">
            <div class="admin-auth__brand">
                <?php if (($theme['logo_url'] ?? '') !== ''): ?>
                    <img class="admin-brand__logo" src="<?= admin_h($theme['logo_url']) ?>" alt="">
                <?php else: ?>
                    <span class="admin-auth__mark">T</span>
                <?php endif; ?>
                <div>
                    <p class="admin-kicker"><?= admin_h($theme['brand_name'] ?? 'IPKF / Troca') ?></p>
                    <h1>ورود به پنل مدیریت</h1>
                </div>
            </div>
            <p class="admin-muted">برای ورود، ایمیل، موبایل یا نام کاربری خود را وارد کنید.</p>

            <?php if ($error): ?>
                <div class="admin-alert"><?= admin_h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/admin/login" class="admin-form">
                <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                <label>
                    <span>شناسه ورود</span>
                    <input name="login" value="<?= admin_h($login) ?>" autocomplete="username" placeholder="ایمیل، موبایل یا نام کاربری" required>
                </label>
                <label>
                    <span>کلمه عبور</span>
                    <input name="password" type="password" autocomplete="current-password" placeholder="کلمه عبور خود را وارد کنید" required>
                </label>
                <button type="submit">ورود به پنل</button>
            </form>

            <div class="admin-auth-links">
                <a href="/admin/forgot-password">بازیابی کلمه عبور</a>
                <a href="/">بازگشت به صفحه اصلی</a>
            </div>
        </section>
    </main>
</body>
</html>

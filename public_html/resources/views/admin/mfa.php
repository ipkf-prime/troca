<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$title = $title ?? 'رمز یکبارمصرف';
$error = $error ?? null;
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
                <span class="admin-auth__mark">✓</span>
                <div>
                    <p class="admin-kicker">امنیت ورود</p>
                    <h1>رمز یکبارمصرف</h1>
                </div>
            </div>
            <p class="admin-muted">رمز یکبارمصرف حساب خود را وارد کنید تا ورود کامل شود.</p>

            <?php if ($error): ?>
                <div class="admin-alert"><?= admin_h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/admin/mfa" class="admin-form">
                <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                <label>
                    <span>رمز یکبارمصرف</span>
                    <input name="code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="8" data-autofocus="true" autofocus placeholder="کد ۶ رقمی" required>
                </label>
                <button type="submit">تایید و ادامه</button>
            </form>

            <div class="admin-auth-links">
                <a href="/admin/mfa/recovery">ورود با کد بازیابی</a>
                <a href="/admin/support">عدم دسترسی به رمز یکبارمصرف</a>
            </div>
        </section>
    </main>
    <script src="<?= admin_h($themeAssets['admin_js']) ?>" defer></script>
</body>
</html>

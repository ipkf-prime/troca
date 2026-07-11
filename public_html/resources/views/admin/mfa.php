<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$title = $title ?? 'تایید دومرحله ای';
$error = $error ?? null;
$themeService = new \App\Services\AdminThemeService();
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
            <p class="admin-kicker">IPKF Security</p>
            <h1>تایید دومرحله ای</h1>
            <p class="admin-muted">کد برنامه احراز هویت یا یکی از کدهای بازیابی را وارد کنید.</p>

            <?php if ($error): ?>
                <div class="admin-alert"><?= admin_h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/admin/mfa" class="admin-form">
                <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                <label>
                    <span>کد TOTP</span>
                    <input name="code" inputmode="numeric" autocomplete="one-time-code">
                </label>
                <label>
                    <span>کد بازیابی</span>
                    <input name="recovery_code" autocomplete="one-time-code">
                </label>
                <button type="submit">تایید و ورود</button>
            </form>
        </section>
    </main>
</body>
</html>

<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$title = $title ?? 'کد بازیابی';
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
            <div class="admin-auth__brand">
                <span class="admin-auth__mark">R</span>
                <div>
                    <p class="admin-kicker">امنیت ورود</p>
                    <h1>ورود با کد بازیابی</h1>
                </div>
            </div>
            <p class="admin-muted">اگر به رمز یکبارمصرف دسترسی ندارید، یکی از کدهای بازیابی ذخیره‌شده را وارد کنید.</p>

            <?php if ($error): ?>
                <div class="admin-alert"><?= admin_h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/admin/mfa/recovery" class="admin-form">
                <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                <label>
                    <span>کد بازیابی</span>
                    <input name="recovery_code" autocomplete="one-time-code" placeholder="XXXX-XXXX-XXXX" required>
                </label>
                <button type="submit">تایید و ادامه</button>
            </form>

            <div class="admin-auth-links">
                <a href="/admin/mfa">بازگشت به رمز یکبارمصرف</a>
                <a href="/admin/support">نیاز به پشتیبانی دارم</a>
            </div>
        </section>
    </main>
</body>
</html>

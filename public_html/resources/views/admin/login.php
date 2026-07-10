<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$title = $title ?? 'ورود به پنل مدیریت';
$error = $error ?? null;
$login = $login ?? '';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= admin_h($title) ?> | IPKF</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-auth-page">
    <main class="admin-auth">
        <section class="admin-auth__panel">
            <p class="admin-kicker">IPKF / Troca</p>
            <h1>ورود به پنل مدیریت</h1>
            <p class="admin-muted">با ایمیل، موبایل یا نام کاربری وارد شوید.</p>

            <?php if ($error): ?>
                <div class="admin-alert"><?= admin_h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/admin/login" class="admin-form">
                <input type="hidden" name="_token" value="<?= admin_h((new \IPKF\Security\Csrf())->token()) ?>">
                <label>
                    <span>شناسه ورود</span>
                    <input name="login" value="<?= admin_h($login) ?>" autocomplete="username" required>
                </label>
                <label>
                    <span>رمز عبور</span>
                    <input name="password" type="password" autocomplete="current-password" required>
                </label>
                <button type="submit">ورود</button>
            </form>
        </section>
    </main>
</body>
</html>

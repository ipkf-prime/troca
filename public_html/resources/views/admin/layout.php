<?php

$context = $context ?? null;
$title = $title ?? 'پنل مدیریت';
$user = $context['user'] ?? null;
$active = $context['active_assignment'] ?? null;
$themeService = new \App\Services\AdminThemeService();
$theme = $themeService->theme();
$avatarUrl = (string) ($user['avatar_url'] ?? $theme['default_avatar_url'] ?? '');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$year = date('Y');

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$systemNav = [
    '/admin/dashboard' => 'داشبورد',
    '/admin/access' => 'دسترسی‌ها',
    '/admin/theme' => 'پوسته پنل',
    '/admin/settings' => 'تنظیمات',
    '/admin/pages' => 'صفحات داخلی',
    '/admin/reports' => 'گزارش‌ها',
    '/admin/support' => 'پشتیبانی',
];

$accountNav = [
    '/admin/profile' => 'پروفایل کاربری',
    '/admin/account' => 'اطلاعات حساب',
    '/admin/security' => 'امنیت و ورود',
    '/admin/mfa' => 'رمز یکبارمصرف',
    '/admin/password' => 'تغییر کلمه عبور',
    '/admin/my-theme' => 'پوسته نمایشی من',
    '/admin/logout' => 'خروج',
];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= admin_h($title) ?> | IPKF</title>
    <link rel="stylesheet" href="/assets/admin/css/admin.css">
    <script src="/assets/admin/js/admin.js" defer></script>
    <style><?= "\n" . $themeService->cssVariables() . "\n" ?></style>
</head>
<body>
    <div class="admin-shell" data-admin-shell>
        <div class="admin-sidebar-overlay" data-admin-sidebar-overlay></div>
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="admin-sidebar__head">
                <a class="admin-brand" href="/admin/dashboard">
                    <?php if (($theme['logo_url'] ?? '') !== ''): ?>
                        <img class="admin-brand__logo" src="<?= admin_h($theme['logo_url']) ?>" alt="">
                    <?php else: ?>
                        <span class="admin-brand__mark">IPKF</span>
                    <?php endif; ?>
                    <span>
                        <strong><?= admin_h($theme['brand_name'] ?? 'پنل مدیریت تروکا') ?></strong>
                        <small>زیرساخت مدیریتی IPKF</small>
                    </span>
                </a>
                <button class="admin-icon-button admin-sidebar__close" type="button" data-admin-sidebar-close aria-label="بستن منو">×</button>
            </div>
            <nav class="admin-nav" aria-label="منوی سامانه">
                <?php foreach ($systemNav as $href => $label): ?>
                    <a class="<?= $currentPath === $href ? 'is-active' : '' ?>" href="<?= admin_h($href) ?>">
                        <?= admin_h($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <button class="admin-icon-button admin-sidebar-toggle" type="button" data-admin-sidebar-toggle aria-controls="admin-sidebar" aria-label="باز کردن منو">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="admin-topbar__title">
                    <p class="admin-kicker"><?= admin_h($theme['brand_name'] ?? 'سامانه هوشمند تروکا') ?></p>
                    <h1><?= admin_h($title) ?></h1>
                </div>
                <div class="admin-topbar__actions">
                    <?php if (($theme['show_active_role'] ?? true) === true): ?>
                        <span class="admin-role"><?= admin_h($active['role_title'] ?? 'بدون نقش فعال') ?></span>
                    <?php endif; ?>
                    <div class="admin-user-menu" data-admin-user-menu>
                        <button class="admin-user-menu__trigger" type="button" data-admin-user-menu-toggle aria-haspopup="true" aria-expanded="false">
                            <?php if ($avatarUrl !== ''): ?>
                                <img class="admin-avatar" src="<?= admin_h($avatarUrl) ?>" alt="">
                            <?php endif; ?>
                            <?php if (($theme['show_user_name'] ?? true) === true): ?>
                                <span><?= admin_h($user['name'] ?? 'کاربر') ?></span>
                            <?php endif; ?>
                            <b aria-hidden="true">▾</b>
                        </button>
                        <div class="admin-dropdown" role="menu">
                            <?php foreach ($accountNav as $href => $label): ?>
                                <a class="<?= $currentPath === $href ? 'is-active' : '' ?> <?= $href === '/admin/logout' ? 'is-danger' : '' ?>" href="<?= admin_h($href) ?>" role="menuitem">
                                    <?= admin_h($label) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </header>

            <?= $content ?? '' ?>

            <?php if (($theme['footer_enabled'] ?? true) === true): ?>
                <footer class="admin-footer">
                    <span><?= admin_h($theme['footer_text'] ?? 'کلیه حقوق این سامانه متعلق به سامانه هوشمند تروکا می‌باشد.') ?></span>
                    <span><?= admin_h($year) ?></span>
                </footer>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

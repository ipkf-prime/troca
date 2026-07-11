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

$nav = [
    '/admin/dashboard' => 'داشبورد',
    '/admin/access' => 'دسترسی',
    '/admin/theme' => 'پوسته',
    '/admin/profile' => 'پروفایل',
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
    <style><?= "\n" . $themeService->cssVariables() . "\n" ?></style>
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="/admin/dashboard">
                <?php if (($theme['logo_url'] ?? '') !== ''): ?>
                    <img class="admin-brand__logo" src="<?= admin_h($theme['logo_url']) ?>" alt="">
                <?php else: ?>
                    <span class="admin-brand__mark">IPKF</span>
                <?php endif; ?>
                <span>
                    <strong><?= admin_h($theme['brand_name'] ?? 'پنل مدیریت تروکا') ?></strong>
                    <small>پنل مدیریت</small>
                </span>
            </a>
            <nav class="admin-nav" aria-label="Admin navigation">
                <?php foreach ($nav as $href => $label): ?>
                    <a class="<?= $currentPath === $href ? 'is-active' : '' ?> <?= $href === '/admin/logout' ? 'admin-nav__logout' : '' ?>" href="<?= admin_h($href) ?>">
                        <?= admin_h($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <p class="admin-kicker"><?= admin_h($theme['brand_name'] ?? 'سامانه هوشمند تروکا') ?></p>
                    <h1><?= admin_h($title) ?></h1>
                </div>
                <div class="admin-user">
                    <?php if ($avatarUrl !== ''): ?>
                        <img class="admin-avatar" src="<?= admin_h($avatarUrl) ?>" alt="">
                    <?php endif; ?>
                    <?php if (($theme['show_active_role'] ?? true) === true): ?>
                        <span class="admin-role"><?= admin_h($active['role_title'] ?? 'بدون نقش فعال') ?></span>
                    <?php endif; ?>
                    <?php if (($theme['show_user_name'] ?? true) === true): ?>
                        <span><?= admin_h($user['name'] ?? '') ?></span>
                    <?php endif; ?>
                </div>
            </header>

            <?= $content ?? '' ?>

            <?php if (($theme['footer_enabled'] ?? true) === true): ?>
                <footer class="admin-footer">
                    <?= admin_h($theme['footer_text'] ?? 'کلیه حقوق این وب‌سایت متعلق به سامانه هوشمند تروکا می‌باشد.') ?>
                    <span><?= admin_h($year) ?></span>
                </footer>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

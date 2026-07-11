<?php

$context = $context ?? null;
$title = $title ?? 'پنل مدیریت';
$user = $context['user'] ?? null;
$active = $context['active_assignment'] ?? null;
$themeService = new \App\Services\AdminThemeService();
$theme = $themeService->theme();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$nav = [
    '/admin/dashboard' => 'داشبورد',
    '/admin/access' => 'دسترسی',
    '/admin/theme' => 'پوسته',
    '/admin/profile' => 'پروفایل',
];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= admin_h($title) ?> | IPKF</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style><?= "\n" . admin_h($themeService->cssVariables()) . "\n" ?></style>
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
                    <small>زیرساخت مدیریت دسترسی</small>
                </span>
            </a>
            <nav class="admin-nav" aria-label="Admin navigation">
                <?php foreach ($nav as $href => $label): ?>
                    <a class="<?= $currentPath === $href ? 'is-active' : '' ?>" href="<?= admin_h($href) ?>">
                        <?= admin_h($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <p class="admin-kicker">IPKF Framework</p>
                    <h1><?= admin_h($title) ?></h1>
                </div>
                <div class="admin-user">
                    <span class="admin-role"><?= admin_h($active['role_title'] ?? 'بدون نقش فعال') ?></span>
                    <span><?= admin_h($user['name'] ?? '') ?></span>
                    <a class="admin-logout" href="/admin/logout">خروج</a>
                </div>
            </header>

            <?= $content ?? '' ?>
        </main>
    </div>
</body>
</html>

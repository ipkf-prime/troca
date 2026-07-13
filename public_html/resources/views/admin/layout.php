<?php

$context = $context ?? null;
$title = $title ?? 'پنل مدیریت';
$user = $context['user'] ?? null;
$active = $context['active_assignment'] ?? null;
$themeService = new \App\Services\AdminThemeService();
$themeUserId = isset($context['user_id']) ? (int) $context['user_id'] : null;
$theme = $themeService->theme($themeUserId);
$avatarUrl = (string) ($user['avatar_url'] ?? $theme['default_avatar_url'] ?? '');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$year = date('Y');
$themeAssets = $themeService->assetUrls();
$themeSource = $themeService->resolvedPresetSource($themeUserId);

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

if (!function_exists('admin_nav_is_active')) {
    function admin_nav_is_active(array $item, string $currentPath): bool
    {
        $paths = $item['active_paths'] ?? [$item['url'] ?? '#'];

        foreach ($paths as $path) {
            if ($currentPath === (string) $path) {
                return true;
            }
        }

        return false;
    }
}

$systemNav = $context['navigation']['system'] ?? [];
$accountNav = $context['navigation']['account'] ?? [];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= admin_h($title) ?> | IPKF</title>
    <link rel="stylesheet" href="<?= admin_h($themeAssets['icons_css']) ?>">
    <link rel="stylesheet" href="<?= admin_h($themeAssets['admin_css']) ?>">
    <script src="<?= admin_h($themeAssets['admin_js']) ?>" defer></script>
    <style id="admin-theme-vars"><?= "\n" . $themeService->cssVariables($themeUserId) . "\n" ?></style>
</head>
<body dir="rtl" data-admin-theme="<?= admin_h($theme['canonical_preset'] ?? $theme['active_preset'] ?? 'official_emerald') ?>" data-admin-theme-source="<?= admin_h($themeSource) ?>">
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
                <?php foreach ($systemNav as $item): ?>
                    <?php $href = (string) ($item['url'] ?? '#'); ?>
                    <a class="<?= admin_nav_is_active($item, $currentPath) ? 'is-active' : '' ?>" href="<?= admin_h($href) ?>">
                        <span class="admin-nav__icon">
                            <?= \App\Support\AdminIcon::html((string) ($item['icon'] ?? 'dashboard')) ?>
                        </span>
                        <span><?= admin_h($item['title'] ?? '') ?></span>
                        <?php if (($item['badge'] ?? '') !== ''): ?>
                            <small class="admin-nav__badge"><?= admin_h($item['badge']) ?></small>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div class="admin-main">
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
                            <?php foreach ($accountNav as $item): ?>
                                <?php $href = (string) ($item['url'] ?? '#'); ?>
                                <a class="<?= $currentPath === $href ? 'is-active' : '' ?> <?= $href === '/admin/logout' ? 'is-danger' : '' ?>" href="<?= admin_h($href) ?>" role="menuitem">
                                    <?= admin_h($item['title'] ?? '') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </header>

            <main class="admin-content">
                <?= $content ?? '' ?>
            </main>

            <?php if (($theme['footer_enabled'] ?? true) === true): ?>
                <footer class="admin-footer">
                    <span><?= admin_h($theme['footer_text'] ?? 'کلیه حقوق این سامانه متعلق به سامانه هوشمند تروکا می‌باشد.') ?></span>
                    <span><?= admin_h($year) ?></span>
                </footer>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

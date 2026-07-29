<?php

$context = $context ?? null;

if (!function_exists('admin_fa')) {
    function admin_fa(string $entities): string
    {
        return html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

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

            if (str_ends_with((string) $path, '/*') && str_starts_with($currentPath, rtrim((string) $path, '/*') . '/')) {
                return true;
            }
        }

        return false;
    }
}

$title = $title ?? admin_fa('&#x067E;&#x0646;&#x0644; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A;');
$user = $context['user'] ?? null;
$active = $context['active_assignment'] ?? null;
$themeService = new \App\Services\AdminThemeService();
$themeUserId = isset($context['user_id']) ? (int) $context['user_id'] : null;
$theme = $themeService->theme($themeUserId);
$avatarUrl = (string) ($user['avatar_url'] ?? $theme['default_avatar_url'] ?? '');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$year = \App\Support\AdminFormat::digits(date('Y'));
$runtimeVersion = \App\Support\AdminFormat::digits(\IPKF\Support\Version::CURRENT);
$themeAssets = $themeService->assetUrls();
$themeSource = $themeService->resolvedPresetSource($themeUserId);
$moduleShell = is_array($context['module_shell'] ?? null) ? $context['module_shell'] : null;
$moduleShellKey = (string) ($moduleShell['key'] ?? '');
$isModuleShell = $moduleShellKey !== '';
$brandTitle = $isModuleShell ? (string) ($moduleShell['title'] ?? '') : (string) ($theme['brand_name'] ?? admin_fa('&#x067E;&#x0646;&#x0644; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x062A;&#x0631;&#x0648;&#x06A9;&#x0627;'));
$brandSubtitle = $isModuleShell ? (string) ($moduleShell['subtitle'] ?? '') : admin_fa('&#x0632;&#x06CC;&#x0631;&#x0633;&#x0627;&#x062E;&#x062A; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A;&#x06CC; IPKF');
$brandHome = $isModuleShell ? (string) ($moduleShell['home_url'] ?? '/admin/dashboard') : '/admin/dashboard';
$moduleAssets = \App\Services\AdminModuleUiContract::safeAssets(
    is_array($moduleShell['assets'] ?? null) ? $moduleShell['assets'] : ['css' => [], 'js' => []]
);
$moduleCssAssets = $moduleAssets['css'];
$moduleJsAssets = $moduleAssets['js'];
$systemNav = $context['navigation']['system'] ?? [];
$accountNav = $context['navigation']['account'] ?? [];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= admin_h($title) ?> | IPKF</title>
    <link rel="stylesheet" href="<?= admin_h($themeAssets['icons_css']) ?>">
    <link rel="stylesheet" href="<?= admin_h($themeAssets['admin_css']) ?>">
    <style id="admin-theme-vars"><?= "\n" . $themeService->cssVariables($themeUserId) . "\n" ?></style>
    <?php foreach ($moduleCssAssets as $asset): ?>
        <link rel="stylesheet" href="<?= admin_h($asset) ?>?v=<?= admin_h((string) (@filemtime(BASE_PATH . '/public' . $asset) ?: '1')) ?>" data-module-asset="css">
    <?php endforeach; ?>
    <script src="<?= admin_h($themeAssets['admin_js']) ?>" defer></script>
    <?php foreach ($moduleJsAssets as $asset): ?>
        <script src="<?= admin_h($asset) ?>?v=<?= admin_h((string) (@filemtime(BASE_PATH . '/public' . $asset) ?: '1')) ?>" defer data-module-asset="js"></script>
    <?php endforeach; ?>
</head>
<body dir="rtl" class="<?= $isModuleShell ? 'admin-module-shell ' . admin_h($moduleShellKey) . '-shell' : 'core-shell' ?>" data-admin-shell-kind="<?= admin_h($isModuleShell ? $moduleShellKey : 'core') ?>" data-admin-module-ui-contract="shared-admin-shell" data-admin-theme="<?= admin_h($theme['canonical_preset'] ?? $theme['active_preset'] ?? 'official_emerald') ?>" data-admin-theme-source="<?= admin_h($themeSource) ?>">
    <div class="admin-shell" data-admin-shell>
        <div class="admin-sidebar-overlay" data-admin-sidebar-overlay></div>
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="admin-sidebar__head">
                <a class="admin-brand" href="<?= admin_h($brandHome) ?>">
                    <?php if (($theme['logo_url'] ?? '') !== ''): ?>
                        <img class="admin-brand__logo" src="<?= admin_h($theme['logo_url']) ?>" alt="">
                    <?php else: ?>
                        <span class="admin-brand__mark">IPKF</span>
                    <?php endif; ?>
                    <span>
                        <strong><?= admin_h($brandTitle) ?></strong>
                        <small><?= admin_h($brandSubtitle) ?></small>
                    </span>
                </a>
                <button class="admin-icon-button admin-sidebar__close" type="button" data-admin-sidebar-close aria-label="<?= admin_h(admin_fa('&#x0628;&#x0633;&#x062A;&#x0646; &#x0645;&#x0646;&#x0648;')) ?>">&times;</button>
            </div>
            <nav class="admin-nav" aria-label="<?= admin_h(admin_fa('&#x0645;&#x0646;&#x0648;&#x06CC; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;')) ?>">
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
                <button class="admin-icon-button admin-sidebar-toggle" type="button" data-admin-sidebar-toggle aria-controls="admin-sidebar" aria-label="<?= admin_h(admin_fa('&#x0628;&#x0627;&#x0632; &#x06A9;&#x0631;&#x062F;&#x0646; &#x0645;&#x0646;&#x0648;')) ?>">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="admin-topbar__title">
                    <p class="admin-kicker"><?= admin_h($brandTitle) ?></p>
                    <h1><?= admin_h($title) ?></h1>
                </div>
                <div class="admin-topbar__actions">
                    <?php if (($theme['show_active_role'] ?? true) === true): ?>
                        <span class="admin-role"><?= admin_h($active['role_title'] ?? admin_fa('&#x0628;&#x062F;&#x0648;&#x0646; &#x0646;&#x0642;&#x0634; &#x0641;&#x0639;&#x0627;&#x0644;')) ?></span>
                    <?php endif; ?>
                    <div class="admin-user-menu" data-admin-user-menu>
                        <button class="admin-user-menu__trigger" type="button" data-admin-user-menu-toggle aria-haspopup="true" aria-expanded="false">
                            <?php if ($avatarUrl !== ''): ?>
                                <img class="admin-avatar" src="<?= admin_h($avatarUrl) ?>" alt="">
                            <?php endif; ?>
                            <?php if (($theme['show_user_name'] ?? true) === true): ?>
                                <span><?= admin_h($user['name'] ?? admin_fa('&#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;')) ?></span>
                            <?php endif; ?>
                            <b aria-hidden="true">&#x25BE;</b>
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
                    <span><?= admin_h($theme['footer_text'] ?? admin_fa('&#x06A9;&#x0644;&#x06CC;&#x0647; &#x062D;&#x0642;&#x0648;&#x0642; &#x0627;&#x06CC;&#x0646; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647; &#x0645;&#x062A;&#x0639;&#x0644;&#x0642; &#x0628;&#x0647; &#x062A;&#x0631;&#x0648;&#x06A9;&#x0627; &#x0645;&#x06CC;&#x200C;&#x0628;&#x0627;&#x0634;&#x062F;.')) ?></span>
                    <span class="admin-footer__meta">
                        <span><?= admin_h(admin_fa('&#x0646;&#x0633;&#x062E;&#x0647;')) ?> <?= admin_h($runtimeVersion) ?></span>
                        <span><?= admin_h($year) ?></span>
                    </span>
                </footer>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

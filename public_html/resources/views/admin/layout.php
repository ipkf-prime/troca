<?php

$context = $context ?? null;

if (!function_exists('admin_fa')) {
    function admin_fa(string $entities): string
    {
        return html_entity_decode(
            $entities,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }
}

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

if (!function_exists('admin_nav_is_active')) {
    function admin_nav_is_active(
        array $item,
        string $currentPath
    ): bool {
        $paths = $item['active_paths']
            ?? [$item['url'] ?? '#'];

        foreach ($paths as $path) {
            if ($currentPath === (string) $path) {
                return true;
            }

            if (
                str_ends_with((string) $path, '/*')
                && str_starts_with(
                    $currentPath,
                    rtrim((string) $path, '/*') . '/'
                )
            ) {
                return true;
            }
        }

        return false;
    }
}

$title = $title
    ?? admin_fa(
        '&#x067E;&#x0646;&#x0644; '
        . '&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A;'
    );

$user = $context['user'] ?? null;
$active = $context['active_assignment'] ?? null;
$themeService = new \App\Services\AdminThemeService();
$themeUserId = isset($context['user_id'])
    ? (int) $context['user_id']
    : null;

$theme = $themeService->theme($themeUserId);

$profileAvatarUrl = $themeUserId !== null
    ? (new \App\Services\ProfileAvatarService())
        ->urlForUser((int) $themeUserId)
    : '';

$avatarUrl = $profileAvatarUrl !== ''
    ? $profileAvatarUrl
    : (string) (
        $user['avatar_url']
        ?? $theme['default_avatar_url']
        ?? ''
    );

$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
) ?: '/';

$year = \App\Support\AdminFormat::digits(date('Y'));
$runtimeVersion = \App\Support\AdminFormat::digits(
    \IPKF\Support\Version::CURRENT
);

$themeAssets = $themeService->assetUrls();
$themeSource = $themeService->resolvedPresetSource(
    $themeUserId
);

$moduleShell = is_array(
    $context['module_shell'] ?? null
)
    ? $context['module_shell']
    : null;

$moduleShellKey = (string) (
    $moduleShell['key'] ?? ''
);

$isModuleShell = $moduleShellKey !== '';

$brandTitle = $isModuleShell
    ? (string) ($moduleShell['title'] ?? '')
    : (string) (
        $theme['brand_name']
        ?? admin_fa(
            '&#x067E;&#x0646;&#x0644; '
            . '&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; '
            . '&#x062A;&#x0631;&#x0648;&#x06A9;&#x0627;'
        )
    );

$brandSubtitle = $isModuleShell
    ? (string) ($moduleShell['subtitle'] ?? '')
    : admin_fa(
        '&#x0632;&#x06CC;&#x0631;&#x0633;&#x0627;&#x062E;&#x062A; '
        . '&#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A;&#x06CC; '
        . 'IPKF'
    );

$brandHome = $isModuleShell
    ? (string) (
        $moduleShell['home_url']
        ?? '/admin/dashboard'
    )
    : '/admin/dashboard';

$moduleAssets =
    \App\Services\AdminModuleUiContract::safeAssets(
        is_array($moduleShell['assets'] ?? null)
            ? $moduleShell['assets']
            : ['css' => [], 'js' => []]
    );

$moduleCssAssets = $moduleAssets['css'];
$moduleJsAssets = $moduleAssets['js'];

$navigationShell = $isModuleShell
    ? $moduleShellKey
    : 'core';

$dynamicNavigation =
    new \App\Services\DynamicAdminNavigationService();

$systemNav = $themeUserId !== null
    ? $dynamicNavigation->navigation(
        (int) $themeUserId,
        $navigationShell
    )
    : [];

$coreTopbarNav = $themeUserId !== null
    ? $dynamicNavigation->topbar(
        (int) $themeUserId,
        'core'
    )
    : [];

$moduleTopbarNav = $themeUserId !== null
    && $navigationShell !== 'core'
        ? $dynamicNavigation->topbar(
            (int) $themeUserId,
            $navigationShell
        )
        : [];

$topbarNavByKey = [];

foreach (
    array_merge($coreTopbarNav, $moduleTopbarNav)
    as $topbarItem
) {
    $topbarKey = (string) (
        $topbarItem['key']
        ?? $topbarItem['url']
        ?? uniqid('topbar-', true)
    );

    if (!isset($topbarNavByKey[$topbarKey])) {
        $topbarNavByKey[$topbarKey] = $topbarItem;
    }
}

$topbarNav = array_values($topbarNavByKey);

$accountNav = $themeUserId !== null
    ? $dynamicNavigation->account(
        (int) $themeUserId,
        'core'
    )
    : [];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title><?= admin_h($title) ?> | IPKF</title>

    <link
        rel="stylesheet"
        href="<?= admin_h($themeAssets['icons_css']) ?>"
    >
    <link
        rel="stylesheet"
        href="<?= admin_h($themeAssets['admin_css']) ?>"
    >

    <style id="admin-theme-vars"><?=
        "\n"
        . $themeService->cssVariables($themeUserId)
        . "\n"
    ?></style>

    <style id="dynamic-navigation-style">
        .admin-nav__group {
            display: grid;
            gap: 3px;
            min-width: 0;
        }

        .admin-nav__group-toggle {
            align-items: center;
            appearance: none;
            background: transparent;
            border: 0;
            border-radius: 10px;
            color: var(--admin-sidebar-text-muted);
            cursor: pointer;
            display: flex;
            font: inherit;
            gap: 11px;
            min-height: 44px;
            padding: 10px 11px;
            text-align: right;
            width: 100%;
        }

        .admin-nav__group-toggle:hover {
            background: rgba(255, 255, 255, .12);
            color: var(--admin-sidebar-text);
        }

        .admin-nav__group-toggle.is-active {
            background: var(--admin-sidebar-active-bg);
            color: var(--admin-sidebar-active-text);
            font-weight: var(--admin-font-weight-bold);
        }

        .admin-nav__group-toggle.is-active
            .admin-nav__icon {
            background: rgba(255, 255, 255, .22);
            border-color: rgba(255, 255, 255, .24);
        }

        .admin-nav__group-title {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-nav__group-chevron {
            border-bottom: 2px solid currentColor;
            border-left: 2px solid currentColor;
            flex: 0 0 8px;
            height: 8px;
            margin-inline-start: 2px;
            transform: rotate(-45deg);
            transition: transform .18s ease;
            width: 8px;
        }

        .admin-nav__group-toggle[aria-expanded="true"]
            .admin-nav__group-chevron {
            transform: rotate(135deg);
        }

        .admin-nav__children {
            display: grid;
            gap: 2px;
            margin: 1px 45px 6px 4px;
            min-width: 0;
        }

        .admin-nav__children[hidden] {
            display: none;
        }

        .admin-nav__children a {
            border-radius: 8px;
            font-size: .78rem;
            min-height: 34px;
            padding: 6px 9px;
        }

        .admin-nav__children a.is-active {
            background: var(--admin-primary-soft);
            color: var(--admin-primary);
            font-weight: var(--admin-font-weight-bold);
        }

        .admin-topbar-notification {
            align-items: center;
            overflow: visible;
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            display: inline-flex;
            height: 42px;
            justify-content: center;
            min-width: 42px;
            padding: .45rem;
            position: relative;
            text-decoration: none;
        }

        .admin-topbar-notification
            > span:not(.admin-icon):not(.admin-bell-icon) {
            display: none;
        }

        .admin-topbar-notification--approval {
            gap: .35rem;
            padding-inline: .65rem;
        }

        .admin-topbar-notification--approval
            > span:not(.admin-icon):not(.admin-bell-icon) {
            display: inline;
            font-size: .7rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .admin-topbar-notification--approval.has-badge {
            background: #fff7e6;
            border-color: #d79a2b;
            color: #8a5a00;
        }

        .admin-topbar-notification .admin-icon,
        .admin-topbar-notification .admin-bell-icon {
            height: 1.15rem;
            width: 1.15rem;
        }

        .admin-topbar-notification.has-badge {
            background: var(--admin-primary-soft);
            border-color: var(--admin-primary);
            color: var(--admin-primary);
        }

        .admin-topbar-notification b {
            align-items: center;
            background: #d92d20;
            border: 2px solid var(--admin-surface);
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-size: .62rem;
            font-weight: 800;
            height: 19px;
            justify-content: center;
            min-width: 19px;
            padding-inline: 3px;
            position: absolute;
            right: -5px;
            top: -5px;
            transform: none;
            z-index: 2;
        }

        .admin-bell-icon svg {
            fill: none;
            height: 100%;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 1.8;
            width: 100%;
        }

        .admin-dropdown a {
            align-items: center;
            display: flex;
            gap: .5rem;
            justify-content: space-between;
        }

        .admin-account-nav-badge {
            background: var(--admin-primary-soft);
            border-radius: 999px;
            color: var(--admin-primary);
            font-size: .66rem;
            min-width: 1.35rem;
            padding: .12rem .35rem;
            text-align: center;
        }

        @media (max-width: 760px) {
            .admin-nav__children {
                margin-inline-start: 38px;
            }
        }
    </style>

    <?php foreach ($moduleCssAssets as $asset): ?>
        <link
            rel="stylesheet"
            href="<?= admin_h($asset) ?>?v=<?=
                admin_h(
                    (string) (
                        @filemtime(
                            BASE_PATH . '/public' . $asset
                        ) ?: '1'
                    )
                )
            ?>"
            data-module-asset="css"
        >
    <?php endforeach; ?>

    <script
        src="<?= admin_h($themeAssets['admin_js']) ?>"
        defer
    ></script>

    <?php foreach ($moduleJsAssets as $asset): ?>
        <script
            src="<?= admin_h($asset) ?>?v=<?=
                admin_h(
                    (string) (
                        @filemtime(
                            BASE_PATH . '/public' . $asset
                        ) ?: '1'
                    )
                )
            ?>"
            defer
            data-module-asset="js"
        ></script>
    <?php endforeach; ?>
</head>
<body
    dir="rtl"
    class="<?=
        $isModuleShell
            ? 'admin-module-shell '
                . admin_h($moduleShellKey)
                . '-shell'
            : 'core-shell'
    ?>"
    data-admin-shell-kind="<?=
        admin_h(
            $isModuleShell
                ? $moduleShellKey
                : 'core'
        )
    ?>"
    data-admin-module-ui-contract="shared-admin-shell"
    data-admin-theme="<?=
        admin_h(
            $theme['canonical_preset']
            ?? $theme['active_preset']
            ?? 'official_emerald'
        )
    ?>"
    data-admin-theme-source="<?= admin_h($themeSource) ?>"
>
    <div class="admin-shell" data-admin-shell>
        <div
            class="admin-sidebar-overlay"
            data-admin-sidebar-overlay
        ></div>

        <aside
            class="admin-sidebar"
            id="admin-sidebar"
        >
            <div class="admin-sidebar__head">
                <a
                    class="admin-brand"
                    href="<?= admin_h($brandHome) ?>"
                >
                    <?php if (
                        ($theme['logo_url'] ?? '') !== ''
                    ): ?>
                        <img
                            class="admin-brand__logo"
                            src="<?= admin_h(
                                $theme['logo_url']
                            ) ?>"
                            alt=""
                        >
                    <?php else: ?>
                        <span class="admin-brand__mark">
                            IPKF
                        </span>
                    <?php endif; ?>

                    <span>
                        <strong>
                            <?= admin_h($brandTitle) ?>
                        </strong>
                        <small>
                            <?= admin_h($brandSubtitle) ?>
                        </small>
                    </span>
                </a>

                <button
                    class="admin-icon-button
                        admin-sidebar__close"
                    type="button"
                    data-admin-sidebar-close
                    aria-label="<?= admin_h(
                        admin_fa(
                            '&#x0628;&#x0633;&#x062A;&#x0646; '
                            . '&#x0645;&#x0646;&#x0648;'
                        )
                    ) ?>"
                >
                    &times;
                </button>
            </div>

            <nav
                class="admin-nav"
                aria-label="<?= admin_h(
                    admin_fa(
                        '&#x0645;&#x0646;&#x0648;&#x06CC; '
                        . '&#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;'
                    )
                ) ?>"
            >
                <?php foreach ($systemNav as $item): ?>
                    <?php
                    $href = (string) (
                        $item['url'] ?? '#'
                    );

                    $children = is_array(
                        $item['children'] ?? null
                    )
                        ? $item['children']
                        : [];

                    $groupActive = admin_nav_is_active(
                        $item,
                        $currentPath
                    );

                    foreach ($children as $child) {
                        if (admin_nav_is_active(
                            $child,
                            $currentPath
                        )) {
                            $groupActive = true;
                            break;
                        }
                    }
                    ?>

                    <?php if ($children !== []): ?>
                        <div
                            class="admin-nav__group"
                            data-admin-nav-group
                        >
                            <button
                                class="admin-nav__group-toggle<?=
                                    $groupActive
                                        ? ' is-active'
                                        : ''
                                ?>"
                                type="button"
                                aria-expanded="<?=
                                    $groupActive
                                        ? 'true'
                                        : 'false'
                                ?>"
                                data-admin-nav-group-toggle
                            >
                                <span class="admin-nav__icon">
                                    <?=
                                        \App\Support\AdminIcon::html(
                                            (string) (
                                                $item['icon']
                                                ?? 'dashboard'
                                            )
                                        )
                                    ?>
                                </span>

                                <span
                                    class="admin-nav__group-title"
                                >
                                    <?= admin_h(
                                        $item['title'] ?? ''
                                    ) ?>
                                </span>

                                <?php if (
                                    ($item['badge'] ?? '') !== ''
                                ): ?>
                                    <small
                                        class="admin-nav__badge"
                                    >
                                        <?= admin_h(
                                            $item['badge']
                                        ) ?>
                                    </small>
                                <?php endif; ?>

                                <span
                                    class="admin-nav__group-chevron"
                                    aria-hidden="true"
                                ></span>
                            </button>

                            <div
                                class="admin-nav__children"
                                data-admin-nav-children
                                <?= $groupActive
                                    ? ''
                                    : 'hidden'
                                ?>
                            >
                                <?php foreach (
                                    $children as $child
                                ): ?>
                                    <?php
                                    $childActive =
                                        admin_nav_is_active(
                                            $child,
                                            $currentPath
                                        );
                                    ?>

                                    <a
                                        class="<?=
                                            $childActive
                                                ? 'is-active'
                                                : ''
                                        ?>"
                                        href="<?= admin_h(
                                            (string) (
                                                $child['url']
                                                ?? '#'
                                            )
                                        ) ?>"
                                        <?=
                                            $childActive
                                                ? 'aria-current="page"'
                                                : ''
                                        ?>
                                    >
                                        <span>
                                            <?= admin_h(
                                                $child['title']
                                                ?? ''
                                            ) ?>
                                        </span>

                                        <?php if (
                                            ($child['badge'] ?? '')
                                                !== ''
                                        ): ?>
                                            <small
                                                class="admin-nav__badge"
                                            >
                                                <?= admin_h(
                                                    $child['badge']
                                                ) ?>
                                            </small>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php
                        $itemActive = admin_nav_is_active(
                            $item,
                            $currentPath
                        );
                        ?>

                        <a
                            class="<?=
                                $itemActive
                                    ? 'is-active'
                                    : ''
                            ?>"
                            href="<?= admin_h($href) ?>"
                            <?=
                                $itemActive
                                    ? 'aria-current="page"'
                                    : ''
                            ?>
                        >
                            <span class="admin-nav__icon">
                                <?=
                                    \App\Support\AdminIcon::html(
                                        (string) (
                                            $item['icon']
                                            ?? 'dashboard'
                                        )
                                    )
                                ?>
                            </span>

                            <span>
                                <?= admin_h(
                                    $item['title'] ?? ''
                                ) ?>
                            </span>

                            <?php if (
                                ($item['badge'] ?? '') !== ''
                            ): ?>
                                <small
                                    class="admin-nav__badge"
                                >
                                    <?= admin_h(
                                        $item['badge']
                                    ) ?>
                                </small>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <button
                    class="admin-icon-button
                        admin-sidebar-toggle"
                    type="button"
                    data-admin-sidebar-toggle
                    aria-controls="admin-sidebar"
                    aria-label="<?= admin_h(
                        admin_fa(
                            '&#x0628;&#x0627;&#x0632; '
                            . '&#x06A9;&#x0631;&#x062F;&#x0646; '
                            . '&#x0645;&#x0646;&#x0648;'
                        )
                    ) ?>"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="admin-topbar__title">
                    <p class="admin-kicker">
                        <?= admin_h($brandTitle) ?>
                    </p>
                    <h1><?= admin_h($title) ?></h1>
                </div>

                <div class="admin-topbar__actions">
                    <?php foreach (
                        $topbarNav as $topbarItem
                    ): ?>
                        <a
                            class="admin-role
                                admin-topbar-notification<?=
                                    ($topbarItem['badge'] ?? '') !== ''
                                        ? ' has-badge'
                                        : ''
                                ?><?=
                                    ($topbarItem['key'] ?? '') ===
                                        'notification-approval-topbar'
                                        ? ' admin-topbar-notification--approval'
                                        : ''
                                ?>"
                            href="<?= admin_h(
                                (string) (
                                    $topbarItem['url']
                                    ?? '#'
                                )
                            ) ?>"
                            title="<?= admin_h(
                                $topbarItem['title'] ?? 'کارتابل من'
                            ) ?>"
                            aria-label="<?= admin_h(
                                $topbarItem['title'] ?? 'کارتابل من'
                            ) ?>"
                        >
                            <?php if (
                                (string) ($topbarItem['icon'] ?? '')
                                    === 'bell'
                            ): ?>
                                <span
                                    class="admin-bell-icon"
                                    aria-hidden="true"
                                >
                                    <svg viewBox="0 0 24 24">
                                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                                        <path d="M10 21h4" />
                                    </svg>
                                </span>
                            <?php else: ?>
                                <?=
                                    \App\Support\AdminIcon::html(
                                        (string) (
                                            $topbarItem['icon']
                                            ?? 'envelope'
                                        )
                                    )
                                ?>
                            <?php endif; ?>

                            <span>
                                <?= admin_h(
                                    $topbarItem['title']
                                    ?? ''
                                ) ?>
                            </span>

                            <?php if (
                                ($topbarItem['badge'] ?? '')
                                    !== ''
                            ): ?>
                                <b>
                                    <?= admin_h(
                                        $topbarItem['badge']
                                    ) ?>
                                </b>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>

                    <?php if (
                        ($theme['show_active_role'] ?? true)
                            === true
                    ): ?>
                        <span class="admin-role">
                            <?= admin_h(
                                $active['role_title']
                                ?? admin_fa(
                                    '&#x0628;&#x062F;&#x0648;&#x0646; '
                                    . '&#x0646;&#x0642;&#x0634; '
                                    . '&#x0641;&#x0639;&#x0627;&#x0644;'
                                )
                            ) ?>
                        </span>
                    <?php endif; ?>

                    <div
                        class="admin-user-menu"
                        data-admin-user-menu
                    >
                        <button
                            class="admin-user-menu__trigger"
                            type="button"
                            data-admin-user-menu-toggle
                            aria-haspopup="true"
                            aria-expanded="false"
                        >
                            <?php if ($avatarUrl !== ''): ?>
                                <img
                                    class="admin-avatar"
                                    src="<?= admin_h(
                                        $avatarUrl
                                    ) ?>"
                                    alt=""
                                >
                            <?php endif; ?>

                            <?php if (
                                ($theme['show_user_name'] ?? true)
                                    === true
                            ): ?>
                                <span>
                                    <?= admin_h(
                                        $user['name']
                                        ?? admin_fa(
                                            '&#x06A9;&#x0627;&#x0631;&#x0628;&#x0631;'
                                        )
                                    ) ?>
                                </span>
                            <?php endif; ?>

                            <b aria-hidden="true">
                                &#x25BE;
                            </b>
                        </button>

                        <div
                            class="admin-dropdown"
                            role="menu"
                        >
                            <?php foreach (
                                $accountNav as $item
                            ): ?>
                                <?php
                                $href = (string) (
                                    $item['url'] ?? '#'
                                );
                                ?>

                                <a
                                    class="<?=
                                        $currentPath === $href
                                            ? 'is-active'
                                            : ''
                                    ?> <?=
                                        $href === '/admin/logout'
                                            ? 'is-danger'
                                            : ''
                                    ?>"
                                    href="<?= admin_h($href) ?>"
                                    role="menuitem"
                                >
                                    <span><?= admin_h(
                                        $item['title'] ?? ''
                                    ) ?></span>

                                    <?php if (
                                        ($item['badge'] ?? '') !== ''
                                    ): ?>
                                        <small
                                            class="admin-account-nav-badge"
                                        >
                                            <?= admin_h(
                                                $item['badge']
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </header>

            <main class="admin-content">
                <?= $content ?? '' ?>
            </main>

            <?php if (
                ($theme['footer_enabled'] ?? true)
                    === true
            ): ?>
                <footer class="admin-footer">
                    <span>
                        <?= admin_h(
                            $theme['footer_text']
                            ?? admin_fa(
                                '&#x06A9;&#x0644;&#x06CC;&#x0647; '
                                . '&#x062D;&#x0642;&#x0648;&#x0642; '
                                . '&#x0627;&#x06CC;&#x0646; '
                                . '&#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647; '
                                . '&#x0645;&#x062A;&#x0639;&#x0644;&#x0642; '
                                . '&#x0628;&#x0647; '
                                . '&#x062A;&#x0631;&#x0648;&#x06A9;&#x0627; '
                                . '&#x0645;&#x06CC;&#x200C;&#x0628;&#x0627;&#x0634;&#x062F;.'
                            )
                        ) ?>
                    </span>

                    <span class="admin-footer__meta">
                        <span>
                            <?= admin_h(
                                admin_fa(
                                    '&#x0646;&#x0633;&#x062E;&#x0647;'
                                )
                            ) ?>
                            <bdi
                                class="admin-footer__version"
                                dir="ltr"
                            >
                                <?= admin_h($runtimeVersion) ?>
                            </bdi>
                        </span>
                        <span><?= admin_h($year) ?></span>
                    </span>
                </footer>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (() => {
        const groups = [
            ...document.querySelectorAll(
                '[data-admin-nav-group]'
            ),
        ];

        const controls = group => ({
            toggle: group.querySelector(
                '[data-admin-nav-group-toggle]'
            ),
            children: group.querySelector(
                '[data-admin-nav-children]'
            ),
        });

        const closeGroup = group => {
            const { toggle, children } =
                controls(group);

            if (!toggle || !children) {
                return;
            }

            toggle.setAttribute(
                'aria-expanded',
                'false'
            );
            children.hidden = true;
        };

        const openGroup = group => {
            const { toggle, children } =
                controls(group);

            if (!toggle || !children) {
                return;
            }

            toggle.setAttribute(
                'aria-expanded',
                'true'
            );
            children.hidden = false;
        };

        groups.forEach(group => {
            const { toggle, children } =
                controls(group);

            if (!toggle || !children) {
                return;
            }

            const activeChild = children.querySelector(
                '.is-active'
            );

            if (activeChild) {
                openGroup(group);
            }

            toggle.addEventListener('click', () => {
                const opening =
                    toggle.getAttribute(
                        'aria-expanded'
                    ) !== 'true';

                groups.forEach(otherGroup => {
                    if (otherGroup !== group) {
                        closeGroup(otherGroup);
                    }
                });

                if (opening) {
                    openGroup(group);
                } else {
                    closeGroup(group);
                }
            });
        });
    })();
    </script>
</body>
</html>

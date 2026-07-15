<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$workspace = $workspace ?? [];
$tabs = $tabs ?? [];
$workspaceContent = $workspaceContent ?? '';
$activeTab = (string) ($workspace['active_tab'] ?? '');
$activeTitle = '';

foreach ($tabs as $tab) {
    if (($tab['key'] ?? '') === $activeTab) {
        $activeTitle = (string) ($tab['title'] ?? '');
        break;
    }
}
?>
<section class="entity-workspace">
    <header class="entity-workspace__header">
        <div class="entity-workspace__avatar">
            <?php if (($workspace['avatar_url'] ?? '') !== ''): ?>
                <img src="<?= admin_h($workspace['avatar_url']) ?>" alt="">
            <?php else: ?>
                <?= \App\Support\AdminIcon::html((string) ($workspace['icon'] ?? 'user')) ?>
            <?php endif; ?>
        </div>
        <div class="entity-workspace__title">
            <span class="admin-muted">کاربر</span>
            <h2><?= admin_h($workspace['title'] ?? '—') ?></h2>
            <p><?= admin_h($workspace['subtitle'] ?? '') ?></p>
            <?php if (($workspace['meta'] ?? []) !== []): ?>
                <div class="entity-workspace__meta">
                    <?php foreach ($workspace['meta'] as $meta): ?>
                        <span>
                            <?= admin_h($meta['label'] ?? '') ?>:
                            <strong<?= isset($meta['dir']) ? ' dir="' . admin_h($meta['dir']) . '"' : '' ?>><?= admin_h($meta['value'] ?? '—') ?></strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="entity-workspace__actions">
            <?php foreach (($workspace['badges'] ?? []) as $badge): ?>
                <span class="admin-status-badge admin-status-badge--<?= admin_h($badge['code'] ?? 'unknown') ?>"><?= admin_h($badge['label'] ?? '—') ?></span>
            <?php endforeach; ?>
            <?php if (($workspace['back_url'] ?? '') !== ''): ?>
                <a class="admin-button admin-button--soft" href="<?= admin_h($workspace['back_url']) ?>"><?= admin_h($workspace['back_label'] ?? 'بازگشت') ?></a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($tabs !== []): ?>
        <nav class="entity-workspace__tabs" aria-label="بخش‌های صفحه">
            <?php foreach ($tabs as $tab): ?>
                <?php if (($tab['is_visible'] ?? true) !== true) { continue; } ?>
                <a class="entity-workspace__tab <?= ($tab['is_active'] ?? false) ? 'is-active' : '' ?>"
                   href="<?= admin_h($tab['url'] ?? '#') ?>"
                   <?= ($tab['is_active'] ?? false) ? 'aria-current="page"' : '' ?>>
                    <?= \App\Support\AdminIcon::html((string) ($tab['icon'] ?? 'dashboard')) ?>
                    <span><?= admin_h($tab['title'] ?? '') ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <details class="entity-workspace__mobile-nav">
            <summary>
                <span>بخش فعلی</span>
                <strong><?= admin_h($activeTitle !== '' ? $activeTitle : 'انتخاب بخش') ?></strong>
            </summary>
            <nav aria-label="بخش‌های صفحه">
                <?php foreach ($tabs as $tab): ?>
                    <?php if (($tab['is_visible'] ?? true) !== true) { continue; } ?>
                    <a class="<?= ($tab['is_active'] ?? false) ? 'is-active' : '' ?>"
                       href="<?= admin_h($tab['url'] ?? '#') ?>"
                       <?= ($tab['is_active'] ?? false) ? 'aria-current="page"' : '' ?>>
                        <?= \App\Support\AdminIcon::html((string) ($tab['icon'] ?? 'dashboard')) ?>
                        <span><?= admin_h($tab['title'] ?? '') ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </details>
    <?php endif; ?>

    <div class="entity-workspace__content">
        <?= $workspaceContent ?>
    </div>
</section>

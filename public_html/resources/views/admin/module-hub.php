<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$module = $module ?? [];
$actions = $module['actions'] ?? [];

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">&#x062F;&#x0627;&#x0634;&#x0628;&#x0648;&#x0631;&#x062F;</a>
    <span aria-hidden="true">/</span>
    <span><?= admin_h($module['title'] ?? '') ?></span>
</nav>

<section class="admin-module-hub admin-module-hub--<?= admin_h($module['color'] ?? 'blue') ?>">
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html((string) ($module['icon'] ?? 'dashboard')) ?>
    </div>
    <div>
        <h2><?= admin_h($module['title'] ?? '') ?></h2>
        <p><?= admin_h($module['description'] ?? '') ?></p>
    </div>
</section>

<section class="admin-action-grid" aria-label="<?= admin_h($module['title'] ?? '') ?>">
    <?php foreach ($actions as $action): ?>
        <a class="admin-action-tile admin-action-tile--<?= admin_h($action['color'] ?? $module['color'] ?? 'blue') ?>" href="<?= admin_h($action['url'] ?? '#') ?>">
            <span class="admin-action-tile__icon">
                <?= \App\Support\AdminIcon::html((string) ($action['icon'] ?? 'dashboard')) ?>
            </span>
            <span class="admin-action-tile__body">
                <strong><?= admin_h($action['title'] ?? '') ?></strong>
                <small><?= admin_h($action['description'] ?? '') ?></small>
            </span>
            <?php if (($action['badge'] ?? '') !== ''): ?>
                <span class="admin-action-tile__badge"><?= admin_h($action['badge']) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

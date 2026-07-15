<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$modules = $context['dashboard_modules'] ?? [];

ob_start();
?>
<?php if ($modules !== []): ?>
    <section class="admin-section admin-dashboard-modules">
        <div class="admin-section__header">
            <div>
                <h2>&#x0645;&#x0627;&#x0698;&#x0648;&#x0644;&#x200C;&#x0647;&#x0627;&#x06CC; &#x0633;&#x0627;&#x0645;&#x0627;&#x0646;&#x0647;</h2>
                <p class="admin-muted">&#x062F;&#x0633;&#x062A;&#x0631;&#x0633;&#x06CC; &#x0628;&#x0647; &#x0628;&#x062E;&#x0634;&#x200C;&#x0647;&#x0627; &#x0628;&#x0631; &#x0627;&#x0633;&#x0627;&#x0633; &#x0646;&#x0642;&#x0634; &#x0641;&#x0639;&#x0627;&#x0644; &#x0634;&#x0645;&#x0627; &#x0646;&#x0645;&#x0627;&#x06CC;&#x0634; &#x062F;&#x0627;&#x062F;&#x0647; &#x0645;&#x06CC;&#x200C;&#x0634;&#x0648;&#x062F;.</p>
            </div>
        </div>
        <div class="admin-module-launcher">
            <?php foreach ($modules as $module): ?>
                <a class="admin-module-launcher__tile admin-module-launcher__tile--<?= admin_h($module['color'] ?? 'blue') ?>" href="<?= admin_h($module['url'] ?? '#') ?>">
                    <span class="admin-module-launcher__icon">
                        <?= \App\Support\AdminIcon::html((string) ($module['icon'] ?? 'dashboard')) ?>
                    </span>
                    <span class="admin-module-launcher__body">
                        <strong><?= admin_h($module['title'] ?? '') ?></strong>
                        <small><?= admin_h($module['subtitle'] ?? $module['description'] ?? '') ?></small>
                    </span>
                    <span class="admin-module-launcher__enter" aria-hidden="true">&#x2190;</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="admin-section">
        <div class="admin-empty-state">&#x062F;&#x0631; &#x062D;&#x0627;&#x0644; &#x062D;&#x0627;&#x0636;&#x0631; &#x0645;&#x0627;&#x0698;&#x0648;&#x0644;&#x06CC; &#x0628;&#x0631;&#x0627;&#x06CC; &#x0646;&#x0642;&#x0634; &#x0641;&#x0639;&#x0627;&#x0644; &#x0634;&#x0645;&#x0627; &#x0646;&#x0645;&#x0627;&#x06CC;&#x0634; &#x062F;&#x0627;&#x062F;&#x0647; &#x0646;&#x0645;&#x06CC;&#x200C;&#x0634;&#x0648;&#x062F;.</div>
    </section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

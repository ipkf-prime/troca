<?php
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

if (!function_exists('admin_module_color_hex')) {
    function admin_module_color_hex($value): string
    {
        $value = strtolower(
            trim((string) ($value ?? ''))
        );

        if (
            preg_match(
                '/^#[0-9a-f]{6}$/',
                $value
            ) === 1
        ) {
            return $value;
        }

        $legacy = [
            'blue' => '#2563eb',
            'teal' => '#0f766e',
            'cyan' => '#0891b2',
            'purple' => '#7c3aed',
            'violet' => '#6d28d9',
            'fuchsia' => '#c026d3',
            'indigo' => '#4f46e5',
            'amber' => '#d97706',
            'orange' => '#f97316',
            'rose' => '#e11d48',
            'green' => '#16a34a',
        ];

        return $legacy[$value] ?? '#2563eb';
    }
}

$modules = $context['dashboard_modules'] ?? [];

ob_start();
?>
<style>
.admin-dashboard-modules {
    padding: clamp(.8rem, 1.4vw, 1.15rem);
}

.admin-dashboard-modules .admin-section__header {
    margin-bottom: .7rem;
}

.admin-dashboard-modules .admin-module-launcher {
    display: grid;
    gap: .7rem;
    grid-auto-rows: 1fr;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    width: 100%;
}

.admin-dashboard-modules .admin-module-launcher__tile {
    box-sizing: border-box;
    justify-self: stretch;
    max-width: none;
    min-height: 116px;
    min-width: 0;
    padding: .8rem .9rem;
    width: 100%;
}

.admin-dashboard-modules .admin-module-launcher__icon {
    height: 48px;
    width: 48px;
}

.admin-dashboard-modules .admin-module-launcher__body strong {
    font-size: 1rem;
}

.admin-dashboard-modules .admin-module-launcher__body small {
    font-size: .72rem;
    line-height: 1.65;
}

.admin-dashboard-modules .admin-module-launcher__enter {
    height: 34px;
    width: 34px;
}

@media (max-width: 1180px) {
    .admin-dashboard-modules .admin-module-launcher {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 850px) {
    .admin-dashboard-modules .admin-module-launcher {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .admin-dashboard-modules .admin-module-launcher {
        grid-template-columns: 1fr;
    }

    .admin-dashboard-modules .admin-module-launcher__tile {
        min-height: 104px;
    }
}
</style>

<?php if ($modules !== []): ?>
    <section class="admin-section admin-dashboard-modules">
        <div class="admin-section__header">
            <div>
                <h2>ماژول‌های سامانه</h2>
                <p class="admin-muted">
                    دسترسی به بخش‌ها بر اساس نقش فعال شما نمایش داده می‌شود.
                </p>
            </div>
        </div>
        <div class="admin-module-launcher">
            <?php foreach ($modules as $module): ?>
                <?php
                $moduleColor = admin_module_color_hex(
                    $module['color'] ?? '#2563eb'
                );
                ?>
                <a
                    class="admin-module-launcher__tile"
                    style="--module-color-a: <?= admin_h($moduleColor) ?>; --module-color-b: color-mix(in srgb, <?= admin_h($moduleColor) ?> 78%, #000);"
                    href="<?= admin_h($module['url'] ?? '#') ?>"
                >
                    <span class="admin-module-launcher__icon">
                        <?= \App\Support\AdminIcon::html(
                            (string) ($module['icon'] ?? 'dashboard')
                        ) ?>
                    </span>
                    <span class="admin-module-launcher__body">
                        <strong><?= admin_h($module['title'] ?? '') ?></strong>
                        <small><?= admin_h(
                            $module['subtitle']
                            ?? $module['description']
                            ?? ''
                        ) ?></small>
                    </span>
                    <span class="admin-module-launcher__enter" aria-hidden="true">←</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="admin-section">
        <div class="admin-empty-state">
            در حال حاضر ماژولی برای نقش فعال شما نمایش داده نمی‌شود.
        </div>
    </section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

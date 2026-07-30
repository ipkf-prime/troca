<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

if (!function_exists('admin_fa')) {
    function admin_fa(string $entities): string
    {
        return html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

$summary = $dashboard['summary'] ?? [];
$tasks = $dashboard['recent_tasks'] ?? [];
$cards = [
    ['title' => admin_fa('&#x067E;&#x0631;&#x0648;&#x0698;&#x0647;&#x200C;&#x0647;&#x0627;'), 'description' => admin_fa('&#x0646;&#x0645;&#x0627;&#x06CC; &#x06A9;&#x0644;&#x06CC; &#x067E;&#x0631;&#x0648;&#x0698;&#x0647;&#x200C;&#x0647;&#x0627;&#x06CC; &#x0641;&#x0639;&#x0627;&#x0644;'), 'value' => (int) ($summary['projects'] ?? 0), 'icon' => 'organization', 'color' => 'green', 'url' => '/admin/work/projects'],
    ['title' => 'کارها', 'description' => 'کارهای باز در ساختار مدیریت کار', 'value' => (int) ($summary['works'] ?? 0), 'icon' => 'circle-check', 'color' => 'teal'],
    ['title' => admin_fa('&#x06A9;&#x0627;&#x0631;&#x0647;&#x0627;&#x06CC; &#x0645;&#x0646;'), 'description' => admin_fa('&#x0627;&#x062A;&#x0635;&#x0627;&#x0644; &#x0628;&#x0647; &#x062A;&#x062E;&#x0635;&#x06CC;&#x0635;&#x200C;&#x0647;&#x0627; &#x062F;&#x0631; &#x0645;&#x0631;&#x062D;&#x0644;&#x0647; &#x0628;&#x0639;&#x062F; &#x062A;&#x06A9;&#x0645;&#x06CC;&#x0644; &#x0645;&#x06CC;&#x200C;&#x0634;&#x0648;&#x062F;'), 'value' => admin_fa('&#x0622;&#x0645;&#x0627;&#x062F;&#x0647;'), 'icon' => 'users', 'color' => 'blue'],
    ['title' => admin_fa('&#x0648;&#x0636;&#x0639;&#x06CC;&#x062A;&#x200C;&#x0647;&#x0627;'), 'description' => admin_fa('&#x0648;&#x0636;&#x0639;&#x06CC;&#x062A;&#x200C;&#x0647;&#x0627;&#x06CC; &#x0633;&#x06CC;&#x0633;&#x062A;&#x0645;&#x06CC; &#x0628;&#x0631;&#x0627;&#x06CC; &#x06A9;&#x0627;&#x0631;&#x0647;&#x0627; &#x0648; &#x062A;&#x0633;&#x06A9;&#x200C;&#x0647;&#x0627;'), 'value' => (int) ($summary['statuses'] ?? 0), 'icon' => 'sliders', 'color' => 'purple'],
];

ob_start();
require __DIR__ . '/work-ui-styles.php';
?>
<section class="admin-page work-dashboard" data-admin-module-page="work">
    <p class="admin-muted work-dashboard__intro"><?= admin_h(admin_fa('&#x0645;&#x0631;&#x06A9;&#x0632; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x067E;&#x0631;&#x0648;&#x0698;&#x0647;&#x200C;&#x0647;&#x0627;&#x060C; &#x06A9;&#x0627;&#x0631;&#x0647;&#x0627; &#x0648; &#x062A;&#x0633;&#x06A9;&#x200C;&#x0647;&#x0627;&#x06CC; &#x062A;&#x06CC;&#x0645;')) ?></p>

    <section class="admin-action-grid" aria-label="بخش‌های مدیریت کار">
        <?php foreach ($cards as $card): ?>
            <?php $cardUrl = (string) ($card['url'] ?? ''); ?>
            <?php if ($cardUrl !== ''): ?>
                <a class="admin-action-tile admin-action-tile--<?= admin_h($card['color']) ?> work-dashboard-card-link" href="<?= admin_h($cardUrl) ?>">
            <?php else: ?>
                <article class="admin-action-tile admin-action-tile--<?= admin_h($card['color']) ?>">
            <?php endif; ?>
                <span class="admin-action-tile__icon">
                    <?= \App\Support\AdminIcon::html((string) $card['icon']) ?>
                </span>
                <span class="admin-action-tile__body">
                    <strong><?= admin_h($card['title']) ?></strong>
                    <small><?= admin_h($card['description']) ?></small>
                </span>
                <span class="admin-action-tile__badge"><?= admin_h($card['value']) ?></span>
            <?php if ($cardUrl !== ''): ?>
                </a>
            <?php else: ?>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>

    <section class="admin-card">
        <h2><?= admin_h(admin_fa('&#x0622;&#x062E;&#x0631;&#x06CC;&#x0646; &#x062A;&#x0633;&#x06A9;&#x200C;&#x0647;&#x0627;')) ?></h2>
        <?php if ($tasks === []): ?>
            <p class="admin-empty-state"><?= admin_h(admin_fa('&#x0647;&#x0646;&#x0648;&#x0632; &#x062A;&#x0633;&#x06A9;&#x06CC; &#x062B;&#x0628;&#x062A; &#x0646;&#x0634;&#x062F;&#x0647; &#x0627;&#x0633;&#x062A;. &#x0633;&#x0627;&#x062E;&#x062A;&#x0627;&#x0631; &#x0627;&#x0648;&#x0644;&#x06CC;&#x0647; &#x0622;&#x0645;&#x0627;&#x062F;&#x0647; &#x0627;&#x0633;&#x062A;.')) ?></p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= admin_h(admin_fa('&#x062A;&#x0633;&#x06A9;')) ?></th>
                            <th>پروژه / کار</th>
                            <th><?= admin_h(admin_fa('&#x0648;&#x0636;&#x0639;&#x06CC;&#x062A;')) ?></th>
                            <th><?= admin_h(admin_fa('&#x0627;&#x0648;&#x0644;&#x0648;&#x06CC;&#x062A;')) ?></th>
                            <th><?= admin_h(admin_fa('&#x067E;&#x06CC;&#x0634;&#x0631;&#x0641;&#x062A;')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?= admin_h($task['title'] ?? '') ?></td>
                                <td><?= admin_h($task['project_title'] ?? '') ?> / <?= admin_h($task['work_title'] ?? '-') ?></td>
                                <td><?= admin_h($task['status_title'] ?? $task['status'] ?? '') ?></td>
                                <td><?= admin_h($task['priority'] ?? '') ?></td>
                                <td><?= admin_h(\App\Support\AdminFormat::digits((string) ((int) ($task['progress_percent'] ?? 0)))) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

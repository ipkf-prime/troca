<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$summary = $dashboard['summary'] ?? [];
$tasks = $dashboard['recent_tasks'] ?? [];
$myWork = $dashboard['my_work'] ?? [];
$myItems = $myWork['items'] ?? [];
$myCounts = $myWork['counts'] ?? [];
$scopeOptions = $myWork['scope_options'] ?? [];
$currentScope = (string) ($myWork['scope'] ?? 'open');
$currentQuery = (string) ($myWork['q'] ?? '');
$defaultSort = $currentScope === 'completed' ? 'updated_at' : 'due_at';
$defaultDirection = $currentScope === 'completed' ? 'desc' : 'asc';
$mySort = \App\Support\AdminTableSort::resolve(
    [],
    [
        'title' => 'title',
        'project' => 'project',
        'type' => 'type',
        'status' => 'status',
        'priority' => 'priority',
        'due_at' => 'due_at',
        'progress' => 'progress',
        'updated_at' => 'updated_at',
    ],
    $defaultSort,
    $defaultDirection
);
$selectedSort = $mySort['column'];
$selectedDirection = $mySort['direction'];
$mySortQuery = ['scope' => $currentScope, 'q' => $currentQuery];
$mySortUrl = static fn (string $column): string => \App\Support\AdminTableSort::url(
    '/admin/work',
    $mySortQuery,
    $column,
    $selectedSort,
    $selectedDirection,
    'my-work'
);
$myIndicator = static fn (string $column): string => \App\Support\AdminTableSort::indicator(
    $column,
    $selectedSort,
    $selectedDirection
);
$myAriaSort = static fn (string $column): string => \App\Support\AdminTableSort::ariaSort(
    $column,
    $selectedSort,
    $selectedDirection
);

$cards = [
    ['title' => 'پروژه‌ها', 'description' => 'نمای کلی پروژه‌های فعال', 'value' => (int) ($summary['projects'] ?? 0), 'icon' => 'organization', 'color' => 'green', 'url' => '/admin/work/projects'],
    ['title' => 'کارها', 'description' => 'کارهای باز در ساختار مدیریت کار', 'value' => (int) ($summary['works'] ?? 0), 'icon' => 'circle-check', 'color' => 'teal', 'url' => '/admin/work/projects'],
    ['title' => 'کارهای من', 'description' => 'کارهای باز تخصیص‌یافته به من', 'value' => (int) ($myCounts['open'] ?? 0), 'icon' => 'users', 'color' => 'blue', 'url' => '/admin/work?scope=open#my-work'],
    ['title' => 'عقب‌افتاده', 'description' => 'کارهای من که از سررسید عبور کرده‌اند', 'value' => (int) ($myCounts['overdue'] ?? 0), 'icon' => 'sliders', 'color' => 'rose', 'url' => '/admin/work?scope=overdue#my-work'],
];

ob_start();
require __DIR__ . '/work-ui-styles.php';
require __DIR__ . '/work-stage5-ui.php';
?>
<section class="admin-page work-dashboard" data-admin-module-page="work">
    <section class="admin-action-grid" aria-label="بخش‌های مدیریت کار">
        <?php foreach ($cards as $card): ?>
            <a class="admin-action-tile admin-action-tile--<?= admin_h($card['color']) ?> work-dashboard-card-link" href="<?= admin_h($card['url']) ?>">
                <span class="admin-action-tile__icon"><?= \App\Support\AdminIcon::html((string) $card['icon']) ?></span>
                <span class="admin-action-tile__body"><strong><?= admin_h($card['title']) ?></strong><small><?= admin_h($card['description']) ?></small></span>
                <span class="admin-action-tile__badge"><?= admin_h(\App\Support\AdminFormat::digits($card['value'])) ?></span>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="admin-section" id="my-work">
        <div class="admin-section__header">
            <div>
                <h2>کارهای من</h2>
                <p class="admin-muted"><?= admin_h(\App\Support\AdminFormat::digits((int) ($myWork['total'] ?? 0))) ?> مورد در فیلتر جاری</p>
            </div>
        </div>

        <div class="work-my-toolbar">
            <div class="work-my-scopes">
                <?php foreach ($scopeOptions as $scopeCode => $scopeTitle): ?>
                    <?php
                    $scopeUrl = '/admin/work?' . http_build_query([
                        'scope' => (string) $scopeCode,
                        'sort' => $selectedSort,
                        'dir' => $selectedDirection,
                    ], '', '&', PHP_QUERY_RFC3986) . '#my-work';
                    ?>
                    <a class="admin-button admin-button--compact<?= $currentScope === $scopeCode ? '' : ' admin-button--soft' ?>" href="<?= admin_h($scopeUrl) ?>">
                        <?= admin_h($scopeTitle) ?>
                        (<?= admin_h(\App\Support\AdminFormat::digits((int) ($myCounts[$scopeCode] ?? 0))) ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" action="/admin/work" class="work-my-search">
                <input type="hidden" name="scope" value="<?= admin_h($currentScope) ?>">
                <input type="hidden" name="sort" value="<?= admin_h($selectedSort) ?>">
                <input type="hidden" name="dir" value="<?= admin_h($selectedDirection) ?>">
                <input type="search" name="q" value="<?= admin_h($currentQuery) ?>" maxlength="120" placeholder="جست‌وجو در عنوان کار یا پروژه" aria-label="جست‌وجو در کارهای من">
                <button class="admin-button admin-button--compact" type="submit">جست‌وجو</button>
                <?php if ($currentQuery !== ''): ?>
                    <a class="admin-button admin-button--soft admin-button--compact" href="<?= admin_h('/admin/work?scope=' . rawurlencode($currentScope) . '#my-work') ?>">بازنشانی</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($myItems === []): ?>
            <p class="admin-empty-state">موردی مطابق این فیلتر پیدا نشد.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ردیف</th>
                            <?php foreach ([
                                'title' => 'عنوان',
                                'project' => 'پروژه',
                                'type' => 'نوع',
                                'status' => 'وضعیت',
                                'priority' => 'اولویت',
                                'due_at' => 'سررسید',
                                'progress' => 'پیشرفت',
                            ] as $column => $label): ?>
                                <th aria-sort="<?= admin_h($myAriaSort($column)) ?>">
                                    <a class="admin-sort-link" href="<?= admin_h($mySortUrl($column)) ?>">
                                        <span><?= admin_h($label) ?></span>
                                        <span class="admin-sort-indicator" aria-hidden="true"><?= admin_h($myIndicator($column)) ?></span>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myItems as $index => $item): ?>
                            <?php
                            $projectReference = (string) ($item['project_reference'] ?? '');
                            $itemReference = (string) ($item['public_reference'] ?? '');
                            $detailUrl = '/admin/work/projects/' . rawurlencode($projectReference)
                                . '/items/' . rawurlencode($itemReference);
                            $dueDate = \App\Support\AdminFormat::jalaliDate(substr((string) ($item['due_at'] ?? ''), 0, 10));
                            ?>
                            <tr>
                                <td><?= admin_h(\App\Support\AdminFormat::digits($index + 1)) ?></td>
                                <td>
                                    <strong><?= admin_h($item['title'] ?? '') ?></strong>
                                    <?php if (!empty($item['parent_title'])): ?><small class="admin-muted">زیرمجموعه: <?= admin_h($item['parent_title']) ?></small><?php endif; ?>
                                </td>
                                <td><?= admin_h($item['project_title'] ?? '') ?></td>
                                <td><span class="admin-pill"><?= admin_h($item['type_title'] ?? '') ?></span></td>
                                <td><?= admin_h($item['status_title'] ?? '') ?></td>
                                <td><?= admin_h($item['priority_title'] ?? '') ?></td>
                                <td>
                                    <?= admin_h($dueDate !== '' ? $dueDate : '—') ?>
                                    <?php if (!empty($item['is_overdue'])): ?><small class="admin-muted">عقب‌افتاده</small><?php endif; ?>
                                </td>
                                <td><?= admin_h(\App\Support\AdminFormat::digits((int) ($item['progress_percent'] ?? 0))) ?>٪</td>
                                <td><a class="admin-button admin-button--soft admin-button--compact" href="<?= admin_h($detailUrl) ?>">مشاهده</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-card">
        <h2>آخرین تسک‌ها</h2>
        <?php if ($tasks === []): ?>
            <p class="admin-empty-state">هنوز تسکی ثبت نشده است.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table" data-admin-client-sort>
                    <thead>
                        <tr>
                            <?php foreach ([
                                ['تسک', 'text'],
                                ['پروژه / کار', 'text'],
                                ['وضعیت', 'text'],
                                ['اولویت', 'text'],
                                ['پیشرفت', 'number'],
                            ] as $index => [$label, $type]): ?>
                                <th aria-sort="none">
                                    <button class="admin-sort-link admin-client-sort" type="button" data-client-sort-index="<?= (int) $index ?>" data-client-sort-type="<?= admin_h($type) ?>">
                                        <span><?= admin_h($label) ?></span><span class="admin-sort-indicator" aria-hidden="true">↕</span>
                                    </button>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <?php
                            $taskUrl = '/admin/work/projects/' . rawurlencode((string) ($task['project_reference'] ?? ''))
                                . '/items/' . rawurlencode((string) ($task['public_reference'] ?? ''));
                            ?>
                            <tr>
                                <td><a href="<?= admin_h($taskUrl) ?>"><?= admin_h($task['title'] ?? '') ?></a></td>
                                <td><?= admin_h($task['project_title'] ?? '') ?> / <?= admin_h($task['work_title'] ?? '-') ?></td>
                                <td><?= admin_h($task['status_title'] ?? $task['status'] ?? '') ?></td>
                                <td><?= admin_h($task['priority'] ?? '') ?></td>
                                <td data-sort-value="<?= (int) ($task['progress_percent'] ?? 0) ?>"><?= admin_h(\App\Support\AdminFormat::digits((int) ($task['progress_percent'] ?? 0))) ?>٪</td>
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

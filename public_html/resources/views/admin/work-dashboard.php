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
$myWork = $dashboard['my_work'] ?? [];
$myItems = $myWork['items'] ?? [];
$myCounts = $myWork['counts'] ?? [];
$scopeOptions = $myWork['scope_options'] ?? [];
$currentScope = (string) ($myWork['scope'] ?? 'open');
$currentQuery = (string) ($myWork['q'] ?? '');

$cards = [
    [
        'title' => 'پروژه‌ها',
        'description' => 'نمای کلی پروژه‌های فعال',
        'value' => (int) ($summary['projects'] ?? 0),
        'icon' => 'organization',
        'color' => 'green',
        'url' => '/admin/work/projects',
    ],
    [
        'title' => 'کارها',
        'description' => 'کارهای باز در ساختار مدیریت کار',
        'value' => (int) ($summary['works'] ?? 0),
        'icon' => 'circle-check',
        'color' => 'teal',
        'url' => '/admin/work/projects',
    ],
    [
        'title' => 'کارهای من',
        'description' => 'کارهای باز تخصیص‌یافته به من',
        'value' => (int) ($myCounts['open'] ?? 0),
        'icon' => 'users',
        'color' => 'blue',
        'url' => '/admin/work?scope=open#my-work',
    ],
    [
        'title' => 'عقب‌افتاده',
        'description' => 'کارهای من که از سررسید عبور کرده‌اند',
        'value' => (int) ($myCounts['overdue'] ?? 0),
        'icon' => 'sliders',
        'color' => 'rose',
        'url' => '/admin/work?scope=overdue#my-work',
    ],
];

ob_start();
require __DIR__ . '/work-ui-styles.php';
?>
<section class="admin-page work-dashboard" data-admin-module-page="work">
    <section class="admin-action-grid" aria-label="بخش‌های مدیریت کار">
        <?php foreach ($cards as $card): ?>
            <a
                class="admin-action-tile admin-action-tile--<?= admin_h($card['color']) ?> work-dashboard-card-link"
                href="<?= admin_h($card['url']) ?>"
            >
                <span class="admin-action-tile__icon">
                    <?= \App\Support\AdminIcon::html((string) $card['icon']) ?>
                </span>
                <span class="admin-action-tile__body">
                    <strong><?= admin_h($card['title']) ?></strong>
                    <small><?= admin_h($card['description']) ?></small>
                </span>
                <span class="admin-action-tile__badge">
                    <?= admin_h(\App\Support\AdminFormat::digits($card['value'])) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="admin-section" id="my-work">
        <div class="admin-section__header">
            <div>
                <h2>کارهای من</h2>
                <p class="admin-muted">
                    <?= admin_h(\App\Support\AdminFormat::digits((int) ($myWork['total'] ?? 0))) ?>
                    مورد در فیلتر جاری
                </p>
            </div>
        </div>

        <div class="admin-form-actions">
            <?php foreach ($scopeOptions as $scopeCode => $scopeTitle): ?>
                <?php
                $scopeUrl = '/admin/work?scope=' . rawurlencode((string) $scopeCode) . '#my-work';
                $scopeCount = (int) ($myCounts[$scopeCode] ?? 0);
                ?>
                <a
                    class="admin-button<?= $currentScope === $scopeCode ? '' : ' admin-button--soft' ?>"
                    href="<?= admin_h($scopeUrl) ?>"
                >
                    <?= admin_h($scopeTitle) ?>
                    (<?= admin_h(\App\Support\AdminFormat::digits($scopeCount)) ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="/admin/work" class="admin-users-search">
            <input type="hidden" name="scope" value="<?= admin_h($currentScope) ?>">
            <div class="admin-users-search__row">
                <input
                    type="search"
                    name="q"
                    value="<?= admin_h($currentQuery) ?>"
                    maxlength="120"
                    placeholder="جست‌وجو در عنوان کار یا پروژه"
                >
                <button class="admin-button" type="submit">جست‌وجو</button>
                <?php if ($currentQuery !== ''): ?>
                    <a
                        class="admin-button admin-button--soft"
                        href="<?= admin_h('/admin/work?scope=' . rawurlencode($currentScope) . '#my-work') ?>"
                    >بازنشانی</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($myItems === []): ?>
            <p class="admin-empty-state">موردی مطابق این فیلتر پیدا نشد.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ردیف</th>
                            <th>عنوان</th>
                            <th>پروژه</th>
                            <th>نوع</th>
                            <th>وضعیت</th>
                            <th>اولویت</th>
                            <th>سررسید</th>
                            <th>پیشرفت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myItems as $index => $item): ?>
                            <?php
                            $projectReference = (string) ($item['project_reference'] ?? '');
                            $itemReference = (string) ($item['public_reference'] ?? '');
                            $editUrl = '/admin/work/projects/' . rawurlencode($projectReference)
                                . '/items/' . rawurlencode($itemReference) . '/edit';
                            $dueDate = \App\Support\AdminFormat::jalaliDate(
                                substr((string) ($item['due_at'] ?? ''), 0, 10)
                            );
                            ?>
                            <tr>
                                <td><?= admin_h(\App\Support\AdminFormat::digits($index + 1)) ?></td>
                                <td>
                                    <strong><?= admin_h($item['title'] ?? '') ?></strong>
                                    <?php if (!empty($item['parent_title'])): ?>
                                        <small class="admin-muted">
                                            زیرمجموعه: <?= admin_h($item['parent_title']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?= admin_h($item['project_title'] ?? '') ?></td>
                                <td><span class="admin-pill"><?= admin_h($item['type_title'] ?? '') ?></span></td>
                                <td><?= admin_h($item['status_title'] ?? '') ?></td>
                                <td><?= admin_h($item['priority_title'] ?? '') ?></td>
                                <td>
                                    <?= admin_h($dueDate !== '' ? $dueDate : '—') ?>
                                    <?php if (!empty($item['is_overdue'])): ?>
                                        <small class="admin-muted">عقب‌افتاده</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= admin_h(\App\Support\AdminFormat::digits((int) ($item['progress_percent'] ?? 0))) ?>٪
                                </td>
                                <td>
                                    <a class="admin-button admin-button--soft admin-button--compact" href="<?= admin_h($editUrl) ?>">
                                        مشاهده و ویرایش
                                    </a>
                                </td>
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
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>تسک</th>
                            <th>پروژه / کار</th>
                            <th>وضعیت</th>
                            <th>اولویت</th>
                            <th>پیشرفت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?= admin_h($task['title'] ?? '') ?></td>
                                <td><?= admin_h($task['project_title'] ?? '') ?> / <?= admin_h($task['work_title'] ?? '-') ?></td>
                                <td><?= admin_h($task['status_title'] ?? $task['status'] ?? '') ?></td>
                                <td><?= admin_h($task['priority'] ?? '') ?></td>
                                <td><?= admin_h(\App\Support\AdminFormat::digits((int) ($task['progress_percent'] ?? 0))) ?>٪</td>
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

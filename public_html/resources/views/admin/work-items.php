<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$page = $page ?? [];
$access = $access ?? [];
$project = $page['project'] ?? [];
$items = $page['items'] ?? [];
$filters = $page['filters'] ?? [];
$options = $page['options'] ?? [];
$projectReference = (string) ($project['public_reference'] ?? '');
$baseUrl = '/admin/work/projects/' . rawurlencode($projectReference) . '/items';
$isArchived = !empty($project['archived_at']);
$canCreate = !$isArchived && !empty($access['can_create_item']);
$canEditAll = !empty($access['is_platform_admin'])
    || in_array((string) ($access['role_code'] ?? ''), ['owner', 'manager'], true);
$saved = isset($_GET['saved']);
$archived = isset($_GET['archived']);
$identityLabels = new \App\Services\UserIdentityLabelService();
foreach ($items as &$identityItem) {
    $identityItem['assignee_name'] = empty($identityItem['assignee_reference'])
        ? ''
        : $identityLabels->labelForReference(
            (string) $identityItem['assignee_reference'],
            (string) ($identityItem['assignee_name'] ?? '')
        );
}
unset($identityItem);
$sort = \App\Support\AdminTableSort::resolve(
    [],
    [
        'sequence' => 'sequence',
        'title' => 'title',
        'type' => 'type',
        'status' => 'status',
        'priority' => 'priority',
        'assignee' => 'assignee',
        'due_at' => 'due_at',
        'progress' => 'progress',
        'updated_at' => 'updated_at',
    ],
    'sequence',
    'asc'
);
$selectedSort = $sort['column'];
$selectedDirection = $sort['direction'];
$sortQuery = [
    'q' => (string) ($filters['q'] ?? ''),
    'type' => (string) ($filters['type'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
];
$sortUrl = static fn (string $column): string => \App\Support\AdminTableSort::url(
    $baseUrl,
    $sortQuery,
    $column,
    $selectedSort,
    $selectedDirection
);
$indicator = static fn (string $column): string => \App\Support\AdminTableSort::indicator(
    $column,
    $selectedSort,
    $selectedDirection
);
$ariaSort = static fn (string $column): string => \App\Support\AdminTableSort::ariaSort(
    $column,
    $selectedSort,
    $selectedDirection
);

ob_start();
require __DIR__ . '/work-ui-styles.php';
require __DIR__ . '/work-stage5-ui.php';
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a><span>/</span>
    <a href="/admin/work">مدیریت کار</a><span>/</span>
    <a href="/admin/work/projects">پروژه‌ها</a><span>/</span>
    <a href="<?= admin_h('/admin/work/projects/' . rawurlencode($projectReference)) ?>"><?= admin_h($project['title'] ?? '') ?></a><span>/</span>
    <span>کارها و تسک‌ها</span>
</nav>

<section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('circle-check') ?></div>
    <div><h2>کارها و تسک‌ها</h2><p><?= admin_h($project['title'] ?? '') ?></p></div>
    <a class="admin-module-hub__back" href="<?= admin_h('/admin/work/projects/' . rawurlencode($projectReference)) ?>">بازگشت به پروژه</a>
</section>

<?php if ($saved): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">اطلاعات با موفقیت ذخیره شد.</div></section>
<?php endif; ?>
<?php if ($archived): ?>
    <section class="admin-section"><div class="admin-alert admin-alert--success">آیتم با موفقیت بایگانی شد.</div></section>
<?php endif; ?>

<section class="admin-section work-compact-section">
    <div class="work-section-heading">
        <div>
            <h3>فهرست کارها</h3>
            <p class="admin-muted"><?= admin_h(\App\Support\AdminFormat::digits((int) ($page['total'] ?? 0))) ?> آیتم</p>
        </div>
        <?php if ($canCreate): ?>
            <a class="admin-button" href="<?= admin_h($baseUrl . '/create') ?>">ایجاد آیتم</a>
        <?php endif; ?>
    </div>

    <form method="get" action="<?= admin_h($baseUrl) ?>" class="work-items-filter">
        <label class="work-items-filter__search">
            <span>جست‌وجو</span>
            <input name="q" value="<?= admin_h($filters['q'] ?? '') ?>" placeholder="عنوان یا شناسه آیتم">
        </label>
        <label>
            <span>نوع</span>
            <select name="type">
                <option value="">همه انواع</option>
                <?php foreach (($options['types'] ?? []) as $code => $label): ?>
                    <option value="<?= admin_h($code) ?>"<?= (string) ($filters['type'] ?? '') === $code ? ' selected' : '' ?>><?= admin_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>وضعیت</span>
            <select name="status">
                <option value="">همه وضعیت‌ها</option>
                <?php foreach (($options['statuses'] ?? []) as $code => $label): ?>
                    <option value="<?= admin_h($code) ?>"<?= (string) ($filters['status'] ?? '') === $code ? ' selected' : '' ?>><?= admin_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <input type="hidden" name="sort" value="<?= admin_h($selectedSort) ?>">
        <input type="hidden" name="dir" value="<?= admin_h($selectedDirection) ?>">
        <button class="admin-button" type="submit">اعمال فیلتر</button>
        <?php if (trim((string) ($filters['q'] ?? '')) !== '' || trim((string) ($filters['type'] ?? '')) !== '' || trim((string) ($filters['status'] ?? '')) !== ''): ?>
            <a class="admin-button admin-button--soft" href="<?= admin_h($baseUrl) ?>">پاک‌کردن</a>
        <?php endif; ?>
    </form>

    <?php if ($items === []): ?>
        <p class="admin-empty-state">آیتمی با این فیلتر پیدا نشد.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table work-items-table">
                <thead>
                    <tr>
                        <?php foreach ([
                            'title' => 'عنوان',
                            'type' => 'نوع',
                            'status' => 'وضعیت',
                            'priority' => 'اولویت',
                            'assignee' => 'مسئول',
                            'due_at' => 'سررسید',
                            'progress' => 'پیشرفت',
                        ] as $column => $label): ?>
                            <th aria-sort="<?= admin_h($ariaSort($column)) ?>">
                                <a class="admin-sort-link" href="<?= admin_h($sortUrl($column)) ?>">
                                    <span><?= admin_h($label) ?></span>
                                    <span class="admin-sort-indicator" aria-hidden="true"><?= admin_h($indicator($column)) ?></span>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $dueDate = \App\Support\AdminFormat::jalaliDate(substr((string) ($item['due_at'] ?? ''), 0, 10));
                        $detailUrl = $baseUrl . '/' . rawurlencode((string) ($item['public_reference'] ?? ''));
                        $editUrl = $detailUrl . '/edit';
                        ?>
                        <tr>
                            <td>
                                <div class="work-item-title" style="--work-item-depth:<?= (int) ($item['depth'] ?? 0) ?>">
                                    <a href="<?= admin_h($detailUrl) ?>"><strong><?= admin_h($item['title'] ?? '') ?></strong></a>
                                    <small class="admin-muted"><?= admin_h($item['public_reference'] ?? '') ?></small>
                                </div>
                            </td>
                            <td><span class="admin-pill"><?= admin_h($item['type_title'] ?? $item['item_type'] ?? '') ?></span></td>
                            <td><span class="admin-status-badge"><?= admin_h($item['status_title'] ?? '') ?></span></td>
                            <td><?= admin_h($item['priority_title'] ?? '') ?></td>
                            <td><?= admin_h(($item['assignee_name'] ?? '') ?: '—') ?></td>
                            <td><?= admin_h($dueDate !== '' ? $dueDate : '—') ?></td>
                            <td><?= admin_h(\App\Support\AdminFormat::digits((int) ($item['progress_percent'] ?? 0))) ?>٪</td>
                            <td>
                                <div class="admin-form-actions">
                                    <a class="admin-button admin-button--soft admin-button--compact" href="<?= admin_h($detailUrl) ?>">مشاهده</a>
                                    <?php if ($canEditAll): ?>
                                        <a class="admin-button admin-button--soft admin-button--compact" href="<?= admin_h($editUrl) ?>">ویرایش</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

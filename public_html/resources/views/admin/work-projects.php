<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$list = $list ?? [];
$items = $list['items'] ?? [];
$q = (string) ($list['q'] ?? '');
$selectedStatus = (string) ($list['status'] ?? '');
$selectedSort = (string) ($list['sort'] ?? 'updated_at');
$selectedDirection = (string) ($list['dir'] ?? 'desc');
$statusOptions = $list['status_options'] ?? [];
$total = (int) ($list['total'] ?? count($items));
$canCreate = !empty($list['can_create']);
$sortQuery = ['q' => $q, 'status' => $selectedStatus];
$sortUrl = static fn (string $column): string => \App\Support\AdminTableSort::url(
    '/admin/work/projects',
    $sortQuery,
    $column,
    $selectedSort,
    $selectedDirection
);
$sortIndicator = static fn (string $column): string => \App\Support\AdminTableSort::indicator(
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
    <span>پروژه‌ها</span>
</nav>

<section class="admin-module-hub admin-module-hub--green work-ui-compact-hub">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('organization') ?></div>
    <div>
        <h2>پروژه‌ها</h2>
        <p>فهرست پروژه‌های مدیریت کار و وضعیت عملیاتی آن‌ها</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/work">بازگشت به داشبورد کار</a>
</section>

<section class="admin-section">
    <div class="admin-users-toolbar work-projects-toolbar">
        <form class="admin-users-search" method="get" action="/admin/work/projects">
            <label for="work-projects-q">جستجو در پروژه‌ها</label>
            <div class="admin-users-search__row">
                <span class="admin-users-search__icon"><?= \App\Support\AdminIcon::html('search') ?></span>
                <input
                    id="work-projects-q"
                    type="search"
                    name="q"
                    value="<?= admin_h($q) ?>"
                    maxlength="120"
                    placeholder="عنوان یا شناسه پروژه"
                >
                <select name="status" aria-label="وضعیت پروژه">
                    <?php foreach ($statusOptions as $code => $statusTitle): ?>
                        <option value="<?= admin_h($code) ?>"<?= $selectedStatus === $code ? ' selected' : '' ?>>
                            <?= admin_h($statusTitle) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="sort" value="<?= admin_h($selectedSort) ?>">
                <input type="hidden" name="dir" value="<?= admin_h($selectedDirection) ?>">
                <div class="work-project-filter-actions">
                    <button class="admin-button" type="submit">اعمال فیلتر</button>
                    <?php if ($q !== '' || $selectedStatus !== ''): ?>
                        <a class="admin-button admin-button--soft" href="/admin/work/projects">بازنشانی</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="work-projects-toolbar__meta">
            <?php if ($canCreate): ?>
                <a class="admin-button" href="/admin/work/projects/create">ایجاد پروژه</a>
            <?php endif; ?>
            <div class="work-project-count">
                <span>تعداد پروژه‌ها:</span>
                <strong><?= admin_h(\App\Support\AdminFormat::digits($total)) ?></strong>
            </div>
        </div>
    </div>

    <?php if ($items === []): ?>
        <div class="admin-empty-state">
            <?= ($q === '' && $selectedStatus === '') ? 'هنوز پروژه‌ای ثبت نشده است.' : 'پروژه‌ای مطابق فیلترها پیدا نشد.' ?>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <?php foreach ([
                            'title' => 'پروژه',
                            'status' => 'وضعیت',
                            'visibility' => 'دسترسی',
                            'owner' => 'مالک',
                            'members' => 'اعضا',
                            'items' => 'آیتم‌ها',
                            'open_items' => 'باز',
                            'created_at' => 'تاریخ ایجاد',
                            'target_date' => 'تاریخ هدف',
                        ] as $column => $label): ?>
                            <th aria-sort="<?= admin_h($ariaSort($column)) ?>">
                                <a class="admin-sort-link" href="<?= admin_h($sortUrl($column)) ?>">
                                    <span><?= admin_h($label) ?></span>
                                    <span class="admin-sort-indicator" aria-hidden="true"><?= admin_h($sortIndicator($column)) ?></span>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $project): ?>
                        <?php $projectUrl = '/admin/work/projects/' . rawurlencode((string) ($project['public_reference'] ?? '')); ?>
                        <tr>
                            <td><?= admin_h(\App\Support\AdminFormat::digits($index + 1)) ?></td>
                            <td>
                                <a href="<?= admin_h($projectUrl) ?>"><strong><?= admin_h($project['title'] ?? '') ?></strong></a>
                                <?php if (!empty($project['organization_snapshot'])): ?>
                                    <small class="admin-muted"><?= admin_h($project['organization_snapshot']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="admin-pill"><?= admin_h($project['status_title'] ?? '') ?></span></td>
                            <td><?= admin_h($project['visibility_title'] ?? '') ?></td>
                            <td><?= admin_h((($project['owner_display_name'] ?? '') ?: (($project['owner_user_reference'] ?? '') ?: '—'))) ?></td>
                            <td><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['member_count'] ?? 0))) ?></td>
                            <td><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['item_count'] ?? 0))) ?></td>
                            <td><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['open_item_count'] ?? 0))) ?></td>
                            <td><?= admin_h(\App\Support\AdminFormat::jalaliDateTime((string) ($project['created_at'] ?? '')) ?: '—') ?></td>
                            <td><?= admin_h(\App\Support\PersianDate::fromGregorianDate((string) ($project['target_date'] ?? '')) ?: '—') ?></td>
                            <td>
                                <a class="admin-button admin-button--soft admin-button--compact" href="<?= admin_h($projectUrl) ?>">مشاهده</a>
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

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
$status = (string) ($list['status'] ?? '');
$statusOptions = $list['status_options'] ?? [];
$total = (int) ($list['total'] ?? count($items));

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/work">مدیریت کار</a>
    <span aria-hidden="true">/</span>
    <span>پروژه‌ها</span>
</nav>

<section class="admin-module-hub admin-module-hub--green">
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html('organization') ?>
    </div>
    <div>
        <h2>پروژه‌ها</h2>
        <p>فهرست پروژه‌های مدیریت کار و وضعیت عملیاتی آن‌ها</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/work">بازگشت به داشبورد کار</a>
</section>

<section class="admin-section">
    <div class="admin-users-toolbar">
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
                    placeholder="عنوان، کد یا شناسه پروژه"
                >
                <select name="status" aria-label="وضعیت پروژه">
                    <?php foreach ($statusOptions as $code => $title): ?>
                        <option value="<?= admin_h($code) ?>"<?= $status === $code ? ' selected' : '' ?>>
                            <?= admin_h($title) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="admin-button" type="submit">اعمال فیلتر</button>
                <?php if ($q !== '' || $status !== ''): ?>
                    <a class="admin-button admin-button--soft" href="/admin/work/projects">بازنشانی</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="admin-users-total">
            <span>تعداد پروژه‌ها</span>
            <strong><?= admin_h(\App\Support\AdminFormat::digits($total)) ?></strong>
        </div>
    </div>

    <?php if ($items === []): ?>
        <div class="admin-empty-state">
            <?= ($q === '' && $status === '') ? 'هنوز پروژه‌ای ثبت نشده است.' : 'پروژه‌ای مطابق فیلترها پیدا نشد.' ?>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>پروژه</th>
                        <th>کد</th>
                        <th>وضعیت</th>
                        <th>دسترسی</th>
                        <th>مالک</th>
                        <th>اعضا</th>
                        <th>آیتم‌ها</th>
                        <th>باز</th>
                        <th>تاریخ هدف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $project): ?>
                        <tr>
                            <td>
                                <strong><?= admin_h($project['title'] ?? '') ?></strong>
                                <?php if (!empty($project['organization_snapshot'])): ?>
                                    <small class="admin-muted"><?= admin_h($project['organization_snapshot']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td dir="ltr"><?= admin_h($project['code'] ?? '') ?></td>
                            <td><span class="admin-pill"><?= admin_h($project['status_title'] ?? '') ?></span></td>
                            <td><?= admin_h($project['visibility_title'] ?? '') ?></td>
                            <td dir="ltr"><?= admin_h($project['owner_user_reference'] ?: '—') ?></td>
                            <td><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['member_count'] ?? 0))) ?></td>
                            <td><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['item_count'] ?? 0))) ?></td>
                            <td><?= admin_h(\App\Support\AdminFormat::digits((int) ($project['open_item_count'] ?? 0))) ?></td>
                            <td dir="ltr"><?= admin_h($project['target_date'] ?: '—') ?></td>
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

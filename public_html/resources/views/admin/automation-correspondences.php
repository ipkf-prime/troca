<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}
$list = $list ?? [];
$items = $list['items'] ?? [];
$filters = $list['filters'] ?? [];
$options = $list['options'] ?? [];
$pagination = $list['pagination'] ?? [];
$dateFromFa = \App\Support\PersianDate::fromGregorianDate((string) ($filters['date_from'] ?? ''));
$dateToFa = \App\Support\PersianDate::fromGregorianDate((string) ($filters['date_to'] ?? ''));
$pageUrl = static function (int $targetPage) use ($filters): string {
    $params = array_filter($filters + ['page' => $targetPage], fn ($value) => $value !== '' && $value !== null);
    $params['page'] = $targetPage;
    return '/admin/automation/correspondences?' . http_build_query($params);
};
$select = static function (string $name, array $items, string $selected, string $label, string $class = ''): string {
    $html = '<label class="' . admin_h($class) . '"><span>' . admin_h($label) . '</span><select name="' . admin_h($name) . '"><option value="">همه</option>';
    foreach ($items as $item) {
        $code = (string) ($item['code'] ?? '');
        $html .= '<option value="' . admin_h($code) . '"' . ($code === $selected ? ' selected' : '') . '>' . admin_h($item['label'] ?? $code) . '</option>';
    }
    return $html . '</select></label>';
};
ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb"><a href="/admin/dashboard">داشبورد</a><span>/</span><a href="/admin/automation">اتوماسیون</a><span>/</span><span>مکاتبات</span></nav>
<section class="admin-module-hub admin-module-hub--teal admin-users-heading">
    <div class="admin-module-hub__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></div>
    <div><h2>مکاتبات اداری</h2><p>فهرست عملیاتی مکاتبات با جستجو، فیلتر و اقدام‌های مجاز</p></div>
    <a class="admin-module-hub__back" href="/admin/automation/correspondences/create">ایجاد پیش نویس</a>
</section>
<section class="admin-section admin-users-panel">
    <form class="automation-filter-grid" method="get" action="/admin/automation/correspondences">
        <label class="automation-filter-grid__search"><span>جستجو</span><input type="search" name="q" value="<?= admin_h($filters['q'] ?? '') ?>" maxlength="80" placeholder="موضوع، شناسه عمومی یا شماره بیرونی"></label>
        <?= $select('status', $options['statuses'] ?? [], (string) ($filters['status'] ?? ''), 'وضعیت', 'automation-filter-grid__select') ?>
        <?= $select('direction', $options['directions'] ?? [], (string) ($filters['direction'] ?? ''), 'نوع/جهت', 'automation-filter-grid__select') ?>
        <?= $select('priority', $options['priorities'] ?? [], (string) ($filters['priority'] ?? ''), 'اولویت', 'automation-filter-grid__select') ?>
        <label class="automation-filter-grid__date"><span>از تاریخ</span><div class="admin-persian-date" data-persian-datepicker><input type="text" name="date_from_fa" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="۱۴۰۵/۰۱/۰۱" value="<?= admin_h($dateFromFa) ?>"><input type="hidden" name="date_from" data-persian-date-output value="<?= admin_h($filters['date_from'] ?? '') ?>"><button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ"><?= \App\Support\AdminIcon::html('calendar') ?></button></div></label>
        <label class="automation-filter-grid__date"><span>تا تاریخ</span><div class="admin-persian-date" data-persian-datepicker><input type="text" name="date_to_fa" data-persian-date-input inputmode="numeric" autocomplete="off" placeholder="۱۴۰۵/۱۲/۲۹" value="<?= admin_h($dateToFa) ?>"><input type="hidden" name="date_to" data-persian-date-output value="<?= admin_h($filters['date_to'] ?? '') ?>"><button type="button" class="admin-persian-date__toggle" data-persian-date-toggle aria-label="انتخاب تاریخ"><?= \App\Support\AdminIcon::html('calendar') ?></button></div></label>
        <div class="automation-filter-grid__actions"><button class="admin-button" type="submit">اعمال فیلتر</button><a class="admin-button admin-button--soft" href="/admin/automation/correspondences">بازنشانی</a></div>
    </form>
    <?php if (($list['ok'] ?? false) !== true): ?>
        <div class="admin-alert">مکاتبات در حال حاضر در دسترس نیستند.</div>
    <?php elseif ($items === []): ?>
        <div class="admin-empty-state">هیچ مکاتبه‌ای مطابق فیلتر فعلی پیدا نشد.</div>
    <?php else: ?>
        <div class="admin-users-table-wrap"><table class="admin-table automation-table"><thead><tr><th>ردیف</th><th>موضوع</th><th>نوع</th><th>وضعیت</th><th>اولویت</th><th>محرمانگی</th><th>طرف اصلی</th><th>نسخه</th><th>تاریخ مرتبط</th><th>آخرین تغییر</th><th>اقدام</th></tr></thead><tbody>
        <?php foreach ($items as $index => $item): ?>
            <tr><td><?= admin_h(\App\Support\AdminFormat::digits(((int)($pagination['page'] ?? 1)-1) * (int)($pagination['per_page'] ?? 15) + $index + 1)) ?></td><td><a class="admin-users-identity admin-users-identity--link" href="<?= admin_h($item['url']) ?>"><strong><?= admin_h($item['subject']) ?></strong></a><small class="admin-user-detail-secondary" dir="ltr"><?= admin_h($item['public_reference']) ?></small></td><td><?= admin_h($item['type']) ?></td><td><span class="admin-status-badge admin-status-badge--<?= admin_h($item['status']['code']) ?>"><?= admin_h($item['status']['label']) ?></span></td><td><?= admin_h($item['priority']) ?></td><td><?= admin_h($item['confidentiality']) ?></td><td><?= admin_h($item['correspondent']) ?></td><td><?= admin_h($item['current_version']) ?></td><td><?= admin_h($item['relevant_date']) ?></td><td><?= admin_h($item['updated_at']) ?></td><td><a class="admin-button admin-button--soft admin-button--compact" href="<?= admin_h($item['url']) ?>">مشاهده</a><?php if ($item['editable']): ?> <a class="admin-button admin-button--compact" href="<?= admin_h($item['edit_url']) ?>">ویرایش</a><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <div class="admin-users-cards"><?php foreach ($items as $item): ?><article class="admin-user-card"><header><span class="admin-user-card__icon"><?= \App\Support\AdminIcon::html('file-lines') ?></span><div><strong><?= admin_h($item['subject']) ?></strong><small dir="ltr"><?= admin_h($item['public_reference']) ?></small></div><span class="admin-status-badge admin-status-badge--<?= admin_h($item['status']['code']) ?>"><?= admin_h($item['status']['label']) ?></span></header><dl><div><dt>نوع</dt><dd><?= admin_h($item['type']) ?></dd></div><div><dt>طرف اصلی</dt><dd><?= admin_h($item['correspondent']) ?></dd></div><div><dt>آخرین تغییر</dt><dd><?= admin_h($item['updated_at']) ?></dd></div></dl><a class="admin-button admin-button--soft" href="<?= admin_h($item['url']) ?>">مشاهده</a></article><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if (($pagination['total'] ?? 0) > 0): ?><div class="admin-pagination"><span>صفحه <?= admin_h(\App\Support\AdminFormat::digits($pagination['page'] ?? 1)) ?> از <?= admin_h(\App\Support\AdminFormat::digits($pagination['last_page'] ?? 1)) ?></span><div><?php if ($pagination['has_previous'] ?? false): ?><a class="admin-button admin-button--soft" href="<?= admin_h($pageUrl((int) $pagination['previous_page'])) ?>">قبلی</a><?php endif; ?><?php if ($pagination['has_next'] ?? false): ?><a class="admin-button" href="<?= admin_h($pageUrl((int) $pagination['next_page'])) ?>">بعدی</a><?php endif; ?></div></div><?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

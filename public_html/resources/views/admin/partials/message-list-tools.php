<?php
$filters = $page['filters'] ?? [];
$pagination = $page['pagination'] ?? ['page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => count($page['items'] ?? [])];
$basePath = $listBasePath ?? '';
$sortOptions = $listSortOptions ?? ['date' => 'تاریخ'];
$displayDate = static function ($value): string {
    $value = trim((string) $value);
    if ($value === '') return '';
    try {
        return \IPKF\Support\PersianDate::fromGregorianDate($value);
    } catch (\Throwable) {
        return $value;
    }
};
$query = static function (array $changes = []) use ($filters): string {
    return http_build_query(array_filter(array_merge($filters, $changes), static fn ($value) => $value !== '' && $value !== null));
};
?>
<form method="get" action="<?= admin_h($basePath) ?>" class="communication-filters">
    <input type="search" name="q" value="<?= admin_h($filters['q'] ?? '') ?>" placeholder="جست‌وجو در پیام‌ها…">
    <select name="status">
        <option value="">همه وضعیت‌ها</option>
        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>باز</option>
        <option value="closed" <?= ($filters['status'] ?? '') === 'closed' ? 'selected' : '' ?>>بسته</option>
    </select>
    <?php if (($showUnreadFilter ?? false) === true): ?>
        <select name="unread"><option value="">همه پیام‌ها</option><option value="1" <?= ($filters['unread'] ?? '') === '1' ? 'selected' : '' ?>>فقط خوانده‌نشده</option></select>
    <?php endif; ?>
    <input type="text" inputmode="numeric" name="from" value="<?= admin_h($displayDate($filters['from'] ?? '')) ?>" placeholder="از تاریخ شمسی" aria-label="از تاریخ شمسی">
    <input type="text" inputmode="numeric" name="to" value="<?= admin_h($displayDate($filters['to'] ?? '')) ?>" placeholder="تا تاریخ شمسی" aria-label="تا تاریخ شمسی">
    <select name="sort"><?php foreach ($sortOptions as $value => $label): ?><option value="<?= admin_h($value) ?>" <?= ($filters['sort'] ?? 'date') === $value ? 'selected' : '' ?>>مرتب‌سازی: <?= admin_h($label) ?></option><?php endforeach; ?></select>
    <select name="direction"><option value="desc" <?= ($filters['direction'] ?? 'desc') === 'desc' ? 'selected' : '' ?>>نزولی</option><option value="asc" <?= ($filters['direction'] ?? '') === 'asc' ? 'selected' : '' ?>>صعودی</option></select>
    <select name="per_page"><?php foreach ([10,20,50,100] as $size): ?><option value="<?= $size ?>" <?= (int) ($pagination['per_page'] ?? 20) === $size ? 'selected' : '' ?>><?= admin_h(\App\Support\AdminFormat::digits($size)) ?> ردیف</option><?php endforeach; ?></select>
    <button class="admin-button" type="submit">اعمال</button>
    <a class="admin-button admin-button--soft" href="<?= admin_h($basePath) ?>">پاک‌کردن</a>
</form>
<div class="communication-list-meta">
    <span><?= admin_h(\App\Support\AdminFormat::digits((int) ($pagination['total'] ?? 0))) ?> نتیجه</span>
    <?php if ((int) ($pagination['last_page'] ?? 1) > 1): ?>
        <nav class="communication-pagination">
            <?php if ((int) $pagination['page'] > 1): ?><a href="<?= admin_h($basePath . '?' . $query(['page' => (int) $pagination['page'] - 1])) ?>">قبلی</a><?php endif; ?>
            <span>صفحه <?= admin_h(\App\Support\AdminFormat::digits($pagination['page'])) ?> از <?= admin_h(\App\Support\AdminFormat::digits($pagination['last_page'])) ?></span>
            <?php if ((int) $pagination['page'] < (int) $pagination['last_page']): ?><a href="<?= admin_h($basePath . '?' . $query(['page' => (int) $pagination['page'] + 1])) ?>">بعدی</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>

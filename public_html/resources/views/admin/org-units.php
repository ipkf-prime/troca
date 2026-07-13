<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

$list = $list ?? [];
$items = $list['items'] ?? [];
$pagination = $list['pagination'] ?? [];
$q = (string) ($list['q'] ?? '');
$ok = (bool) ($list['ok'] ?? false);
$total = (int) ($pagination['total'] ?? 0);
$page = (int) ($pagination['page'] ?? 1);
$lastPage = (int) ($pagination['last_page'] ?? 1);

$pageUrl = static function (int $targetPage) use ($q): string {
    $params = ['page' => $targetPage];

    if ($q !== '') {
        $params['q'] = $q;
    }

    return '/admin/org-units?' . http_build_query($params);
};

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/modules/organization">ساختار سازمانی</a>
    <span aria-hidden="true">/</span>
    <span>واحدهای سازمانی</span>
</nav>

<section class="admin-module-hub admin-module-hub--teal admin-users-heading">
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html('sitemap') ?>
    </div>
    <div>
        <h2>واحدهای سازمانی</h2>
        <p>مشاهده واحدها و سلسله‌مراتب داخلی سازمان</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/modules/organization">بازگشت به ساختار سازمانی</a>
</section>

<section class="admin-section admin-users-panel">
    <div class="admin-users-toolbar">
        <form class="admin-users-search" method="get" action="/admin/org-units">
            <label for="admin-org-units-q">جستجو در واحدهای سازمانی</label>
            <div class="admin-users-search__row">
                <span class="admin-users-search__icon"><?= \App\Support\AdminIcon::html('search') ?></span>
                <input id="admin-org-units-q" type="search" name="q" value="<?= admin_h($q) ?>" maxlength="80" placeholder="عنوان، کد، نوع یا واحد بالادست">
                <button class="admin-button" type="submit">جستجو</button>
                <?php if ($q !== ''): ?>
                    <a class="admin-button admin-button--soft" href="/admin/org-units">بازنشانی</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="admin-users-total">
            <span>تعداد کل</span>
            <strong><?= admin_h($total) ?></strong>
        </div>
    </div>

    <?php if (!$ok): ?>
        <div class="admin-alert">امکان دریافت فهرست واحدهای سازمانی در حال حاضر وجود ندارد.</div>
    <?php elseif ($items === []): ?>
        <div class="admin-empty-state">
            <?= $q === '' ? 'هنوز واحد سازمانی ثبت نشده است.' : 'واحد سازمانی مطابق جستجو پیدا نشد.' ?>
        </div>
    <?php else: ?>
        <div class="admin-users-table-wrap admin-org-units-table-wrap">
            <table class="admin-table admin-org-units-table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>عنوان واحد</th>
                        <th>کد</th>
                        <th>نوع</th>
                        <th>واحد بالادست</th>
                        <th>سطح</th>
                        <th>وضعیت</th>
                        <th>ترتیب نمایش</th>
                        <th>تاریخ ایجاد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $unit): ?>
                        <tr>
                            <td><?= admin_h($unit['id']) ?></td>
                            <td>
                                <span class="admin-org-unit-title" style="--org-indent: <?= (int) $unit['indent'] ?>px">
                                    <span class="admin-org-unit-title__branch"><?= \App\Support\AdminIcon::html('sitemap') ?></span>
                                    <strong><?= admin_h($unit['title']) ?></strong>
                                </span>
                            </td>
                            <td dir="ltr"><?= admin_h($unit['code']) ?></td>
                            <td><?= admin_h($unit['type']) ?></td>
                            <td><?= admin_h($unit['parent_title']) ?></td>
                            <td><?= admin_h($unit['depth']) ?></td>
                            <td>
                                <span class="admin-status-badge admin-status-badge--<?= admin_h($unit['status']['code']) ?>">
                                    <?= admin_h($unit['status']['label']) ?>
                                </span>
                            </td>
                            <td><?= admin_h($unit['sort_order']) ?></td>
                            <td dir="ltr"><?= admin_h($unit['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-users-cards" aria-label="واحدهای سازمانی">
            <?php foreach ($items as $unit): ?>
                <article class="admin-user-card admin-org-unit-card">
                    <header>
                        <span class="admin-user-card__icon"><?= \App\Support\AdminIcon::html('building') ?></span>
                        <div>
                            <strong><?= admin_h($unit['title']) ?></strong>
                            <small dir="ltr"><?= admin_h($unit['code']) ?></small>
                        </div>
                        <span class="admin-status-badge admin-status-badge--<?= admin_h($unit['status']['code']) ?>">
                            <?= admin_h($unit['status']['label']) ?>
                        </span>
                    </header>
                    <dl>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('sitemap') ?> واحد بالادست</dt>
                            <dd><?= admin_h($unit['parent_title']) ?></dd>
                        </div>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('building') ?> نوع</dt>
                            <dd><?= admin_h($unit['type']) ?></dd>
                        </div>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('sliders') ?> سطح و ترتیب</dt>
                            <dd><?= admin_h($unit['depth']) ?> / <?= admin_h($unit['sort_order']) ?></dd>
                        </div>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('calendar') ?> تاریخ ایجاد</dt>
                            <dd dir="ltr"><?= admin_h($unit['created_at']) ?></dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($ok && $total > 0): ?>
        <div class="admin-pagination">
            <span>صفحه <?= admin_h($page) ?> از <?= admin_h($lastPage) ?></span>
            <div>
                <?php if (($pagination['has_previous'] ?? false) === true): ?>
                    <a class="admin-button admin-button--soft" href="<?= admin_h($pageUrl((int) $pagination['previous_page'])) ?>">قبلی</a>
                <?php endif; ?>
                <?php if (($pagination['has_next'] ?? false) === true): ?>
                    <a class="admin-button" href="<?= admin_h($pageUrl((int) $pagination['next_page'])) ?>">بعدی</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

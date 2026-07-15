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

    return '/admin/positions?' . http_build_query($params);
};

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/modules/organization">ساختار سازمانی</a>
    <span aria-hidden="true">/</span>
    <span>سمت‌ها</span>
</nav>

<section class="admin-module-hub admin-module-hub--teal admin-users-heading">
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html('id-badge') ?>
    </div>
    <div>
        <h2>سمت‌ها</h2>
        <p>مشاهده عناوین و سمت‌های سازمانی</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/modules/organization">بازگشت به ساختار سازمانی</a>
</section>

<section class="admin-section admin-users-panel">
    <div class="admin-users-toolbar">
        <form class="admin-users-search" method="get" action="/admin/positions">
            <label for="admin-positions-q">جستجو در سمت‌ها</label>
            <div class="admin-users-search__row">
                <span class="admin-users-search__icon"><?= \App\Support\AdminIcon::html('search') ?></span>
                <input id="admin-positions-q" type="search" name="q" value="<?= admin_h($q) ?>" maxlength="80" placeholder="عنوان، کد یا توضیحات">
                <button class="admin-button" type="submit">جستجو</button>
                <?php if ($q !== ''): ?>
                    <a class="admin-button admin-button--soft" href="/admin/positions">بازنشانی</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="admin-users-total">
            <span>تعداد کل</span>
            <strong><?= admin_h(\App\Support\AdminFormat::digits($total)) ?></strong>
        </div>
    </div>

    <?php if (!$ok): ?>
        <div class="admin-alert">امکان دریافت فهرست سمت‌ها در حال حاضر وجود ندارد.</div>
    <?php elseif ($items === []): ?>
        <div class="admin-empty-state">
            <?= $q === '' ? 'هنوز سمتی ثبت نشده است.' : 'سمتی مطابق جستجو پیدا نشد.' ?>
        </div>
    <?php else: ?>
        <div class="admin-users-table-wrap admin-positions-table-wrap">
            <table class="admin-table admin-positions-table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>عنوان سمت</th>
                        <th>کد</th>
                        <th>توضیحات کوتاه</th>
                        <th>وضعیت</th>
                        <th>ترتیب نمایش</th>
                        <th>تاریخ ایجاد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $position): ?>
                        <tr>
                            <td><?= admin_h(\App\Support\AdminFormat::digits($position['id'])) ?></td>
                            <td>
                                <span class="admin-position-title">
                                    <?= \App\Support\AdminIcon::html('id-badge') ?>
                                    <strong><?= admin_h($position['title']) ?></strong>
                                </span>
                            </td>
                            <td dir="ltr"><?= admin_h($position['code']) ?></td>
                            <td><?= admin_h($position['description']) ?></td>
                            <td>
                                <span class="admin-status-badge admin-status-badge--<?= admin_h($position['status']['code']) ?>">
                                    <?= admin_h($position['status']['label']) ?>
                                </span>
                            </td>
                            <td><?= admin_h(\App\Support\AdminFormat::digits($position['sort_order'])) ?></td>
                            <td dir="ltr"><?= admin_h(\App\Support\AdminFormat::digits($position['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-users-cards" aria-label="سمت‌ها">
            <?php foreach ($items as $position): ?>
                <article class="admin-user-card admin-position-card">
                    <header>
                        <span class="admin-user-card__icon"><?= \App\Support\AdminIcon::html('id-badge') ?></span>
                        <div>
                            <strong><?= admin_h($position['title']) ?></strong>
                            <small dir="ltr"><?= admin_h($position['code']) ?></small>
                        </div>
                        <span class="admin-status-badge admin-status-badge--<?= admin_h($position['status']['code']) ?>">
                            <?= admin_h($position['status']['label']) ?>
                        </span>
                    </header>
                    <dl>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('file-lines') ?> توضیحات کوتاه</dt>
                            <dd><?= admin_h($position['description']) ?></dd>
                        </div>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('sliders') ?> ترتیب نمایش</dt>
                            <dd><?= admin_h(\App\Support\AdminFormat::digits($position['sort_order'])) ?></dd>
                        </div>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('calendar') ?> تاریخ ایجاد</dt>
                            <dd dir="ltr"><?= admin_h(\App\Support\AdminFormat::digits($position['created_at'])) ?></dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($ok && $total > 0): ?>
        <div class="admin-pagination">
            <span>صفحه <?= admin_h(\App\Support\AdminFormat::digits($page)) ?> از <?= admin_h(\App\Support\AdminFormat::digits($lastPage)) ?></span>
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

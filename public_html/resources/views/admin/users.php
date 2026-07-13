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

    return '/admin/users?' . http_build_query($params);
};

ob_start();
?>
<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span aria-hidden="true">/</span>
    <a href="/admin/modules/users">مدیریت کاربران</a>
    <span aria-hidden="true">/</span>
    <span>کاربران</span>
</nav>

<section class="admin-module-hub admin-module-hub--blue admin-users-heading">
    <div class="admin-module-hub__icon">
        <?= \App\Support\AdminIcon::html('users') ?>
    </div>
    <div>
        <h2>کاربران</h2>
        <p>مشاهده حساب‌های کاربری و وضعیت دسترسی آن‌ها</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/modules/users">بازگشت به مدیریت کاربران</a>
</section>

<section class="admin-section admin-users-panel">
    <div class="admin-users-toolbar">
        <form class="admin-users-search" method="get" action="/admin/users">
            <label for="admin-users-q">جستجو در کاربران</label>
            <div class="admin-users-search__row">
                <span class="admin-users-search__icon"><?= \App\Support\AdminIcon::html('search') ?></span>
                <input id="admin-users-q" type="search" name="q" value="<?= admin_h($q) ?>" maxlength="80" placeholder="نام، نام کاربری، موبایل یا ایمیل">
                <button class="admin-button" type="submit">جستجو</button>
                <?php if ($q !== ''): ?>
                    <a class="admin-button admin-button--soft" href="/admin/users">بازنشانی</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="admin-users-total">
            <span>تعداد کل</span>
            <strong><?= admin_h(\App\Support\AdminFormat::digits($total)) ?></strong>
        </div>
    </div>

    <?php if (!$ok): ?>
        <div class="admin-alert">امکان دریافت فهرست کاربران در حال حاضر وجود ندارد.</div>
    <?php elseif ($items === []): ?>
        <div class="admin-empty-state">
            <?= $q === '' ? 'هنوز کاربری ثبت نشده است.' : 'کاربری مطابق جستجو پیدا نشد.' ?>
        </div>
    <?php else: ?>
        <div class="admin-users-table-wrap">
            <table class="admin-table admin-users-table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام کامل</th>
                        <th>نام کاربری</th>
                        <th>موبایل</th>
                        <th>ایمیل</th>
                        <th>وضعیت حساب</th>
                        <th>نقش‌های فعال</th>
                        <th>واحد اصلی</th>
                        <th>تاریخ ایجاد</th>
                        <th>جزئیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $user): ?>
                        <tr>
                            <td><?= admin_h(\App\Support\AdminFormat::digits($user['id'])) ?></td>
                            <td>
                                <a class="admin-users-identity admin-users-identity--link" href="<?= admin_h($user['detail_url']) ?>">
                                    <?= \App\Support\AdminIcon::html('user') ?>
                                    <strong><?= admin_h($user['name']) ?></strong>
                                </a>
                            </td>
                            <td dir="ltr"><?= admin_h($user['username']) ?></td>
                            <td dir="ltr"><?= admin_h($user['mobile']) ?></td>
                            <td dir="ltr"><?= admin_h($user['email']) ?></td>
                            <td>
                                <span class="admin-status-badge admin-status-badge--<?= admin_h($user['status']['code']) ?>">
                                    <?= admin_h($user['status']['label']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (($user['roles'] ?? []) !== []): ?>
                                    <span class="admin-role-stack">
                                        <?php foreach (array_slice($user['roles'], 0, 2) as $role): ?>
                                            <span class="admin-pill"><?= admin_h($role) ?></span>
                                        <?php endforeach; ?>
                                        <?php if ((int) $user['role_count'] > 2): ?>
                                            <span class="admin-pill admin-pill--muted">+<?= admin_h(\App\Support\AdminFormat::digits(((int) $user['role_count']) - 2)) ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <?= admin_h($user['role_summary']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= admin_h($user['primary_org_unit']) ?></td>
                            <td dir="ltr"><?= admin_h(\App\Support\AdminFormat::digits($user['created_at'])) ?></td>
                            <td><a class="admin-button admin-button--soft admin-button--compact" href="<?= admin_h($user['detail_url']) ?>">مشاهده</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-users-cards" aria-label="کاربران">
            <?php foreach ($items as $user): ?>
                <article class="admin-user-card">
                    <header>
                        <span class="admin-user-card__icon"><?= \App\Support\AdminIcon::html('user') ?></span>
                        <div>
                            <strong><?= admin_h($user['name']) ?></strong>
                            <small dir="ltr"><?= admin_h($user['username']) ?></small>
                        </div>
                        <span class="admin-status-badge admin-status-badge--<?= admin_h($user['status']['code']) ?>">
                            <?= admin_h($user['status']['label']) ?>
                        </span>
                    </header>
                    <dl>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('mobile') ?> موبایل</dt>
                            <dd dir="ltr"><?= admin_h($user['mobile']) ?></dd>
                        </div>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('email') ?> ایمیل</dt>
                            <dd dir="ltr"><?= admin_h($user['email']) ?></dd>
                        </div>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('roles') ?> نقش‌ها</dt>
                            <dd><?= admin_h($user['role_summary']) ?></dd>
                        </div>
                        <div>
                            <dt><?= \App\Support\AdminIcon::html('calendar') ?> تاریخ ایجاد</dt>
                            <dd dir="ltr"><?= admin_h(\App\Support\AdminFormat::digits($user['created_at'])) ?></dd>
                        </div>
                    </dl>
                    <a class="admin-button admin-button--soft" href="<?= admin_h($user['detail_url']) ?>">مشاهده جزئیات</a>
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

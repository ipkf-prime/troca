<?php
if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$list = $list ?? [];
$items = $list['items'] ?? [];
$pagination = $list['pagination'] ?? [];
$q = (string) ($list['q'] ?? '');
$sort = (string) ($list['sort'] ?? 'created_at');
$dir = (string) ($list['dir'] ?? 'desc');
$ok = (bool) ($list['ok'] ?? false);
$total = (int) ($pagination['total'] ?? 0);
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 20);
$lastPage = (int) ($pagination['last_page'] ?? 1);
$canCreate = (bool) ($canCreate ?? false);
$canUpdate = (bool) ($canUpdate ?? false);
$status = (string) ($status ?? '');

$baseQuery = [
    'q' => $q,
    'sort' => $sort,
    'dir' => $dir,
];

$pageUrl = static function (
    int $targetPage
) use ($baseQuery): string {
    return '/admin/users?' . http_build_query(
        array_filter(
            $baseQuery + ['page' => $targetPage],
            static fn ($value): bool =>
                $value !== null && $value !== ''
        )
    );
};

$sortUrl = static function (
    string $column,
    string $defaultDirection = 'asc'
) use ($q, $sort, $dir): string {
    return \App\Support\AdminTableSort::url(
        '/admin/users',
        $column,
        $sort,
        $dir,
        ['q' => $q],
        $defaultDirection
    );
};

$sortIndicator = static function (
    string $column
) use ($sort, $dir): string {
    return \App\Support\AdminTableSort::indicator(
        $column,
        $sort,
        $dir
    );
};

ob_start();
?>
<style>
.admin-users-toolbar {
    align-items:end;
    display:grid;
    gap:.7rem;
    grid-template-columns:minmax(18rem,1fr) auto auto;
}

.admin-users-search {
    min-width:0;
}

.admin-users-search__row {
    display:grid;
    gap:.55rem;
    grid-template-columns:2.5rem minmax(12rem,1fr) auto auto;
}

.admin-users-toolbar__action {
    align-items:center;
    display:flex;
}

.admin-users-total {
    min-width:6.5rem;
    white-space:nowrap;
}

.admin-users-table {
    table-layout:auto;
    width:100%;
}

.admin-users-table col.col-index { width:3.4rem; }
.admin-users-table col.col-username { width:7.5rem; }
.admin-users-table col.col-status { width:7.5rem; }
.admin-users-table col.col-actions { width:11rem; }

.admin-sort-link {
    align-items:center;
    background:transparent;
    border:0;
    color:inherit;
    display:inline-flex;
    font:inherit;
    gap:.3rem;
    padding:0;
    text-decoration:none;
    white-space:nowrap;
}

.admin-sort-link__indicator {
    color:var(--admin-text-muted);
    font-size:.72rem;
    min-width:.8rem;
}

.admin-sort-link.is-active {
    color:var(--admin-primary);
}

.admin-users-highest-role {
    align-items:center;
    display:inline-flex;
    gap:.35rem;
    max-width:14rem;
}

.admin-users-highest-role .admin-pill {
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.admin-users-role-count {
    color:var(--admin-text-muted);
    font-size:.66rem;
    white-space:nowrap;
}

@media (max-width:1050px) {
    .admin-users-toolbar {
        grid-template-columns:minmax(15rem,1fr) auto;
    }

    .admin-users-total {
        grid-column:2;
        grid-row:1;
    }

    .admin-users-toolbar__action {
        grid-column:2;
        grid-row:2;
    }
}

@media (max-width:760px) {
    .admin-users-toolbar {
        align-items:stretch;
        grid-template-columns:1fr 1fr;
    }

    .admin-users-search {
        grid-column:1/-1;
        grid-row:2;
    }

    .admin-users-search__row {
        grid-template-columns:2.5rem minmax(0,1fr);
    }

    .admin-users-search__row button,
    .admin-users-search__row a {
        width:100%;
    }

    .admin-users-toolbar__action,
    .admin-users-total {
        grid-column:auto;
        grid-row:1;
    }
}
</style>

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
        <p>ایجاد، مشاهده و مدیریت حساب‌های کاربری و دسترسی‌ها</p>
    </div>
    <a class="admin-module-hub__back" href="/admin/modules/users">
        بازگشت به مدیریت کاربران
    </a>
</section>

<?php if ($status === 'created'): ?>
    <section class="admin-section">
        <div class="admin-alert admin-alert--success">
            کاربر جدید با موفقیت ایجاد شد.
        </div>
    </section>
<?php endif; ?>

<section class="admin-section admin-users-panel">
    <div class="admin-users-toolbar">
        <form
            class="admin-users-search"
            method="get"
            action="/admin/users"
        >
            <input
                type="hidden"
                name="sort"
                value="<?= admin_h($sort) ?>"
            >
            <input
                type="hidden"
                name="dir"
                value="<?= admin_h($dir) ?>"
            >
            <label for="admin-users-q">جستجو در کاربران</label>
            <div class="admin-users-search__row">
                <span class="admin-users-search__icon">
                    <?= \App\Support\AdminIcon::html('search') ?>
                </span>
                <input
                    id="admin-users-q"
                    type="search"
                    name="q"
                    value="<?= admin_h($q) ?>"
                    maxlength="80"
                    placeholder="نام، نام کاربری، موبایل یا ایمیل"
                >
                <button class="admin-button" type="submit">
                    جستجو
                </button>
                <?php if ($q !== ''): ?>
                    <a
                        class="admin-button admin-button--soft"
                        href="<?= admin_h(
                            '/admin/users?'
                            . http_build_query([
                                'sort' => $sort,
                                'dir' => $dir,
                            ])
                        ) ?>"
                    >
                        بازنشانی
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($canCreate): ?>
            <div class="admin-users-toolbar__action">
                <a class="admin-button" href="/admin/users/create">
                    ایجاد کاربر
                </a>
            </div>
        <?php endif; ?>

        <div class="admin-users-total">
            <span>تعداد کل</span>
            <strong>
                <?= admin_h(
                    \App\Support\AdminFormat::digits($total)
                ) ?>
            </strong>
        </div>
    </div>

    <?php if (!$ok): ?>
        <div class="admin-alert">
            امکان دریافت فهرست کاربران در حال حاضر وجود ندارد.
        </div>
    <?php elseif ($items === []): ?>
        <div class="admin-empty-state">
            <?= $q === ''
                ? 'هنوز کاربری ثبت نشده است.'
                : 'کاربری مطابق جستجو پیدا نشد.' ?>
        </div>
    <?php else: ?>
        <div class="admin-users-table-wrap">
            <table class="admin-table admin-users-table">
                <colgroup>
                    <col class="col-index">
                    <col>
                    <col class="col-username">
                    <col>
                    <col>
                    <col class="col-status">
                    <col>
                    <col>
                    <col>
                    <col class="col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <?php foreach ([
                            'name' => ['نام کامل', 'asc'],
                            'username' => ['نام کاربری', 'asc'],
                            'mobile' => ['موبایل', 'asc'],
                            'email' => ['ایمیل', 'asc'],
                            'status' => ['وضعیت حساب', 'asc'],
                            'role' => ['بالاترین نقش', 'desc'],
                            'org_unit' => ['واحد اصلی', 'asc'],
                            'created_at' => ['تاریخ ایجاد', 'desc'],
                        ] as $column => [$label, $defaultDirection]): ?>
                            <th>
                                <a
                                    class="admin-sort-link<?= $sort === $column
                                        ? ' is-active'
                                        : '' ?>"
                                    href="<?= admin_h(
                                        $sortUrl(
                                            $column,
                                            $defaultDirection
                                        )
                                    ) ?>"
                                >
                                    <span><?= admin_h($label) ?></span>
                                    <span class="admin-sort-link__indicator">
                                        <?= admin_h(
                                            $sortIndicator($column)
                                        ) ?>
                                    </span>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $user): ?>
                        <tr>
                            <td>
                                <?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        (($page - 1) * $perPage)
                                        + $index
                                        + 1
                                    )
                                ) ?>
                            </td>
                            <td>
                                <a
                                    class="admin-users-identity admin-users-identity--link"
                                    href="<?= admin_h(
                                        $user['detail_url']
                                    ) ?>"
                                >
                                    <?= \App\Support\AdminIcon::html('user') ?>
                                    <strong>
                                        <?= admin_h($user['name']) ?>
                                    </strong>
                                </a>
                            </td>
                            <td dir="ltr">
                                <?= admin_h($user['username']) ?>
                            </td>
                            <td dir="ltr">
                                <?= admin_h($user['mobile']) ?>
                            </td>
                            <td dir="ltr">
                                <?= admin_h($user['email']) ?>
                            </td>
                            <td>
                                <span class="admin-status-badge admin-status-badge--<?= admin_h(
                                    $user['status']['code']
                                ) ?>">
                                    <?= admin_h(
                                        $user['status']['label']
                                    ) ?>
                                </span>
                            </td>
                            <td>
                                <span class="admin-users-highest-role">
                                    <span class="admin-pill">
                                        <?= admin_h(
                                            $user['highest_role']
                                        ) ?>
                                    </span>
                                    <?php if (
                                        (int) $user['role_count'] > 1
                                    ): ?>
                                        <small class="admin-users-role-count">
                                            +<?= admin_h(
                                                \App\Support\AdminFormat::digits(
                                                    (int) $user['role_count'] - 1
                                                )
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?= admin_h(
                                    $user['primary_org_unit']
                                ) ?>
                            </td>
                            <td dir="ltr">
                                <?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $user['created_at']
                                    )
                                ) ?>
                            </td>
                            <td>
                                <div class="admin-form-actions">
                                    <a
                                        class="admin-button admin-button--soft admin-button--compact"
                                        href="<?= admin_h(
                                            $user['detail_url']
                                        ) ?>"
                                    >
                                        مشاهده
                                    </a>
                                    <?php if ($canUpdate): ?>
                                        <a
                                            class="admin-button admin-button--soft admin-button--compact"
                                            href="<?= admin_h(
                                                '/admin/users/'
                                                . (int) $user['id']
                                                . '/edit'
                                            ) ?>"
                                        >
                                            ویرایش
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-users-cards" aria-label="کاربران">
            <?php foreach ($items as $user): ?>
                <article class="admin-user-card">
                    <header>
                        <span class="admin-user-card__icon">
                            <?= \App\Support\AdminIcon::html('user') ?>
                        </span>
                        <div>
                            <strong><?= admin_h($user['name']) ?></strong>
                            <small dir="ltr">
                                <?= admin_h($user['username']) ?>
                            </small>
                        </div>
                        <span class="admin-status-badge admin-status-badge--<?= admin_h(
                            $user['status']['code']
                        ) ?>">
                            <?= admin_h($user['status']['label']) ?>
                        </span>
                    </header>

                    <dl>
                        <div>
                            <dt>
                                <?= \App\Support\AdminIcon::html('mobile') ?>
                                موبایل
                            </dt>
                            <dd dir="ltr">
                                <?= admin_h($user['mobile']) ?>
                            </dd>
                        </div>
                        <div>
                            <dt>
                                <?= \App\Support\AdminIcon::html('email') ?>
                                ایمیل
                            </dt>
                            <dd dir="ltr">
                                <?= admin_h($user['email']) ?>
                            </dd>
                        </div>
                        <div>
                            <dt>
                                <?= \App\Support\AdminIcon::html('roles') ?>
                                نقش‌ها
                            </dt>
                            <dd>
                                <?= admin_h(
                                    $user['highest_role']
                                ) ?>
                                <?php if (
                                    (int) $user['role_count'] > 1
                                ): ?>
                                    <small class="admin-muted">
                                        +<?= admin_h(
                                            \App\Support\AdminFormat::digits(
                                                (int) $user['role_count'] - 1
                                            )
                                        ) ?>
                                        نقش دیگر
                                    </small>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>
                                <?= \App\Support\AdminIcon::html('calendar') ?>
                                تاریخ ایجاد
                            </dt>
                            <dd dir="ltr">
                                <?= admin_h(
                                    \App\Support\AdminFormat::digits(
                                        $user['created_at']
                                    )
                                ) ?>
                            </dd>
                        </div>
                    </dl>

                    <div class="admin-form-actions">
                        <a
                            class="admin-button admin-button--soft"
                            href="<?= admin_h($user['detail_url']) ?>"
                        >
                            مشاهده جزئیات
                        </a>
                        <?php if ($canUpdate): ?>
                            <a
                                class="admin-button admin-button--soft"
                                href="<?= admin_h(
                                    '/admin/users/'
                                    . (int) $user['id']
                                    . '/edit'
                                ) ?>"
                            >
                                ویرایش
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($ok && $total > 0): ?>
        <div class="admin-pagination">
            <span>
                صفحه
                <?= admin_h(
                    \App\Support\AdminFormat::digits($page)
                ) ?>
                از
                <?= admin_h(
                    \App\Support\AdminFormat::digits($lastPage)
                ) ?>
            </span>
            <div>
                <?php if (
                    ($pagination['has_previous'] ?? false) === true
                ): ?>
                    <a
                        class="admin-button admin-button--soft"
                        href="<?= admin_h(
                            $pageUrl(
                                (int) $pagination['previous_page']
                            )
                        ) ?>"
                    >
                        قبلی
                    </a>
                <?php endif; ?>

                <?php if (
                    ($pagination['has_next'] ?? false) === true
                ): ?>
                    <a
                        class="admin-button"
                        href="<?= admin_h(
                            $pageUrl(
                                (int) $pagination['next_page']
                            )
                        ) ?>"
                    >
                        بعدی
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';

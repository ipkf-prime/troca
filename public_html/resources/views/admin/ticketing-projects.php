<?php

declare(strict_types=1);

if (!function_exists('ticketing_h')) {
    function ticketing_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$list =
    $list
    ?? [];

$items =
    $list['items']
    ?? [];

$q =
    (string) (
        $list['q']
        ?? ''
    );

$status =
    (string) (
        $list['status']
        ?? ''
    );

$total =
    (int) (
        $list['total']
        ?? count($items)
    );

ob_start();
?>

<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/dashboard">
        داشبورد
    </a>
    <span>/</span>

    <a href="/admin/ticketing">
        پشتیبانی و تیکتینگ
    </a>
    <span>/</span>

    <span>
        پروژه‌های پشتیبانی
    </span>
</nav>


<div class="admin-page ticketing-page">

    <div class="admin-page-header">
        <div>
            <h1>
                پروژه‌های پشتیبانی
            </h1>

            <p>
                تعریف و مدیریت پروژه‌های مستقل
                پشتیبانی در سامانه تیکتینگ
            </p>
        </div>

        <div class="admin-form-actions">

            <a
                class="admin-button admin-button--soft"
                href="/admin/ticketing/participants"
            >
                مخاطبان تیکتینگ
            </a>

            <a
                class="admin-button"
                href="/admin/ticketing/projects/create"
            >
                پروژه جدید
            </a>

        </div>
    </div>


    <section class="admin-section">

        <div class="admin-users-toolbar">

            <form
                class="admin-users-search"
                method="get"
                action="/admin/ticketing/projects"
            >
                <label for="support-project-q">
                    جستجو
                </label>

                <div class="admin-users-search__row">

                    <input
                        id="support-project-q"
                        type="search"
                        name="q"
                        maxlength="120"
                        value="<?= ticketing_h($q) ?>"
                        placeholder="عنوان، کد یا شناسه پروژه"
                    >

                    <select
                        name="status"
                        aria-label="وضعیت پروژه"
                    >
                        <option value="">
                            همه وضعیت‌ها
                        </option>

                        <option
                            value="active"
                            <?= $status === 'active'
                                ? ' selected'
                                : '' ?>
                        >
                            فعال
                        </option>

                        <option
                            value="inactive"
                            <?= $status === 'inactive'
                                ? ' selected'
                                : '' ?>
                        >
                            غیرفعال
                        </option>
                    </select>

                    <button
                        class="admin-button"
                        type="submit"
                    >
                        اعمال فیلتر
                    </button>

                    <?php if (
                        $q !== ''
                        || $status !== ''
                    ): ?>
                        <a
                            class="admin-button admin-button--soft"
                            href="/admin/ticketing/projects"
                        >
                            بازنشانی
                        </a>
                    <?php endif; ?>

                </div>
            </form>

            <div class="admin-muted">
                تعداد:
                <strong>
                    <?= ticketing_h(
                        \App\Support\AdminFormat::digits(
                            $total
                        )
                    ) ?>
                </strong>
            </div>

        </div>


        <?php if ($items === []): ?>

            <div class="admin-empty-state">
                هنوز پروژه پشتیبانی تعریف نشده است.
            </div>

        <?php else: ?>

            <div class="admin-table-wrap">

                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>کد</th>
                        <th>سرویس</th>
                        <th>عضو</th>
                        <th>تیکت</th>
                        <th>وضعیت</th>
                        <th>آخرین تغییر</th>
                        <th>عملیات</th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php foreach (
                        $items
                        as $project
                    ): ?>

                        <?php
                        $editUrl =
                            '/admin/ticketing/projects/'
                            . rawurlencode(
                                (string) $project[
                                    'public_reference'
                                ]
                            )
                            . '/edit';

                        $topologyUrl =
                            substr(
                                $editUrl,
                                0,
                                -5
                            )
                            . '/topology';

                        $routingUrl =
                            substr(
                                $editUrl,
                                0,
                                -5
                            )
                            . '/routing';
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= ticketing_h(
                                        $project['title']
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $project['code']
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (int) $project[
                                            'service_count'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (int) $project[
                                            'member_count'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (int) $project[
                                            'ticket_count'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <span class="admin-pill">
                                    <?= (int) $project[
                                        'is_active'
                                    ] === 1
                                        ? 'فعال'
                                        : 'غیرفعال' ?>
                                </span>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::jalaliDateTime(
                                        (string) (
                                            $project[
                                                'updated_at'
                                            ]
                                            ?? ''
                                        )
                                    )
                                    ?: '—'
                                ) ?>
                            </td>

                            <td>
                                <a
                                    class="admin-button admin-button--soft admin-button--compact"
                                    href="<?= ticketing_h($editUrl) ?>"
                                >
                                    ویرایش
                                </a>

                                <a
                                    class="admin-button admin-button--soft admin-button--compact"
                                    href="<?= ticketing_h($topologyUrl) ?>"
                                >
                                    ساختار پشتیبانی
                                </a>

                                <a
                                    class="admin-button admin-button--soft admin-button--compact"
                                    href="<?= ticketing_h($routingUrl) ?>"
                                >
                                    موضوعات و مسیریابی
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php

$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';

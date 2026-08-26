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

$priority =
    (string) (
        $list['priority']
        ?? ''
    );

$statusOptions =
    $list['status_options']
    ?? [];

$priorityOptions =
    $list['priority_options']
    ?? [];

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
        تیکت‌های من
    </span>
</nav>


<div class="admin-page ticketing-page ticketing-list-page">

    <div class="admin-page-header ticketing-page-head">
        <div>
            <h1>
                تیکت‌های من
            </h1>

            <p>
                مشاهده و پیگیری درخواست‌های
                ثبت‌شده توسط شما
            </p>
        </div>

        <a
            class="admin-button"
            href="/admin/ticketing/tickets/create"
        >
            تیکت جدید
        </a>
    </div>


    <section class="admin-section ticketing-filter-section">

        <div class="admin-users-toolbar ticketing-filter-toolbar">

            <form
                class="admin-users-search ticketing-filter-form"
                method="get"
                action="/admin/ticketing/tickets"
            >
                <label for="ticketing-q">
                    جستجو در تیکت‌ها
                </label>

                <div class="admin-users-search__row ticketing-filter-row">

                    <input
                        id="ticketing-q"
                        type="search"
                        name="q"
                        value="<?= ticketing_h($q) ?>"
                        maxlength="120"
                        placeholder="شماره، عنوان یا متن شناسه"
                    >

                    <select
                        name="status"
                        aria-label="وضعیت"
                    >
                        <option value="">
                            همه وضعیت‌ها
                        </option>

                        <?php foreach (
                            $statusOptions
                            as $code => $label
                        ): ?>

                            <option
                                value="<?= ticketing_h($code) ?>"
                                <?= $status === (string) $code
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($label) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>


                    <select
                        name="priority"
                        aria-label="اولویت"
                    >
                        <option value="">
                            همه اولویت‌ها
                        </option>

                        <?php foreach (
                            $priorityOptions
                            as $code => $label
                        ): ?>

                            <option
                                value="<?= ticketing_h($code) ?>"
                                <?= $priority === (string) $code
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($label) ?>
                            </option>

                        <?php endforeach; ?>
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
                        || $priority !== ''
                    ): ?>

                        <a
                            class="admin-button admin-button--soft"
                            href="/admin/ticketing/tickets"
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
                <?php if (
                    $q === ''
                    && $status === ''
                    && $priority === ''
                ): ?>
                    هنوز تیکتی ثبت نشده است.
                <?php else: ?>
                    تیکتی مطابق فیلترها پیدا نشد.
                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="admin-table-wrap">

                <table class="admin-table">

                    <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>شماره</th>
                        <th>عنوان</th>
                        <th>دسته</th>
                        <th>اولویت</th>
                        <th>وضعیت</th>
                        <th>ثبت</th>
                        <th>آخرین فعالیت</th>
                        <th>عملیات</th>
                    </tr>
                    </thead>


                    <tbody>

                    <?php foreach (
                        $items
                        as $index => $ticket
                    ): ?>

                        <?php
                        $url =
                            '/admin/ticketing/tickets/'
                            . rawurlencode(
                                (string) (
                                    $ticket[
                                        'public_reference'
                                    ]
                                    ?? ''
                                )
                            );
                        ?>

                        <tr>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        $index + 1
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (string) (
                                            $ticket[
                                                'ticket_number'
                                            ]
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <a href="<?= ticketing_h($url) ?>">
                                    <strong>
                                        <?= ticketing_h(
                                            $ticket[
                                                'subject'
                                            ]
                                            ?? ''
                                        ) ?>
                                    </strong>
                                </a>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $ticket[
                                        'category_title'
                                    ]
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $ticket[
                                        'priority_title'
                                    ]
                                    ?? ''
                                ) ?>
                            </td>

                            <td>
                                <span class="admin-pill">
                                    <?= ticketing_h(
                                        $ticket[
                                            'status_title'
                                        ]
                                        ?? ''
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::jalaliDateTime(
                                        (string) (
                                            $ticket[
                                                'created_at'
                                            ]
                                            ?? ''
                                        )
                                    )
                                    ?: '—'
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::jalaliDateTime(
                                        (string) (
                                            $ticket[
                                                'last_activity_at'
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
                                    href="<?= ticketing_h($url) ?>"
                                >
                                    مشاهده
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

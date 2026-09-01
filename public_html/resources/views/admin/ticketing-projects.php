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
                class="admin-button admin-button--soft"
                href="/admin/ticketing/statuses"
            >
                عنوان وضعیت‌ها
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
                        <th>زیرسامانه</th>
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
                                <a data-project-action-icon="edit" title="ویرایش پروژه" aria-label="ویرایش پروژه" style="width:2.35rem;height:2.35rem;min-width:2.35rem;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:.7rem"
                                    class="admin-button admin-button--soft admin-button--compact"
                                    href="<?= ticketing_h($editUrl) ?>"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </a>


                                <!-- TICKETING_PROJECT_ACTION_ICON_SIZE_CONTRACT -->
<style>
[data-project-action-icon]{
    width:2.15rem !important;
    height:2.15rem !important;
    min-width:2.15rem !important;
    max-width:2.15rem !important;
    padding:0 !important;
    border-radius:.62rem !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    line-height:1 !important
}

[data-project-action-icon] svg{
    width:1rem !important;
    height:1rem !important;
    min-width:1rem !important;
    max-width:1rem !important;
    display:block !important;
    stroke-width:1.8
}

[data-project-action-icon]:hover{
    transform:none !important
}
</style>

<!-- TICKETING_PROJECT_ACTIONS_ICON_UNIFICATION -->
<!-- TICKETING_PROJECT_MEMBER_ACCESS_DIRECT_LINK -->
                                <a data-project-action-icon="members"
                                    class="admin-button admin-button--soft ticketing-project-member-icon"
                                    href="<?= ticketing_h(
                                        '/admin/ticketing/projects/'
                                        . rawurlencode(
                                            (string) (
                                                $project['public_reference']
                                                ?? ''
                                            )
                                        )
                                        . '/members'
                                    ) ?>"
                                    title="اعضا و دسترسی‌ها"
                                    aria-label="اعضا و دسترسی‌ها"
                                >
                                    <?= \App\Support\AdminIcon::html('users') ?>
                                </a>

<a data-project-action-icon="topology" title="ساختار پشتیبانی" aria-label="ساختار پشتیبانی" style="width:2.35rem;height:2.35rem;min-width:2.35rem;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:.7rem"
                                    class="admin-button admin-button--soft admin-button--compact"
                                    href="<?= ticketing_h($topologyUrl) ?>"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="2" width="6" height="5" rx="1"/><rect x="2" y="17" width="6" height="5" rx="1"/><rect x="16" y="17" width="6" height="5" rx="1"/><path d="M12 7v5"/><path d="M5 17v-2a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v2"/></svg>
                                </a>

                                <a data-project-action-icon="routing" title="موضوعات و مسیریابی" aria-label="موضوعات و مسیریابی" style="width:2.35rem;height:2.35rem;min-width:2.35rem;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:.7rem"
                                    class="admin-button admin-button--soft admin-button--compact"
                                    href="<?= ticketing_h($routingUrl) ?>"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="6" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 6h8"/><path d="M18 8v8"/><path d="M6 8v5a5 5 0 0 0 5 5h5"/></svg>
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

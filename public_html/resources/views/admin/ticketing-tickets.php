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

$projectReference =
    (string) (
        $list['project_reference']
        ?? ''
    );

$layerId =
    (int) (
        $list['layer_id']
        ?? 0
    );

$assigneeId =
    (int) (
        $list['assignee_id']
        ?? 0
    );

$sort1 =
    (string) (
        $list['sort1']
        ?? 'last_activity'
    );

$dir1 =
    (string) (
        $list['dir1']
        ?? 'desc'
    );

$sort2 =
    (string) (
        $list['sort2']
        ?? 'created_at'
    );

$dir2 =
    (string) (
        $list['dir2']
        ?? 'desc'
    );

$statusOptions =
    $list['status_options']
    ?? [];

$priorityOptions =
    $list['priority_options']
    ?? [];

$projectTabs =
    $list['project_tabs']
    ?? [];

$layerOptions =
    $list['layer_options']
    ?? [];

$assigneeOptions =
    $list['assignee_options']
    ?? [];

$sortOptions =
    $list['sort_options']
    ?? [];

$total =
    (int) (
        $list['total']
        ?? count($items)
    );


$state = [
    'q' => $q,
    'status' => $status,
    'priority' => $priority,
    'project' => $projectReference,

    'layer' =>
        $layerId > 0
            ? $layerId
            : '',

    'assignee' =>
        $assigneeId > 0
            ? $assigneeId
            : '',

    'sort1' => $sort1,
    'dir1' => $dir1,
    'sort2' => $sort2,
    'dir2' => $dir2,
];


$urlWith =
    static function (
        array $changes
    ) use ($state): string {

        $query =
            array_merge(
                $state,
                $changes
            );

        foreach (
            $query
            as $key => $value
        ) {
            if (
                $value === ''
                || $value === null
                || $value === 0
                || $value === '0'
            ) {
                unset($query[$key]);
            }
        }

        $string =
            http_build_query(
                $query
            );

        return
            '/admin/ticketing/tickets'
            . (
                $string !== ''
                    ? '?' . $string
                    : ''
            );
    };


$primaryProjects =
    array_slice(
        $projectTabs,
        0,
        5
    );

$moreProjects =
    array_slice(
        $projectTabs,
        5
    );


ob_start();
?>

<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span>/</span>
    <a href="/admin/ticketing">پشتیبانی و تیکتینگ</a>
    <span>/</span>
    <span>تیکت‌های من</span>
</nav>


<div class="admin-page ticketing-page ticketing-list-page">

    <div class="admin-page-header ticketing-page-head">
        <div>
            <h1>تیکت‌های من</h1>

            <p>
                مشاهده مرحله رسیدگی و کارشناس جاری
                در پروژه‌های پشتیبانی
            </p>
        </div>

        <a
            class="admin-button"
            href="/admin/ticketing/tickets/create"
        >
            تیکت جدید
        </a>
    </div>


    <?php if ($projectTabs !== []): ?>

        <nav
            class="ticketing-project-tabs"
            aria-label="پروژه‌های پشتیبانی"
        >
            <a
                class="ticketing-project-tab<?= $projectReference === ''
                    ? ' is-active'
                    : '' ?>"
                href="<?= ticketing_h(
                    $urlWith([
                        'project' => '',
                    ])
                ) ?>"
            >
                همه پروژه‌ها
            </a>


            <?php foreach (
                $primaryProjects
                as $project
            ): ?>

                <?php
                $reference =
                    (string) (
                        $project['public_reference']
                        ?? ''
                    );
                ?>

                <a
                    class="ticketing-project-tab<?= $projectReference === $reference
                        ? ' is-active'
                        : '' ?>"
                    href="<?= ticketing_h(
                        $urlWith([
                            'project' =>
                                $reference,
                        ])
                    ) ?>"
                >
                    <span>
                        <?= ticketing_h(
                            $project['title']
                            ?? ''
                        ) ?>
                    </span>

                    <b>
                        <?= ticketing_h(
                            \App\Support\AdminFormat::digits(
                                (int) (
                                    $project[
                                        'open_ticket_count'
                                    ]
                                    ?? 0
                                )
                            )
                        ) ?>
                    </b>
                </a>

            <?php endforeach; ?>


            <?php if ($moreProjects !== []): ?>
                <details class="ticketing-project-more">
                    <summary>
                        بیشتر
                    </summary>

                    <div class="ticketing-project-more__menu">

                        <?php foreach (
                            $moreProjects
                            as $project
                        ): ?>

                            <?php
                            $reference =
                                (string) (
                                    $project[
                                        'public_reference'
                                    ]
                                    ?? ''
                                );
                            ?>

                            <a
                                href="<?= ticketing_h(
                                    $urlWith([
                                        'project' =>
                                            $reference,
                                    ])
                                ) ?>"
                            >
                                <span>
                                    <?= ticketing_h(
                                        $project['title']
                                        ?? ''
                                    ) ?>
                                </span>

                                <b>
                                    <?= ticketing_h(
                                        \App\Support\AdminFormat::digits(
                                            (int) (
                                                $project[
                                                    'open_ticket_count'
                                                ]
                                                ?? 0
                                            )
                                        )
                                    ) ?>
                                </b>
                            </a>

                        <?php endforeach; ?>

                    </div>
                </details>
            <?php endif; ?>

        </nav>

    <?php endif; ?>


    <section class="admin-section ticketing-filter-section">

        <form
            method="get"
            action="/admin/ticketing/tickets"
            class="ticketing-filter-form"
        >

            <?php if ($projectReference !== ''): ?>
                <input
                    type="hidden"
                    name="project"
                    value="<?= ticketing_h(
                        $projectReference
                    ) ?>"
                >
            <?php endif; ?>


            <div class="ticketing-filter-grid">

                <label>
                    <span>جستجو</span>

                    <input
                        type="search"
                        name="q"
                        value="<?= ticketing_h($q) ?>"
                        maxlength="120"
                        placeholder="شماره، عنوان، موضوع، پروژه یا کارشناس"
                    >
                </label>


                <label>
                    <span>وضعیت</span>

                    <select name="status">
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
                </label>


                <label>
                    <span>اولویت</span>

                    <select name="priority">
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
                </label>


                <label>
                    <span>مرحله جاری</span>

                    <select name="layer">
                        <option value="">
                            همه مراحل
                        </option>

                        <?php foreach (
                            $layerOptions
                            as $id => $label
                        ): ?>
                            <option
                                value="<?= ticketing_h($id) ?>"
                                <?= $layerId === (int) $id
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label>
                    <span>کارشناس جاری</span>

                    <select name="assignee">
                        <option value="">
                            همه کارشناسان
                        </option>

                        <?php foreach (
                            $assigneeOptions
                            as $id => $label
                        ): ?>
                            <option
                                value="<?= ticketing_h($id) ?>"
                                <?= $assigneeId === (int) $id
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

            </div>


            <details class="ticketing-sort-details">
                <summary>
                    مرتب‌سازی چندمرحله‌ای
                </summary>

                <div class="ticketing-sort-grid">

                    <label>
                        <span>مرتب‌سازی اول</span>

                        <select name="sort1">
                            <?php foreach (
                                $sortOptions
                                as $code => $label
                            ): ?>
                                <option
                                    value="<?= ticketing_h($code) ?>"
                                    <?= $sort1 === (string) $code
                                        ? ' selected'
                                        : '' ?>
                                >
                                    <?= ticketing_h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>


                    <label>
                        <span>جهت</span>

                        <select name="dir1">
                            <option
                                value="desc"
                                <?= $dir1 === 'desc'
                                    ? ' selected'
                                    : '' ?>
                            >
                                نزولی
                            </option>

                            <option
                                value="asc"
                                <?= $dir1 === 'asc'
                                    ? ' selected'
                                    : '' ?>
                            >
                                صعودی
                            </option>
                        </select>
                    </label>


                    <label>
                        <span>مرتب‌سازی دوم</span>

                        <select name="sort2">
                            <?php foreach (
                                $sortOptions
                                as $code => $label
                            ): ?>
                                <option
                                    value="<?= ticketing_h($code) ?>"
                                    <?= $sort2 === (string) $code
                                        ? ' selected'
                                        : '' ?>
                                >
                                    <?= ticketing_h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>


                    <label>
                        <span>جهت دوم</span>

                        <select name="dir2">
                            <option
                                value="desc"
                                <?= $dir2 === 'desc'
                                    ? ' selected'
                                    : '' ?>
                            >
                                نزولی
                            </option>

                            <option
                                value="asc"
                                <?= $dir2 === 'asc'
                                    ? ' selected'
                                    : '' ?>
                            >
                                صعودی
                            </option>
                        </select>
                    </label>

                </div>
            </details>


            <div class="admin-form-actions ticketing-filter-actions">

                <button
                    class="admin-button"
                    type="submit"
                >
                    اعمال
                </button>

                <a
                    class="admin-button admin-button--soft"
                    href="<?= ticketing_h(
                        $urlWith([
                            'q' => '',
                            'status' => '',
                            'priority' => '',
                            'layer' => '',
                            'assignee' => '',
                            'sort1' =>
                                'last_activity',
                            'dir1' =>
                                'desc',
                            'sort2' =>
                                'created_at',
                            'dir2' =>
                                'desc',
                        ])
                    ) ?>"
                >
                    بازنشانی
                </a>

                <span class="admin-muted ticketing-result-count">
                    <?= ticketing_h(
                        \App\Support\AdminFormat::digits(
                            $total
                        )
                    ) ?>
                    تیکت
                </span>

            </div>

        </form>


        <?php if ($items === []): ?>

            <div class="admin-empty-state">
                تیکتی مطابق انتخاب فعلی وجود ندارد.
            </div>

        <?php else: ?>

            <div class="admin-table-wrap ticketing-project-grid">

                <table class="admin-table">

                    <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>شماره</th>

                        <?php if ($projectReference === ''): ?>
                            <th>پروژه</th>
                        <?php endif; ?>

                        <th>عنوان</th>
                        <th>مرحله جاری</th>
                        <th>کارشناس جاری</th>
                        <th>اولویت</th>
                        <th>وضعیت</th>
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
                        $reference =
                            (string) (
                                $ticket[
                                    'public_reference'
                                ]
                                ?? ''
                            );

                        $url =
                            '/admin/ticketing/tickets/'
                            . rawurlencode($reference);

                        $stage =
                            trim(
                                (string) (
                                    $ticket[
                                        'layer_title'
                                    ]
                                    ?? ''
                                )
                            );

                        $assignee =
                            trim(
                                (string) (
                                    $ticket[
                                        'assignee_name'
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
                                    \App\Support\TicketingDisplay::ticketNumberFromRow($ticket)
                                ) ?>
                            </td>


                            <?php if ($projectReference === ''): ?>

                                <td>
                                    <?= ticketing_h(
                                        $ticket[
                                            'project_title'
                                        ]
                                        ?? '—'
                                    ) ?>
                                </td>

                            <?php endif; ?>


                            <td class="ticketing-title-cell">

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

                                <?php if (
                                    !empty(
                                        $ticket[
                                            'topic_title'
                                        ]
                                    )
                                ): ?>
                                    <small>
                                        موضوع:
                                        <?= ticketing_h(
                                            $ticket[
                                                'topic_title'
                                            ]
                                        ) ?>
                                    </small>
                                <?php endif; ?>

                            </td>


                            <td class="ticketing-stage-cell">
                                <strong>
                                    <?= ticketing_h(
                                        $stage !== ''
                                            ? $stage
                                            : 'در انتظار مسیریابی'
                                    ) ?>
                                </strong>

                                <?php if (
                                    !empty(
                                        $ticket[
                                            'team_title'
                                        ]
                                    )
                                ): ?>
                                    <small>
                                        <?= ticketing_h(
                                            $ticket[
                                                'team_title'
                                            ]
                                        ) ?>
                                    </small>
                                <?php endif; ?>
                            </td>


                            <td>
                                <?= ticketing_h(
                                    $assignee !== ''
                                        ? $assignee
                                        : 'در انتظار تخصیص'
                                ) ?>
                            </td>


                            <td>
                                <?= ticketing_h(
                                    $ticket[
                                        'priority_title'
                                    ]
                                    ?? '—'
                                ) ?>
                            </td>


                            <td>
                                <span class="admin-pill">
                                    <?= ticketing_h(
                                        $ticket[
                                            'status_title'
                                        ]
                                        ?? '—'
                                    ) ?>
                                </span>
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

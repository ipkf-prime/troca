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

$topicId =
    (int) (
        $list['topic_id']
        ?? 0
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

$topicOptions =
    $list['topic_options']
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

    'topic' =>
        $topicId > 0
            ? $topicId
            : '',

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

        /*
         * TICKETING_MY_TICKETS_PROJECT_TOPIC_RESET_T3G
         */
        if (
            array_key_exists(
                'project',
                $changes
            )
            &&
            (string) (
                $changes[
                    'project'
                ]
                ?? ''
            )
            !==
            (string) (
                $state[
                    'project'
                ]
                ?? ''
            )
            &&
            !array_key_exists(
                'topic',
                $changes
            )
        ) {
            $changes[
                'topic'
            ] = '';
        }

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


/*
 * TICKETING_COMPACT_FILTER_UX_T3G
 *
 * The primary row contains only frequent filters.
 * Secondary workflow controls remain available on demand.
 */
$advancedFilterCount = 0;

if ($layerId > 0) {
    $advancedFilterCount++;
}

if ($assigneeId > 0) {
    $advancedFilterCount++;
}

if (
    $sort1 !== 'last_activity'
    ||
    $dir1 !== 'desc'
    ||
    $sort2 !== 'created_at'
    ||
    $dir2 !== 'desc'
) {
    $advancedFilterCount++;
}

/*
 * TICKETING_MY_ADVANCED_DEFAULT_COLLAPSED_T3G
 *
 * Active advanced filters remain applied and their count remains visible,
 * but the secondary panel never expands automatically on page load.
 */
$advancedFiltersOpen =
    false;


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
            data-ticketing-auto-filter-form
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


            <!-- TICKETING_COMPACT_FILTER_UX_T3G -->
            <!-- TICKETING_COMPACT_FILTER_PRIMARY_T3G_START -->

            <div
                class="ticketing-compact-filter-grid ticketing-compact-filter-grid--requester"
            >

                <label
                    class="ticketing-compact-filter__field ticketing-compact-filter__search"
                >
                    <span>جستجو</span>

                    <input
                        type="search"
                        name="q"
                        value="<?= ticketing_h($q) ?>"
                        maxlength="120"
                        placeholder="شماره، عنوان، موضوع، پروژه، درخواست‌کننده، سازمان یا کارشناس"
                    >
                </label>


                <label class="ticketing-compact-filter__field">
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


                <label class="ticketing-compact-filter__field">
                    <span>موضوع</span>

                    <select name="topic">
                        <option value="">
                            همه موضوع‌ها
                        </option>

                        <?php foreach (
                            $topicOptions
                            as $id => $label
                        ): ?>
                            <option
                                value="<?= ticketing_h($id) ?>"
                                <?= $topicId === (int) $id
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label class="ticketing-compact-filter__field">
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


                <div class="ticketing-compact-filter__actions">

                    <button
                        type="button"
                        class="ui-button admin-button admin-button--soft ticketing-compact-more-button"
                        data-active="<?= $advancedFilterCount ? '1' : '0' ?>"
                        data-ticketing-advanced-toggle
                        aria-expanded="<?= $advancedFiltersOpen
                            ? 'true'
                            : 'false' ?>"
                        aria-controls="ticketing-my-advanced-filters"
                    >
                        <span>
                            فیلترهای بیشتر
                        </span>

                        <?php if ($advancedFilterCount > 0): ?>
                            <b class="ticketing-compact-more-button__count">
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (string) $advancedFilterCount
                                    )
                                ) ?>
                            </b>
                        <?php endif; ?>
                    </button>


                    <a
                        class="ticketing-icon-action ticketing-icon-action--soft ticketing-compact-filter__reset"
                        href="<?= ticketing_h(
                            $urlWith([
                                'q' => '',
                                'status' => '',
                                'priority' => '',
                                'topic' => '',
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
                        aria-label="بازنشانی فیلترها"
                        title="بازنشانی فیلترها"
                        data-tooltip="بازنشانی فیلترها"
                    >
                        <?= \App\Support\TicketingIcon::svg(
                            'reset'
                        ) ?>
                    </a>


                    <span class="ticketing-compact-filter__result-count">
                        <?= ticketing_h(
                            \App\Support\AdminFormat::digits(
                                $total
                            )
                        ) ?>
                        تیکت
                    </span>

                </div>

            </div>

            <!-- TICKETING_COMPACT_FILTER_PRIMARY_T3G_END -->


            <!-- TICKETING_COMPACT_FILTER_ADVANCED_T3G_START -->

            <div
                id="ticketing-my-advanced-filters"
                class="ticketing-advanced-filter-panel"
                data-ticketing-advanced-panel
                <?= $advancedFiltersOpen
                    ? ''
                    : 'hidden' ?>
            >

                <div class="ticketing-advanced-filter-grid">

                    <label class="ticketing-compact-filter__field">
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


                    <label class="ticketing-compact-filter__field">
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


                <div class="ticketing-advanced-filter-panel__title">
                    مرتب‌سازی
                </div>


                <div class="ticketing-advanced-sort-grid">

                    <label class="ticketing-compact-filter__field">
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


                    <label class="ticketing-compact-filter__field">
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


                    <label class="ticketing-compact-filter__field">
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


                    <label class="ticketing-compact-filter__field">
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

            </div>

            <!-- TICKETING_COMPACT_FILTER_ADVANCED_T3G_END -->

        </form>

        <!-- TICKETING_MY_TICKETS_AUTO_FILTER_T3D_START -->
        <script>
        (() => {
            'use strict';

            const form =
                document.querySelector(
                    '[data-ticketing-auto-filter-form]'
                );

            if (!form) {
                return;
            }


            /*
             * TICKETING_COMPACT_FILTER_TOGGLE_T3G
             */
            const advancedToggle =
                form.querySelector(
                    '[data-ticketing-advanced-toggle]'
                );

            const advancedPanel =
                form.querySelector(
                    '[data-ticketing-advanced-panel]'
                );


            if (
                advancedToggle
                &&
                advancedPanel
            ) {
                advancedToggle.addEventListener(
                    'click',
                    () => {
                        const open =
                            advancedPanel.hidden;

                        advancedPanel.hidden =
                            !open;

                        advancedToggle.setAttribute(
                            'aria-expanded',
                            open
                                ? 'true'
                                : 'false'
                        );
                    }
                );
            }


            const debounceMs = 500;

            const search =
                form.querySelector(
                    'input[name="q"]'
                );

            const autoSubmitFields = [
                'status',
                'priority',
                'topic',
                'layer',
                'assignee',
                'sort1',
                'dir1',
                'sort2',
                'dir2',
            ];

            let searchTimer = null;
            let composing = false;


            const clearSearchTimer = () => {
                if (searchTimer === null) {
                    return;
                }

                window.clearTimeout(
                    searchTimer
                );

                searchTimer = null;
            };


            const submitForm = () => {
                clearSearchTimer();

                if (
                    typeof form.requestSubmit
                        === 'function'
                ) {
                    form.requestSubmit();
                    return;
                }

                form.submit();
            };


            const scheduleSearchSubmit = () => {
                clearSearchTimer();

                if (composing) {
                    return;
                }

                searchTimer =
                    window.setTimeout(
                        submitForm,
                        debounceMs
                    );
            };


            autoSubmitFields.forEach(name => {
                const field =
                    form.querySelector(
                        `[name="${name}"]`
                    );

                if (!field) {
                    return;
                }

                field.addEventListener(
                    'change',
                    submitForm
                );
            });


            if (!search) {
                return;
            }


            search.addEventListener(
                'compositionstart',
                () => {
                    composing = true;
                    clearSearchTimer();
                }
            );


            search.addEventListener(
                'compositionend',
                () => {
                    composing = false;
                    scheduleSearchSubmit();
                }
            );


            search.addEventListener(
                'input',
                () => {
                    if (!composing) {
                        scheduleSearchSubmit();
                    }
                }
            );


            search.addEventListener(
                'keydown',
                event => {
                    if (
                        event.key !== 'Enter'
                        ||
                        event.isComposing
                        ||
                        composing
                    ) {
                        return;
                    }

                    event.preventDefault();

                    submitForm();
                }
            );
        })();
        </script>
        <!-- TICKETING_MY_TICKETS_AUTO_FILTER_T3D_END -->


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

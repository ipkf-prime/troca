<?php

if (!function_exists('ticketing_h')) {
    function ticketing_h(
        $value
    ): string {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}


$page =
    is_array($page ?? null)
        ? $page
        : [];

$items =
    is_array(
        $page['items']
        ?? null
    )
        ? $page['items']
        : [];

$counts =
    is_array(
        $page['counts']
        ?? null
    )
        ? $page['counts']
        : [];

$scope =
    (string) (
        $page['scope']
        ?? 'all'
    );

$q =
    (string) (
        $page['q']
        ?? ''
    );


$filters =
    is_array(
        $page['filters']
        ?? null
    )
        ? $page['filters']
        : [];

$filterOptions =
    is_array(
        $page['filter_options']
        ?? null
    )
        ? $page['filter_options']
        : [];

$pagination =
    is_array(
        $page['pagination']
        ?? null
    )
        ? $page['pagination']
        : [];


$ticketStatus =
    (string) (
        $filters['ticket_status']
        ?? 'active'
    );

$priority =
    (string) (
        $filters['priority']
        ?? ''
    );

$topicId =
    (int) (
        $filters['topic_id']
        ?? 0
    );

$layerId =
    (int) (
        $filters['layer_id']
        ?? 0
    );

$assignee =
    (string) (
        $filters['assignee']
        ?? ''
    );

$sort =
    (string) (
        $filters['sort']
        ?? 'priority_desc'
    );

$perPage =
    (int) (
        $pagination['per_page']
        ?? 25
    );

$currentPage =
    max(
        1,
        (int) (
            $pagination['page']
            ?? 1
        )
    );

$totalPages =
    max(
        1,
        (int) (
            $pagination['total_pages']
            ?? 1
        )
    );

$totalItems =
    max(
        0,
        (int) (
            $pagination['total']
            ?? count($items)
        )
    );


$statuses =
    is_array(
        $filterOptions['statuses']
        ?? null
    )
        ? $filterOptions['statuses']
        : [];

$priorities =
    is_array(
        $filterOptions['priorities']
        ?? null
    )
        ? $filterOptions['priorities']
        : [];

$topics =
    is_array(
        $filterOptions['topics']
        ?? null
    )
        ? $filterOptions['topics']
        : [];


$topicProjectIds = [];

foreach (
    $topics
    as $topicOption
) {
    $projectId =
        (int) (
            $topicOption[
                'project_id'
            ]
            ?? 0
        );

    if ($projectId > 0) {
        $topicProjectIds[
            $projectId
        ] = true;
    }
}

$showTopicProject =
    count(
        $topicProjectIds
    ) > 1;


$layers =
    is_array(
        $filterOptions['layers']
        ?? null
    )
        ? $filterOptions['layers']
        : [];

$assignees =
    is_array(
        $filterOptions['assignees']
        ?? null
    )
        ? $filterOptions['assignees']
        : [];


$sortOptions = [
    'priority_desc' =>
        'اولویت بالاتر',

    'activity_desc' =>
        'فعالیت جدیدتر',

    'activity_asc' =>
        'فعالیت قدیمی‌تر',

    'created_desc' =>
        'ثبت جدیدتر',

    'created_asc' =>
        'ثبت قدیمی‌تر',
];


$cartableUrl =
    static function (
        array $overrides = []
    ) use (
        $scope,
        $q,
        $ticketStatus,
        $priority,
        $topicId,
        $layerId,
        $assignee,
        $sort,
        $currentPage,
        $perPage
    ): string {
        $params = [
            'scope' =>
                $scope,

            'q' =>
                $q,

            'ticket_status' =>
                $ticketStatus,

            'priority' =>
                $priority,

            'topic' =>
                $topicId,

            'layer_id' =>
                $layerId,

            'assignee' =>
                $assignee,

            'sort' =>
                $sort,

            'page' =>
                $currentPage,

            'per_page' =>
                $perPage,
        ];


        foreach (
            $overrides
            as $key => $value
        ) {
            $params[$key] =
                $value;
        }


        if (
            ($params['q'] ?? '')
            === ''
        ) {
            unset(
                $params['q']
            );
        }

        if (
            ($params['priority'] ?? '')
            === ''
        ) {
            unset(
                $params['priority']
            );
        }

        if (
            (int) (
                $params['topic']
                ?? 0
            ) < 1
        ) {
            unset(
                $params['topic']
            );
        }

        if (
            (int) (
                $params['layer_id']
                ?? 0
            ) < 1
        ) {
            unset(
                $params['layer_id']
            );
        }

        if (
            ($params['assignee'] ?? '')
            === ''
        ) {
            unset(
                $params['assignee']
            );
        }

        if (
            (int) (
                $params['page']
                ?? 1
            ) <= 1
        ) {
            unset(
                $params['page']
            );
        }


        return
            '/admin/ticketing/staff'
            . (
                $params === []
                    ? ''
                    : '?'
                        . http_build_query(
                            $params,
                            '',
                            '&',
                            PHP_QUERY_RFC3986
                        )
            );
    };

$isStaff =
    !empty(
        $page['is_staff']
    );

$viewerUserReference =
    trim(
        (string) (
            $page[
                'viewer_user_reference'
            ]
            ?? ''
        )
    );

$status =
    trim(
        (string) (
            $status
            ?? ''
        )
    );

$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();


$notices = [
    'taken-over' => [
        'ok',
        'تیکت با موفقیت تحویل گرفته شد.',
    ],

    'transferred' => [
        'ok',
        'تیکت به کارشناس جدید منتقل شد.',
    ],

    'escalated' => [
        'ok',
        'تیکت به سطح بالاتر ارجاع شد.',
    ],

    'csrf' => [
        'error',
        'اعتبار فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.',
    ],

    'forbidden' => [
        'error',
        'برای انجام این عملیات مجوز لازم را ندارید.',
    ],

    'already-owner' => [
        'error',
        'این تیکت هم‌اکنون در اختیار شماست.',
    ],

    'invalid-target' => [
        'error',
        'کارشناس مقصد معتبر نیست.',
    ],

    'same-assignee' => [
        'error',
        'کارشناس مقصد با کارشناس جاری یکسان است.',
    ],

    'no-escalation' => [
        'error',
        'برای این مرحله مسیر ارجاع بالاتر تعریف نشده است.',
    ],

    'no-escalation-route' => [
        'error',
        'صف یا تیم سطح بالاتر آماده دریافت تیکت نیست.',
    ],

    'no-assignee' => [
        'error',
        'در سطح مقصد کارشناس قابل تخصیص وجود ندارد.',
    ],

    'closed' => [
        'error',
        'روی تیکت بسته‌شده عملیات کارشناسی قابل انجام نیست.',
    ],

    'not-routed' => [
        'error',
        'این تیکت هنوز وارد مسیر پشتیبانی نشده است.',
    ],

    'not-found' => [
        'error',
        'تیکت مورد نظر پیدا نشد.',
    ],

    'operation-failed' => [
        'error',
        'عملیات انجام نشد.',
    ],
];


$scopeTabs = [
    'all' => [
        'قابل رسیدگی',
        (int) ($counts['all'] ?? 0),
    ],

    'my' => [
        'تخصیص‌یافته به من',
        (int) ($counts['my'] ?? 0),
    ],

    'unassigned' => [
        'بدون کارشناس',
        (int) ($counts['unassigned'] ?? 0),
    ],
];


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
        کارتابل پشتیبانی
    </span>
</nav>


<div class="admin-page ticketing-page ticketing-staff-page">

    <div class="admin-page-header ticketing-page-head">

        <div>
            <div class="admin-muted">
                عملیات کارشناسی و مدیریت صف‌های پشتیبانی
            </div>

            <h1>
                کارتابل پشتیبانی
            </h1>
        </div>

    </div>


    <?php if (isset($notices[$status])): ?>

        <?php
        [$noticeType, $noticeMessage] =
            $notices[$status];
        ?>

        <div
            class="<?= $noticeType === 'ok'
                ? 'admin-alert admin-alert--success'
                : 'admin-alert' ?>"
            role="status"
        >
            <?= ticketing_h(
                $noticeMessage
            ) ?>
        </div>

    <?php endif; ?>


    <?php if (!$isStaff): ?>

        <section class="admin-section">

            <div class="admin-alert">
                برای حساب شما عضویت فعال در تیم‌های پشتیبانی تعریف نشده است.
            </div>

        </section>

    <?php else: ?>

        <section class="admin-section ticketing-staff-section">

            <div
                class="ticketing-staff-scope-tabs"
                aria-label="بخش‌های کارتابل"
            >

                <?php foreach (
                    $scopeTabs
                    as $scopeCode => [$scopeTitle, $scopeCount]
                ): ?>

                    <a
                        class="ticketing-staff-scope-tab <?= $scope === $scopeCode
                            ? 'is-active'
                            : '' ?>"
                        href="<?= ticketing_h(
                            $cartableUrl(
                                [
                                    /*
                                     * TICKETING_CARTABLE_SCOPE_PRESET_RESET_V1
                                     *
                                     * Summary cards are operational presets.
                                     * Clicking one starts a clean ACTIVE view
                                     * for that scope instead of inheriting the
                                     * current list filters.
                                     *
                                     * per_page is intentionally preserved.
                                     */
                                    'scope' =>
                                        $scopeCode,

                                    'q' =>
                                        '',

                                    'ticket_status' =>
                                        'active',

                                    'priority' =>
                                        '',

                                    'topic' =>
                                        0,

                                    'layer_id' =>
                                        0,

                                    'assignee' =>
                                        '',

                                    'sort' =>
                                        'priority_desc',

                                    'page' =>
                                        1,
                                ]
                            )
                        ) ?>"
                    >
                        <span>
                            <?= ticketing_h(
                                $scopeTitle
                            ) ?>
                        </span>

                        <strong>
                            <?= ticketing_h(
                                \App\Support\AdminFormat::digits(
                                    (string) $scopeCount
                                )
                            ) ?>
                        </strong>
                    </a>

                <?php endforeach; ?>

            </div>


            <form
                method="get"
                action="/admin/ticketing/staff"
                class="ticketing-staff-search ticketing-staff-filter-grid"
            >
                <input
                    type="hidden"
                    name="scope"
                    value="<?= ticketing_h(
                        $scope
                    ) ?>"
                >


                <label class="ticketing-staff-search__field ticketing-staff-filter--search">

                    <span>
                        جستجو
                    </span>

                    <input
                        type="search"
                        class="ui-input"
                        data-ticketing-search-auto-submit
                        name="q"
                        maxlength="180"
                        value="<?= ticketing_h(
                            $q
                        ) ?>"
                        placeholder="شماره تیکت، عنوان، موضوع، پروژه، درخواست‌کننده، سازمان یا کارشناس"
                    >

                </label>


                <label class="ticketing-staff-search__field">

                    <span>
                        وضعیت
                    </span>

                    <select
                        class="ui-select ticketing-cartable-filter-select"
                        data-ticketing-filter-auto-submit
                        name="ticket_status"
                    >
                        <option
                            value="active"
                            <?= $ticketStatus === 'active'
                                ? 'selected'
                                : '' ?>
                        >
                            تیکت‌های جاری
                        </option>

                        <option
                            value="all"
                            <?= $ticketStatus === 'all'
                                ? 'selected'
                                : '' ?>
                        >
                            همه وضعیت‌ها
                        </option>

                        <?php foreach (
                            $statuses
                            as $option
                        ): ?>
                            <?php
                            $optionCode =
                                (string) (
                                    $option['code']
                                    ?? ''
                                );

                            $optionTitle =
                                (string) (
                                    $option['title']
                                    ?? $optionCode
                                );
                            ?>

                            <option
                                value="<?= ticketing_h(
                                    $optionCode
                                ) ?>"
                                <?= $ticketStatus === $optionCode
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $optionTitle
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label class="ticketing-staff-search__field">

                    <span>
                        اولویت
                    </span>

                    <select
                        class="ui-select ticketing-cartable-filter-select"
                        data-ticketing-filter-auto-submit
                        name="priority"
                    >
                        <option value="">
                            همه اولویت‌ها
                        </option>

                        <?php foreach (
                            $priorities
                            as $option
                        ): ?>
                            <?php
                            $optionCode =
                                (string) (
                                    $option['code']
                                    ?? ''
                                );

                            $optionTitle =
                                (string) (
                                    $option['title']
                                    ?? $optionCode
                                );
                            ?>

                            <option
                                value="<?= ticketing_h(
                                    $optionCode
                                ) ?>"
                                <?= $priority === $optionCode
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $optionTitle
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label class="ticketing-staff-search__field">

                    <span>
                        موضوع
                    </span>

                    <select
                        class="ui-select ticketing-cartable-filter-select"
                        data-ticketing-filter-auto-submit
                        name="topic"
                    >
                        <option value="0">
                            همه موضوع‌ها
                        </option>

                        <?php foreach (
                            $topics
                            as $option
                        ): ?>

                            <?php
                            $optionId =
                                (int) (
                                    $option[
                                        'id'
                                    ]
                                    ?? 0
                                );

                            $optionTitle =
                                trim(
                                    (string) (
                                        $option[
                                            'title'
                                        ]
                                        ?? ''
                                    )
                                );

                            $optionProjectTitle =
                                trim(
                                    (string) (
                                        $option[
                                            'project_title'
                                        ]
                                        ?? ''
                                    )
                                );

                            if ($optionTitle === '') {
                                $optionTitle =
                                    'موضوع #'
                                    . $optionId;
                            }

                            if (
                                $showTopicProject
                                &&
                                $optionProjectTitle !== ''
                            ) {
                                $optionTitle .=
                                    ' — '
                                    . $optionProjectTitle;
                            }
                            ?>

                            <option
                                value="<?= ticketing_h(
                                    (string) $optionId
                                ) ?>"
                                <?= $topicId === $optionId
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $optionTitle
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label class="ticketing-staff-search__field">

                    <span>
                        مرحله
                    </span>

                    <select
                        class="ui-select ticketing-cartable-filter-select"
                        data-ticketing-filter-auto-submit
                        name="layer_id"
                    >
                        <option value="0">
                            همه سطوح
                        </option>

                        <?php foreach (
                            $layers
                            as $option
                        ): ?>
                            <?php
                            $optionId =
                                (int) (
                                    $option['id']
                                    ?? 0
                                );

                            $optionTitle =
                                (string) (
                                    $option['title']
                                    ?? ''
                                );
                            ?>

                            <option
                                value="<?= ticketing_h(
                                    (string) $optionId
                                ) ?>"
                                <?= $layerId === $optionId
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $optionTitle
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label class="ticketing-staff-search__field">

                    <span>
                        کارشناس جاری
                    </span>

                    <select
                        class="ui-select ticketing-cartable-filter-select"
                        data-ticketing-filter-auto-submit
                        name="assignee"
                        <?= $scope === 'unassigned'
                            ? 'disabled'
                            : '' ?>
                    >
                        <option value="">
                            همه کارشناسان
                        </option>

                        <?php foreach (
                            $assignees
                            as $option
                        ): ?>
                            <?php
                            $optionReference =
                                (string) (
                                    $option[
                                        'user_reference'
                                    ]
                                    ?? ''
                                );

                            $optionTitle =
                                trim(
                                    (string) (
                                        $option[
                                            'display_name'
                                        ]
                                        ?? ''
                                    )
                                );

                            if ($optionTitle === '') {
                                $optionTitle =
                                    $optionReference;
                            }
                            ?>

                            <option
                                value="<?= ticketing_h(
                                    $optionReference
                                ) ?>"
                                <?= $assignee === $optionReference
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $optionTitle
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label class="ticketing-staff-search__field">

                    <span>
                        مرتب‌سازی
                    </span>

                    <select
                        class="ui-select ticketing-cartable-filter-select"
                        data-ticketing-filter-auto-submit
                        name="sort"
                    >
                        <?php foreach (
                            $sortOptions
                            as $optionCode => $optionTitle
                        ): ?>

                            <option
                                value="<?= ticketing_h(
                                    $optionCode
                                ) ?>"
                                <?= $sort === $optionCode
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $optionTitle
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label class="ticketing-staff-search__field">

                    <span>
                        تعداد در صفحه
                    </span>

                    <select
                        class="ui-select ticketing-cartable-filter-select"
                        data-ticketing-filter-auto-submit
                        name="per_page"
                    >
                        <?php foreach (
                            [
                                25,
                                50,
                            ]
                            as $perPageOption
                        ): ?>

                            <option
                                value="<?= ticketing_h(
                                    (string) $perPageOption
                                ) ?>"
                                <?= $perPage === $perPageOption
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (string) $perPageOption
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <div class="ticketing-staff-search__actions">


                    <a
                        class="ticketing-icon-action ticketing-icon-action--soft"
                        href="<?= ticketing_h(
                            $cartableUrl(
                                [
                                    'q' =>
                                        '',

                                    'ticket_status' =>
                                        'active',

                                    'priority' =>
                                        '',

                                    'topic' =>
                                        0,

                                    'layer_id' =>
                                        0,

                                    'assignee' =>
                                        '',

                                    'sort' =>
                                        'priority_desc',

                                    'page' =>
                                        1,
                                ]
                            )
                        ) ?>"
                        aria-label="بازنشانی فیلترها"
                        title="بازنشانی فیلترها"
                        data-tooltip="بازنشانی فیلترها"
                    >
                        <?= \App\Support\TicketingIcon::svg(
                            'reset'
                        ) ?>
                    </a>

                </div>

            </form>


            <div class="ticketing-staff-list-head">

                <strong>
                    <?= ticketing_h(
                        \App\Support\AdminFormat::digits(
                            (string) $totalItems
                        )
                    ) ?>
                    تیکت
                </strong>


            </div>


            <?php if ($items === []): ?>

                <div class="admin-empty">
                    تیکتی در این بخش وجود ندارد.
                </div>

            <?php else: ?>

                <div class="admin-table-wrap ticketing-staff-table-wrap">

                    <table class="admin-table ticketing-staff-table">

                        <thead>
                        <tr>
                            <th class="ticketing-col-number">
                                شماره
                            </th>

                            <th class="ticketing-col-title">
                                عنوان و موضوع
                            </th>

                            <th class="ticketing-col-stage">
                                مرحله و تیم
                            </th>

                            <th class="ticketing-col-assignee">
                                کارشناس جاری
                            </th>

                            <th class="ticketing-col-priority">
                                اولویت
                            </th>

                            <th class="ticketing-col-status">
                                وضعیت
                            </th>

                            <th class="ticketing-col-activity">
                                آخرین فعالیت
                            </th>

                            <th class="ticketing-col-actions">
                                عملیات
                            </th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($items as $ticket): ?>

                            <?php
                            $actions =
                                is_array(
                                    $ticket[
                                        'staff_actions'
                                    ]
                                    ?? null
                                )
                                    ? $ticket[
                                        'staff_actions'
                                    ]
                                    : [];

                            $targets =
                                is_array(
                                    $actions[
                                        'transfer_targets'
                                    ]
                                    ?? null
                                )
                                    ? $actions[
                                        'transfer_targets'
                                    ]
                                    : [];

                            $reference =
                                (string) (
                                    $ticket[
                                        'public_reference'
                                    ]
                                    ?? ''
                                );

                            $ticketUrl =
                                '/admin/ticketing/tickets/'
                                . rawurlencode(
                                    $reference
                                );

                            $baseOperationUrl =
                                '/admin/ticketing/staff/'
                                . rawurlencode(
                                    $reference
                                );

                            $canTakeover =
                                !empty(
                                    $actions[
                                        'can_takeover'
                                    ]
                                );

                            $canTransfer =
                                !empty(
                                    $actions[
                                        'can_transfer'
                                    ]
                                )
                                && $targets !== [];

                            $canEscalate =
                                !empty(
                                    $actions[
                                        'can_escalate'
                                    ]
                                );

                            $escalationTarget =
                                trim(
                                    (string) (
                                        $actions[
                                            'escalation_target_title'
                                        ]
                                        ?? ''
                                    )
                                );

                            $escalationTooltip =
                                $escalationTarget !== ''
                                    ? 'ارجاع به '
                                        . $escalationTarget
                                    : 'ارجاع به سطح بالاتر';


                            /*
                             * TICKETING_R4B_CARTABLE_VISUAL
                             */
                            $priorityCode =
                                trim(
                                    (string) (
                                        $ticket[
                                            'priority_code'
                                        ]
                                        ?? ''
                                    )
                                );

                            $priorityColor =
                                trim(
                                    (string) (
                                        $ticket[
                                            'priority_color'
                                        ]
                                        ?? ''
                                    )
                                );

                            if (
                                preg_match(
                                    '/^#[0-9a-fA-F]{6}$/',
                                    $priorityColor
                                ) !== 1
                            ) {
                                $priorityColor =
                                    '#64748b';
                            }

                            $assigneeUserReference =
                                trim(
                                    (string) (
                                        $ticket[
                                            'assignee_user_reference'
                                        ]
                                        ?? ''
                                    )
                                );

                            $isAssignedToViewer =
                                $viewerUserReference !== ''
                                && $assigneeUserReference !== ''
                                && hash_equals(
                                    $viewerUserReference,
                                    $assigneeUserReference
                                );

                            $rowClass =
                                'ticketing-staff-row'
                                . (
                                    $isAssignedToViewer
                                        ? ' ticketing-staff-row--assigned-to-me'
                                        : ''
                                );
                            ?>

                            <tr
                                class="<?= ticketing_h(
                                    $rowClass
                                ) ?>"
                                style="--ticketing-priority-color: <?= ticketing_h(
                                    $priorityColor
                                ) ?>"
                            >

                                <td class="ticketing-col-number">

                                    <a
                                        class="ticketing-ticket-number-link"
                                        href="<?= ticketing_h(
                                            $ticketUrl
                                        ) ?>"
                                    >
                                        <?= ticketing_h(
                                            \App\Support\TicketingDisplay
                                                ::ticketNumberFromRow(
                                                    $ticket
                                                )
                                        ) ?>
                                    </a>

                                </td>


                                <td class="ticketing-col-title">

                                    <a
                                        class="ticketing-staff-title-link"
                                        href="<?= ticketing_h(
                                            $ticketUrl
                                        ) ?>"
                                    >
                                        <?= ticketing_h(
                                            $ticket[
                                                'subject'
                                            ]
                                            ?? ''
                                        ) ?>
                                    </a>

                                    <div class="admin-muted ticketing-staff-subline">
                                        <?= ticketing_h(
                                            $ticket[
                                                'support_topic_title_snapshot'
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </div>

                                </td>


                                <td class="ticketing-col-stage">

                                    <strong>
                                        <?= ticketing_h(
                                            $ticket[
                                                'layer_title'
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </strong>

                                    <div class="admin-muted ticketing-staff-subline">
                                        <?= ticketing_h(
                                            $ticket[
                                                'team_title'
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </div>

                                </td>


                                <td class="ticketing-col-assignee">

                                    <span
                                        class="ticketing-assignee-name<?= $isAssignedToViewer
                                            ? ' ticketing-assignee-name--mine'
                                            : '' ?>"
                                        <?php if ($isAssignedToViewer): ?>
                                            title="این تیکت به شما تخصیص داده شده است"
                                        <?php endif; ?>
                                    >
                                        <?= ticketing_h(
                                            trim(
                                                (string) (
                                                    $ticket[
                                                        'assignee_name'
                                                    ]
                                                    ?? ''
                                                )
                                            ) !== ''
                                                ? $ticket[
                                                    'assignee_name'
                                                ]
                                                : 'بدون کارشناس'
                                        ) ?>
                                    </span>

                                </td>


                                <td class="ticketing-col-priority">

                                    <span
                                        class="ticketing-staff-priority"
                                        data-priority-code="<?= ticketing_h(
                                            $priorityCode
                                        ) ?>"
                                    >
                                        <?= ticketing_h(
                                            $ticket[
                                                'priority_title'
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </span>

                                </td>


                                <td class="ticketing-col-status">

                                    <span class="admin-pill">
                                        <?= ticketing_h(
                                            $ticket[
                                                'status_title'
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </span>

                                </td>


                                <td class="ticketing-col-activity">

                                    <?= ticketing_h(
                                        \App\Support\AdminFormat
                                            ::jalaliDateTime(
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


                                <td class="ticketing-col-actions">

                                    <div class="ticketing-staff-icon-actions">


                                        <a
                                            class="ticketing-icon-action ticketing-icon-action--soft"
                                            href="<?= ticketing_h(
                                                $ticketUrl
                                            ) ?>"
                                            aria-label="مشاهده تیکت"
                                            title="مشاهده تیکت"
                                            data-tooltip="مشاهده تیکت"
                                        >
                                            <?= \App\Support\TicketingIcon::svg(
                                                'view'
                                            ) ?>
                                        </a>


                                        <?php if ($canTakeover): ?>

                                            <form
                                                method="post"
                                                action="<?= ticketing_h(
                                                    $baseOperationUrl
                                                    . '/takeover'
                                                ) ?>"
                                                class="ticketing-inline-operation-form"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="_token"
                                                    value="<?= ticketing_h(
                                                        $csrf
                                                    ) ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="ticketing-icon-action ticketing-icon-action--takeover"
                                                    aria-label="تحویل گرفتن تیکت"
                                                    title="تحویل گرفتن تیکت"
                                                    data-tooltip="تحویل گرفتن تیکت"
                                                >
                                                    <?= \App\Support\TicketingIcon::svg(
                                                        'takeover'
                                                    ) ?>
                                                </button>
                                            </form>

                                        <?php endif; ?>


                                        <?php if ($canTransfer): ?>

                                            <details
                                                class="ticketing-transfer-menu"
                                            >

                                                <summary
                                                    class="ticketing-icon-action ticketing-icon-action--transfer"
                                                    aria-label="انتقال به کارشناس دیگر"
                                                    title="انتقال به کارشناس دیگر"
                                                    data-tooltip="انتقال به کارشناس دیگر"
                                                >
                                                    <?= \App\Support\TicketingIcon::svg(
                                                        'transfer'
                                                    ) ?>
                                                </summary>


                                                <div class="ticketing-transfer-menu__body">

                                                    <strong>
                                                        انتقال به کارشناس
                                                    </strong>

                                                    <form
                                                        method="post"
                                                        action="<?= ticketing_h(
                                                            $baseOperationUrl
                                                            . '/transfer'
                                                        ) ?>"
                                                        class="ticketing-transfer-form"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="_token"
                                                            value="<?= ticketing_h(
                                                                $csrf
                                                            ) ?>"
                                                        >

                                                        <select
                                                            name="target_member_id"
                                                            required
                                                            aria-label="کارشناس مقصد"
                                                        >
                                                            <option value="">
                                                                کارشناس مقصد را انتخاب کنید
                                                            </option>

                                                            <?php foreach (
                                                                $targets
                                                                as $target
                                                            ): ?>

                                                                <option
                                                                    value="<?= ticketing_h(
                                                                        (string) (
                                                                            $target[
                                                                                'project_member_id'
                                                                            ]
                                                                            ?? ''
                                                                        )
                                                                    ) ?>"
                                                                >
                                                                    <?= ticketing_h(
                                                                        $target[
                                                                            'display_name_snapshot'
                                                                        ]
                                                                        ?? ''
                                                                    ) ?>
                                                                </option>

                                                            <?php endforeach; ?>

                                                        </select>


                                                        <button
                                                            type="submit"
                                                            class="ticketing-icon-action ticketing-icon-action--primary"
                                                            aria-label="تأیید انتقال"
                                                            title="تأیید انتقال"
                                                            data-tooltip="تأیید انتقال"
                                                        >
                                                            <?= \App\Support\TicketingIcon::svg(
                                                                'confirm'
                                                            ) ?>
                                                        </button>

                                                    </form>

                                                </div>

                                            </details>

                                        <?php endif; ?>


                                        <?php if ($canEscalate): ?>

                                            <form
                                                method="post"
                                                action="<?= ticketing_h(
                                                    $baseOperationUrl
                                                    . '/escalate'
                                                ) ?>"
                                                class="ticketing-inline-operation-form"
                                                onsubmit="return confirm('<?= ticketing_h(
                                                    $escalationTooltip
                                                ) ?> انجام شود؟');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="_token"
                                                    value="<?= ticketing_h(
                                                        $csrf
                                                    ) ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="ticketing-icon-action ticketing-icon-action--escalate"
                                                    aria-label="<?= ticketing_h(
                                                        $escalationTooltip
                                                    ) ?>"
                                                    title="<?= ticketing_h(
                                                        $escalationTooltip
                                                    ) ?>"
                                                    data-tooltip="<?= ticketing_h(
                                                        $escalationTooltip
                                                    ) ?>"
                                                >
                                                    <?= \App\Support\TicketingIcon::svg(
                                                        'escalate'
                                                    ) ?>
                                                </button>
                                            </form>

                                        <?php endif; ?>


                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <?php if ($totalPages > 1): ?>

                    <?php
                    $pageStart =
                        max(
                            1,
                            $currentPage - 2
                        );

                    $pageEnd =
                        min(
                            $totalPages,
                            $currentPage + 2
                        );
                    ?>

                    <nav
                        class="ticketing-staff-pagination"
                        aria-label="صفحه‌بندی کارتابل"
                    >

                        <a
                            class="ticketing-staff-page-link <?= $currentPage <= 1
                                ? 'is-disabled'
                                : '' ?>"
                            href="<?= ticketing_h(
                                $cartableUrl(
                                    [
                                        'page' =>
                                            max(
                                                1,
                                                $currentPage - 1
                                            ),
                                    ]
                                )
                            ) ?>"
                            <?= $currentPage <= 1
                                ? 'aria-disabled="true" tabindex="-1"'
                                : '' ?>
                        >
                            قبلی
                        </a>


                        <?php if ($pageStart > 1): ?>

                            <a
                                class="ticketing-staff-page-link"
                                href="<?= ticketing_h(
                                    $cartableUrl(
                                        [
                                            'page' => 1,
                                        ]
                                    )
                                ) ?>"
                            >
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        '1'
                                    )
                                ) ?>
                            </a>

                            <?php if ($pageStart > 2): ?>
                                <span
                                    class="ticketing-staff-page-gap"
                                    aria-hidden="true"
                                >
                                    …
                                </span>
                            <?php endif; ?>

                        <?php endif; ?>


                        <?php for (
                            $pageIndex = $pageStart;
                            $pageIndex <= $pageEnd;
                            $pageIndex++
                        ): ?>

                            <?php if (
                                $pageIndex
                                === $currentPage
                            ): ?>

                                <span
                                    class="ticketing-staff-page-link is-current"
                                    aria-current="page"
                                >
                                    <?= ticketing_h(
                                        \App\Support\AdminFormat::digits(
                                            (string) $pageIndex
                                        )
                                    ) ?>
                                </span>

                            <?php else: ?>

                                <a
                                    class="ticketing-staff-page-link"
                                    href="<?= ticketing_h(
                                        $cartableUrl(
                                            [
                                                'page' =>
                                                    $pageIndex,
                                            ]
                                        )
                                    ) ?>"
                                >
                                    <?= ticketing_h(
                                        \App\Support\AdminFormat::digits(
                                            (string) $pageIndex
                                        )
                                    ) ?>
                                </a>

                            <?php endif; ?>

                        <?php endfor; ?>


                        <?php if (
                            $pageEnd
                            < $totalPages
                        ): ?>

                            <?php if (
                                $pageEnd
                                < $totalPages - 1
                            ): ?>
                                <span
                                    class="ticketing-staff-page-gap"
                                    aria-hidden="true"
                                >
                                    …
                                </span>
                            <?php endif; ?>

                            <a
                                class="ticketing-staff-page-link"
                                href="<?= ticketing_h(
                                    $cartableUrl(
                                        [
                                            'page' =>
                                                $totalPages,
                                        ]
                                    )
                                ) ?>"
                            >
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (string) $totalPages
                                    )
                                ) ?>
                            </a>

                        <?php endif; ?>


                        <a
                            class="ticketing-staff-page-link <?= $currentPage >= $totalPages
                                ? 'is-disabled'
                                : '' ?>"
                            href="<?= ticketing_h(
                                $cartableUrl(
                                    [
                                        'page' =>
                                            min(
                                                $totalPages,
                                                $currentPage + 1
                                            ),
                                    ]
                                )
                            ) ?>"
                            <?= $currentPage >= $totalPages
                                ? 'aria-disabled="true" tabindex="-1"'
                                : '' ?>
                        >
                            بعدی
                        </a>

                    </nav>

                <?php endif; ?>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

<!-- TICKETING_CARTABLE_AUTO_FILTER_SCRIPT_V1 -->
<script
    src="/assets/admin/js/ticketing-cartable.js"
    defer
></script>

<?php
$content = ob_get_clean();

require __DIR__ . '/layout.php';

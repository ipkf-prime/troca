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


$uiText =
    static function (
        string $contentKey
    ): string {
        return
            \App\Services\UiContent\UiContentInlineGuide::bodyText(
                $contentKey,
                'ticketing',
                'ticketing-staff'
            );
    };

$sortOptions = [
    'priority_desc' =>
        $uiText('ticketing.t2.ticketing-staff.ui.19a40629d2b51d655fcc'),

    'activity_desc' =>
        $uiText('ticketing.t2.ticketing-staff.ui.4a96cca3c11856f0e60a'),

    'activity_asc' =>
        $uiText('ticketing.t2.ticketing-staff.ui.7cc38b7ee5202a95534b'),

    'created_desc' =>
        $uiText('ticketing.t2.ticketing-staff.ui.10ae22df8b5738df36df'),

    'created_asc' =>
        $uiText('ticketing.t2.ticketing-staff.ui.df5eeacd1f16c72de583'),
];


/*
 * TICKETING_COMPACT_FILTER_UX_T3G
 */
$advancedFilterCount = 0;

if ($layerId > 0) {
    $advancedFilterCount++;
}

if ($assignee !== '') {
    $advancedFilterCount++;
}

if ($sort !== 'priority_desc') {
    $advancedFilterCount++;
}

$advancedFiltersOpen =
    $advancedFilterCount > 0
    ||
    $perPage !== 25;


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
        $uiText('ticketing.t2.ticketing-staff.ui.11b8ca1a75687d8003b3'),
    ],

    'transferred' => [
        'ok',
        $uiText('ticketing.t2.ticketing-staff.ui.36e6fd7dbe89e06e3990'),
    ],

    'escalated' => [
        'ok',
        $uiText('ticketing.t2.ticketing-staff.ui.5ea38d80a508d616ce55'),
    ],

    'csrf' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.7f31f94f1ec6ced3bef0'),
    ],

    'forbidden' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.457033c98d70994dcff5'),
    ],

    'already-owner' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.0ee176859b8fd67b58ab'),
    ],

    'invalid-target' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.c8ef80f6664a230ea7a2'),
    ],

    'same-assignee' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.de7724ea6cc1fec57f81'),
    ],

    'no-escalation' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.064d69dc4e09020122fb'),
    ],

    'no-escalation-route' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.306cac3cfb7955bdf2d0'),
    ],

    'no-assignee' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.87242363e136eda9d9b1'),
    ],

    'closed' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.071d1bb1b7d3e0c8bee8'),
    ],

    'not-routed' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.d71a8ecdbf17a78c57c8'),
    ],

    'not-found' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.2872e00964349a392512'),
    ],

    'operation-failed' => [
        'error',
        $uiText('ticketing.t2.ticketing-staff.ui.8128db7b0d7158c4a56c'),
    ],
];


$scopeTabs = [
    'all' => [
        $uiText('ticketing.t2.ticketing-staff.ui.86380b0e00701b3eefa6'),
        (int) ($counts['all'] ?? 0),
    ],

    'my' => [
        $uiText('ticketing.t2.ticketing-staff.ui.f5498dc9efa6256199dd'),
        (int) ($counts['my'] ?? 0),
    ],

    'unassigned' => [
        $uiText('ticketing.t2.ticketing-staff.ui.7278e1b50731394594b4'),
        (int) ($counts['unassigned'] ?? 0),
    ],
];


ob_start();
?>

<nav
    class="admin-breadcrumb"
    aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.eb5d8c9dc410c816f330')) ?>"
>
    <a href="/admin/dashboard">
        <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.6a2175dc4a8676799c08')) ?>
    </a>

    <span>/</span>

    <a href="/admin/ticketing">
        <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.5523e019aa3ad6a3ba0e')) ?>
    </a>

    <span>/</span>

    <span>
        <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.230ea3cdf3f7fef6a92c')) ?>
    </span>
</nav>


<div class="admin-page ticketing-page ticketing-staff-page">

    <div class="admin-page-header ticketing-page-head">

        <div>
            <div class="admin-muted">
                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.6e36cde9d3e46a2702a5')) ?>
            </div>

            <h1>
                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.230ea3cdf3f7fef6a92c')) ?>
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
                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.c65e37157d5de9bc478e')) ?>
            </div>

        </section>

    <?php else: ?>

        <section class="admin-section ticketing-staff-section">

            <div
                class="ticketing-staff-scope-tabs"
                aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.2050947f0773c0fbb4f8')) ?>"
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


                <!-- TICKETING_COMPACT_FILTER_UX_T3G -->
                <!-- TICKETING_COMPACT_FILTER_PRIMARY_T3G_START -->

                <div
                    class="ticketing-compact-filter-grid ticketing-compact-filter-grid--staff"
                >

                    <label
                        class="ticketing-staff-search__field ticketing-compact-filter__field ticketing-compact-filter__search"
                    >
                        <span>
                            <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.d2cce2eed5c382e42b36')) ?>
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
                            placeholder="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.cb641a78f665fa8c737d')) ?>"
                        >
                    </label>


                    <label
                        class="ticketing-staff-search__field ticketing-compact-filter__field"
                    >
                        <span>
                            <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.b439d0b64417e081ba58')) ?>
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
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.ca18255f5d5bee1a89f6')) ?>
                            </option>

                            <option
                                value="all"
                                <?= $ticketStatus === 'all'
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.ad0f38a647689e4a55bd')) ?>
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


                    <label
                        class="ticketing-staff-search__field ticketing-compact-filter__field"
                    >
                        <span>
                            <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.0d39243f97342269199e')) ?>
                        </span>

                        <select
                            class="ui-select ticketing-cartable-filter-select"
                            data-ticketing-filter-auto-submit
                            name="topic"
                        >
                            <option value="0">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.37396354dbb78b34b3a4')) ?>
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
                                        $uiText('ticketing.t2.ticketing-staff.ui.669bbb8a20b29026960b')
                                        . $optionId;
                                }

                                if (
                                    $showTopicProject
                                    &&
                                    $optionProjectTitle !== ''
                                ) {
                                    $optionTitle .=
                                        $uiText('ticketing.t2.ticketing-staff.ui.d126300710bd5e5fc353')
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


                    <label
                        class="ticketing-staff-search__field ticketing-compact-filter__field"
                    >
                        <span>
                            <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.1b7fde85a260fc820c59')) ?>
                        </span>

                        <select
                            class="ui-select ticketing-cartable-filter-select"
                            data-ticketing-filter-auto-submit
                            name="priority"
                        >
                            <option value="">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.5f07f69d4e581a676800')) ?>
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


                    <div class="ticketing-compact-filter__actions">

                        <button
                            type="button"
                            class="ui-button admin-button admin-button--soft ticketing-compact-more-button"
                        data-active="<?= $advancedFilterCount ? '1' : '0' ?>"
                            data-ticketing-advanced-toggle
                            aria-expanded="<?= $advancedFiltersOpen
                                ? 'true'
                                : 'false' ?>"
                            aria-controls="ticketing-staff-advanced-filters"
                        >
                            <span>
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.f0b0aece08ead981f12b')) ?>
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
                            aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.b8155d98258aa4806ceb')) ?>"
                            title="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.b8155d98258aa4806ceb')) ?>"
                            data-tooltip="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.b8155d98258aa4806ceb')) ?>"
                        >
                            <?= \App\Support\TicketingIcon::svg(
                                'reset'
                            ) ?>
                        </a>

                    </div>

                </div>

                <!-- TICKETING_COMPACT_FILTER_PRIMARY_T3G_END -->


                <!-- TICKETING_COMPACT_FILTER_ADVANCED_T3G_START -->

                <div
                    id="ticketing-staff-advanced-filters"
                    class="ticketing-advanced-filter-panel"
                    data-ticketing-advanced-panel
                    <?= $advancedFiltersOpen
                        ? ''
                        : 'hidden' ?>
                >

                    <div
                        class="ticketing-advanced-filter-grid ticketing-advanced-filter-grid--staff"
                    >

                        <label
                            class="ticketing-staff-search__field ticketing-compact-filter__field"
                        >
                            <span>
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.c59e71f54a3b85372aa8')) ?>
                            </span>

                            <select
                                class="ui-select ticketing-cartable-filter-select"
                                data-ticketing-filter-auto-submit
                                name="layer_id"
                            >
                                <option value="0">
                                    <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.5d5cb7e97c87251e9cf2')) ?>
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


                        <label
                            class="ticketing-staff-search__field ticketing-compact-filter__field"
                        >
                            <span>
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.6db8acf90a6a2d79194f')) ?>
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
                                    <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.7cb7f5898532c3f33c62')) ?>
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


                        <label
                            class="ticketing-staff-search__field ticketing-compact-filter__field"
                        >
                            <span>
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.11da3ae63697b5c61110')) ?>
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


                        <label
                            class="ticketing-staff-search__field ticketing-compact-filter__field"
                        >
                            <span>
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.fac812f233987c6d7b1c')) ?>
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

                    </div>

                </div>

                <!-- TICKETING_COMPACT_FILTER_ADVANCED_T3G_END -->

            </form>


            <div class="ticketing-staff-list-head">

                <strong>
                    <?= ticketing_h(
                        \App\Support\AdminFormat::digits(
                            (string) $totalItems
                        )
                    ) ?>
                    <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.2ef1b5d7e02b92fabae7')) ?>
                </strong>


            </div>


            <?php if ($items === []): ?>

                <div class="admin-empty">
                    <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.a25fd73e4724974ff002')) ?>
                </div>

            <?php else: ?>

                <div class="admin-table-wrap ticketing-staff-table-wrap">

                    <table class="admin-table ticketing-staff-table">

                        <thead>
                        <tr>
                            <th class="ticketing-col-number">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.44f38a21f4262e6ac443')) ?>
                            </th>

                            <th class="ticketing-col-title">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.73eda8c4e6f165dc212d')) ?>
                            </th>

                            <th class="ticketing-col-stage">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.0c00efb25ab5dad74589')) ?>
                            </th>

                            <th class="ticketing-col-assignee">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.6db8acf90a6a2d79194f')) ?>
                            </th>

                            <th class="ticketing-col-priority">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.1b7fde85a260fc820c59')) ?>
                            </th>

                            <th class="ticketing-col-status">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.b439d0b64417e081ba58')) ?>
                            </th>

                            <th class="ticketing-col-activity">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.8c53f59ba3fb7f01e911')) ?>
                            </th>

                            <th class="ticketing-col-actions">
                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.7a89f0ee392778207da2')) ?>
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
                                    ? $uiText('ticketing.t2.ticketing-staff.ui.9ef6ddd1e1e4f5b9ecf1')
                                        . $escalationTarget
                                    : $uiText('ticketing.t2.ticketing-staff.ui.af20d33edd74699dbf20');


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
                                            ?? $uiText('ticketing.t2.ticketing-staff.ui.791881aaca58a8132824')
                                        ) ?>
                                    </div>

                                </td>


                                <td class="ticketing-col-stage">

                                    <strong>
                                        <?= ticketing_h(
                                            $ticket[
                                                'layer_title'
                                            ]
                                            ?? $uiText('ticketing.t2.ticketing-staff.ui.791881aaca58a8132824')
                                        ) ?>
                                    </strong>

                                    <div class="admin-muted ticketing-staff-subline">
                                        <?= ticketing_h(
                                            $ticket[
                                                'team_title'
                                            ]
                                            ?? $uiText('ticketing.t2.ticketing-staff.ui.791881aaca58a8132824')
                                        ) ?>
                                    </div>

                                </td>


                                <td class="ticketing-col-assignee">

                                    <span
                                        class="ticketing-assignee-name<?= $isAssignedToViewer
                                            ? ' ticketing-assignee-name--mine'
                                            : '' ?>"
                                        <?php if ($isAssignedToViewer): ?>
                                            title="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.38b815c41e0790b3bc18')) ?>"
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
                                                : $uiText('ticketing.t2.ticketing-staff.ui.7278e1b50731394594b4')
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
                                            ?? $uiText('ticketing.t2.ticketing-staff.ui.791881aaca58a8132824')
                                        ) ?>
                                    </span>

                                </td>


                                <td class="ticketing-col-status">

                                    <span class="admin-pill">
                                        <?= ticketing_h(
                                            $ticket[
                                                'status_title'
                                            ]
                                            ?? $uiText('ticketing.t2.ticketing-staff.ui.791881aaca58a8132824')
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
                                        ?: $uiText('ticketing.t2.ticketing-staff.ui.791881aaca58a8132824')
                                    ) ?>

                                </td>


                                <td class="ticketing-col-actions">

                                    <div class="ticketing-staff-icon-actions">


                                        <a
                                            class="ticketing-icon-action ticketing-icon-action--soft"
                                            href="<?= ticketing_h(
                                                $ticketUrl
                                            ) ?>"
                                            aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.9c97b57c73ce534717c7')) ?>"
                                            title="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.9c97b57c73ce534717c7')) ?>"
                                            data-tooltip="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.9c97b57c73ce534717c7')) ?>"
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
                                                    aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.1896adc02cc861ae1fcb')) ?>"
                                                    title="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.1896adc02cc861ae1fcb')) ?>"
                                                    data-tooltip="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.1896adc02cc861ae1fcb')) ?>"
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
                                                    aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.bce60232b273b14b5dc1')) ?>"
                                                    title="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.bce60232b273b14b5dc1')) ?>"
                                                    data-tooltip="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.bce60232b273b14b5dc1')) ?>"
                                                >
                                                    <?= \App\Support\TicketingIcon::svg(
                                                        'transfer'
                                                    ) ?>
                                                </summary>


                                                <div class="ticketing-transfer-menu__body">

                                                    <strong>
                                                        <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.3565ae78babcb27857d8')) ?>
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
                                                            aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.ba0ba73416cab4a7f656')) ?>"
                                                        >
                                                            <option value="">
                                                                <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.f2d1724fcc4f81b6090d')) ?>
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
                                                            aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.13d8830096292946a992')) ?>"
                                                            title="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.13d8830096292946a992')) ?>"
                                                            data-tooltip="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.13d8830096292946a992')) ?>"
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
                                                ) ?> <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.cc6995f13af5bb88cf21')) ?>');"
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
                        aria-label="<?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.a10c54d5dd3233921513')) ?>"
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
                            <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.a23d53c1677d49558d0d')) ?>
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
                            <?= ticketing_h($uiText('ticketing.t2.ticketing-staff.ui.97b04f16b43984016636')) ?>
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

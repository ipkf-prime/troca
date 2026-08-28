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

$isStaff =
    !empty(
        $page['is_staff']
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
                        href="/admin/ticketing/staff?scope=<?= ticketing_h(
                            rawurlencode(
                                $scopeCode
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
                class="ticketing-staff-search"
            >
                <input
                    type="hidden"
                    name="scope"
                    value="<?= ticketing_h(
                        $scope
                    ) ?>"
                >

                <label class="ticketing-staff-search__field">

                    <span>
                        جستجو
                    </span>

                    <input
                        type="search"
                        name="q"
                        maxlength="180"
                        value="<?= ticketing_h(
                            $q
                        ) ?>"
                        placeholder="شماره تیکت، عنوان، موضوع، درخواست‌کننده یا کارشناس"
                    >

                </label>


                <div class="ticketing-staff-search__actions">

                    <button
                        type="submit"
                        class="ticketing-icon-action ticketing-icon-action--primary"
                        aria-label="اعمال جستجو"
                        title="اعمال جستجو"
                        data-tooltip="اعمال جستجو"
                    >
                        <?= \App\Support\TicketingIcon::svg(
                            'search'
                        ) ?>
                    </button>


                    <a
                        class="ticketing-icon-action ticketing-icon-action--soft"
                        href="/admin/ticketing/staff?scope=<?= ticketing_h(
                            rawurlencode(
                                $scope
                            )
                        ) ?>"
                        aria-label="بازنشانی جستجو"
                        title="بازنشانی جستجو"
                        data-tooltip="بازنشانی جستجو"
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
                            (string) count($items)
                        )
                    ) ?>
                    تیکت
                </strong>

                <span class="admin-muted">
                    برای هر عملیات، نشانگر را روی آیکون نگه دارید.
                </span>

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
                            ?>

                            <tr>

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

                                </td>


                                <td class="ticketing-col-priority">

                                    <?= ticketing_h(
                                        $ticket[
                                            'priority_title'
                                        ]
                                        ?? '—'
                                    ) ?>

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

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();

require __DIR__ . '/layout.php';

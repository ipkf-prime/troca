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


<div class="admin-page ticketing-page">

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

        <section class="admin-section">

            <div class="admin-tabs">

                <a
                    class="<?= $scope === 'all'
                        ? 'is-active'
                        : '' ?>"
                    href="/admin/ticketing/staff?scope=all"
                >
                    قابل رسیدگی

                    <span class="admin-pill">
                        <?= ticketing_h(
                            \App\Support\AdminFormat::digits(
                                (string) (
                                    $counts['all']
                                    ?? 0
                                )
                            )
                        ) ?>
                    </span>
                </a>


                <a
                    class="<?= $scope === 'my'
                        ? 'is-active'
                        : '' ?>"
                    href="/admin/ticketing/staff?scope=my"
                >
                    تخصیص‌یافته به من

                    <span class="admin-pill">
                        <?= ticketing_h(
                            \App\Support\AdminFormat::digits(
                                (string) (
                                    $counts['my']
                                    ?? 0
                                )
                            )
                        ) ?>
                    </span>
                </a>


                <a
                    class="<?= $scope === 'unassigned'
                        ? 'is-active'
                        : '' ?>"
                    href="/admin/ticketing/staff?scope=unassigned"
                >
                    بدون کارشناس

                    <span class="admin-pill">
                        <?= ticketing_h(
                            \App\Support\AdminFormat::digits(
                                (string) (
                                    $counts['unassigned']
                                    ?? 0
                                )
                            )
                        ) ?>
                    </span>
                </a>

            </div>


            <form
                method="get"
                action="/admin/ticketing/staff"
                class="ticketing-toolbar"
            >
                <input
                    type="hidden"
                    name="scope"
                    value="<?= ticketing_h($scope) ?>"
                >

                <label>
                    <span>
                        جستجو
                    </span>

                    <input
                        type="search"
                        name="q"
                        maxlength="180"
                        value="<?= ticketing_h($q) ?>"
                        placeholder="شماره تیکت، عنوان، موضوع، درخواست‌کننده یا کارشناس"
                    >
                </label>

                <button
                    class="admin-button"
                    type="submit"
                >
                    اعمال
                </button>

                <a
                    class="admin-button admin-button--soft"
                    href="/admin/ticketing/staff?scope=<?= ticketing_h(
                        rawurlencode($scope)
                    ) ?>"
                >
                    بازنشانی
                </a>
            </form>


            <?php if ($items === []): ?>

                <div class="admin-empty">
                    تیکتی در این بخش وجود ندارد.
                </div>

            <?php else: ?>

                <div class="admin-table-wrap">

                    <table class="admin-table">

                        <thead>
                        <tr>
                            <th>شماره</th>
                            <th>عنوان</th>
                            <th>مرحله</th>
                            <th>کارشناس جاری</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>آخرین فعالیت</th>
                            <th>عملیات</th>
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

                            $baseOperationUrl =
                                '/admin/ticketing/staff/'
                                . rawurlencode(
                                    $reference
                                );
                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= ticketing_h(
                                            \App\Support\TicketingDisplay
                                                ::ticketNumberFromRow(
                                                    $ticket
                                                )
                                        ) ?>
                                    </strong>
                                </td>


                                <td>
                                    <strong>
                                        <?= ticketing_h(
                                            $ticket[
                                                'subject'
                                            ]
                                            ?? ''
                                        ) ?>
                                    </strong>

                                    <div class="admin-muted">
                                        <?= ticketing_h(
                                            $ticket[
                                                'support_topic_title_snapshot'
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </div>
                                </td>


                                <td>
                                    <strong>
                                        <?= ticketing_h(
                                            $ticket[
                                                'layer_title'
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </strong>

                                    <div class="admin-muted">
                                        <?= ticketing_h(
                                            $ticket[
                                                'team_title'
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </div>
                                </td>


                                <td>
                                    <?= ticketing_h(
                                        $ticket[
                                            'assignee_name'
                                        ]
                                        ?? 'بدون کارشناس'
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


                                <td>

                                    <div class="admin-form-actions">

                                        <?php if (
                                            !empty(
                                                $actions[
                                                    'can_takeover'
                                                ]
                                            )
                                        ): ?>

                                            <form
                                                method="post"
                                                action="<?= ticketing_h(
                                                    $baseOperationUrl
                                                    . '/takeover'
                                                ) ?>"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="_token"
                                                    value="<?= ticketing_h(
                                                        $csrf
                                                    ) ?>"
                                                >

                                                <button
                                                    class="admin-button admin-button--soft admin-button--compact"
                                                    type="submit"
                                                >
                                                    تحویل گرفتن
                                                </button>
                                            </form>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $actions[
                                                    'can_transfer'
                                                ]
                                            )
                                            && $targets !== []
                                        ): ?>

                                            <form
                                                method="post"
                                                action="<?= ticketing_h(
                                                    $baseOperationUrl
                                                    . '/transfer'
                                                ) ?>"
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
                                                >
                                                    <option value="">
                                                        انتقال به...
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
                                                    class="admin-button admin-button--soft admin-button--compact"
                                                    type="submit"
                                                >
                                                    انتقال
                                                </button>
                                            </form>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $actions[
                                                    'can_escalate'
                                                ]
                                            )
                                        ): ?>

                                            <form
                                                method="post"
                                                action="<?= ticketing_h(
                                                    $baseOperationUrl
                                                    . '/escalate'
                                                ) ?>"
                                                onsubmit="return confirm('تیکت به سطح بالاتر ارجاع شود؟');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="_token"
                                                    value="<?= ticketing_h(
                                                        $csrf
                                                    ) ?>"
                                                >

                                                <button
                                                    class="admin-button admin-button--soft admin-button--compact"
                                                    type="submit"
                                                >
                                                    ارجاع به سطح بالاتر
                                                </button>

                                                <?php if (
                                                    trim(
                                                        (string) (
                                                            $actions[
                                                                'escalation_target_title'
                                                            ]
                                                            ?? ''
                                                        )
                                                    ) !== ''
                                                ): ?>

                                                    <small class="admin-muted">
                                                        <?= ticketing_h(
                                                            $actions[
                                                                'escalation_target_title'
                                                            ]
                                                        ) ?>
                                                    </small>

                                                <?php endif; ?>

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

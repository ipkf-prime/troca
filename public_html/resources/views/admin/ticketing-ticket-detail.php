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

$detail =
    $detail
    ?? [];

$ticket =
    $detail['ticket']
    ?? [];

$messages =
    $detail['messages']
    ?? [];

$events =
    $detail['events']
    ?? [];

$status =
    (string) (
        $status
        ?? ''
    );

$eventLabels = [
    'ticket_created' =>
        'تیکت ثبت شد',
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

    <a href="/admin/ticketing/tickets">
        تیکت‌های من
    </a>
    <span>/</span>

    <span>
        <?= ticketing_h(
            \App\Support\TicketingDisplay::ticketNumberFromRow($ticket)
        ) ?>
    </span>
</nav>


<div class="admin-page ticketing-page ticketing-detail-page">

    <?php if ($status === 'created'): ?>
        <div
            class="admin-alert admin-alert--success"
            role="status"
        >
            تیکت با موفقیت ثبت شد.
        </div>
    <?php endif; ?>


    <div class="admin-page-header ticketing-page-head">

        <div>
            <div class="admin-muted">
                <?= ticketing_h(
                    \App\Support\TicketingDisplay::ticketNumberFromRow($ticket)
                ) ?>
            </div>

            <h1>
                <?= ticketing_h(
                    $ticket['subject']
                    ?? ''
                ) ?>
            </h1>
        </div>

        <a
            class="admin-button admin-button--soft"
            href="/admin/ticketing/tickets"
        >
            بازگشت به تیکت‌ها
        </a>

    </div>


    <div class="admin-grid admin-grid-4 ticketing-detail-metrics">

        <section class="admin-card">
            <div class="admin-card-body">
                <div class="admin-muted">
                    وضعیت
                </div>

                <strong>
                    <?= ticketing_h(
                        $ticket[
                            'status_title'
                        ]
                        ?? ''
                    ) ?>
                </strong>
            </div>
        </section>


        <section class="admin-card">
            <div class="admin-card-body">
                <div class="admin-muted">
                    اولویت
                </div>

                <strong>
                    <?= ticketing_h(
                        $ticket[
                            'priority_title'
                        ]
                        ?? ''
                    ) ?>
                </strong>
            </div>
        </section>


        <section class="admin-card">
            <div class="admin-card-body">
                <div class="admin-muted">
                    دسته
                </div>

                <strong>
                    <?= ticketing_h(
                        $ticket[
                            'category_title'
                        ]
                        ?? '—'
                    ) ?>
                </strong>
            </div>
        </section>


        <section class="admin-card">
            <div class="admin-card-body">
                <div class="admin-muted">
                    تاریخ ثبت
                </div>

                <strong>
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
                </strong>
            </div>
        </section>

    </div>


        <section class="admin-section ticketing-route-summary">

        <div class="ticketing-route-summary__item">
            <span>پروژه</span>

            <strong>
                <?= ticketing_h(
                    $ticket['project_title']
                    ?? '—'
                ) ?>
            </strong>
        </div>

        <div class="ticketing-route-summary__item">
            <span>موضوع</span>

            <strong>
                <?= ticketing_h(
                    $ticket['topic_title']
                    ?? '—'
                ) ?>
            </strong>
        </div>

        <div class="ticketing-route-summary__item">
            <span>مرحله جاری</span>

            <strong>
                <?= ticketing_h(
                    $ticket['layer_title']
                    ?? 'در انتظار مسیریابی'
                ) ?>
            </strong>

            <?php if (!empty($ticket['team_title'])): ?>
                <small>
                    <?= ticketing_h(
                        $ticket['team_title']
                    ) ?>
                </small>
            <?php endif; ?>
        </div>

        <div class="ticketing-route-summary__item">
            <span>کارشناس جاری</span>

            <strong>
                <?= ticketing_h(
                    !empty($ticket['assignee_name'])
                        ? $ticket['assignee_name']
                        : 'در انتظار تخصیص'
                ) ?>
            </strong>
        </div>

    </section>


<section
        class="admin-section ticketing-conversation"
    >
        <div class="admin-page-header">
            <div>
                <h2>
                    گفتگو
                </h2>
            </div>
        </div>


        <?php if ($messages === []): ?>

            <div class="admin-empty-state">
                پیامی برای این تیکت ثبت نشده است.
            </div>

        <?php else: ?>

            <?php foreach ($messages as $message): ?>

                <article
                    class="admin-card"
                    style="margin-bottom:.75rem"
                >
                    <div class="admin-card-body">

                        <div
                            style="
                                display:flex;
                                justify-content:space-between;
                                gap:1rem;
                                margin-bottom:.75rem;
                            "
                        >
                            <strong>
                                <?= ticketing_h(
                                    $message[
                                        'author_display_name_snapshot'
                                    ]
                                    ?? ''
                                ) ?>
                            </strong>

                            <span class="admin-muted">
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::jalaliDateTime(
                                        (string) (
                                            $message[
                                                'created_at'
                                            ]
                                            ?? ''
                                        )
                                    )
                                    ?: '—'
                                ) ?>
                            </span>
                        </div>

                        <div
                            style="
                                white-space:pre-wrap;
                                line-height:1.9;
                            "
                        ><?= ticketing_h(
                            $message['body']
                            ?? ''
                        ) ?></div>

                    </div>
                </article>

            <?php endforeach; ?>

        <?php endif; ?>


        <div
            class="admin-muted"
            style="margin-top:.75rem"
        >
            پاسخ به تیکت، یادداشت داخلی و
            پیوست در مرحله عملیاتی بعدی
            فعال می‌شود.
        </div>

    </section>


    <section class="admin-section ticketing-history">

        <div class="admin-page-header">
            <div>
                <h2>
                    تاریخچه
                </h2>
            </div>
        </div>


        <?php if ($events === []): ?>

            <div class="admin-empty-state">
                رویدادی ثبت نشده است.
            </div>

        <?php else: ?>

            <div class="admin-table-wrap">
                <table class="admin-table">

                    <thead>
                    <tr>
                        <th>رویداد</th>
                        <th>کاربر</th>
                        <th>وضعیت نهایی</th>
                        <th>زمان</th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($events as $event): ?>

                        <?php
                        $eventCode =
                            (string) (
                                $event[
                                    'event_code'
                                ]
                                ?? ''
                            );
                        ?>

                        <tr>
                            <td>
                                <?= ticketing_h(
                                    \App\Support\TicketingDisplay::eventTitle(
                                        $eventCode
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $event[
                                        'actor_display_name_snapshot'
                                    ]
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\TicketingDisplay::statusTitle(
                                        (string) (
                                            $event[
                                                'resulting_status_code'
                                            ]
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::jalaliDateTime(
                                        (string) (
                                            $event[
                                                'occurred_at'
                                            ]
                                            ?? ''
                                        )
                                    )
                                    ?: '—'
                                ) ?>
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
/* ticketing_lifecycle_a8d1 */

$lifecycleTicket =
    is_array($ticket ?? null)
        ? $ticket
        : (
            is_array(
                $page['ticket']
                ?? null
            )
                ? $page['ticket']
                : []
        );

$lifecycleReference =
    trim(
        (string) (
            $lifecycleTicket[
                'public_reference'
            ]
            ?? ''
        )
    );

$lifecycleStatus =
    trim(
        (string) (
            $lifecycleTicket[
                'status_code'
            ]
            ?? ''
        )
    );

$lifecycleUserId =
    (int) (
        $context['user_id']
        ?? 0
    );

$lifecycleCanReply = false;

if ($lifecycleUserId > 0) {
    try {
        $lifecycleCanReply =
            (
                new \App\Services\AuthorizationService()
            )->hasPermission(
                $lifecycleUserId,
                'ticketing.ticket.reply'
            );
    } catch (\Throwable) {
        $lifecycleCanReply = false;
    }
}

$lifecycleClosed =
    in_array(
        $lifecycleStatus,
        [
            'resolved',
            'closed',
            'cancelled',
        ],
        true
    );

$lifecycleH =
    static function (
        mixed $value
    ): string {
        return htmlspecialchars(
            (string) (
                $value
                ?? ''
            ),
            ENT_QUOTES
            | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    };

$lifecycleStatusMessage =
    [
        'reply_sent' =>
            [
                'success',
                'پاسخ کارشناس ثبت شد و تیکت در انتظار پاسخ درخواست‌کننده قرار گرفت.',
            ],

        'reply_empty' =>
            [
                'danger',
                'متن پاسخ نمی‌تواند خالی باشد.',
            ],

        'reply_too_long' =>
            [
                'danger',
                'متن پاسخ از حداکثر طول مجاز بیشتر است.',
            ],

        'reply_closed' =>
            [
                'danger',
                'برای تیکت بسته یا خاتمه‌یافته امکان ثبت پاسخ وجود ندارد.',
            ],

        'reply_forbidden' =>
            [
                'danger',
                'برای ثبت پاسخ در این پروژه دسترسی لازم وجود ندارد.',
            ],

        'reply_invalid_csrf' =>
            [
                'danger',
                'نشست فرم معتبر نیست. صفحه را تازه‌سازی و دوباره تلاش کنید.',
            ],

        'reply_failed' =>
            [
                'danger',
                'ثبت پاسخ انجام نشد. دوباره تلاش کنید.',
            ],
    ][
        trim(
            (string) (
                $_GET['status']
                ?? ''
            )
        )
    ]
    ?? null;
?>

<?php if (
    is_array($lifecycleStatusMessage)
): ?>
    <section class="admin-section">
        <div
            class="admin-alert admin-alert--<?= $lifecycleH(
                $lifecycleStatusMessage[0]
            ) ?>"
            role="status"
        >
            <?= $lifecycleH(
                $lifecycleStatusMessage[1]
            ) ?>
        </div>
    </section>
<?php endif; ?>

<?php if (
    $lifecycleCanReply
    && !$lifecycleClosed
    && $lifecycleReference !== ''
): ?>
    <?php
    $lifecycleCsrf =
        (
            new \IPKF\Security\Csrf()
        )->token();
    ?>

    <section
        class="admin-section ticketing-staff-reply"
        data-ticketing-staff-reply
    >
        <div class="admin-section__header">
            <div>
                <h3>پاسخ کارشناس</h3>
                <p class="admin-muted">
                    پاسخ عمومی برای درخواست‌کننده ثبت می‌شود و وضعیت تیکت به «در انتظار پاسخ درخواست‌کننده» تغییر می‌کند.
                </p>
            </div>
        </div>

        <form
            method="post"
            action="<?= $lifecycleH(
                '/admin/ticketing/tickets/'
                . rawurlencode(
                    $lifecycleReference
                )
                . '/reply'
            ) ?>"
            data-ticketing-staff-reply-form
        >
            <input
                type="hidden"
                name="_token"
                value="<?= $lifecycleH(
                    $lifecycleCsrf
                ) ?>"
            >

            <label>
                <span>متن پاسخ</span>

                <textarea
                    name="body"
                    rows="5"
                    maxlength="20000"
                    required
                    placeholder="پاسخ کارشناس را وارد کنید..."
                ></textarea>
            </label>

            <div class="admin-form-actions">
                <button
                    class="admin-button"
                    type="submit"
                >
                    ثبت پاسخ
                </button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php
$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';

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


<!-- ticketing_detail_a8d3 -->
<div
    class="ticketing-detail-workspace"
    data-ticketing-detail-workspace
>
    <div
        class="ticketing-reply-slot"
        data-ticketing-reply-slot
    ></div>

    <!-- ticketing_detail_a8d3_three_tabs -->
    <nav
        class="admin-tabs ticketing-detail-tabs"
        data-ticketing-detail-tabs
        role="tablist"
        aria-label="وضعیت، سوابق گفتگو و تاریخچه تیکت"
    >
        <button
            class="admin-tab is-active"
            type="button"
            role="tab"
            aria-selected="true"
            data-ticketing-detail-tab="status"
        >
            وضعیت
        </button>

        <button
            class="admin-tab"
            type="button"
            role="tab"
            aria-selected="false"
            data-ticketing-detail-tab="conversation"
        >
            سوابق گفتگو
        </button>

        <button
            class="admin-tab"
            type="button"
            role="tab"
            aria-selected="false"
            data-ticketing-detail-tab="history"
        >
            تاریخچه
        </button>
    </nav>

    <section
        class="admin-section ticketing-status-panel ticketing-detail-panel is-active"
        data-ticketing-detail-panel="status"
        role="tabpanel"
    ></section>

    <section
        class="admin-section ticketing-conversation ticketing-detail-panel"
        data-ticketing-detail-panel="conversation"
        role="tabpanel"
        hidden
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
                    class="admin-card ticketing-message-bubble"
                    data-ticketing-message-author="<?= ticketing_h(
                        (string) (
                            $message['author_kind']
                            ?? 'other'
                        )
                    ) ?>"
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




    </section>


    <section
        class="admin-section ticketing-history ticketing-detail-panel"
        data-ticketing-detail-panel="history"
        role="tabpanel"
        hidden
    >

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
    <!-- /ticketing_detail_a8d3 -->
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

/*
 * ticketing_lifecycle_a8d2
 *
 * Requester authorization is based on ticket ownership.
 * Staff permissions and navigation path do not grant
 * Requester authority on this shared Detail page.
 */
$lifecycleActorReference =
    $lifecycleUserId > 0
        ? 'user:' . $lifecycleUserId
        : '';

$lifecycleRequesterReference =
    trim(
        (string) (
            $lifecycleTicket[
                'requester_user_reference'
            ]
            ?? ''
        )
    );

$lifecycleIsRequester =
    $lifecycleActorReference !== ''
    &&
    $lifecycleRequesterReference !== ''
    &&
    hash_equals(
        $lifecycleRequesterReference,
        $lifecycleActorReference
    );

$lifecycleRequesterExpected =
    $lifecycleIsRequester
    &&
    $lifecycleStatus ===
        'waiting_requester';


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
        'requester_reply_sent' =>
            [
                'success',
                'پاسخ درخواست‌کننده ثبت شد و تیکت دوباره در حال بررسی قرار گرفت.',
            ],

        'requester_reply_empty' =>
            [
                'danger',
                'متن پاسخ نمی‌تواند خالی باشد.',
            ],

        'requester_reply_too_long' =>
            [
                'danger',
                'متن پاسخ از حداکثر طول مجاز بیشتر است.',
            ],

        'requester_reply_forbidden' =>
            [
                'danger',
                'این تیکت متعلق به حساب کاربری شما نیست.',
            ],

        'requester_reply_not_expected' =>
            [
                'danger',
                'در وضعیت فعلی، تیکت منتظر پاسخ درخواست‌کننده نیست.',
            ],

        'requester_reply_invalid_csrf' =>
            [
                'danger',
                'نشست فرم معتبر نیست. صفحه را تازه‌سازی و دوباره تلاش کنید.',
            ],

        'requester_reply_failed' =>
            [
                'danger',
                'ثبت پاسخ درخواست‌کننده انجام نشد. دوباره تلاش کنید.',
            ],

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
    $lifecycleRequesterExpected
    && !$lifecycleClosed
    && $lifecycleReference !== ''
): ?>
    <?php
    $requesterReplyCsrf =
        (
            new \IPKF\Security\Csrf()
        )->token();
    ?>

    <section
        class="admin-section ticketing-requester-reply"
        data-ticketing-requester-reply
    >
        <div class="admin-section__header">
            <div>
                <h3>پاسخ درخواست‌کننده</h3>

                <p class="admin-muted">
                    پاسخ شما برای تیم پشتیبانی ثبت می‌شود و تیکت دوباره به وضعیت «در حال بررسی» بازمی‌گردد.
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
                . '/requester-reply'
            ) ?>"
            data-ticketing-requester-reply-form
        >
            <input
                type="hidden"
                name="_token"
                value="<?= $lifecycleH(
                    $requesterReplyCsrf
                ) ?>"
            >

            <label>
                <span>متن پاسخ</span>

                <textarea
                    name="body"
                    rows="5"
                    maxlength="20000"
                    required
                    placeholder="پاسخ خود را وارد کنید..."
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


<?php if (
    $lifecycleCanReply
    && !$lifecycleRequesterExpected
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

<script>
(() => {
    const root =
        document.querySelector(
            '[data-ticketing-detail-workspace]'
        );

    if (!root) {
        return;
    }

    const replySlot =
        root.querySelector(
            '[data-ticketing-reply-slot]'
        );

    const statusPanel =
        root.querySelector(
            '[data-ticketing-detail-panel="status"]'
        );

    const activeReply =
        document.querySelector(
            '.ticketing-requester-reply,'
            + '.ticketing-staff-reply'
        );

    /*
     * Summary sections rendered immediately before the
     * A8D3 workspace belong to the Status tab.
     *
     * Keep page header / breadcrumb / subject outside.
     */
    if (statusPanel) {
        const parent =
            root.parentElement;

        if (parent) {
            const candidates =
                Array.from(
                    parent.children
                );

            const rootIndex =
                candidates.indexOf(
                    root
                );

            if (rootIndex >= 0) {
                candidates
                    .slice(
                        0,
                        rootIndex
                    )
                    .filter(
                        (element) =>
                            element.tagName ===
                            'SECTION'
                    )
                    .forEach(
                        (section) => {
                            statusPanel.appendChild(
                                section
                            );
                        }
                    );
            }
        }

        if (activeReply) {
            statusPanel.appendChild(
                activeReply
            );
        }
    }

    if (replySlot) {
        replySlot.remove();
    }

    const tabs =
        Array.from(
            root.querySelectorAll(
                '[data-ticketing-detail-tab]'
            )
        );

    const panels =
        Array.from(
            root.querySelectorAll(
                '[data-ticketing-detail-panel]'
            )
        );

    const activate =
        (target) => {
            tabs.forEach(
                (tab) => {
                    const active =
                        tab.getAttribute(
                            'data-ticketing-detail-tab'
                        ) === target;

                    tab.classList.toggle(
                        'is-active',
                        active
                    );

                    tab.setAttribute(
                        'aria-selected',
                        active
                            ? 'true'
                            : 'false'
                    );
                }
            );

            panels.forEach(
                (panel) => {
                    const active =
                        panel.getAttribute(
                            'data-ticketing-detail-panel'
                        ) === target;

                    panel.hidden =
                        !active;

                    panel.classList.toggle(
                        'is-active',
                        active
                    );
                }
            );
        };

    tabs.forEach(
        (tab) => {
            tab.addEventListener(
                'click',
                (event) => {
                    event.preventDefault();

                    activate(
                        tab.getAttribute(
                            'data-ticketing-detail-tab'
                        )
                    );
                }
            );
        }
    );

    activate(
        'status'
    );
})();
</script>

<?php
$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';

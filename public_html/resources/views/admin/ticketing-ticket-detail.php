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

$attachments =
    is_array(
        $detail['attachments']
        ?? null
    )
        ? $detail['attachments']
        : [];

$attachmentsByMessage = [];

foreach ($attachments as $attachment) {
    $messageId =
        (int) (
            $attachment['message_id']
            ?? 0
        );

    if ($messageId < 1) {
        continue;
    }

    $attachmentsByMessage[
        $messageId
    ][] =
        $attachment;
}

/* ticketing_conversation_experience_a8d5 */

$status =
    (string) (
        $status
        ?? ''
    );

$eventLabels = [
        'ticket_priority_changed' => 'تغییر اولویت',
    'ticket_created' =>
        'تیکت ثبت شد',
];

$eventLabels[
    'ticket_requester_updated'
] = 'توضیح درخواست‌کننده';

$eventLabels[
    'ticket_requester_resolved'
] = 'حل‌شدن توسط درخواست‌کننده';

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
        aria-label="پاسخ و عملیات، تاریخچه و جزئیات تیکت"
    >
        <button
            class="admin-tab is-active"
            type="button"
            role="tab"
            aria-selected="true"
            data-ticketing-detail-tab="status"
        >
            پاسخ و عملیات
        </button>

        <button
            class="admin-tab"
            type="button"
            role="tab"
            aria-selected="false"
            data-ticketing-detail-tab="conversation"
        >
            تاریخچه
        </button>

        <button
            class="admin-tab"
            type="button"
            role="tab"
            aria-selected="false"
            data-ticketing-detail-tab="history"
        >
            جزئیات
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

        <!-- ticketing_unified_timeline_r4a2r3 -->
        <div
            class="ticketing-unified-timeline"
            data-ticketing-unified-timeline
            aria-label="تاریخچه یکپارچه تیکت"
        ></div>

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
                    data-ticketing-timeline-kind="message"
                    data-ticketing-timeline-time="<?= ticketing_h(
                        (string) (
                            $message['created_at']
                            ?? ''
                        )
                    ) ?>"
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

                            class="ticketing-message-body is-collapsed"
                            data-ticketing-message-body
                        ><?= ticketing_h(
                            $message['body']
                            ?? ''
                        ) ?></div>

                        <button
                            class="ticketing-message-more"
                            type="button"
                            data-ticketing-message-toggle
                            aria-expanded="false"
                            hidden
                        >
                            مشاهده بیشتر
                        </button>

                        <?php
                        $messageAttachments =
                            $attachmentsByMessage[
                                (int) (
                                    $message['id']
                                    ?? 0
                                )
                            ]
                            ?? [];
                        ?>

                        <?php if (
                            $messageAttachments !== []
                        ): ?>

                            <div
                                class="ticketing-message-attachments"
                            >
                                <div
                                    class="ticketing-message-attachments-title"
                                >
                                    پیوست‌ها
                                </div>

                                <?php foreach (
                                    $messageAttachments
                                    as $attachment
                                ): ?>

                                    <?php
                                    $bytes =
                                        max(
                                            0,
                                            (int) (
                                                $attachment[
                                                    'size_bytes'
                                                ]
                                                ?? 0
                                            )
                                        );

                                    $kb =
                                        max(
                                            1,
                                            (int) ceil(
                                                $bytes
                                                / 1024
                                            )
                                        );

                                    $kbFa =
                                        strtr(
                                            (string) $kb,
                                            [
                                                '0' => '۰',
                                                '1' => '۱',
                                                '2' => '۲',
                                                '3' => '۳',
                                                '4' => '۴',
                                                '5' => '۵',
                                                '6' => '۶',
                                                '7' => '۷',
                                                '8' => '۸',
                                                '9' => '۹',
                                            ]
                                        );
                                    ?>

                                    <div
                                        class="ticketing-attachment-chip"
                                        data-ticketing-attachment
                                    >
                                        <span
                                            class="ticketing-attachment-icon"
                                            aria-hidden="true"
                                        >
                                            ◇
                                        </span>

                                        <span
                                            class="ticketing-attachment-name"
                                            title="<?= ticketing_h(
                                                $attachment[
                                                    'original_name'
                                                ]
                                                ?? 'پیوست'
                                            ) ?>"
                                        >
                                            <?= ticketing_h(
                                                $attachment[
                                                    'original_name'
                                                ]
                                                ?? 'پیوست'
                                            ) ?>
                                        </span>

                                        <span
                                            class="ticketing-attachment-size"
                                        >
                                            <?= ticketing_h(
                                                $kbFa
                                            ) ?>
                                            کیلوبایت
                                        </span>

                                        <?php
                                        $attachmentScanStatus =
                                            (string) (
                                                $attachment[
                                                    'scan_status_code'
                                                ]
                                                ?? ''
                                            );

                                        $attachmentReady =
                                            in_array(
                                                $attachmentScanStatus,
                                                [
                                                    'clean',
                                                    'approved',
                                                ],
                                                true
                                            );
                                        ?>

                                        <?php if (
                                            $attachmentReady
                                        ): ?>

                                            <a
                                                class="ticketing-attachment-action"
                                                href="<?= ticketing_h(
                                                    '/admin/ticketing/tickets/'
                                                    . rawurlencode(
                                                        (string) (
                                                            $ticket[
                                                                'public_reference'
                                                            ]
                                                            ?? ''
                                                        )
                                                    )
                                                    . '/attachments/'
                                                    . rawurlencode(
                                                        (string) (
                                                            $attachment[
                                                                'id'
                                                            ]
                                                            ?? ''
                                                        )
                                                    )
                                                ) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                data-ticketing-attachment-open
                                            >
                                                مشاهده
                                            </a>

                                        <?php else: ?>

                                            <button
                                                class="ticketing-attachment-action"
                                                type="button"
                                                disabled
                                                title="فایل هنوز از نظر امنیتی بررسی نشده است."
                                            >
                                                مشاهده
                                            </button>

                                            <span
                                                class="ticketing-attachment-status"
                                            >
                                                در انتظار بررسی
                                            </span>

                                        <?php endif; ?>
                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>


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

        <div
            class="ticketing-details-host"
            data-ticketing-details-host
        ></div>


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

                        <tr
                            data-ticketing-event-source
                            data-ticketing-timeline-kind="event"
                            data-ticketing-event-code="<?= ticketing_h(
                                $eventCode
                            ) ?>"
                            data-ticketing-timeline-time="<?= ticketing_h(
                                (string) (
                                    $event['occurred_at']
                                    ?? ''
                                )
                            ) ?>"
                            data-ticketing-event-actor="<?= ticketing_h(
                                (string) (
                                    $event[
                                        'actor_display_name_snapshot'
                                    ]
                                    ?? ''
                                )
                            ) ?>"
                            data-ticketing-event-previous="<?= ticketing_h(
                                (string) (
                                    $event[
                                        'previous_status_code'
                                    ]
                                    ?? ''
                                )
                            ) ?>"
                            data-ticketing-event-result="<?= ticketing_h(
                                (string) (
                                    $event[
                                        'resulting_status_code'
                                    ]
                                    ?? ''
                                )
                            ) ?>"
                        >
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

/*
 * TICKETING_STAFF_REPLY_UI_OWNERSHIP_GUARD
 *
 * ticketing.ticket.reply is only the coarse permission.
 * Exact project + exact current assignee + lifecycle turn
 * decide whether the reply form is rendered.
 */
$lifecycleStaffReplyAccess = [
    'can_reply' => false,
    'state' => 'reply_forbidden',
];

if (
    $lifecycleCanReply
    &&
    !$lifecycleIsRequester
    &&
    $lifecycleReference !== ''
) {
    try {
        $lifecycleStaffReplyAccess =
            (
                new \App\Services\Ticketing\TicketStaffReplyAccessService()
            )->evaluate(
                $lifecycleReference,
                $lifecycleUserId
            );
    } catch (\Throwable) {
        $lifecycleStaffReplyAccess = [
            'can_reply' => false,
            'state' => 'reply_forbidden',
        ];
    }
}

$lifecycleStaffOwnsReply =
    !empty(
        $lifecycleStaffReplyAccess[
            'can_reply'
        ]
    );

$lifecycleStaffReplyState =
    trim(
        (string) (
            $lifecycleStaffReplyAccess[
                'state'
            ]
            ?? 'reply_forbidden'
        )
    );

/*
 * TICKETING_DETAIL_RESOLVE_CLOSE_REOPEN_CAPABILITIES
 */
$lifecycleTransitionCapabilities = [
    'found' => false,
    'can_resolve' => false,
    'can_close' => false,
    'can_reopen' => false,
];

if (
    $lifecycleReference !== ''
    &&
    $lifecycleUserId > 0
) {
    try {
        $lifecycleTransitionCapabilities =
            (
                new \App\Services\Ticketing\TicketLifecycleTransitionService()
            )->capabilities(
                $lifecycleReference,
                $lifecycleUserId
            );
    } catch (\Throwable) {
        $lifecycleTransitionCapabilities = [
            'found' => false,
            'can_resolve' => false,
            'can_close' => false,
            'can_reopen' => false,
        ];
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

/*
 * TICKETING_PRIORITY_GOVERNANCE
 *
 * Priority correction is independent from lifecycle ownership.
 * The service combines ticketing.ticket.reply with exact active
 * project/team operational membership before allowing changes.
 */
$priorityGovernance = [
    'found' => false,
    'can_change' => false,
    'ticket' => [],
    'priorities' => [],
    'history' => [],
];

if (
    $lifecycleReference !== ''
    && $lifecycleUserId > 0
) {
    try {
        $priorityGovernance =
            (
                new \App\Services\Ticketing\TicketPriorityManagementService()
            )->panel(
                $lifecycleReference,
                $lifecycleUserId
            );
    } catch (\Throwable) {
        $priorityGovernance = [
            'found' => false,
            'can_change' => false,
            'ticket' => [],
            'priorities' => [],
            'history' => [],
        ];
    }
}

$priorityGovernanceTicket =
    is_array(
        $priorityGovernance['ticket']
        ?? null
    )
        ? $priorityGovernance['ticket']
        : [];

$priorityGovernancePriorities =
    is_array(
        $priorityGovernance['priorities']
        ?? null
    )
        ? $priorityGovernance['priorities']
        : [];

$priorityGovernanceHistory =
    is_array(
        $priorityGovernance['history']
        ?? null
    )
        ? $priorityGovernance['history']
        : [];

$priorityGovernanceCanChange =
    !empty(
        $priorityGovernance['can_change']
    );

$priorityGovernanceCurrentCode = trim(
    (string) (
        $priorityGovernanceTicket['priority_code']
        ?? $lifecycleTicket['priority_code']
        ?? ''
    )
);

$priorityGovernanceCurrentTitle = trim(
    (string) (
        $priorityGovernanceTicket['priority_title']
        ?? $lifecycleTicket['priority_title']
        ?? $priorityGovernanceCurrentCode
    )
);

$priorityNotice = trim(
    (string) (
        $_GET['priority_notice']
        ?? ''
    )
);

$priorityNoticeMap = [
    'priority_changed' => [
        'success',
        'اولویت تیکت تغییر کرد و دلیل آن در تاریخچه ثبت شد.',
    ],
    'priority_unchanged' => [
        'info',
        'اولویت انتخاب‌شده با اولویت فعلی یکسان است.',
    ],
    'priority_invalid' => [
        'danger',
        'اولویت انتخاب‌شده معتبر نیست.',
    ],
    'priority_reason_invalid' => [
        'danger',
        'برای تغییر اولویت، دلیل معتبر حداقل سه‌حرفی وارد کنید.',
    ],
    'priority_forbidden' => [
        'danger',
        'برای اصلاح اولویت این تیکت دسترسی عملیاتی لازم را ندارید.',
    ],
    'priority_invalid_csrf' => [
        'danger',
        'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی و دوباره اقدام کنید.',
    ],
    'priority_failed' => [
        'danger',
        'تغییر اولویت انجام نشد. دوباره تلاش کنید.',
    ],
];

$lifecycleStatusMessage =
    [
        'ticket_resolved' =>
            [
                'success',
                'تیکت با موفقیت به وضعیت «حل‌شده» منتقل شد.',
            ],

        'ticket_closed' =>
            [
                'success',
                'تیکت با موفقیت بسته شد.',
            ],

        'ticket_reopened' =>
            [
                'success',
                'تیکت بازگشایی شد و برای ادامه رسیدگی به وضعیت «در حال بررسی» برگشت.',
            ],

        'lifecycle_invalid_csrf' =>
            [
                'danger',
                'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی و دوباره اقدام کنید.',
            ],

        'lifecycle_owner_required' =>
            [
                'danger',
                'فقط کارشناس فعلی که تیکت در اختیار اوست می‌تواند تیکت را حل‌شده اعلام کند.',
            ],

        'lifecycle_waiting_requester' =>
            [
                'danger',
                'تا قبل از پاسخ درخواست‌کننده، امکان حل‌شده کردن تیکت وجود ندارد.',
            ],

        'lifecycle_invalid_state' =>
            [
                'danger',
                'این اقدام با وضعیت فعلی تیکت سازگار نیست.',
            ],

        'lifecycle_resolve_first' =>
            [
                'danger',
                'برای بستن تیکت، ابتدا باید تیکت به وضعیت «حل‌شده» منتقل شود.',
            ],

        'lifecycle_close_forbidden' =>
            [
                'danger',
                'بستن این تیکت فقط برای کارشناس فعلی یا مدیر پروژه مجاز است.',
            ],

        'lifecycle_reopen_invalid_state' =>
            [
                'danger',
                'فقط تیکت بسته‌شده قابل بازگشایی است.',
            ],

        'lifecycle_reopen_forbidden' =>
            [
                'danger',
                'بازگشایی این تیکت فقط برای درخواست‌کننده همان تیکت یا مدیر پروژه مجاز است.',
            ],

        'lifecycle_transition_conflict' =>
            [
                'danger',
                'وضعیت تیکت هم‌زمان تغییر کرده است. صفحه را تازه‌سازی و دوباره بررسی کنید.',
            ],

        'lifecycle_failed' =>
            [
                'danger',
                'تغییر وضعیت تیکت انجام نشد.',
            ],
        'requester_reply_sent' =>
            [
                'success',
                'پاسخ درخواست‌کننده ثبت شد و تیکت برای ادامه رسیدگی کارشناس در وضعیت «در حال بررسی» قرار گرفت.',
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

        /* TICKETING_REQUESTER_ATTACHMENT_ERROR_SURFACE_V2 */
        'requester_attachment_too_many' =>
            ['danger', 'تعداد فایل‌های انتخاب‌شده بیش از حد مجاز است.'],

        'requester_attachment_upload_failed' =>
            ['danger', 'بارگذاری فایل کامل نشد. فایل را دوباره انتخاب و ارسال کنید.'],

        'requester_attachment_upload_invalid' =>
            ['danger', 'فایل بارگذاری‌شده معتبر نیست. فایل را دوباره انتخاب کنید.'],

        'requester_attachment_empty' =>
            ['danger', 'فایل انتخاب‌شده خالی است.'],

        'requester_attachment_too_large' =>
            ['danger', 'حجم یکی از فایل‌های انتخاب‌شده بیش از حد مجاز است.'],

        'requester_attachment_total_too_large' =>
            ['danger', 'مجموع حجم فایل‌های انتخاب‌شده بیش از حد مجاز است.'],

        'requester_attachment_type_invalid' =>
            ['danger', 'نوع یا پسوند فایل انتخاب‌شده مجاز نیست.'],

        'requester_attachment_infected' =>
            ['danger', 'فایل انتخاب‌شده آلوده تشخیص داده شد و بارگذاری آن انجام نشد.'],

        'requester_attachment_scan_failed' =>
            ['danger', 'بررسی امنیتی فایل در حال حاضر انجام نشد. فایل بارگذاری نشد.'],

        'requester_attachment_invalid' =>
            ['danger', 'فایل پیوست معتبر نیست یا امکان بارگذاری آن وجود ندارد.'],

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

        'reply_waiting_requester' =>
            [
                'info',
                'این تیکت در انتظار پاسخ درخواست‌کننده است. در حال حاضر نوبت اقدام کارشناس نیست.',
            ],

        'reply_takeover_required' =>
            [
                'warning',
                'این تیکت هنوز در اختیار کارشناس مشخصی نیست. ابتدا آن را از کارتابل در اختیار بگیرید.',
            ],

        'reply_not_assignee' =>
            [
                'warning',
                'این تیکت در اختیار شما نیست. برای پاسخ ابتدا باید مالکیت عملیاتی تیکت را در کارتابل دریافت کنید.',
            ],

        'reply_assignment_invalid' =>
            [
                'danger',
                'تخصیص عملیاتی تیکت معتبر نیست. نقش پروژه یا عضویت تیم کارشناس باید توسط مدیر بررسی شود.',
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

<?php
$lifecycleRequesterStatusCode =
    trim(
        (string) (
            $ticket['status_code']
            ?? ''
        )
    );

$lifecycleRequesterCanUpdate =
    $lifecycleIsRequester
    && $lifecycleReference !== ''
    && in_array(
        $lifecycleRequesterStatusCode,
        [
            'new',
            'in_progress',
            'waiting_requester',
            'waiting_internal',
            'resolved',
        ],
        true
    );
?>

<?php
/*
 * TICKETING_ROUTING_EXCEPTION_FOUNDATION_V1
 *
 * Shared read-only routing-health foundation.
 * Recovery mutation is intentionally NOT implemented in this step.
 */
$routingExceptionPanel = null;

if (
    $lifecycleReference !== ''
    &&
    $lifecycleUserId > 0
) {
    try {
        $routingExceptionPanel =
            (
                new \App\Services\Ticketing\TicketRoutingExceptionService()
            )->panel(
                $lifecycleReference,
                $lifecycleUserId
            );
    } catch (\Throwable) {
        $routingExceptionPanel = null;
    }
}

$routingExceptionClassification =
    is_array(
        $routingExceptionPanel['classification']
        ?? null
    )
        ? $routingExceptionPanel['classification']
        : [];

$routingExceptionDefaultTopic =
    is_array(
        $routingExceptionPanel['default_topic']
        ?? null
    )
        ? $routingExceptionPanel['default_topic']
        : null;

$routingExceptionTopics =
    is_array(
        $routingExceptionPanel['selectable_topics']
        ?? null
    )
        ? $routingExceptionPanel['selectable_topics']
        : [];

$routingRecoveryNotice =
    trim(
        (string) (
            $_GET['routing_notice']
            ?? ''
        )
    );

$routingRecoveryIncident =
    trim(
        (string) (
            $_GET['error']
            ?? ''
        )
    );

$routingRecoveryNoticeMessages = [
    'routing_recovery_applied' =>
        'موضوع ثبت شد و تیکت با مسیر استاندارد سامانه مسیریابی شد.',
    'routing_recovery_invalid' =>
        'درخواست بازیابی مسیریابی معتبر نیست.',
    'routing_recovery_invalid_csrf' =>
        'اعتبار فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.',
    'routing_recovery_invalid_topic' =>
        'موضوع انتخاب‌شده برای این تیکت معتبر نیست.',
    'routing_recovery_not_eligible' =>
        'وضعیت تیکت تغییر کرده و دیگر واجد شرایط این بازیابی نیست.',
    'routing_recovery_no_route' =>
        'برای موضوع انتخاب‌شده قانون مسیریابی استاندارد معتبری پیدا نشد.',
    'routing_recovery_invalid_topology' =>
        'ساختار مسیر انتخاب‌شده کامل یا معتبر نیست.',
    'routing_recovery_no_eligible_assignee' =>
        'مسیر پیدا شد اما کارشناس واجد شرایط برای تخصیص خودکار موجود نیست.',
    'routing_recovery_forbidden' =>
        'اجازه انجام بازیابی مسیریابی را ندارید.',
    'routing_recovery_failed' =>
        'بازیابی مسیریابی انجام نشد.',
];
?>

<?php
/*
 * TICKETING_ROUTING_RECOVERY_V1_UI
 * A success notice is independent from the exception card because
 * the repaired ticket becomes healthy immediately after recovery.
 */
?>

<?php if (
    $routingRecoveryNotice !== ''
    && isset(
        $routingRecoveryNoticeMessages[
            $routingRecoveryNotice
        ]
    )
): ?>
    <section
        class="admin-section"
        data-ticketing-routing-recovery-notice
        hidden
    >
        <div
            class="<?= $routingRecoveryNotice === 'routing_recovery_applied'
                ? 'admin-alert admin-alert--success'
                : 'admin-alert admin-alert--danger' ?>"
            role="<?= $routingRecoveryNotice === 'routing_recovery_applied'
                ? 'status'
                : 'alert' ?>"
        >
            <?= $lifecycleH(
                (string) $routingRecoveryNoticeMessages[
                    $routingRecoveryNotice
                ]
            ) ?>

            <?php if (
                $routingRecoveryIncident !== ''
            ): ?>
                <div class="admin-muted">
                    کد پیگیری:
                    <?= $lifecycleH(
                        $routingRecoveryIncident
                    ) ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script data-ticketing-routing-recovery-notice-relocate>
    (() => {
        const moveRoutingRecoveryNotice = () => {
            const block = document.querySelector(
                '[data-ticketing-routing-recovery-notice]'
            );
            const operations = document.querySelector(
                '[data-ticketing-detail-panel="status"]'
            );

            const operationsTab = document.querySelector(
                '[data-ticketing-detail-tab="status"]'
            );

            if (!block || !operations) {
                return;
            }

            operations.prepend(block);
            block.hidden = false;

            /*
             * TICKETING_ROUTING_RECOVERY_ERROR_TAB_V1
             *
             * Recovery is submitted from Response & Operations.
             * After redirect, keep that same tab active so the
             * result/error is immediately visible to the user.
             */
            if (operationsTab) {
                operationsTab.click();
            }
        };

        if (document.readyState === 'complete') {
            moveRoutingRecoveryNotice();
        } else {
            window.addEventListener(
                'load',
                moveRoutingRecoveryNotice,
                {once: true}
            );
        }
    })();
    </script>
<?php endif; ?>

<?php if (
    is_array($routingExceptionPanel)
    &&
    !empty($routingExceptionPanel['found'])
    &&
    !empty($routingExceptionPanel['can_manage'])
    &&
    !empty($routingExceptionClassification['actionable'])
): ?>
    <section
        class="admin-section"
        data-ticketing-routing-exception
        data-ticketing-routing-exception-code="<?= $lifecycleH(
            (string) (
                $routingExceptionClassification['code']
                ?? ''
            )
        ) ?>"
        hidden
    >
        <div class="admin-section__header">
            <div>
                <h3>نیازمند مداخله مسیریابی</h3>
                <p class="admin-muted">
                    این وضعیت توسط کنترل سلامت عمومی مسیریابی شناسایی شده است.
                </p>
            </div>
        </div>

        <div class="admin-alert admin-alert--warning">
            <strong>
                <?= $lifecycleH(
                    (string) (
                        $routingExceptionClassification['title']
                        ?? 'اختلال مسیریابی'
                    )
                ) ?>
            </strong>

            <div>
                <?= $lifecycleH(
                    (string) (
                        $routingExceptionClassification['message']
                        ?? ''
                    )
                ) ?>
            </div>

            <?php if (
                is_array($routingExceptionDefaultTopic)
            ): ?>
                <div>
                    موضوع پیش‌فرض فعال این خدمت:
                    <strong>
                        <?= $lifecycleH(
                            (string) (
                                $routingExceptionDefaultTopic['title']
                                ?? ''
                            )
                        ) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <?php if (
                (
                    $routingExceptionClassification['code']
                    ?? ''
                ) === 'missing_topic'
                && $routingExceptionTopics !== []
            ): ?>
                <?php
                $routingRecoveryCsrf =
                    (
                        new \IPKF\Security\Csrf()
                    )->token();

                $routingDefaultTopicId =
                    is_array($routingExceptionDefaultTopic)
                        ? (int) (
                            $routingExceptionDefaultTopic['id']
                            ?? 0
                        )
                        : 0;
                ?>

                <form
                    method="post"
                    action="/admin/ticketing/tickets/<?= $lifecycleH(
                        rawurlencode($lifecycleReference)
                    ) ?>/recover-routing"
                    data-ticketing-routing-recovery-form
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= $lifecycleH(
                            $routingRecoveryCsrf
                        ) ?>"
                    >

                    <div class="admin-form-grid">
                        <label>
                            <span>موضوع پشتیبانی</span>

                            <select
                                name="support_topic_id"
                                required
                            >
                                <?php foreach (
                                    $routingExceptionTopics
                                    as $topic
                                ): ?>
                                    <?php
                                    $topicId = (int) (
                                        $topic['id']
                                        ?? 0
                                    );
                                    ?>

                                    <option
                                        value="<?= $lifecycleH(
                                            $topicId
                                        ) ?>"
                                        <?= $topicId ===
                                            $routingDefaultTopicId
                                                ? 'selected'
                                                : '' ?>
                                    >
                                        <?= $lifecycleH(
                                            (string) (
                                                $topic['title']
                                                ?? ''
                                            )
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <p class="admin-muted">
                        فقط موضوع را انتخاب کنید؛
                        قانون، صف، تیم و کارشناس توسط موتور استاندارد مسیریابی تعیین می‌شوند.
                    </p>

                    <div class="admin-form-actions">
                        <button
                            type="submit"
                            class="admin-button"
                        >
                            ثبت موضوع و مسیریابی
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="admin-muted">
                    این وضعیت از طریق این فرم قابل بازیابی خودکار نیست؛
                    ابتدا تنظیمات مسیریابی مربوط به علت خطا باید اصلاح شود.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script data-ticketing-routing-exception-relocate>
    (() => {
        const moveRoutingException = () => {
            const block =
                document.querySelector(
                    '[data-ticketing-routing-exception]'
                );

            const operations =
                document.querySelector(
                    '[data-ticketing-detail-panel="status"]'
                );

            if (!block || !operations) {
                return;
            }

            operations.prepend(block);
            block.hidden = false;
        };

        if (document.readyState === 'complete') {
            moveRoutingException();
        } else {
            window.addEventListener(
                'load',
                moveRoutingException,
                {once: true}
            );
        }
    })();
    </script>
<?php endif; ?>

<?php if (!empty($priorityGovernance['found'])): ?>
    <section
        class="admin-section ticketing-priority-governance"
        data-ticketing-priority-governance
    >
        <div class="admin-section__header">
            <div>
                <h3>اولویت تیکت</h3>
                <p class="admin-muted">
                    اولویت فعلی و سوابق اصلاح آن در این بخش نگهداری می‌شود.
                </p>
            </div>

            <strong class="ticketing-priority-current">
                <?= $lifecycleH(
                    $priorityGovernanceCurrentTitle
                    !== ''
                        ? $priorityGovernanceCurrentTitle
                        : '—'
                ) ?>
            </strong>
        </div>

        <?php if (
            $priorityNotice !== ''
            && isset($priorityNoticeMap[$priorityNotice])
        ): ?>
            <?php
            $priorityNoticeRow =
                $priorityNoticeMap[$priorityNotice];
            ?>
            <div
                class="admin-alert admin-alert--<?= $lifecycleH(
                    (string) $priorityNoticeRow[0]
                ) ?>"
                role="status"
            >
                <?= $lifecycleH(
                    (string) $priorityNoticeRow[1]
                ) ?>
            </div>
        <?php endif; ?>

        <?php if ($priorityGovernanceCanChange): ?>
            <?php
            $priorityCsrf =
                (new \IPKF\Security\Csrf())->token();
            ?>

            <form
                method="post"
                action="<?= $lifecycleH(
                    '/admin/ticketing/tickets/'
                    . rawurlencode($lifecycleReference)
                    . '/priority'
                ) ?>"
                class="ticketing-priority-form"
                data-ticketing-priority-form
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= $lifecycleH($priorityCsrf) ?>"
                >

                <label>
                    <span>اولویت جدید</span>
                    <select
                        name="priority_code"
                        required
                    >
                        <?php foreach (
                            $priorityGovernancePriorities
                            as $priorityOption
                        ): ?>
                            <?php
                            $priorityOptionCode = trim(
                                (string) (
                                    $priorityOption['code']
                                    ?? ''
                                )
                            );
                            ?>
                            <option
                                value="<?= $lifecycleH(
                                    $priorityOptionCode
                                ) ?>"
                                <?= $priorityOptionCode ===
                                    $priorityGovernanceCurrentCode
                                        ? 'selected'
                                        : '' ?>
                            >
                                <?= $lifecycleH(
                                    (string) (
                                        $priorityOption['title']
                                        ?? $priorityOptionCode
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="ticketing-priority-form__reason">
                    <span>دلیل تغییر</span>
                    <textarea
                        name="priority_reason"
                        rows="3"
                        minlength="3"
                        maxlength="1000"
                        required
                        placeholder="دلیل اصلاح اولویت را ثبت کنید..."
                    ></textarea>
                </label>

                <div class="admin-form-actions">
                    <button
                        class="admin-button"
                        type="submit"
                    >
                        ثبت تغییر اولویت
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($priorityGovernanceHistory !== []): ?>
            <div class="ticketing-priority-history">
                <h4>تاریخچه تغییر اولویت</h4>

                <?php foreach (
                    $priorityGovernanceHistory
                    as $priorityHistoryRow
                ): ?>
                    <article
                        class="ticketing-priority-history__item"
                    >
                        <div>
                            <strong>
                                <?= $lifecycleH(
                                    (string) (
                                        $priorityHistoryRow[
                                            'old_priority_title'
                                        ]
                                        ?? $priorityHistoryRow[
                                            'old_priority_code'
                                        ]
                                        ?? '—'
                                    )
                                ) ?>
                                ←
                                <?= $lifecycleH(
                                    (string) (
                                        $priorityHistoryRow[
                                            'new_priority_title'
                                        ]
                                        ?? $priorityHistoryRow[
                                            'new_priority_code'
                                        ]
                                        ?? '—'
                                    )
                                ) ?>
                            </strong>

                            <small>
                                <?= $lifecycleH(
                                    (string) (
                                        $priorityHistoryRow[
                                            'actor_display_name'
                                        ]
                                        ?? $priorityHistoryRow[
                                            'actor_user_reference'
                                        ]
                                        ?? '—'
                                    )
                                ) ?>
                                ·
                                <?= $lifecycleH(
                                    \App\Support\AdminFormat::jalaliDateTime(
                                        (string) (
                                            $priorityHistoryRow[
                                                'occurred_at'
                                            ]
                                            ?? ''
                                        )
                                    )
                                    ?: '—'
                                ) ?>
                            </small>
                        </div>

                        <p>
                            <?= $lifecycleH(
                                (string) (
                                    $priorityHistoryRow['reason']
                                    ?? '—'
                                )
                            ) ?>
                        </p>

                        <?php if (!empty(
                            $priorityHistoryRow[
                                'sla_recalculation_required'
                            ]
                        )): ?>
                            <span class="admin-pill">
                                نیازمند بازبینی SLA
                            </span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<script data-ticketing-priority-relocate>
(() => {
    /*
     * TICKETING_DETAIL_TAB_SCOPE_RECONCILIATION_V2
     *
     * Canonical ownership:
     *
     * Details:
     *   - four ticket metrics
     *   - canonical generated routing / ownership detail
     *
     * Response and Operations:
     *   - active reply
     *   - priority correction
     *   - lifecycle actions
     *
     * History:
     *   - unified timeline
     *   - priority-change history
     */

    const workspace =
        document.querySelector(
            '[data-ticketing-detail-workspace]'
        );

    if (!workspace) {
        return;
    }

    const priorityPanel =
        document.querySelector(
            '[data-ticketing-priority-governance]'
        );

    const detailMetrics =
        document.querySelector(
            '.ticketing-detail-metrics'
        );

    /*
     * Capture the old server-rendered SECTION summary before
     * the legacy runtime can relocate it.
     *
     * The Details host later builds the canonical equivalent,
     * therefore these captured sections are browser duplicates.
     */
    let legacySummarySections = [];

    const workspaceParent =
        workspace.parentElement;

    if (workspaceParent) {

        const siblings =
            Array.from(
                workspaceParent.children
            );

        const workspaceIndex =
            siblings.indexOf(
                workspace
            );

        if (workspaceIndex >= 0) {

            legacySummarySections =
                siblings
                    .slice(
                        0,
                        workspaceIndex
                    )
                    .filter(
                        (element) =>
                            element.tagName ===
                            'SECTION'
                    );
        }
    }

    const reconcile =
        () => {

            const tabs =
                Array.from(
                    workspace.querySelectorAll(
                        '[data-ticketing-detail-tab]'
                    )
                );

            const panels =
                Array.from(
                    workspace.querySelectorAll(
                        '[data-ticketing-detail-panel]'
                    )
                );

            const normalizedText =
                (element) =>
                    (
                        element
                        && element.textContent
                            ? element.textContent
                            : ''
                    )
                        .replace(
                            /\s+/gu,
                            ' '
                        )
                        .trim();

            const panelByLabel =
                (label) => {

                    const tab =
                        tabs.find(
                            (candidate) =>
                                normalizedText(
                                    candidate
                                ) === label
                        );

                    if (!tab) {
                        return null;
                    }

                    const key =
                        tab.getAttribute(
                            'data-ticketing-detail-tab'
                        );

                    if (!key) {
                        return null;
                    }

                    return (
                        panels.find(
                            (candidate) =>
                                candidate.getAttribute(
                                    'data-ticketing-detail-panel'
                                ) === key
                        )
                        || null
                    );
                };

            const detailsPanel =
                panelByLabel(
                    'جزئیات'
                );

            const operationsPanel =
                panelByLabel(
                    'پاسخ و عملیات'
                );

            const historyPanel =
                panelByLabel(
                    'تاریخچه'
                );

            /*
             * Lifecycle markup occurs later in the HTML than
             * this script; resolve it only after parsing.
             */
            const lifecycleActions =
                document.querySelector(
                    '[data-ticketing-lifecycle-actions]'
                );

            /*
             * TICKETING_REAL_TICKET_DETAILS
             *
             * The server-rendered summary is the authoritative
             * ticket detail presentation. It already contains
             * project, subject, current stage/team and current
             * assignee.
             *
             * Do not replace that useful information with the
             * old routing-only generated placeholder.
             */
            const generatedDetailsHost =
                workspace.querySelector(
                    '[data-ticketing-details-host]'
                );

            if (generatedDetailsHost) {
                generatedDetailsHost.remove();
            }

            if (detailsPanel) {

                detailsPanel.classList.add(
                    'ticketing-details-panel'
                );

                const detailsHeading =
                    document.createElement(
                        'header'
                    );

                detailsHeading.className =
                    'ticketing-details-heading';

                const detailsTitle =
                    document.createElement(
                        'h3'
                    );

                detailsTitle.textContent =
                    'مشخصات تیکت';

                const detailsDescription =
                    document.createElement(
                        'p'
                    );

                detailsDescription.textContent =
                    'وضعیت، اولویت، دسته، زمان ثبت، پروژه، موضوع و اطلاعات جاری رسیدگی';

                detailsHeading.appendChild(
                    detailsTitle
                );

                detailsHeading.appendChild(
                    detailsDescription
                );

                detailsPanel.append(
                    detailsHeading
                );

                if (detailMetrics) {

                    detailMetrics.classList.add(
                        'ticketing-details-metrics-block'
                    );

                    detailsPanel.append(
                        detailMetrics
                    );
                }

                legacySummarySections.forEach(
                    (section) => {

                        section.classList.add(
                            'ticketing-details-summary-block'
                        );

                        detailsPanel.append(
                            section
                        );
                    }
                );
            }

            /*
             * Priority form/control belongs to Operations.
             * Its audit history is detached to History.
             */
            const priorityHistory =
                priorityPanel
                    ? priorityPanel.querySelector(
                        '.ticketing-priority-history'
                    )
                    : null;

            if (
                operationsPanel
                && priorityPanel
            ) {
                operationsPanel.append(
                    priorityPanel
                );
            }

            /*
             * Lifecycle actions belong only to Operations.
             */
            if (
                operationsPanel
                && lifecycleActions
            ) {
                operationsPanel.append(
                    lifecycleActions
                );
            }

            /*
             * Priority audit belongs only to History.
             */
            if (
                historyPanel
                && priorityHistory
            ) {
                historyPanel.append(
                    priorityHistory
                );
            }

            workspace.setAttribute(
                'data-ticketing-tab-scope-reconciled',
                'v2'
            );
        };

    if (
        document.readyState ===
        'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            reconcile,
            {
                once: true,
            }
        );

        return;
    }

    reconcile();
})();
</script>

<?php if ($lifecycleRequesterCanUpdate): ?>
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
                <h3>افزودن توضیح</h3>

                <p class="admin-muted">
                    تا پیش از بسته‌شدن یا لغو تیکت می‌توانید توضیح یا فایل تکمیلی اضافه کنید. مسیر ارجاع و کارشناس فعلی حفظ می‌شود.
                </p>

                <?php if (
                    $lifecycleRequesterStatusCode
                    === 'resolved'
                ): ?>
                    <!-- ticketing_requester_actions_r4a2r1 -->
                    <div
                        class="admin-alert admin-alert--info ticketing-requester-reopen-note"
                        data-ticketing-requester-reopen-note
                    >
                        این تیکت حل‌شده است. با ثبت توضیح جدید،
                        تیکت دوباره به وضعیت «در حال بررسی»
                        برمی‌گردد و رسیدگی ادامه پیدا می‌کند.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <form
            method="post"
            enctype="multipart/form-data"
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

            <input
                type="hidden"
                name="intent"
                value="update"
            >

            <label>
                <span>توضیح تکمیلی</span>

                <textarea
                    name="body"
                    rows="5"
                    maxlength="20000"
                    required
                    placeholder="توضیح خود را وارد کنید..."
                ></textarea>
            </label>

            <label>
                <span>پیوست</span>

                <input
                    type="file"
                    name="attachments[]"
                    multiple
                >

                <small class="admin-muted">
                    فایل‌های انتخاب‌شده با همان کنترل امن پیوست تیکت ذخیره می‌شوند.
                </small>
            </label>

            <div class="admin-form-actions">
                <button
                    class="admin-button"
                    type="submit"
                >
                    ثبت توضیح
                </button>
            </div>
        </form>

        <?php if (
            $lifecycleRequesterStatusCode
            !== 'resolved'
        ): ?>
            <form
                method="post"
                action="<?= $lifecycleH(
                    '/admin/ticketing/tickets/'
                    . rawurlencode(
                        $lifecycleReference
                    )
                    . '/requester-reply'
                ) ?>"
                data-ticketing-requester-resolve-form
                onsubmit="return confirm('مشکل شما حل شده و تیکت به وضعیت حل‌شده منتقل شود؟');"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= $lifecycleH(
                        $requesterReplyCsrf
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="intent"
                    value="resolve"
                >

                <div class="admin-form-actions">
                    <button
                        class="admin-button admin-button--soft"
                        type="submit"
                    >
                        مشکلم حل شد
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>


<?php if (
    $lifecycleCanReply
    &&
    !$lifecycleIsRequester
    &&
    !$lifecycleClosed
    &&
    !$lifecycleStaffOwnsReply
    &&
    $lifecycleReference !== ''
): ?>

    <section
        class="admin-section ticketing-staff-reply-ownership"
        data-ticketing-staff-reply-ownership
    >

        <?php if (
            $lifecycleStaffReplyState ===
            'reply_waiting_requester'
        ): ?>

            <div class="admin-alert admin-alert--info">
                تیکت در انتظار پاسخ درخواست‌کننده است. پس از پاسخ درخواست‌کننده، رسیدگی توسط کارشناس فعلی ادامه پیدا می‌کند.
            </div>

        <?php elseif (
            $lifecycleStaffReplyState ===
            'reply_assignment_invalid'
        ): ?>

            <div class="admin-alert admin-alert--danger">
                تخصیص عملیاتی این تیکت معتبر نیست. نقش پروژه یا عضویت تیم کارشناس باید بررسی شود.
                تا اصلاح تخصیص، پاسخ کارشناس مجاز نیست.
            </div>

        <?php else: ?>

            <div class="admin-alert admin-alert--warning">
                این تیکت در اختیار شما نیست.
                برای پاسخ ابتدا باید آن را در کارتابل پشتیبانی در اختیار بگیرید.

                <a
                    class="admin-button admin-button--soft"
                    href="/admin/ticketing/staff"
                >
                    رفتن به کارتابل پشتیبانی
                </a>
            </div>

        <?php endif; ?>

    </section>

<?php endif; ?>

<?php if (
    $lifecycleStaffOwnsReply
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
            enctype="multipart/form-data"
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

            <label>
                <span>
                    پیوست‌ها (اختیاری)
                </span>

                <input
                    type="file"
                    name="attachments[]"
                    multiple
                >

                <small class="admin-muted">
                    حداکثر ۵ فایل؛ هر فایل حداکثر ۱۰ مگابایت
                    و مجموع فایل‌ها حداکثر ۲۵ مگابایت.
                </small>
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
    !empty(
        $lifecycleTransitionCapabilities[
            'can_resolve'
        ]
    )
    ||
    !empty(
        $lifecycleTransitionCapabilities[
            'can_close'
        ]
    )
    ||
    !empty(
        $lifecycleTransitionCapabilities[
            'can_reopen'
        ]
    )
): ?>
    <?php
    /*
     * Lifecycle transition CSRF is deliberately independent
     * from the staff/requester reply composers.
     *
     * A resolved ticket may expose Close while no reply form
     * exists on the page.
     */
    $lifecycleActionCsrf =
        (
            new \IPKF\Security\Csrf()
        )->token();
    ?>


    <section
        class="admin-section ticketing-lifecycle-actions"
        data-ticketing-lifecycle-actions
    >
        <div class="admin-section__header">
            <div>
                <h3>اقدامات تیکت</h3>
                <p class="admin-muted">
                    اقدامات نهایی چرخه تیکت بر اساس وضعیت، مالک فعلی و نقش پروژه نمایش داده می‌شوند.
                </p>
            </div>
        </div>

        <div class="ticketing-lifecycle-actions__grid">

            <?php if (
                !empty(
                    $lifecycleTransitionCapabilities[
                        'can_resolve'
                    ]
                )
            ): ?>

                <form
                    method="post"
                    action="<?= $lifecycleH(
                        '/admin/ticketing/tickets/'
                        . rawurlencode(
                            $lifecycleReference
                        )
                        . '/resolve'
                    ) ?>"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= $lifecycleH(
                            $lifecycleActionCsrf
                        ) ?>"
                    >

                    <button
                        class="admin-button ticketing-lifecycle-action--resolve"
                        type="submit"
                    >
                        ثبت به‌عنوان حل‌شده
                    </button>
                </form>

            <?php endif; ?>


            <?php if (
                !empty(
                    $lifecycleTransitionCapabilities[
                        'can_close'
                    ]
                )
            ): ?>

                <form
                    method="post"
                    action="<?= $lifecycleH(
                        '/admin/ticketing/tickets/'
                        . rawurlencode(
                            $lifecycleReference
                        )
                        . '/close'
                    ) ?>"
                    onsubmit="return confirm('تیکت بسته شود؟');"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= $lifecycleH(
                            $lifecycleActionCsrf
                        ) ?>"
                    >

                    <button
                        class="admin-button admin-button--soft ticketing-lifecycle-action--close"
                        type="submit"
                    >
                        بستن تیکت
                    </button>
                </form>

            <?php endif; ?>


            <?php if (
                !empty(
                    $lifecycleTransitionCapabilities[
                        'can_reopen'
                    ]
                )
            ): ?>

                <form
                    method="post"
                    action="<?= $lifecycleH(
                        '/admin/ticketing/tickets/'
                        . rawurlencode(
                            $lifecycleReference
                        )
                        . '/reopen'
                    ) ?>"
                    onsubmit="return confirm('تیکت بازگشایی شود و رسیدگی ادامه پیدا کند؟');"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= $lifecycleH(
                            $lifecycleActionCsrf
                        ) ?>"
                    >

                    <button
                        class="admin-button ticketing-lifecycle-action--reopen"
                        type="submit"
                    >
                        بازگشایی تیکت
                    </button>
                </form>

            <?php endif; ?>

        </div>
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
     * TICKETING_LEGACY_SUMMARY_RELOCATION_DISABLED
     *
     * Static summary ownership is handled by the V2
     * reconciliation controller.
     *
     * The legacy runtime now moves only the active reply into
     * the Response and Operations panel.
     */
    if (
        statusPanel
        && activeReply
    ) {
        statusPanel.appendChild(
            activeReply
        );
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
        'conversation'
    );
})();
</script>

<script>
(() => {
    const conversationPanel =
        document.querySelector(
            '[data-ticketing-detail-panel="conversation"]'
        );

    const initializeTicketMessages = () => {
        /*
         * The conversation tab is hidden by default.
         * Never measure message height while its panel is hidden.
         */
        if (
            conversationPanel
            && conversationPanel.hidden
        ) {
            return;
        }

        const bodies =
            Array.from(
                document.querySelectorAll(
                    '[data-ticketing-message-body]'
                )
            );

        bodies.forEach(
            (body) => {
                const card =
                    body.closest(
                        '.ticketing-message-bubble'
                    );

                if (!card) {
                    return;
                }

                const button =
                    card.querySelector(
                        '[data-ticketing-message-toggle]'
                    );

                if (!button) {
                    return;
                }

                /*
                 * Return to natural layout before measuring.
                 */
                body.classList.remove(
                    'is-collapsed',
                    'is-expanded'
                );

                const computed =
                    window.getComputedStyle(
                        body
                    );

                let lineHeight =
                    parseFloat(
                        computed.lineHeight
                    );

                if (
                    !Number.isFinite(lineHeight)
                    ||
                    lineHeight <= 0
                ) {
                    lineHeight =
                        (
                            parseFloat(
                                computed.fontSize
                            )
                            || 13
                        )
                        * 1.65;
                }

                const collapsedHeight =
                    Math.ceil(
                        lineHeight * 6
                    );

                const naturalHeight =
                    body.scrollHeight;

                const overflowing =
                    naturalHeight
                    > collapsedHeight + 4;

                body.style.setProperty(
                    '--ticketing-message-collapsed-height',
                    collapsedHeight + 'px'
                );

                button.hidden =
                    !overflowing;

                if (!overflowing) {
                    return;
                }

                body.classList.add(
                    'is-collapsed'
                );

                /*
                 * Bind toggle only once, even if the tab
                 * is opened several times.
                 */
                if (
                    button.dataset
                        .ticketingMessageToggleBound
                    === '1'
                ) {
                    return;
                }

                button.dataset
                    .ticketingMessageToggleBound =
                    '1';

                button.addEventListener(
                    'click',
                    () => {
                        const expanded =
                            body.classList.contains(
                                'is-expanded'
                            );

                        if (expanded) {
                            body.classList.remove(
                                'is-expanded'
                            );

                            body.classList.add(
                                'is-collapsed'
                            );

                            button.textContent =
                                'مشاهده بیشتر';

                            button.setAttribute(
                                'aria-expanded',
                                'false'
                            );

                            return;
                        }

                        body.classList.remove(
                            'is-collapsed'
                        );

                        body.classList.add(
                            'is-expanded'
                        );

                        button.textContent =
                            'مشاهده کمتر';

                        button.setAttribute(
                            'aria-expanded',
                            'true'
                        );
                    }
                );
            }
        );
    };


    const scheduleInitialization = () => {
        /*
         * Two RAFs ensure the newly visible tab
         * has completed layout before measurement.
         */
        window.requestAnimationFrame(
            () => {
                window.requestAnimationFrame(
                    initializeTicketMessages
                );
            }
        );
    };


    if (conversationPanel) {
        /*
         * This is the key fix:
         * recalculate immediately when the tab panel
         * transitions from hidden to visible.
         */
        const observer =
            new MutationObserver(
                () => {
                    if (
                        !conversationPanel.hidden
                    ) {
                        scheduleInitialization();
                    }
                }
            );

        observer.observe(
            conversationPanel,
            {
                attributes: true,
                attributeFilter: [
                    'hidden',
                ],
            }
        );
    }


    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            scheduleInitialization,
            {
                once: true,
            }
        );
    } else {
        scheduleInitialization();
    }
})();
</script>


<script>
(() => {
    const statusTitles = {
        new: 'جدید',
        in_progress: 'در حال بررسی',
        waiting_requester:
            'در انتظار پاسخ درخواست‌کننده',
        waiting_internal:
            'در انتظار اقدام داخلی',
        resolved: 'حل‌شده',
        closed: 'بسته‌شده',
        cancelled: 'لغوشده',
    };

    const statusTitle = (code) => {
        const value =
            String(code || '').trim();

        return (
            statusTitles[value]
            || value
            || '—'
        );
    };

    const normalizedTime = (value) => {
        return String(value || '')
            .trim()
            .replace(' ', 'T');
    };


    const buildUnifiedTimeline = () => {
        const timeline =
            document.querySelector(
                '[data-ticketing-unified-timeline]'
            );

        const conversation =
            document.querySelector(
                '[data-ticketing-detail-panel="conversation"]'
            );

        if (!timeline || !conversation) {
            return;
        }

        const items = [];

        Array.from(
            conversation.querySelectorAll(
                '.ticketing-message-bubble'
                + '[data-ticketing-timeline-kind="message"]'
            )
        ).forEach(
            (node, index) => {
                items.push({
                    kind: 'message',
                    node,
                    time:
                        normalizedTime(
                            node.dataset
                                .ticketingTimelineTime
                        ),
                    index,
                });
            }
        );

        Array.from(
            document.querySelectorAll(
                '[data-ticketing-event-source]'
            )
        ).forEach(
            (row, index) => {
                const code =
                    String(
                        row.dataset
                            .ticketingEventCode
                        || ''
                    ).trim();

                const previous =
                    String(
                        row.dataset
                            .ticketingEventPrevious
                        || ''
                    ).trim();

                const result =
                    String(
                        row.dataset
                            .ticketingEventResult
                        || ''
                    ).trim();

                /*
                 * Normal requester update is already shown
                 * by its Message Card. Only show its event
                 * when lifecycle changed.
                 */
                if (
                    code ===
                        'ticket_requester_updated'
                    && (
                        previous === result
                        || result === ''
                    )
                ) {
                    return;
                }

                const cells =
                    Array.from(
                        row.querySelectorAll('td')
                    );

                const label =
                    (
                        cells[0]
                        ?.textContent
                        || code
                    ).trim();

                const actor =
                    String(
                        row.dataset
                            .ticketingEventActor
                        || (
                            cells[1]
                            ?.textContent
                            || ''
                        )
                    ).trim();

                const renderedTime =
                    (
                        cells[3]
                        ?.textContent
                        || ''
                    ).trim();

                let description = label;

                if (
                    code ===
                    'ticket_requester_resolved'
                ) {
                    description =
                        'درخواست‌کننده مشکل را '
                        + 'حل‌شده اعلام کرد.';

                } else if (
                    previous !== ''
                    && result !== ''
                    && previous !== result
                ) {
                    description =
                        label
                        + '؛ وضعیت از «'
                        + statusTitle(previous)
                        + '» به «'
                        + statusTitle(result)
                        + '» تغییر کرد.';
                }

                const event =
                    document.createElement(
                        'div'
                    );

                event.className =
                    'ticketing-timeline-event';

                event.dataset
                    .ticketingTimelineEvent = '';

                const dot =
                    document.createElement(
                        'span'
                    );

                dot.className =
                    'ticketing-timeline-event__dot';

                dot.setAttribute(
                    'aria-hidden',
                    'true'
                );

                const content =
                    document.createElement(
                        'div'
                    );

                content.className =
                    'ticketing-timeline-event__content';

                const message =
                    document.createElement(
                        'span'
                    );

                message.className =
                    'ticketing-timeline-event__text';

                message.textContent =
                    description;

                content.appendChild(
                    message
                );

                const metaParts = [];

                if (
                    actor !== ''
                    && actor !== '—'
                ) {
                    metaParts.push(actor);
                }

                if (renderedTime !== '') {
                    metaParts.push(
                        renderedTime
                    );
                }

                if (metaParts.length > 0) {
                    const meta =
                        document.createElement(
                            'small'
                        );

                    meta.className =
                        'ticketing-timeline-event__meta';

                    meta.textContent =
                        metaParts.join(' · ');

                    content.appendChild(
                        meta
                    );
                }

                event.appendChild(dot);
                event.appendChild(content);

                items.push({
                    kind: 'event',
                    node: event,
                    time:
                        normalizedTime(
                            row.dataset
                                .ticketingTimelineTime
                        ),
                    index:
                        index + 100000,
                });
            }
        );

        items.sort(
            (left, right) => {
                const byTime =
                    left.time.localeCompare(
                        right.time
                    );

                if (byTime !== 0) {
                    return byTime;
                }

                if (
                    left.kind
                    !== right.kind
                ) {
                    return (
                        left.kind === 'message'
                            ? -1
                            : 1
                    );
                }

                return (
                    left.index
                    - right.index
                );
            }
        );

        timeline.replaceChildren();

        const appendItem = (
            item,
            destination = timeline
        ) => {
            const wrapper =
                document.createElement(
                    'div'
                );

            wrapper.className =
                'ticketing-timeline-item '
                + (
                    item.kind === 'message'
                        ? 'ticketing-timeline-item--message'
                        : 'ticketing-timeline-item--event'
                );

            wrapper.appendChild(
                item.node
            );

            destination.appendChild(
                wrapper
            );
        };


        const appendEventRun = (run) => {
            /*
             * Short runs remain fully visible.
             */
            if (run.length <= 6) {
                run.forEach(
                    (item) => {
                        appendItem(item);
                    }
                );

                return;
            }

            /*
             * Preserve the complete audit history while
             * avoiding a wall of repetitive system lines.
             * First two and last two stay visible.
             */
            run.slice(0, 2).forEach(
                (item) => {
                    appendItem(item);
                }
            );

            const middle =
                run.slice(
                    2,
                    run.length - 2
                );

            const disclosure =
                document.createElement(
                    'details'
                );

            disclosure.className =
                'ticketing-timeline-event-group';

            disclosure.dataset
                .ticketingTimelineEventGroup = '';

            const summary =
                document.createElement(
                    'summary'
                );

            summary.className =
                'ticketing-timeline-event-group__summary';

            summary.textContent =
                'نمایش '
                + new Intl.NumberFormat(
                    'fa-IR'
                ).format(
                    middle.length
                )
                + ' رویداد میانی';

            const body =
                document.createElement(
                    'div'
                );

            body.className =
                'ticketing-timeline-event-group__body';

            middle.forEach(
                (item) => {
                    appendItem(
                        item,
                        body
                    );
                }
            );

            disclosure.appendChild(
                summary
            );

            disclosure.appendChild(
                body
            );

            timeline.appendChild(
                disclosure
            );

            run.slice(-2).forEach(
                (item) => {
                    appendItem(item);
                }
            );
        };


        let cursor = 0;

        while (cursor < items.length) {
            const item =
                items[cursor];

            if (item.kind !== 'event') {
                appendItem(item);
                cursor += 1;
                continue;
            }

            const run = [];

            while (
                cursor < items.length
                && items[cursor].kind === 'event'
            ) {
                run.push(
                    items[cursor]
                );

                cursor += 1;
            }

            appendEventRun(run);
        }
    };


    const buildDetails = () => {
        const host =
            document.querySelector(
                '[data-ticketing-details-host]'
            );

        if (!host) {
            return;
        }

        host.replaceChildren();

        const stack =
            document.createElement(
                'div'
            );

        stack.className =
            'ticketing-details-stack';

        const header =
            document.createElement(
                'header'
            );

        header.className =
            'ticketing-details-heading';

        const title =
            document.createElement('h3');

        title.textContent =
            'جزئیات تیکت';

        const description =
            document.createElement('p');

        description.textContent =
            'پروژه، موضوع، مرحله، تیم و مسئول جاری رسیدگی';

        header.appendChild(title);
        header.appendChild(description);
        stack.appendChild(header);

        /*
         * ticketing_timeline_polish_r4a2r4
         *
         * Do not repeat the four summary metrics already
         * visible above the tabs. Details contains only
         * routing / operational context.
         */
        const route =
            document.querySelector(
                '.ticketing-route-summary'
            );

        if (route) {
            const clone =
                route.cloneNode(true);

            clone.classList.add(
                'ticketing-details-route'
            );

            stack.appendChild(clone);

        } else {
            const empty =
                document.createElement(
                    'div'
                );

            empty.className =
                'admin-empty-state';

            empty.textContent =
                'اطلاعات تکمیلی مسیر رسیدگی ثبت نشده است.';

            stack.appendChild(empty);
        }

        host.appendChild(stack);
    };


    const localizeFilePickers = () => {
        const number =
            new Intl.NumberFormat(
                'fa-IR'
            );

        document
            .querySelectorAll(
                'input[type="file"]'
                + '[name="attachments[]"]'
            )
            .forEach(
                (input) => {
                    if (
                        input.dataset
                            .ticketingFilePickerReady
                        === '1'
                    ) {
                        return;
                    }

                    input.dataset
                        .ticketingFilePickerReady =
                        '1';

                    const wrapper =
                        document.createElement(
                            'div'
                        );

                    wrapper.className =
                        'ticketing-file-picker';

                    wrapper.dataset
                        .ticketingFilePicker = '';

                    input.parentNode
                        .insertBefore(
                            wrapper,
                            input
                        );

                    wrapper.appendChild(
                        input
                    );

                    const control =
                        document.createElement(
                            'div'
                        );

                    control.className =
                        'ticketing-file-picker-control';

                    const button =
                        document.createElement(
                            'button'
                        );

                    button.type = 'button';

                    button.className =
                        'admin-button '
                        + 'admin-button--soft '
                        + 'admin-button--compact';

                    button.textContent =
                        'انتخاب فایل';

                    const state =
                        document.createElement(
                            'span'
                        );

                    state.className =
                        'ticketing-file-picker-state';

                    state.textContent =
                        'فایلی انتخاب نشده است';

                    control.appendChild(
                        button
                    );

                    control.appendChild(
                        state
                    );

                    wrapper.appendChild(
                        control
                    );

                    button.addEventListener(
                        'click',
                        () => input.click()
                    );

                    input.addEventListener(
                        'change',
                        () => {
                            const count =
                                input.files
                                    ?.length
                                || 0;

                            if (count === 0) {
                                state.textContent =
                                    'فایلی انتخاب نشده است';

                                return;
                            }

                            if (count === 1) {
                                state.textContent =
                                    input.files[0]
                                        ?.name
                                    || 'یک فایل انتخاب شد';

                                return;
                            }

                            state.textContent =
                                number.format(
                                    count
                                )
                                + ' فایل انتخاب شد';
                        }
                    );
                }
            );
    };


    const initialize = () => {
        buildUnifiedTimeline();
        buildDetails();
        localizeFilePickers();
    };

    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            initialize,
            {
                once: true,
            }
        );
    } else {
        initialize();
    }
})();
</script>

<?php
$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';

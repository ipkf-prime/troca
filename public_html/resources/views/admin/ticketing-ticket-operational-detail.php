<?php

declare(strict_types=1);


/*
 * TICKETING_OPERATIONAL_DETAIL_HISTORY_T3C1_VIEW
 *
 * Staff-only operational presentation.
 *
 * Requester Detail never receives assignments / SLA datasets from
 * TicketService::detailForUser(), therefore this partial renders
 * nothing for the requester-only path.
 */

$t3c1Detail =
    is_array(
        $detail
        ?? null
    )
        ? $detail
        : [];


$t3c1HasOperationalDataset =
    array_key_exists(
        'assignments',
        $t3c1Detail
    )
    &&
    array_key_exists(
        'sla_state',
        $t3c1Detail
    )
    &&
    array_key_exists(
        'sla_events',
        $t3c1Detail
    );


if (!$t3c1HasOperationalDataset) {
    return;
}


$t3c1Ticket =
    is_array(
        $t3c1Detail['ticket']
        ?? null
    )
        ? $t3c1Detail['ticket']
        : [];


$t3c1Reference =
    trim(
        (string) (
            $t3c1Ticket[
                'public_reference'
            ]
            ?? ''
        )
    );


$t3c1UserId =
    (int) (
        $context['user_id']
        ?? 0
    );


if (
    $t3c1Reference === ''
    ||
    $t3c1UserId < 1
) {
    return;
}


/*
 * Re-evaluate visibility through the canonical staff cartable boundary.
 * Unrestricted TicketService::detail() alone is never enough to expose
 * operational controls.
 */
try {

    $t3c1StaffContext =
        (
            new \App\Services\Ticketing\TicketStaffOperationsService()
        )->detailContext(
            $t3c1Reference,
            $t3c1UserId
        );

} catch (\Throwable) {

    $t3c1StaffContext = [
        'visible' => false,
        'ticket' => [],
        'actions' => [],
    ];
}


if (
    empty(
        $t3c1StaffContext[
            'visible'
        ]
    )
) {
    return;
}


$t3c1Assignments =
    is_array(
        $t3c1Detail[
            'assignments'
        ]
        ?? null
    )
        ? $t3c1Detail[
            'assignments'
        ]
        : [];


$t3c1SlaState =
    is_array(
        $t3c1Detail[
            'sla_state'
        ]
        ?? null
    )
        ? $t3c1Detail[
            'sla_state'
        ]
        : null;


$t3c1SlaEvents =
    is_array(
        $t3c1Detail[
            'sla_events'
        ]
        ?? null
    )
        ? $t3c1Detail[
            'sla_events'
        ]
        : [];


$t3c1TicketEvents =
    is_array(
        $t3c1Detail[
            'events'
        ]
        ?? null
    )
        ? $t3c1Detail[
            'events'
        ]
        : [];


$t3c1Actions =
    is_array(
        $t3c1StaffContext[
            'actions'
        ]
        ?? null
    )
        ? $t3c1StaffContext[
            'actions'
        ]
        : [];


$t3c1TransferTargets =
    is_array(
        $t3c1Actions[
            'transfer_targets'
        ]
        ?? null
    )
        ? $t3c1Actions[
            'transfer_targets'
        ]
        : [];


$t3c1StatusCode =
    strtolower(
        trim(
            (string) (
                $t3c1Ticket[
                    'status_code'
                ]
                ?? ''
            )
        )
    );


$t3c1TicketClosed =
    !empty(
        $t3c1Ticket[
            'is_closed'
        ]
        ?? false
    )
    ||
    in_array(
        $t3c1StatusCode,
        [
            'resolved',
            'closed',
            'cancelled',
        ],
        true
    );


$t3c1CanTakeover =
    !$t3c1TicketClosed
    &&
    !empty(
        $t3c1Actions[
            'can_takeover'
        ]
    );


$t3c1CanTransfer =
    !$t3c1TicketClosed
    &&
    !empty(
        $t3c1Actions[
            'can_transfer'
        ]
    )
    &&
    $t3c1TransferTargets !== [];


$t3c1CanEscalate =
    !$t3c1TicketClosed
    &&
    !empty(
        $t3c1Actions[
            'can_escalate'
        ]
    );


$t3c1HasOperations =
    $t3c1CanTakeover
    ||
    $t3c1CanTransfer
    ||
    $t3c1CanEscalate;


$t3c1Csrf = '';

if ($t3c1HasOperations) {
    try {
        $t3c1Csrf =
            (
                new \IPKF\Security\Csrf()
            )->token();

    } catch (\Throwable) {
        $t3c1Csrf = '';
    }
}


$t3c1SlaStateTitles = [
    'active' =>
        'فعال',

    'paused' =>
        'متوقف',

    'breached' =>
        'نقض‌شده',

    'completed' =>
        'تکمیل‌شده',

    'closed' =>
        'بسته‌شده',
];


$t3c1SlaStateCode =
    strtolower(
        trim(
            (string) (
                $t3c1SlaState[
                    'state_code'
                ]
                ?? ''
            )
        )
    );


$t3c1SlaStateTitle =
    $t3c1SlaStateTitles[
        $t3c1SlaStateCode
    ]
    ??
    (
        $t3c1SlaStateCode !== ''
            ? $t3c1SlaStateCode
            : '—'
    );
?>


<section
    class="admin-section ticketing-operational-audit"
    data-ticketing-operational-audit
    hidden
>
    <div class="admin-section__header">
        <div>
            <h3>
                تاریخچه عملیاتی
            </h3>

            <p class="admin-muted">
                سوابق تخصیص، رخدادهای عملیاتی و رویدادهای SLA
            </p>
        </div>
    </div>


    <?php if ($t3c1SlaState !== null): ?>

        <div class="admin-card">
            <div class="admin-card-body">

                <h4>
                    وضعیت SLA
                </h4>

                <div class="admin-grid admin-grid-4">

                    <div>
                        <span class="admin-muted">
                            وضعیت
                        </span>

                        <strong>
                            <?= ticketing_h(
                                $t3c1SlaStateTitle
                            ) ?>
                        </strong>
                    </div>


                    <div>
                        <span class="admin-muted">
                            مهلت پاسخ
                        </span>

                        <strong>
                            <?= ticketing_h(
                                \App\Support\AdminFormat::jalaliDateTime(
                                    (string) (
                                        $t3c1SlaState[
                                            'response_due_at'
                                        ]
                                        ?? ''
                                    )
                                )
                                ?: '—'
                            ) ?>
                        </strong>
                    </div>


                    <div>
                        <span class="admin-muted">
                            مهلت حل
                        </span>

                        <strong>
                            <?= ticketing_h(
                                \App\Support\AdminFormat::jalaliDateTime(
                                    (string) (
                                        $t3c1SlaState[
                                            'resolution_due_at'
                                        ]
                                        ?? ''
                                    )
                                )
                                ?: '—'
                            ) ?>
                        </strong>
                    </div>


                    <div>
                        <span class="admin-muted">
                            تعداد ارجاع خودکار
                        </span>

                        <strong>
                            <?= ticketing_h(
                                \App\Support\AdminFormat::digits(
                                    (string) (
                                        $t3c1SlaState[
                                            'auto_escalation_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            ) ?>
                        </strong>
                    </div>

                </div>

            </div>
        </div>

    <?php endif; ?>


    <?php if ($t3c1Assignments !== []): ?>

        <div class="admin-card">
            <div class="admin-card-body">

                <h4>
                    سوابق تخصیص و مالکیت
                </h4>

                <div class="admin-table-wrap">

                    <table class="admin-table">

                        <thead>
                        <tr>
                            <th>
                                کارشناس
                            </th>

                            <th>
                                نوع تخصیص
                            </th>

                            <th>
                                زمان شروع
                            </th>

                            <th>
                                زمان پایان
                            </th>
                        </tr>
                        </thead>


                        <tbody>

                        <?php foreach (
                            $t3c1Assignments
                            as $assignment
                        ): ?>

                            <tr>

                                <td>
                                    <?= ticketing_h(
                                        $assignment[
                                            'assignee_display_name_snapshot'
                                        ]
                                        ?? '—'
                                    ) ?>
                                </td>


                                <td>
                                    <?= ticketing_h(
                                        \App\Support\TicketingDisplay
                                            ::assignmentModeTitle(
                                                (string) (
                                                    $assignment[
                                                        'assignment_mode_code'
                                                    ]
                                                    ?? ''
                                                )
                                            )
                                    ) ?>

                                    <?php if (
                                        trim(
                                            (string) (
                                                $assignment[
                                                    'assignment_reason'
                                                ]
                                                ?? ''
                                            )
                                        ) !== ''
                                    ): ?>

                                        <div class="admin-muted">
                                            <?= ticketing_h(
                                                $assignment[
                                                    'assignment_reason'
                                                ]
                                            ) ?>
                                        </div>

                                    <?php endif; ?>

                                </td>


                                <td>
                                    <?= ticketing_h(
                                        \App\Support\AdminFormat::jalaliDateTime(
                                            (string) (
                                                $assignment[
                                                    'assigned_at'
                                                ]
                                                ?? ''
                                            )
                                        )
                                        ?: '—'
                                    ) ?>
                                </td>


                                <td>

                                    <?php if (
                                        empty(
                                            $assignment[
                                                'unassigned_at'
                                            ]
                                            ?? null
                                        )
                                    ): ?>

                                        <span class="admin-pill">
                                            فعال
                                        </span>

                                    <?php else: ?>

                                        <?= ticketing_h(
                                            \App\Support\AdminFormat::jalaliDateTime(
                                                (string) $assignment[
                                                    'unassigned_at'
                                                ]
                                            )
                                            ?: '—'
                                        ) ?>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>
        </div>

    <?php endif; ?>


    <?php if ($t3c1TicketEvents !== []): ?>

        <div class="admin-card">
            <div class="admin-card-body">

                <h4>
                    شرح رویدادهای عملیاتی
                </h4>

                <div class="ticketing-operational-event-list">

                    <?php foreach (
                        $t3c1TicketEvents
                        as $event
                    ): ?>

                        <?php
                        $t3c1EventCode =
                            trim(
                                (string) (
                                    $event[
                                        'event_code'
                                    ]
                                    ?? ''
                                )
                            );

                        $t3c1EventSummary =
                            \App\Support\TicketingDisplay
                                ::eventSummary(
                                    $event
                                );
                        ?>

                        <article class="ticketing-timeline-event">

                            <div class="ticketing-timeline-event__content">

                                <strong>
                                    <?= ticketing_h(
                                        \App\Support\TicketingDisplay
                                            ::eventTitle(
                                                $t3c1EventCode
                                            )
                                    ) ?>
                                </strong>


                                <div class="admin-muted">

                                    <?= ticketing_h(
                                        trim(
                                            (string) (
                                                $event[
                                                    'actor_display_name_snapshot'
                                                ]
                                                ?? ''
                                            )
                                        ) !== ''
                                            ? $event[
                                                'actor_display_name_snapshot'
                                            ]
                                            : (
                                                $event[
                                                    'actor_user_reference'
                                                ]
                                                ?? '—'
                                            )
                                    ) ?>

                                    ·

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

                                </div>


                                <?php if (
                                    $t3c1EventSummary !== ''
                                ): ?>

                                    <p>
                                        <?= ticketing_h(
                                            $t3c1EventSummary
                                        ) ?>
                                    </p>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            </div>
        </div>

    <?php endif; ?>


    <?php if ($t3c1SlaEvents !== []): ?>

        <div class="admin-card">
            <div class="admin-card-body">

                <h4>
                    رویدادهای SLA
                </h4>


                <?php foreach (
                    $t3c1SlaEvents
                    as $slaEvent
                ): ?>

                    <?php
                    $t3c1SlaEventCode =
                        trim(
                            (string) (
                                $slaEvent[
                                    'event_code'
                                ]
                                ?? ''
                            )
                        );

                    $t3c1SlaSummary =
                        \App\Support\TicketingDisplay
                            ::slaEventSummary(
                                $slaEvent
                            );

                    $t3c1SlaActor =
                        trim(
                            (string) (
                                $slaEvent[
                                    'actor_display_name_snapshot'
                                ]
                                ?? ''
                            )
                        );

                    if ($t3c1SlaActor === '') {
                        $t3c1SlaActor =
                            trim(
                                (string) (
                                    $slaEvent[
                                        'actor_user_reference'
                                    ]
                                    ?? ''
                                )
                            );
                    }

                    if ($t3c1SlaActor === '') {
                        $t3c1SlaActor =
                            'موتور SLA تیکتینگ';
                    }
                    ?>


                    <article class="ticketing-timeline-event">

                        <div class="ticketing-timeline-event__content">

                            <strong>
                                <?= ticketing_h(
                                    \App\Support\TicketingDisplay
                                        ::slaEventTitle(
                                            $t3c1SlaEventCode
                                        )
                                ) ?>
                            </strong>


                            <div class="admin-muted">

                                <?= ticketing_h(
                                    $t3c1SlaActor
                                ) ?>

                                ·

                                <?= ticketing_h(
                                    \App\Support\AdminFormat::jalaliDateTime(
                                        (string) (
                                            $slaEvent[
                                                'occurred_at'
                                            ]
                                            ?? ''
                                        )
                                    )
                                    ?: '—'
                                ) ?>

                            </div>


                            <?php if (
                                $t3c1SlaSummary !== ''
                            ): ?>

                                <p>
                                    <?= ticketing_h(
                                        $t3c1SlaSummary
                                    ) ?>
                                </p>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>
        </div>

    <?php endif; ?>

</section>


<?php if (
    $t3c1HasOperations
    &&
    $t3c1Csrf !== ''
): ?>

    <section
        class="admin-section ticketing-detail-staff-operations"
        data-ticketing-detail-staff-operations
        hidden
    >
        <div class="admin-section__header">

            <div>
                <h3>
                    عملیات کارتابل
                </h3>

                <p class="admin-muted">
                    عملیات مجاز همین تیکت با همان قواعد کارتابل پشتیبانی
                </p>
            </div>

        </div>


        <div class="admin-form-actions">


            <?php if ($t3c1CanTakeover): ?>

                <form
                    method="post"
                    action="<?= ticketing_h(
                        '/admin/ticketing/staff/'
                        . rawurlencode(
                            $t3c1Reference
                        )
                        . '/takeover'
                    ) ?>"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= ticketing_h(
                            $t3c1Csrf
                        ) ?>"
                    >

                    <button
                        class="ui-button admin-button admin-button--soft"
                        type="submit"
                    >
                        تحویل گرفتن تیکت
                    </button>
                </form>

            <?php endif; ?>


            <?php if ($t3c1CanTransfer): ?>

                <form
                    method="post"
                    action="<?= ticketing_h(
                        '/admin/ticketing/staff/'
                        . rawurlencode(
                            $t3c1Reference
                        )
                        . '/transfer'
                    ) ?>"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= ticketing_h(
                            $t3c1Csrf
                        ) ?>"
                    >


                    <select
                        class="ui-select"
                        name="target_member_id"
                        required
                        aria-label="کارشناس مقصد"
                    >
                        <option value="">
                            کارشناس مقصد را انتخاب کنید
                        </option>


                        <?php foreach (
                            $t3c1TransferTargets
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
                        class="ui-button admin-button admin-button--soft"
                        type="submit"
                    >
                        انتقال به کارشناس
                    </button>

                </form>

            <?php endif; ?>


            <?php if ($t3c1CanEscalate): ?>

                <?php
                $t3c1EscalationTarget =
                    trim(
                        (string) (
                            $t3c1Actions[
                                'escalation_target_title'
                            ]
                            ?? ''
                        )
                    );
                ?>

                <form
                    method="post"
                    action="<?= ticketing_h(
                        '/admin/ticketing/staff/'
                        . rawurlencode(
                            $t3c1Reference
                        )
                        . '/escalate'
                    ) ?>"
                    data-ticketing-confirm="ارجاع تیکت به سطح بالاتر انجام شود؟"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= ticketing_h(
                            $t3c1Csrf
                        ) ?>"
                    >


                    <button
                        class="ui-button admin-button admin-button--soft"
                        type="submit"
                    >
                        ارجاع به سطح بالاتر

                        <?php if (
                            $t3c1EscalationTarget !== ''
                        ): ?>

                            —
                            <?= ticketing_h(
                                $t3c1EscalationTarget
                            ) ?>

                        <?php endif; ?>

                    </button>

                </form>

            <?php endif; ?>


        </div>

    </section>

<?php endif; ?>


<script data-ticketing-t3c1-operational-relocate>
(() => {

    const relocate = () => {

        const workspace =
            document.querySelector(
                '[data-ticketing-detail-workspace]'
            );

        if (!workspace) {
            return;
        }


        const historyPanel =
            workspace.querySelector(
                '[data-ticketing-detail-panel="history"]'
            );


        const operationsPanel =
            workspace.querySelector(
                '[data-ticketing-detail-panel="status"]'
            );


        const audit =
            document.querySelector(
                '[data-ticketing-operational-audit]'
            );


        const staffOperations =
            document.querySelector(
                '[data-ticketing-detail-staff-operations]'
            );


        if (
            historyPanel
            &&
            audit
        ) {
            historyPanel.append(
                audit
            );

            audit.hidden = false;
        }


        if (
            operationsPanel
            &&
            staffOperations
        ) {
            operationsPanel.append(
                staffOperations
            );

            staffOperations.hidden = false;
        }


        /*
         * TICKETING_OPERATIONAL_DETAIL_HISTORY_T3C1_FOUNDATION_V1
         *
         * No inline browser event handlers.
         */
        document
            .querySelectorAll(
                '[data-ticketing-confirm]'
            )
            .forEach(
                (form) => {

                    if (
                        form.dataset
                            .ticketingConfirmBound
                        === '1'
                    ) {
                        return;
                    }

                    form.dataset
                        .ticketingConfirmBound =
                        '1';

                    form.addEventListener(
                        'submit',
                        (event) => {

                            const message =
                                String(
                                    form.getAttribute(
                                        'data-ticketing-confirm'
                                    )
                                    || ''
                                )
                                    .trim();

                            if (
                                message !== ''
                                &&
                                !window.confirm(
                                    message
                                )
                            ) {
                                event.preventDefault();
                            }
                        }
                    );
                }
            );


        workspace.setAttribute(
            'data-ticketing-operational-detail-history',
            't3c1'
        );
    };


    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            relocate,
            {
                once: true,
            }
        );

        return;
    }


    relocate();

})();
</script>

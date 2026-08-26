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
            \App\Support\AdminFormat::digits(
                (string) (
                    $ticket[
                        'ticket_number'
                    ]
                    ?? ''
                )
            )
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
                    \App\Support\AdminFormat::digits(
                        (string) (
                            $ticket[
                                'ticket_number'
                            ]
                            ?? ''
                        )
                    )
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
                                    $eventLabels[
                                        $eventCode
                                    ]
                                    ?? $eventCode
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
                                    $event[
                                        'resulting_status_code'
                                    ]
                                    ?? '—'
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
$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';

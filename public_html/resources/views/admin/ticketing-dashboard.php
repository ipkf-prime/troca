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

/** @var array $context */
/** @var array $dashboard */

$dashboard =
    $dashboard
    ?? [];

$recent =
    $dashboard['recent']
    ?? [];

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
    <span>
        پشتیبانی و تیکتینگ
    </span>
</nav>

<div class="admin-page ticketing-page ticketing-dashboard-page">

    <div class="admin-page-header ticketing-page-head">
        <div>
            <h1>
                پشتیبانی و تیکتینگ
            </h1>

            <p>
                ثبت و پیگیری درخواست‌های
                پشتیبانی
            </p>
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-button admin-button--soft"
                href="/admin/ticketing/tickets"
            >
                تیکت‌های من
            </a>

            <a
                class="admin-button"
                href="/admin/ticketing/tickets/create"
            >
                تیکت جدید
            </a>
        </div>
    </div>


    <div class="admin-grid admin-grid-4 ticketing-metrics">

        <?php foreach ([
            [
                'label' => 'همه تیکت‌ها',
                'value' =>
                    $dashboard['total']
                    ?? 0,
            ],
            [
                'label' => 'باز',
                'value' =>
                    $dashboard['open']
                    ?? 0,
            ],
            [
                'label' => 'در انتظار',
                'value' =>
                    $dashboard['waiting']
                    ?? 0,
            ],
            [
                'label' => 'بسته‌شده',
                'value' =>
                    $dashboard['closed']
                    ?? 0,
            ],
        ] as $metric): ?>

            <section class="admin-card">
                <div class="admin-card-body">
                    <div class="admin-muted">
                        <?= ticketing_h(
                            $metric['label']
                        ) ?>
                    </div>

                    <div class="ticketing-metric-value">
                        <?= ticketing_h(
                            \App\Support\AdminFormat::digits(
                                (int) $metric['value']
                            )
                        ) ?>
                    </div>
                </div>
            </section>

        <?php endforeach; ?>

    </div>


    <section
        class="admin-section ticketing-recent"
    >
        <div class="admin-page-header">
            <div>
                <h2>
                    آخرین تیکت‌های من
                </h2>

                <p>
                    آخرین درخواست‌های ثبت‌شده
                    توسط حساب کاربری شما
                </p>
            </div>
        </div>

        <?php if ($recent === []): ?>

            <div class="admin-empty-state">
                هنوز تیکتی ثبت نکرده‌اید.
            </div>

        <?php else: ?>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>شماره</th>
                        <th>عنوان</th>
                        <th>اولویت</th>
                        <th>وضعیت</th>
                        <th>آخرین فعالیت</th>
                        <th>عملیات</th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($recent as $ticket): ?>

                        <?php
                        $url =
                            '/admin/ticketing/tickets/'
                            . rawurlencode(
                                (string) (
                                    $ticket[
                                        'public_reference'
                                    ]
                                    ?? ''
                                )
                            );
                        ?>

                        <tr>
                            <td>
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
                            </td>

                            <td>
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
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $ticket[
                                        'priority_title'
                                    ]
                                    ?? ''
                                ) ?>
                            </td>

                            <td>
                                <span class="admin-pill">
                                    <?= ticketing_h(
                                        $ticket[
                                            'status_title'
                                        ]
                                        ?? ''
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


    <section
        class="admin-card ticketing-queue-card"
    >
        <div class="admin-card-body">
            <h2>
                صف پشتیبانی
            </h2>

            <p class="admin-muted">
                صف کارشناسان، ارجاع،
                پاسخ‌گویی و SLA با نقش و
                دسترسی مستقل در مرحله
                عملیاتی بعدی فعال می‌شود.
            </p>
        </div>
    </section>

</div>

<?php
$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';

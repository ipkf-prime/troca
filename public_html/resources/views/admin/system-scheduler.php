<?php

declare(strict_types=1);

if (!function_exists('scheduler_h')) {
    function scheduler_h(
        mixed $value
    ): string {
        return
            htmlspecialchars(
                (string) ($value ?? ''),
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8',
                false
            );
    }
}

if (!function_exists('scheduler_digits')) {
    function scheduler_digits(
        mixed $value
    ): string {
        return
            \App\Support\AdminFormat::digits(
                (string) ($value ?? '')
            );
    }
}


if (!function_exists('scheduler_local_datetime')) {
    function scheduler_local_datetime(
        mixed $value,
        string $timezone = 'Asia/Tehran'
    ): string {
        $value =
            trim(
                (string) ($value ?? '')
            );

        if ($value === '') {
            return '—';
        }

        try {
            $date =
                new \DateTimeImmutable(
                    $value,
                    new \DateTimeZone('UTC')
                );

            return
                scheduler_digits(
                    $date
                        ->setTimezone(
                            new \DateTimeZone(
                                $timezone
                            )
                        )
                        ->format(
                            'Y/m/d H:i:s'
                        )
                );

        } catch (\Throwable) {
            return
                scheduler_digits(
                    $value
                );
        }
    }
}


if (!function_exists('scheduler_scope_label')) {
    function scheduler_scope_label(
        mixed $value
    ): string {
        return
            match (
                strtolower(
                    trim(
                        (string) ($value ?? '')
                    )
                )
            ) {
                'project' =>
                    'پروژه',

                'holding' =>
                    'هلدینگ',

                'organization' =>
                    'سازمان',

                'branch' =>
                    'شعبه',

                'provider' =>
                    'سرویس‌دهنده',

                'global' =>
                    'کل سامانه',

                default =>
                    'محدوده اجرا',
            };
    }
}


$page =
    is_array(
        $page
        ?? null
    )
        ? $page
        : [];

$applications =
    is_array(
        $page['applications']
        ?? null
    )
        ? $page['applications']
        : [];

$notice =
    trim(
        (string) (
            $notice
            ?? ''
        )
    );

$csrf =
    (new \IPKF\Security\Csrf())
        ->token();

$stateLabels = [
    'active' =>
        'فعال',

    'paused' =>
        'متوقف موقت',

    'disabled' =>
        'غیرفعال',
];

$scheduleLabels = [
    'interval' =>
        'تناوبی',

    'manual' =>
        'فقط دستی',
];

$statusLabels = [
    'success' =>
        'موفق',

    'failed' =>
        'خطا',

    'running' =>
        'در حال اجرا',

    'skipped' =>
        'رد شده',
];

ob_start();
?>

<style>
.scheduler-app {
    border: 1px solid rgba(148, 163, 184, .28);
    border-radius: 14px;
    margin: 0 0 18px;
    overflow: hidden;
}

.scheduler-app__header {
    align-items: center;
    display: flex;
    gap: 12px;
    justify-content: space-between;
    padding: 16px;
}

.scheduler-grid {
    display: grid;
    gap: 12px;
    padding: 0 16px 16px;
}

.scheduler-job {
    border: 1px solid rgba(148, 163, 184, .22);
    border-radius: 12px;
    padding: 14px;
}

.scheduler-job__header {
    align-items: center;
    display: flex;
    gap: 12px;
    justify-content: space-between;
}

.scheduler-form {
    align-items: end;
    display: grid;
    gap: 10px;
    grid-template-columns:
        minmax(120px, .7fr)
        minmax(120px, .7fr)
        minmax(110px, .6fr)
        auto;
    margin-top: 14px;
}

.scheduler-form label {
    display: grid;
    gap: 5px;
}

.scheduler-meta {
    display: grid;
    gap: 8px;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    margin-top: 12px;
}

.scheduler-meta > div {
    background: rgba(148, 163, 184, .08);
    border-radius: 9px;
    padding: 9px;
}

@media (max-width: 900px) {
    .scheduler-form,
    .scheduler-meta {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 600px) {
    .scheduler-form,
    .scheduler-meta {
        grid-template-columns: 1fr;
    }

    .scheduler-job__header {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>


<nav
    class="admin-breadcrumb"
    aria-label="breadcrumb"
>
    <a href="/admin/dashboard">
        داشبورد
    </a>

    <span>/</span>

    <span>
        مدیریت سامانه
    </span>

    <span>/</span>

    <span>
        مدیریت اجرای خودکار
    </span>
</nav>


<section class="admin-section">

    <div class="admin-section__header">
        <div>
            <h2>
                مدیریت اجرای خودکار
            </h2>

            <p class="admin-muted">
                مدیریت متمرکز کارهای اجرایی، محدوده‌ها،
                زمان‌بندی و تاریخچه اجرای خودکار سامانه‌ها
            </p>
        </div>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="admin-card">
            <div class="admin-card-body">
                <?= scheduler_h($notice) ?>
            </div>
        </div>
    <?php endif; ?>

</section>


<?php foreach ($applications as $application): ?>

    <?php
    $applicationKey =
        (string) (
            $application['key']
            ?? ''
        );

    $bindings =
        is_array(
            $application['bindings']
            ?? null
        )
            ? $application['bindings']
            : [];

    $runs =
        is_array(
            $application['runs']
            ?? null
        )
            ? $application['runs']
            : [];
    ?>

    <section class="scheduler-app">

        <div class="scheduler-app__header">

            <div>
                <h3>
                    <?= scheduler_h(
                        $application['title']
                        ?? $applicationKey
                    ) ?>
                </h3>

            </div>

            <span class="admin-pill">
                <?= (
                    $application['status']
                    ?? ''
                ) === 'ready'
                    ? 'آماده'
                    : 'در دسترس نیست' ?>
            </span>

        </div>


        <?php if ($bindings === []): ?>

            <div class="admin-empty-state">
                کار اجرایی یا محدوده فعالی ثبت نشده است.
            </div>

        <?php else: ?>

            <div class="scheduler-grid">

                <?php foreach ($bindings as $binding): ?>

                    <?php
                    $bindingId =
                        (int) (
                            $binding['binding_id']
                            ?? 0
                        );

                    $state =
                        (string) (
                            $binding['state_code']
                            ?? 'active'
                        );

                    $scheduleType =
                        (string) (
                            $binding['schedule_type']
                            ?? 'interval'
                        );
                    ?>

                    <article class="scheduler-job">

                        <div class="scheduler-job__header">

                            <div>
                                <strong>
                                    <?= scheduler_h(
                                        $binding['job_title']
                                        ?? $binding['job_key']
                                        ?? ''
                                    ) ?>
                                </strong>

                                <div class="admin-muted">
                                    <?= scheduler_h(
                                        scheduler_scope_label(
                                            $binding['scope_type']
                                            ?? ''
                                        )
                                    ) ?>:

                                    <?= scheduler_h(
                                        $binding['scope_title_snapshot']
                                        ?? ''
                                    ) ?>

                                    <?php if (
                                        trim(
                                            (string) (
                                                $binding['scope_reference']
                                                ?? ''
                                            )
                                        ) !== ''
                                    ): ?>

                                        <span>
                                            (شناسه:
                                            <?= scheduler_h(
                                                $binding['scope_reference']
                                                ?? ''
                                            ) ?>)
                                        </span>

                                    <?php endif; ?>
                                </div>
                            </div>


                            <form
                                method="post"
                                action="<?= scheduler_h(
                                    '/admin/system/scheduler/'
                                    . rawurlencode($applicationKey)
                                    . '/'
                                    . $bindingId
                                    . '/run'
                                ) ?>"
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    value="<?= scheduler_h($csrf) ?>"
                                >

                                <button
                                    type="submit"
                                    class="admin-button admin-button--soft"
                                    <?= $state === 'disabled'
                                        ? 'disabled'
                                        : '' ?>
                                >
                                    همین الآن اجرا کن
                                </button>
                            </form>

                        </div>


                        <form
                            class="scheduler-form"
                            method="post"
                            action="<?= scheduler_h(
                                '/admin/system/scheduler/'
                                . rawurlencode($applicationKey)
                                . '/'
                                . $bindingId
                                . '/schedule'
                            ) ?>"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value="<?= scheduler_h($csrf) ?>"
                            >

                            <label>
                                <span>
                                    وضعیت
                                </span>

                                <select name="state_code">

                                    <?php foreach (
                                        $stateLabels
                                        as $code => $label
                                    ): ?>

                                        <option
                                            value="<?= scheduler_h($code) ?>"
                                            <?= $state === $code
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= scheduler_h($label) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </label>


                            <label>
                                <span>
                                    نوع اجرا
                                </span>

                                <select name="schedule_type">

                                    <?php foreach (
                                        $scheduleLabels
                                        as $code => $label
                                    ): ?>

                                        <option
                                            value="<?= scheduler_h($code) ?>"
                                            <?= $scheduleType === $code
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= scheduler_h($label) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </label>


                            <label>
                                <span>
                                    دوره اجرا (دقیقه)
                                </span>

                                <input
                                    type="number"
                                    min="1"
                                    max="1440"
                                    name="interval_minutes"
                                    value="<?= scheduler_h(
                                        $binding['interval_minutes']
                                        ?? 5
                                    ) ?>"
                                >
                            </label>


                            <div>
                                <button
                                    type="submit"
                                    class="admin-button"
                                >
                                    ذخیره تنظیمات
                                </button>
                            </div>

                        </form>


                        <div class="scheduler-meta">

                            <div>
                                <small class="admin-muted">
                                    اجرای بعدی
                                </small>

                                <div>
                                    <?= scheduler_h(
                                        scheduler_local_datetime(
                                            $binding['next_run_at']
                                            ?? null,
                                            $binding['timezone']
                                            ?? 'Asia/Tehran'
                                        )
                                    ) ?>
                                </div>
                            </div>

                            <div>
                                <small class="admin-muted">
                                    آخرین اجرا
                                </small>

                                <div>
                                    <?= scheduler_h(
                                        scheduler_local_datetime(
                                            $binding['last_run_at']
                                            ?? null,
                                            $binding['timezone']
                                            ?? 'Asia/Tehran'
                                        )
                                    ) ?>
                                </div>
                            </div>

                            <div>
                                <small class="admin-muted">
                                    نتیجه آخر
                                </small>

                                <div>
                                    <?= scheduler_h(
                                        $statusLabels[
                                            $binding['last_status_code']
                                            ?? ''
                                        ]
                                        ?? (
                                            $binding['last_status_code']
                                            ?? '—'
                                        )
                                    ) ?>
                                </div>
                            </div>

                            <div>
                                <small class="admin-muted">
                                    خطاهای متوالی
                                </small>

                                <div>
                                    <?= scheduler_h(
                                        scheduler_digits(
                                            $binding['consecutive_failures']
                                            ?? 0
                                        )
                                    ) ?>
                                </div>
                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <div class="admin-table-wrap">
            <table class="admin-table">

                <thead>
                    <tr>
                        <th>عملیات</th>
                        <th>محدوده</th>
                        <th>نوع اجرا</th>
                        <th>وضعیت</th>
                        <th>شروع اجرا</th>
                        <th>مدت اجرا</th>
                        <th>خطا</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($runs === []): ?>

                        <tr>
                            <td colspan="7">
                                هنوز اجرایی ثبت نشده است.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($runs as $run): ?>

                            <tr>
                                <td>
                                    <?= scheduler_h(
                                        $run['job_title']
                                        ?? $run['job_key']
                                        ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= scheduler_h(
                                        $run['scope_title_snapshot']
                                        ?? $run['scope_reference']
                                        ?? ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= scheduler_h(
                                        (
                                            $run['trigger_code']
                                            ?? ''
                                        ) === 'manual'
                                            ? 'دستی'
                                            : 'خودکار'
                                    ) ?>
                                </td>

                                <td>
                                    <?= scheduler_h(
                                        $statusLabels[
                                            $run['status_code']
                                            ?? ''
                                        ]
                                        ?? (
                                            $run['status_code']
                                            ?? ''
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= scheduler_h(
                                        scheduler_local_datetime(
                                            $run['started_at']
                                            ?? null
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?php
                                    $duration =
                                        $run['duration_ms']
                                        ?? null;
                                    ?>

                                    <?= $duration === null
                                        ? '—'
                                        : scheduler_h(
                                            scheduler_digits(
                                                $duration
                                            )
                                            . ' میلی‌ثانیه'
                                        ) ?>
                                </td>

                                <td>
                                    <?= scheduler_h(
                                        $run['error_message']
                                        ?? '—'
                                    ) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>
        </div>

    </section>

<?php endforeach; ?>


<?php

$content =
    ob_get_clean();

require
    __DIR__
    . '/layout.php';

<?php

declare(strict_types=1);

if (!function_exists('ticketing_h')) {
    function ticketing_h(
        $value
    ): string {
        return
            htmlspecialchars(
                (string) (
                    $value
                    ?? ''
                ),
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8',
                false
            );
    }
}


$policies =
    is_array(
        $policies
        ?? null
    )
        ? $policies
        : [];

$projects =
    is_array(
        $projects
        ?? null
    )
        ? $projects
        : [];

$services =
    is_array(
        $services
        ?? null
    )
        ? $services
        : [];

$topics =
    is_array(
        $topics
        ?? null
    )
        ? $topics
        : [];

$queues =
    is_array(
        $queues
        ?? null
    )
        ? $queues
        : [];

$priorities =
    is_array(
        $priorities
        ?? null
    )
        ? $priorities
        : [];

$calendars =
    is_array(
        $calendars
        ?? null
    )
        ? $calendars
        : [];

$statuses =
    is_array(
        $statuses
        ?? null
    )
        ? $statuses
        : [];

$form =
    is_array(
        $form
        ?? null
    )
        ? $form
        : [];

$errors =
    is_array(
        $errors
        ?? null
    )
        ? $errors
        : [];

$notice =
    trim(
        (string) (
            $notice
            ?? ''
        )
    );

$scopeType =
    (string) (
        $form[
            'scope_type'
        ]
        ?? 'global'
    );

$pauseStatuses =
    is_array(
        $form[
            'pause_statuses'
        ]
        ?? null
    )
        ? $form[
            'pause_statuses'
        ]
        : [];

$scopeLabels = [
    'global' => 'عمومی',
    'project' => 'پروژه',
    'service' => 'خدمت',
    'topic' => 'موضوع',
    'queue' => 'صف',
];


$policyScope =
    static function (
        array $policy
    ) use (
        $scopeLabels
    ): string {

        if (
            !empty(
                $policy[
                    'topic_id'
                ]
            )
        ) {
            return
                'موضوع: '
                . (
                    $policy[
                        'topic_title'
                    ]
                    ?? '—'
                );
        }

        if (
            !empty(
                $policy[
                    'service_id'
                ]
            )
        ) {
            return
                'خدمت: '
                . (
                    $policy[
                        'service_title'
                    ]
                    ?? '—'
                );
        }

        if (
            !empty(
                $policy[
                    'queue_id'
                ]
            )
        ) {
            return
                'صف: '
                . (
                    $policy[
                        'queue_title'
                    ]
                    ?? '—'
                );
        }

        if (
            !empty(
                $policy[
                    'project_id'
                ]
            )
        ) {
            return
                'پروژه: '
                . (
                    $policy[
                        'project_title'
                    ]
                    ?? '—'
                );
        }

        return
            $scopeLabels[
                'global'
            ];
    };


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

    <a href="/admin/ticketing/projects">
        پروژه‌های پشتیبانی
    </a>

    <span>/</span>

    <span>
        مدیریت SLA
    </span>
</nav>


<div
    class="admin-page ticketing-page"
    data-ticketing-sla-management
>
    <div class="admin-page-header">
        <div>
            <h1>
                مدیریت سیاست‌های SLA
            </h1>

            <p>
                زمان پاسخ، زمان حل و سیاست
                ارجاع خودکار را به‌صورت داینامیک
                برای موضوع، خدمت، صف، پروژه یا
                سطح عمومی مدیریت کنید.
            </p>
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-button admin-button--soft"
                href="/admin/ticketing/projects"
            >
                پروژه‌های پشتیبانی
            </a>

            <a
                class="admin-button admin-button--soft"
                href="/admin/system/scheduler"
            >
                زمان‌بندی اجرا
            </a>
        </div>
    </div>


    <?php if ($notice !== ''): ?>
        <div
            class="admin-alert admin-alert--success"
            role="status"
        >
            <?= ticketing_h(
                $notice
            ) ?>
        </div>
    <?php endif; ?>


    <?php if ($errors !== []): ?>
        <div
            class="admin-alert admin-alert--danger"
            role="alert"
        >
            <strong>
                سیاست SLA ذخیره نشد.
            </strong>

            <ul>
                <?php foreach (
                    $errors
                    as $error
                ): ?>
                    <li>
                        <?= ticketing_h(
                            is_scalar($error)
                                ? $error
                                : ''
                        ) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>


    <section class="admin-section">

        <header class="sla-section-header">
            <div>
                <h2>
                    تعریف نسخه جدید سیاست
                </h2>

                <p class="admin-muted">
                    ذخیره مجدد یک Scope،
                    نسخه فعال قبلی را برای
                    تیکت‌های جدید خاتمه می‌دهد؛
                    تیکت‌های قبلی Policy تاریخی
                    خود را حفظ می‌کنند.
                </p>
            </div>
        </header>


        <form
            method="post"
            action="/admin/ticketing/sla"
            data-sla-policy-form
        >
            <input
                type="hidden"
                name="_token"
                value="<?= ticketing_h(
                    (
                        new \IPKF\Security\Csrf()
                    )->token()
                ) ?>"
            >


            <div class="sla-form-grid">

                <label>
                    <span>
                        سطح اعمال
                    </span>

                    <select
                        name="scope_type"
                        data-sla-scope-type
                        required
                    >
                        <?php foreach (
                            $scopeLabels
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= ticketing_h(
                                    $value
                                ) ?>"
                                <?= $scopeType
                                    === $value
                                        ? ' selected'
                                        : '' ?>
                            >
                                <?= ticketing_h(
                                    $label
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label
                    data-sla-scope-field="project"
                >
                    <span>
                        پروژه
                    </span>

                    <select
                        name="project_id"
                    >
                        <option value="">
                            انتخاب پروژه
                        </option>

                        <?php foreach (
                            $projects
                            as $project
                        ): ?>
                            <option
                                value="<?= ticketing_h(
                                    $project['id']
                                    ?? ''
                                ) ?>"
                                <?= (string) (
                                    $form[
                                        'project_id'
                                    ]
                                    ?? ''
                                ) ===
                                (string) (
                                    $project['id']
                                    ?? ''
                                )
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $project['title']
                                    ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label
                    data-sla-scope-field="service"
                >
                    <span>
                        خدمت
                    </span>

                    <select
                        name="service_id"
                    >
                        <option value="">
                            انتخاب خدمت
                        </option>

                        <?php foreach (
                            $services
                            as $service
                        ): ?>
                            <option
                                value="<?= ticketing_h(
                                    $service['id']
                                    ?? ''
                                ) ?>"
                                <?= (string) (
                                    $form[
                                        'service_id'
                                    ]
                                    ?? ''
                                ) ===
                                (string) (
                                    $service['id']
                                    ?? ''
                                )
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    (
                                        $service[
                                            'project_title'
                                        ]
                                        ?? ''
                                    )
                                    . ' / '
                                    . (
                                        $service[
                                            'title'
                                        ]
                                        ?? ''
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label
                    data-sla-scope-field="topic"
                >
                    <span>
                        موضوع
                    </span>

                    <select
                        name="topic_id"
                    >
                        <option value="">
                            انتخاب موضوع
                        </option>

                        <?php foreach (
                            $topics
                            as $topic
                        ): ?>
                            <option
                                value="<?= ticketing_h(
                                    $topic['id']
                                    ?? ''
                                ) ?>"
                                <?= (string) (
                                    $form[
                                        'topic_id'
                                    ]
                                    ?? ''
                                ) ===
                                (string) (
                                    $topic['id']
                                    ?? ''
                                )
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    (
                                        $topic[
                                            'project_title'
                                        ]
                                        ?? ''
                                    )
                                    . ' / '
                                    . (
                                        $topic[
                                            'service_title'
                                        ]
                                        ?? 'بدون خدمت'
                                    )
                                    . ' / '
                                    . (
                                        $topic['title']
                                        ?? ''
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label
                    data-sla-scope-field="queue"
                >
                    <span>
                        صف
                    </span>

                    <select
                        name="queue_id"
                    >
                        <option value="">
                            انتخاب صف
                        </option>

                        <?php foreach (
                            $queues
                            as $queue
                        ): ?>
                            <option
                                value="<?= ticketing_h(
                                    $queue['id']
                                    ?? ''
                                ) ?>"
                                <?= (string) (
                                    $form[
                                        'queue_id'
                                    ]
                                    ?? ''
                                ) ===
                                (string) (
                                    $queue['id']
                                    ?? ''
                                )
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    (
                                        $queue[
                                            'project_title'
                                        ]
                                        ?? ''
                                    )
                                    . ' / '
                                    . (
                                        $queue[
                                            'node_title'
                                        ]
                                        ?? ''
                                    )
                                    . ' / '
                                    . (
                                        $queue['title']
                                        ?? ''
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label>
                    <span>
                        اولویت
                    </span>

                    <select
                        name="priority_code"
                        required
                    >
                        <?php foreach (
                            $priorities
                            as $priority
                        ): ?>
                            <option
                                value="<?= ticketing_h(
                                    $priority['code']
                                    ?? ''
                                ) ?>"
                                <?= (string) (
                                    $form[
                                        'priority_code'
                                    ]
                                    ?? ''
                                ) ===
                                (string) (
                                    $priority['code']
                                    ?? ''
                                )
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $priority['title']
                                    ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label>
                    <span>
                        تقویم کاری
                    </span>

                    <select
                        name="calendar_id"
                        required
                    >
                        <?php foreach (
                            $calendars
                            as $calendar
                        ): ?>
                            <option
                                value="<?= ticketing_h(
                                    $calendar['id']
                                    ?? ''
                                ) ?>"
                                <?= (string) (
                                    $form[
                                        'calendar_id'
                                    ]
                                    ?? ''
                                ) ===
                                (string) (
                                    $calendar['id']
                                    ?? ''
                                )
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $calendar['title']
                                    ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label class="sla-field-wide">
                    <span>
                        عنوان سیاست
                    </span>

                    <input
                        type="text"
                        name="title"
                        maxlength="255"
                        required
                        value="<?= ticketing_h(
                            $form['title']
                            ?? ''
                        ) ?>"
                        placeholder="مثلاً SLA موضوع مشکلات فنی - اولویت عادی"
                    >
                </label>


                <label>
                    <span>
                        زمان پاسخ
                        <small>
                            (دقیقه کاری)
                        </small>
                    </span>

                    <input
                        type="number"
                        name="response_minutes"
                        min="1"
                        required
                        value="<?= ticketing_h(
                            $form[
                                'response_minutes'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>


                <label>
                    <span>
                        زمان حل
                        <small>
                            (دقیقه کاری)
                        </small>
                    </span>

                    <input
                        type="number"
                        name="resolution_minutes"
                        min="1"
                        required
                        value="<?= ticketing_h(
                            $form[
                                'resolution_minutes'
                            ]
                            ?? ''
                        ) ?>"
                    >
                </label>


                <label>
                    <span>
                        حداکثر ارجاع خودکار
                    </span>

                    <input
                        type="number"
                        name="max_auto_escalations"
                        min="0"
                        max="50"
                        value="<?= ticketing_h(
                            $form[
                                'max_auto_escalations'
                            ]
                            ?? 3
                        ) ?>"
                    >
                </label>


                <label>
                    <span>
                        فاصله ارجاع بعدی
                        <small>
                            (دقیقه کاری)
                        </small>
                    </span>

                    <input
                        type="number"
                        name="escalation_repeat_minutes"
                        min="1"
                        max="10080"
                        value="<?= ticketing_h(
                            $form[
                                'escalation_repeat_minutes'
                            ]
                            ?? 60
                        ) ?>"
                    >
                </label>


                <label>
                    <span>
                        ترتیب اولویت Policy
                    </span>

                    <input
                        type="number"
                        name="sort_order"
                        min="0"
                        max="100000"
                        value="<?= ticketing_h(
                            $form[
                                'sort_order'
                            ]
                            ?? 100
                        ) ?>"
                    >
                </label>


                <label>
                    <span>
                        ارجاع خودکار
                    </span>

                    <span class="ticketing-switch">
                        <input
                            type="checkbox"
                            name="auto_escalate"
                            value="1"
                            <?= !empty(
                                $form[
                                    'auto_escalate'
                                ]
                            )
                                ? 'checked'
                                : '' ?>
                        >

                        <span
                            class="ticketing-switch__track"
                            aria-hidden="true"
                        ></span>

                        <span class="ticketing-switch__label">
                            پس از Breach فعال باشد
                        </span>
                    </span>
                </label>


                <fieldset class="sla-field-full sla-pause-fieldset">
                    <legend>
                        وضعیت‌های توقف زمان SLA
                    </legend>

                    <div class="sla-pause-options">

                        <?php foreach (
                            $statuses
                            as $status
                        ): ?>

                            <?php
                            $statusCode =
                                (string) (
                                    $status['code']
                                    ?? ''
                                );
                            ?>

                            <label>
                                <input
                                    type="checkbox"
                                    name="pause_statuses[]"
                                    value="<?= ticketing_h(
                                        $statusCode
                                    ) ?>"
                                    <?= in_array(
                                        $statusCode,
                                        $pauseStatuses,
                                        true
                                    )
                                        ? 'checked'
                                        : '' ?>
                                >

                                <span>
                                    <?= ticketing_h(
                                        $status['title']
                                        ?? $statusCode
                                    ) ?>
                                </span>
                            </label>

                        <?php endforeach; ?>

                    </div>
                </fieldset>

            </div>


            <div class="admin-form-actions">
                <button
                    type="submit"
                    class="admin-button"
                >
                    ذخیره نسخه جدید SLA
                </button>
            </div>

        </form>

    </section>


    <section class="admin-section">

        <header class="sla-section-header">
            <div>
                <h2>
                    نسخه‌های سیاست SLA
                </h2>

                <p class="admin-muted">
                    Policy فعال برای تیکت‌های جدید
                    استفاده می‌شود. نسخه‌های غیرفعال
                    برای حفظ تاریخچه تیکت‌های قبلی
                    حذف نمی‌شوند.
                </p>
            </div>
        </header>


        <?php if ($policies === []): ?>

            <div class="admin-empty-state">
                هنوز سیاست SLA تعریف نشده است.
            </div>

        <?php else: ?>

            <div class="admin-table-wrap">

                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>دامنه</th>
                        <th>اولویت</th>
                        <th>پاسخ</th>
                        <th>حل</th>
                        <th>ارجاع</th>
                        <th>وضعیت</th>
                        <th>شروع</th>
                        <th>عملیات</th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php foreach (
                        $policies
                        as $policy
                    ): ?>

                        <?php
                        $reference =
                            (string) (
                                $policy[
                                    'public_reference'
                                ]
                                ?? ''
                            );

                        $active =
                            (
                                $policy[
                                    'status'
                                ]
                                ?? ''
                            ) === 'active';
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= ticketing_h(
                                        $policy['title']
                                        ?? ''
                                    ) ?>
                                </strong>

                                <div class="admin-muted">
                                    <?= ticketing_h(
                                        $policy[
                                            'calendar_title'
                                        ]
                                        ?? ''
                                    ) ?>
                                </div>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $policyScope(
                                        $policy
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $policy[
                                        'priority_title'
                                    ]
                                    ?? $policy[
                                        'priority_code'
                                    ]
                                    ?? ''
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (int) (
                                            $policy[
                                                'response_minutes'
                                            ]
                                            ?? 0
                                        )
                                    )
                                ) ?>
                                دقیقه
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::digits(
                                        (int) (
                                            $policy[
                                                'resolution_minutes'
                                            ]
                                            ?? 0
                                        )
                                    )
                                ) ?>
                                دقیقه
                            </td>

                            <td>
                                <?php if (
                                    (
                                        $policy[
                                            'breach_action_code'
                                        ]
                                        ?? ''
                                    ) === 'escalate'
                                ): ?>

                                    حداکثر
                                    <?= ticketing_h(
                                        \App\Support\AdminFormat::digits(
                                            (int) (
                                                $policy[
                                                    'max_auto_escalations'
                                                ]
                                                ?? 0
                                            )
                                        )
                                    ) ?>

                                    بار

                                <?php else: ?>

                                    غیرفعال

                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="admin-pill">
                                    <?= $active
                                        ? 'فعال'
                                        : 'تاریخی / غیرفعال' ?>
                                </span>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    \App\Support\AdminFormat::jalaliDateTime(
                                        (string) (
                                            $policy[
                                                'effective_from_at'
                                            ]
                                            ?? ''
                                        )
                                    )
                                    ?: '—'
                                ) ?>
                            </td>

                            <td>
                                <div class="sla-row-actions">

                                    <a
                                        class="admin-button admin-button--soft admin-button--compact"
                                        href="/admin/ticketing/sla?copy=<?= ticketing_h(
                                            rawurlencode(
                                                $reference
                                            )
                                        ) ?>"
                                    >
                                        نسخه جدید
                                    </a>


                                    <?php if ($active): ?>

                                        <form
                                            method="post"
                                            action="/admin/ticketing/sla/<?= ticketing_h(
                                                rawurlencode(
                                                    $reference
                                                )
                                            ) ?>/disable"
                                        >
                                            <input
                                                type="hidden"
                                                name="_token"
                                                value="<?= ticketing_h(
                                                    (
                                                        new \IPKF\Security\Csrf()
                                                    )->token()
                                                ) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="admin-button admin-button--soft admin-button--compact"
                                                onclick="return confirm('این Policy برای تیکت‌های جدید غیرفعال شود؟');"
                                            >
                                                غیرفعال‌کردن
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

</div>


<style>
[data-ticketing-sla-management] .sla-form-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:1rem
}

[data-ticketing-sla-management] .sla-field-wide{
    grid-column:span 2
}

[data-ticketing-sla-management] .sla-field-full{
    grid-column:1/-1
}

[data-ticketing-sla-management] .sla-form-grid>label{
    display:flex;
    flex-direction:column;
    gap:.45rem
}

[data-ticketing-sla-management] .sla-section-header{
    margin-bottom:1rem
}

[data-ticketing-sla-management] .sla-section-header h2{
    margin:0 0 .3rem
}

[data-ticketing-sla-management] .sla-pause-fieldset{
    border:1px solid var(--admin-border,#d8dee4);
    border-radius:.8rem;
    padding:1rem
}

[data-ticketing-sla-management] .sla-pause-options{
    display:flex;
    flex-wrap:wrap;
    gap:.7rem 1rem
}

[data-ticketing-sla-management] .sla-pause-options label{
    display:inline-flex;
    align-items:center;
    gap:.4rem
}

[data-ticketing-sla-management] .sla-row-actions{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:.4rem
}

[data-ticketing-sla-management] .sla-row-actions form{
    margin:0
}

@media (max-width:900px){
    [data-ticketing-sla-management] .sla-form-grid{
        grid-template-columns:1fr
    }

    [data-ticketing-sla-management] .sla-field-wide{
        grid-column:auto
    }
}
</style>


<script>
(function () {
    'use strict';

    var scope =
        document.querySelector(
            '[data-sla-scope-type]'
        );

    if (!scope) {
        return;
    }

    var fields =
        document.querySelectorAll(
            '[data-sla-scope-field]'
        );

    var apply =
        function () {
            var selected =
                scope.value || 'global';

            fields.forEach(
                function (field) {
                    field.hidden =
                        field.getAttribute(
                            'data-sla-scope-field'
                        ) !== selected;
                }
            );
        };

    scope.addEventListener(
        'change',
        apply
    );

    apply();
})();
</script>

<?php

$content =
    ob_get_clean()
    ?: '';

require
    __DIR__
    . '/layout.php';

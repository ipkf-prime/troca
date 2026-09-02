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

$page =
    $page
    ?? [];

$project =
    $page['project']
    ?? [];

$services =
    $page['services']
    ?? [];

$topics =
    $page['topics']
    ?? [];

$rules =
    $page['rules']
    ?? [];

$layers =
    $page['layers']
    ?? [];

$nodes =
    $page['nodes']
    ?? [];

$queues =
    $page['queues']
    ?? [];

$teams =
    $page['teams']
    ?? [];

$staff =
    $page['staff']
    ?? [];

$errors =
    $errors
    ?? [];

$status =
    $status
    ?? '';

$reference =
    (string) (
        $project['public_reference']
        ?? ''
    );

$action =
    '/admin/ticketing/projects/'
    . rawurlencode($reference)
    . '/routing';

$csrf =
    (
        new \IPKF\Security\Csrf()
    )->token();

$statusMessages = [
    'topic-created' =>
        'موضوع پشتیبانی ثبت شد.',

    'topic-updated' =>
        'تغییرات موضوع پشتیبانی ذخیره شد.',

    'rule-created' =>
        'قانون مسیریابی ثبت شد.',
];

ob_start();
?>

<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="/admin/dashboard">داشبورد</a>
    <span>/</span>
    <a href="/admin/ticketing">پشتیبانی و تیکتینگ</a>
    <span>/</span>
    <a href="/admin/ticketing/projects">پروژه‌ها</a>
    <span>/</span>
    <span>موضوعات و مسیریابی</span>
</nav>


<div class="admin-page ticketing-page">

    <div class="admin-page-header">
        <div>
            <h1>
                موضوعات و مسیریابی:
                <?= ticketing_h(
                    $project['title']
                    ?? ''
                ) ?>
            </h1>

            <p>
                تعریف موضوع‌های چندسطحی و قوانین
                داینامیک مسیریابی پشتیبانی
            </p>
        </div>

        <a
            class="admin-button admin-button--soft"
            href="/admin/ticketing/projects"
        >
            بازگشت
        </a>
    </div>


    <?php if (
        $status !== ''
        && isset(
            $statusMessages[$status]
        )
    ): ?>
        <section class="admin-section">
            <div class="admin-alert admin-alert--success">
                <?= ticketing_h(
                    $statusMessages[$status]
                ) ?>
            </div>
        </section>
    <?php endif; ?>


    <?php if ($errors !== []): ?>
        <section class="admin-section">
            <div class="admin-alert admin-alert--danger">
                <strong>عملیات انجام نشد.</strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li>
                            <?= ticketing_h($error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>


        <nav
        class="admin-tabs ticketing-management-tabs"
        data-admin-tabs
        role="tablist"
        aria-label="مدیریت موضوعات و مسیریابی"
    >
        <button
            class="admin-tab is-active"
            type="button"
            data-admin-tab="routing-topics"
            role="tab"
        >
            موضوعات
        </button>

        <button
            class="admin-tab"
            type="button"
            data-admin-tab="routing-rules"
            role="tab"
        >
            قوانین مسیریابی
        </button>
    </nav>


<section class="admin-tab-panel is-active admin-section" data-admin-tab-panel="routing-topics">
        <h2>موضوعات پشتیبانی</h2>

        <form method="post" action="<?= ticketing_h($action) ?>">

            <input
                type="hidden"
                name="_token"
                value="<?= ticketing_h($csrf) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="topic.create"
            >

            <div class="admin-form-grid">

                <label>
                    <span>عنوان موضوع</span>
                    <input
                        type="text"
                        name="title"
                        maxlength="255"
                        required
                    >
                </label>

                <label>
                    <span>کد</span>
                    <input
                        type="text"
                        name="code"
                        maxlength="100"
                        dir="ltr"
                        required
                    >
                </label>

                <label>
                    <span>زیرسامانه</span>
                    <select name="service_id">
                        <option value="0">
                            همه زیرسامانه‌های پروژه
                        </option>

                        <?php foreach ($services as $service): ?>
                            <option
                                value="<?= ticketing_h(
                                    $service['id']
                                ) ?>"
                            >
                                <?= ticketing_h(
                                    $service['title']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>موضوع والد</span>
                    <select name="parent_topic_id">
                        <option value="0">
                            بدون والد
                        </option>

                        <?php foreach ($topics as $topic): ?>
                            <option
                                value="<?= ticketing_h(
                                    $topic['id']
                                ) ?>"
                            >
                                <?= ticketing_h(
                                    $topic['title']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>ترتیب</span>
                    <input
                        type="number"
                        name="sort_order"
                        min="0"
                        value="0"
                    >
                </label>
            </div>

            <div class="ticketing-routing-checks">

                <label>
                    <input
                        type="checkbox"
                        name="is_selectable"
                        value="1"
                        checked
                    >
                    قابل انتخاب توسط کاربر
                </label>

                <label>
                    <input
                        type="checkbox"
                        name="is_default"
                        value="1"
                    >
                    موضوع پیش‌فرض
                </label>

            </div>

            <button
                class="admin-button"
                type="submit"
            >
                افزودن موضوع
            </button>
        </form>


        <?php if ($topics !== []): ?>

            <div class="admin-table-wrap ticketing-routing-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>والد</th>
                        <th>زیرسامانه</th>
                        <th>کد</th>
                        <th>انتخاب</th>
                        <th>پیش‌فرض</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($topics as $topic): ?>
                        <tr>
                            <td>
                                <?= ticketing_h(
                                    $topic['title']
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $topic['parent_title']
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $topic['service_title']
                                    ?? 'همه'
                                ) ?>
                            </td>

                            <td dir="ltr">
                                <?= ticketing_h(
                                    $topic['code']
                                ) ?>
                            </td>

                            <td>
                                <?= (int) $topic['is_selectable']
                                    ? 'بله'
                                    : 'خیر' ?>
                            </td>

                            <td>
                                <?= (int) $topic['is_default']
                                    ? 'بله'
                                    : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>


        <?php if ($topics !== []): ?>
            <div
                class="ticketing-topic-governance"
                data-ticketing-topic-governance
            >
                <div class="ticketing-topic-governance__header">
                    <h3>ویرایش دسته و موضوع</h3>

                    <p class="admin-muted">
                        عنوان، توضیح و ترتیب کم‌ریسک هستند.
                        تغییر والد، زیرسامانه، وضعیت، قابل‌انتخاب‌بودن یا پیش‌فرض‌بودن
                        قبل از ذخیره با اثر آن روی تیکت‌ها و قوانین کنترل می‌شود.
                        حذف فیزیکی موضوع استفاده‌شده انجام نمی‌شود؛ در صورت نیاز آن را غیرفعال کنید.
                    </p>
                </div>

                <div class="ticketing-topic-governance__list">
                    <?php foreach ($topics as $topic): ?>
                        <?php
                        $topicId =
                            (int) (
                                $topic['id']
                                ?? 0
                            );

                        $topicServiceId =
                            isset(
                                $topic['service_id']
                            )
                            && $topic['service_id'] !== null
                                ? (int) $topic['service_id']
                                : 0;

                        $topicParentId =
                            isset(
                                $topic['parent_topic_id']
                            )
                            && $topic['parent_topic_id'] !== null
                                ? (int) $topic['parent_topic_id']
                                : 0;

                        $childCount =
                            (int) (
                                $topic['child_count']
                                ?? 0
                            );

                        $ruleCount =
                            (int) (
                                $topic['routing_rule_count']
                                ?? 0
                            );

                        $activeRuleCount =
                            (int) (
                                $topic['active_routing_rule_count']
                                ?? 0
                            );

                        $ticketCount =
                            (int) (
                                $topic['ticket_count']
                                ?? 0
                            );

                        $openTicketCount =
                            (int) (
                                $topic['open_ticket_count']
                                ?? 0
                            );

                        $hasImpact =
                            (
                                $childCount
                                + $ruleCount
                                + $ticketCount
                            ) > 0;
                        ?>

                        <details
                            class="ticketing-topic-governance__item"
                            name="ticketing-topic-governance"
                            data-ticketing-topic-governance-item
                            data-topic-id="<?= ticketing_h($topicId) ?>"
                        >
                            <summary>
                                <span>
                                    <strong>
                                        <?= ticketing_h(
                                            (string) (
                                                $topic['title']
                                                ?? ''
                                            )
                                        ) ?>
                                    </strong>

                                    <code dir="ltr">
                                        <?= ticketing_h(
                                            (string) (
                                                $topic['code']
                                                ?? ''
                                            )
                                        ) ?>
                                    </code>
                                </span>

                                <span class="ticketing-topic-governance__impact-summary">
                                    <?= ticketing_h($childCount) ?>
                                    زیرموضوع
                                    ·
                                    <?= ticketing_h($ruleCount) ?>
                                    قانون
                                    ·
                                    <?= ticketing_h($ticketCount) ?>
                                    تیکت
                                </span>
                            </summary>

                            <form
                                method="post"
                                action="<?= ticketing_h($action) ?>"
                                class="ticketing-topic-governance__form"
                                data-ticketing-topic-edit-form
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    value="<?= ticketing_h($csrf) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="topic.update"
                                >

                                <input
                                    type="hidden"
                                    name="topic_id"
                                    value="<?= ticketing_h($topicId) ?>"
                                >

                                <div class="admin-form-grid">
                                    <label>
                                        <span>عنوان</span>
                                        <input
                                            type="text"
                                            name="title"
                                            maxlength="255"
                                            value="<?= ticketing_h(
                                                (string) (
                                                    $topic['title']
                                                    ?? ''
                                                )
                                            ) ?>"
                                            required
                                        >
                                    </label>

                                    <div class="ticketing-topic-governance__field">
                                        <span>کد داخلی</span>

                                        <div
                                            class="ticketing-topic-governance__readonly-field"
                                            dir="ltr"
                                            aria-label="کد داخلی"
                                        >
                                            <code>
                                                <?= ticketing_h(
                                                    (string) (
                                                        $topic['code']
                                                        ?? ''
                                                    )
                                                ) ?>
                                            </code>
                                        </div>
                                    </div>

                                    <label>
                                        <span>زیرسامانه</span>
                                        <select name="service_id">
                                            <option
                                                value="0"
                                                <?= $topicServiceId === 0
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                عمومی / همه زیرسامانه‌ها
                                            </option>

                                            <?php foreach ($services as $service): ?>
                                                <?php
                                                $serviceId =
                                                    (int) (
                                                        $service['id']
                                                        ?? 0
                                                    );
                                                ?>
                                                <option
                                                    value="<?= ticketing_h($serviceId) ?>"
                                                    <?= $serviceId === $topicServiceId
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= ticketing_h(
                                                        (string) (
                                                            $service['title']
                                                            ?? ''
                                                        )
                                                    ) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>

                                    <label>
                                        <span>موضوع والد / دسته</span>
                                        <select name="parent_topic_id">
                                            <option
                                                value="0"
                                                <?= $topicParentId === 0
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                بدون والد
                                            </option>

                                            <?php foreach ($topics as $parentOption): ?>
                                                <?php
                                                $parentOptionId =
                                                    (int) (
                                                        $parentOption['id']
                                                        ?? 0
                                                    );

                                                if (
                                                    $parentOptionId < 1
                                                    || $parentOptionId === $topicId
                                                ) {
                                                    continue;
                                                }
                                                ?>

                                                <option
                                                    value="<?= ticketing_h(
                                                        $parentOptionId
                                                    ) ?>"
                                                    <?= $parentOptionId === $topicParentId
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= ticketing_h(
                                                        (string) (
                                                            $parentOption['title']
                                                            ?? ''
                                                        )
                                                    ) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>

                                    <label>
                                        <span>وضعیت</span>
                                        <select name="status">
                                            <option
                                                value="active"
                                                <?= (
                                                    $topic['status']
                                                    ?? 'active'
                                                ) === 'active'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                فعال
                                            </option>

                                            <option
                                                value="inactive"
                                                <?= (
                                                    $topic['status']
                                                    ?? ''
                                                ) === 'inactive'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                غیرفعال
                                            </option>
                                        </select>
                                    </label>

                                    <label>
                                        <span>ترتیب نمایش</span>
                                        <input
                                            type="number"
                                            name="sort_order"
                                            min="0"
                                            value="<?= ticketing_h(
                                                (int) (
                                                    $topic['sort_order']
                                                    ?? 0
                                                )
                                            ) ?>"
                                        >
                                    </label>
                                </div>

                                <label class="ticketing-topic-governance__description">
                                    <span>توضیحات</span>
                                    <textarea
                                        name="description"
                                        rows="3"
                                        maxlength="10000"
                                    ><?= ticketing_h(
                                        (string) (
                                            $topic['description']
                                            ?? ''
                                        )
                                    ) ?></textarea>
                                </label>

                                <div class="ticketing-routing-checks">
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="is_selectable"
                                            value="1"
                                            <?= (int) (
                                                $topic['is_selectable']
                                                ?? 0
                                            ) === 1
                                                ? 'checked'
                                                : '' ?>
                                        >
                                        قابل انتخاب
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="is_default"
                                            value="1"
                                            <?= (int) (
                                                $topic['is_default']
                                                ?? 0
                                            ) === 1
                                                ? 'checked'
                                                : '' ?>
                                        >
                                        موضوع پیش‌فرض
                                    </label>
                                </div>

                                <div
                                    class="ticketing-topic-governance__impact<?= $hasImpact
                                        ? ' has-impact'
                                        : '' ?>"
                                    data-ticketing-topic-impact
                                >
                                    <strong>اثر فعلی این موضوع</strong>

                                    <span>
                                        زیرموضوع:
                                        <?= ticketing_h($childCount) ?>
                                    </span>

                                    <span>
                                        قانون مسیریابی:
                                        <?= ticketing_h($ruleCount) ?>
                                        <?php if ($activeRuleCount > 0): ?>
                                            (فعال:
                                            <?= ticketing_h($activeRuleCount) ?>)
                                        <?php endif; ?>
                                    </span>

                                    <span>
                                        تیکت:
                                        <?= ticketing_h($ticketCount) ?>
                                        <?php if ($openTicketCount > 0): ?>
                                            (باز:
                                            <?= ticketing_h($openTicketCount) ?>)
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <?php if ($hasImpact): ?>
                                    <label class="ticketing-topic-governance__confirm">
                                        <input
                                            type="checkbox"
                                            name="confirm_impact"
                                            value="1"
                                        >
                                        اثر تغییر ساختاری را بررسی کردم.
                                    </label>
                                <?php endif; ?>

                                <div class="admin-form-actions">
                                    <button
                                        class="admin-button"
                                        type="submit"
                                    >
                                        ذخیره تغییرات
                                    </button>
                                </div>
                            </form>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>


    <section class="admin-tab-panel admin-section" data-admin-tab-panel="routing-rules" hidden>
        <h2>قوانین مسیریابی</h2>

        <?php if (
            $layers !== []
            && $nodes !== []
            && $queues !== []
            && $teams !== []
        ): ?>

            <form method="post" action="<?= ticketing_h($action) ?>">

                <input
                    type="hidden"
                    name="_token"
                    value="<?= ticketing_h($csrf) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="rule.create"
                >

                <div class="admin-form-grid">

                    <label>
                        <span>عنوان قانون</span>
                        <input
                            type="text"
                            name="title"
                            maxlength="255"
                            required
                        >
                    </label>

                    <label>
                        <span>زیرسامانه</span>
                        <select name="service_id">
                            <option value="0">
                                همه زیرسامانه‌ها
                            </option>

                            <?php foreach ($services as $service): ?>
                                <option
                                    value="<?= ticketing_h(
                                        $service['id']
                                    ) ?>"
                                >
                                    <?= ticketing_h(
                                        $service['title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>موضوع</span>
                        <select name="topic_id">
                            <option value="0">
                                همه موضوع‌ها
                            </option>

                            <?php foreach ($topics as $topic): ?>
                                <option
                                    value="<?= ticketing_h(
                                        $topic['id']
                                    ) ?>"
                                >
                                    <?= ticketing_h(
                                        $topic['title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>دامنه</span>
                        <select name="scope_type_code">
                            <option value="all">
                                همه
                            </option>

                            <option value="organization">
                                سازمان مشخص
                            </option>
                        </select>
                    </label>

                    <label>
                        <span>مرجع دامنه</span>
                        <input
                            type="text"
                            name="scope_reference"
                            maxlength="190"
                            dir="ltr"
                        >
                    </label>

                    <label>
                        <span>لایه مقصد</span>
                        <select name="target_layer_id" required>
                            <?php foreach ($layers as $layer): ?>
                                <option
                                    value="<?= ticketing_h(
                                        $layer['id']
                                    ) ?>"
                                >
                                    <?= ticketing_h(
                                        $layer['title']
                                        . ' / '
                                        . $layer['rank_order']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>گره مقصد</span>
                        <select name="target_node_id" required>
                            <?php foreach ($nodes as $node): ?>
                                <option
                                    value="<?= ticketing_h(
                                        $node['id']
                                    ) ?>"
                                >
                                    <?= ticketing_h(
                                        $node['title']
                                        . ' - '
                                        . $node['layer_title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>صف مقصد</span>
                        <select name="target_queue_id" required>
                            <?php foreach ($queues as $queue): ?>
                                <option
                                    value="<?= ticketing_h(
                                        $queue['id']
                                    ) ?>"
                                >
                                    <?= ticketing_h(
                                        $queue['title']
                                        . ' - '
                                        . $queue['node_title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>تیم مقصد</span>
                        <select name="target_team_id" required>
                            <?php foreach ($teams as $team): ?>
                                <option
                                    value="<?= ticketing_h(
                                        $team['id']
                                    ) ?>"
                                >
                                    <?= ticketing_h(
                                        $team['title']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>روش تخصیص</span>
                        <select name="assignment_mode_code">

                            <option value="inherit">
                                از صف
                            </option>

                            <option value="least_loaded">
                                کم‌بارترین کارشناس
                            </option>

                            <option value="round_robin">
                                گردشی
                            </option>

                            <option value="manual">
                                دستی
                            </option>

                            <option value="fixed">
                                کارشناس ثابت
                            </option>

                        </select>
                    </label>

                    <label>
                        <span>کارشناس ثابت</span>

                        <select name="fixed_project_member_id">
                            <option value="0">
                                ندارد
                            </option>

                            <?php foreach ($staff as $member): ?>
                                <option
                                    value="<?= ticketing_h(
                                        $member['id']
                                    ) ?>"
                                >
                                    <?= ticketing_h(
                                        $member[
                                            'display_name_snapshot'
                                        ]
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>اولویت قانون</span>
                        <input
                            type="number"
                            name="priority"
                            min="1"
                            max="100000"
                            value="100"
                        >
                    </label>

                    <label>
                        <span>ترتیب</span>
                        <input
                            type="number"
                            name="sort_order"
                            min="0"
                            value="0"
                        >
                    </label>

                </div>

                <button
                    class="admin-button"
                    type="submit"
                >
                    افزودن قانون
                </button>
            </form>

        <?php else: ?>

            <div class="admin-muted">
                ابتدا Topology پروژه شامل لایه، گره،
                صف و تیم را تکمیل کنید.
            </div>

        <?php endif; ?>


        <?php if ($rules !== []): ?>

            <div class="admin-table-wrap ticketing-routing-table">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>قانون</th>
                        <th>موضوع</th>
                        <th>دامنه</th>
                        <th>مقصد</th>
                        <th>تیم</th>
                        <th>نحوه تخصیص</th>
                        <th>اولویت</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($rules as $rule): ?>
                        <tr>
                            <td>
                                <?= ticketing_h(
                                    $rule['title']
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $rule['topic_title']
                                    ?? 'همه'
                                ) ?>
                            </td>

                            <td dir="ltr">
                                <?= ticketing_h(
                                    $rule['scope_type_code']
                                    . (
                                        !empty(
                                            $rule[
                                                'scope_reference'
                                            ]
                                        )
                                            ? ':'
                                                . $rule[
                                                    'scope_reference'
                                                ]
                                            : ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $rule['layer_title']
                                    . ' → '
                                    . $rule['node_title']
                                    . ' → '
                                    . $rule['queue_title']
                                ) ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $rule['team_title']
                                ) ?>
                            </td>

                            <td dir="ltr">
                                <?= ticketing_h(
                                    $rule[
                                        'assignment_mode_code'
                                    ]
                                ) ?>

                                <?php if (
                                    !empty(
                                        $rule[
                                            'fixed_member_name'
                                        ]
                                    )
                                ): ?>
                                    /
                                    <?= ticketing_h(
                                        $rule[
                                            'fixed_member_name'
                                        ]
                                    ) ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= ticketing_h(
                                    $rule['priority']
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


<style>
.ticketing-routing-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 14px 24px;
    margin: 16px 0;
}

.ticketing-routing-checks label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.ticketing-routing-table {
    margin-top: 20px;
}

.ticketing-routing-table table {
    min-width: 900px;
}
</style>


<script>
(function () {
    const labels = {
        all: 'همه',
        organization: 'سازمان',
        inherit: 'از تنظیمات صف',
        manual: 'دستی',
        least_loaded: 'کم‌بارترین کارشناس',
        round_robin: 'گردشی',
        fixed: 'کارشناس ثابت'
    };

    document
        .querySelectorAll(
            '.ticketing-page td, .ticketing-page option'
        )
        .forEach(function (element) {
            const value =
                String(
                    element.textContent || ''
                ).trim();

            if (
                Object.prototype.hasOwnProperty.call(
                    labels,
                    value
                )
            ) {
                element.textContent =
                    labels[value];
            }
        });
})();
</script>


<script>
/*
 * Ticketing explicit tab controller v1
 */
(function () {
    function activateTab(nav, key) {
        if (!nav || !key) {
            return;
        }

        const page =
            nav.closest('.ticketing-page')
            || document;

        const buttons =
            nav.querySelectorAll(
                '[data-admin-tab]'
            );

        buttons.forEach(function (button) {
            const buttonKey =
                button.getAttribute(
                    'data-admin-tab'
                );

            const active =
                buttonKey === key;

            button.classList.toggle(
                'is-active',
                active
            );

            button.setAttribute(
                'aria-selected',
                active
                    ? 'true'
                    : 'false'
            );
        });


        const panels =
            page.querySelectorAll(
                '[data-admin-tab-panel]'
            );

        panels.forEach(function (panel) {
            const panelKey =
                panel.getAttribute(
                    'data-admin-tab-panel'
                );

            const active =
                panelKey === key;

            panel.classList.toggle(
                'is-active',
                active
            );

            if (active) {
                panel.removeAttribute(
                    'hidden'
                );
            } else {
                panel.setAttribute(
                    'hidden',
                    ''
                );
            }
        });
    }


    document
        .querySelectorAll(
            '[data-admin-tabs]'
        )
        .forEach(function (nav) {

            const buttons =
                nav.querySelectorAll(
                    '[data-admin-tab]'
                );

            buttons.forEach(function (button) {

                button.addEventListener(
                    'click',
                    function () {
                        activateTab(
                            nav,
                            button.getAttribute(
                                'data-admin-tab'
                            )
                        );
                    }
                );
            });


            let initial =
                nav.querySelector(
                    '[data-admin-tab].is-active'
                );

            if (!initial) {
                initial =
                    nav.querySelector(
                        '[data-admin-tab]'
                    );
            }

            if (initial) {
                activateTab(
                    nav,
                    initial.getAttribute(
                        'data-admin-tab'
                    )
                );
            }
        });


    window.ticketingActivateTab =
        function (key) {
            document
                .querySelectorAll(
                    '[data-admin-tabs]'
                )
                .forEach(function (nav) {
                    if (
                        nav.querySelector(
                            '[data-admin-tab="'
                            + key
                            + '"]'
                        )
                    ) {
                        activateTab(
                            nav,
                            key
                        );
                    }
                });
        };
})();
</script>

<?php

$content =
    ob_get_clean()
    ?: '';

require __DIR__ . '/layout.php';

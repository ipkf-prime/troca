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

$form =
    $form
    ?? [];

$options =
    $options
    ?? [];

$errors =
    $errors
    ?? [];

$initialTab =
    isset($errors['body'])
    || isset($errors['attachments'])
        ? 'ticket-detail'
        : 'ticket-info';

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
        تیکت جدید
    </span>
</nav>


<div class="admin-page ticketing-page ticketing-form-page">

    <div class="admin-page-header ticketing-page-head">
        <div>
            <h1>
                ثبت تیکت جدید
            </h1>

            <p>
                موضوع و شرح درخواست را
                ثبت کنید.
            </p>
        </div>
    </div>


    <?php if ($errors !== []): ?>

        <section class="admin-section">
            <div
                class="admin-alert admin-alert--danger"
                role="alert"
            >
                <strong>
                    ثبت تیکت انجام نشد.
                </strong>

                <ul>
                    <?php foreach (
                        $errors
                        as $error
                    ): ?>
                        <li>
                            <?= ticketing_h($error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

    <?php endif; ?>


        <nav
        class="admin-tabs ticketing-form-tabs"
        data-admin-tabs
        role="tablist"
        aria-label="بخش‌های ثبت تیکت"
    >
        <button
            class="admin-tab is-active"
            type="button"
            data-admin-tab="ticket-info"
            role="tab"
        >
            اطلاعات تیکت
        </button>

        <button
            class="admin-tab"
            type="button"
            data-admin-tab="ticket-detail"
            role="tab"
        >
            شرح و پیوست
        </button>
    </nav>


<section class="admin-tab-panel is-active admin-section ticketing-form-section" data-admin-tab-panel="ticket-info">

        <form
            class="ticketing-create-form"
            method="post"
            action="/admin/ticketing/tickets"
         enctype="multipart/form-data">
            <input
                type="hidden"
                name="_token"
                value="<?= ticketing_h(
                    (
                        new \IPKF\Security\Csrf()
                    )->token()
                ) ?>"
            >


            <div class="admin-form-grid">

                <label>
                    <span>
                        پروژه پشتیبانی
                    </span>

                    <select
                        name="support_project_id"
                        id="ticket-support-project"
                        required
                    >
                        <?php foreach (
                            $options['projects']
                            ?? []
                            as $id => $project
                        ): ?>
                            <option
                                value="<?= ticketing_h($id) ?>"
                                <?= (string) (
                                    $form[
                                        'support_project_id'
                                    ]
                                    ?? ''
                                ) === (string) $id
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


                <label>
                    <span>
                        زیرسامانه
                    </span>

                    <select
                        name="support_service_id"
                        id="ticket-support-service"
                        required
                    >
                        <?php foreach (
                            $options['services']
                            ?? []
                            as $id => $service
                        ): ?>
                            <option
                                value="<?= ticketing_h($id) ?>"
                                data-project="<?= ticketing_h(
                                    $service['project_id']
                                    ?? ''
                                ) ?>"
                                <?= (string) (
                                    $form[
                                        'support_service_id'
                                    ]
                                    ?? ''
                                ) === (string) $id
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h(
                                    $service['title']
                                    ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>


                <label>
                    <span>
                        موضوع پشتیبانی
                    </span>

                    <select
                        name="support_topic_id"
                        id="ticket-support-topic"
                    >
                        <option value="">
                            بدون موضوع
                        </option>

                        <?php foreach (
                            $options['topics']
                            ?? []
                            as $id => $topic
                        ): ?>
                            <option
                                value="<?= ticketing_h($id) ?>"
                                data-project="<?= ticketing_h(
                                    $topic['project_id']
                                    ?? ''
                                ) ?>"
                                data-service="<?= ticketing_h(
                                    $topic['service_id']
                                    ?? ''
                                ) ?>"
                                <?= (string) (
                                    $form[
                                        'support_topic_id'
                                    ]
                                    ?? ''
                                ) === (string) $id
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?php if (
                                    !empty(
                                        $topic[
                                            'parent_title'
                                        ]
                                    )
                                ): ?>
                                    <?= ticketing_h(
                                        $topic[
                                            'parent_title'
                                        ]
                                    ) ?>
                                    ←
                                <?php endif; ?>

                                <?= ticketing_h(
                                    $topic['title']
                                    ?? ''
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>



                <label class="admin-form-grid__wide">
                    <span>
                        عنوان تیکت
                    </span>

                    <input
                        type="text"
                        name="subject"
                        value="<?= ticketing_h(
                            $form['subject']
                            ?? ''
                        ) ?>"
                        maxlength="500"
                        required
                        autofocus
                        placeholder="خلاصه مشکل یا درخواست"
                    >
                </label>


                <label>
                    <span>
                        دسته‌بندی
                    </span>

                    <select
                        name="category_id"
                        required
                    >
                        <?php foreach (
                            $options['categories']
                            ?? []
                            as $id => $label
                        ): ?>

                            <option
                                value="<?= ticketing_h($id) ?>"
                                <?= (string) (
                                    $form['category_id']
                                    ?? ''
                                ) === (string) $id
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($label) ?>
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
                            $options['priorities']
                            ?? []
                            as $code => $label
                        ): ?>

                            <option
                                value="<?= ticketing_h($code) ?>"
                                <?= (string) (
                                    $form[
                                        'priority_code'
                                    ]
                                    ?? 'normal'
                                ) === (string) $code
                                    ? ' selected'
                                    : '' ?>
                            >
                                <?= ticketing_h($label) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>
                </label>




            </div>



    <section class="admin-tab-panel ticketing-form-section" data-admin-tab-panel="ticket-detail" hidden>

        <div class="admin-form-grid ticketing-details-grid">
<label class="admin-form-grid__wide">
                    <span>
                        شرح درخواست
                    </span>

                    <textarea
                        name="body"
                        rows="6"
                        maxlength="20000"
                        required
                        placeholder="شرح کامل مشکل، مراحل وقوع و اطلاعات مورد نیاز برای بررسی"
                    ><?= ticketing_h(
                        $form['body']
                        ?? ''
                    ) ?></textarea>
                </label>
        </div>


        <header>
            <h3>پیوست‌ها</h3>
            <p>
                فایل‌های مرتبط با درخواست را می‌توانید
                همراه تیکت ارسال کنید.
            </p>
        </header>

        <label>
            <span>فایل‌های پیوست</span>

            <input
                type="file"
                name="attachments[]"
                multiple
                accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.log,.csv,.doc,.docx,.xls,.xlsx,.zip"
            >

            <small class="admin-muted">
                حداکثر ۵ فایل؛ هر فایل حداکثر ۱۰ مگابایت
                و مجموع فایل‌ها حداکثر ۲۵ مگابایت.
            </small>
        </label>
    </section>

<div class="admin-form-actions">

                <button
                    class="admin-button"
                    type="submit"
                >
                    ثبت تیکت
                </button>

                <a
                    class="admin-button admin-button--soft"
                    href="/admin/ticketing/tickets"
                >
                    انصراف
                </a>

            </div>

        </form>

    </section>

</div>

<script>
(function () {
    const project =
        document.getElementById(
            'ticket-support-project'
        );

    const service =
        document.getElementById(
            'ticket-support-service'
        );

    if (!project || !service) {
        return;
    }

    function syncServices() {
        const projectId =
            String(project.value);

        let firstVisible = null;
        let selectedVisible = false;

        Array.from(
            service.options
        ).forEach(function (option) {
            const visible =
                String(
                    option.dataset.project
                    || ''
                ) === projectId;

            option.hidden =
                !visible;

            option.disabled =
                !visible;

            if (visible && firstVisible === null) {
                firstVisible = option;
            }

            if (
                visible
                && option.selected
            ) {
                selectedVisible = true;
            }
        });

        if (
            !selectedVisible
            && firstVisible
        ) {
            firstVisible.selected = true;
        }
    }

    project.addEventListener(
        'change',
        syncServices
    );

    syncServices();
})();
</script>


<script>
(function () {
    const project =
        document.getElementById(
            'ticket-support-project'
        );

    const service =
        document.getElementById(
            'ticket-support-service'
        );

    const topic =
        document.getElementById(
            'ticket-support-topic'
        );

    if (
        !project
        || !service
        || !topic
    ) {
        return;
    }

    function syncTopics() {
        const projectId =
            String(project.value || '');

        const serviceId =
            String(service.value || '');

        let firstVisible = null;
        let selectedVisible = false;
        let visibleCount = 0;

        Array.from(
            topic.options
        ).forEach(function (option) {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const optionProject =
                String(
                    option.dataset.project
                    || ''
                );

            const optionService =
                String(
                    option.dataset.service
                    || ''
                );

            const visible =
                optionProject === projectId
                && (
                    optionService === ''
                    || optionService === serviceId
                );

            option.hidden =
                !visible;

            option.disabled =
                !visible;

            if (visible) {
                visibleCount += 1;

                if (firstVisible === null) {
                    firstVisible = option;
                }

                if (option.selected) {
                    selectedVisible = true;
                }
            }
        });

        if (
            visibleCount > 0
            && !selectedVisible
            && firstVisible
        ) {
            firstVisible.selected = true;
        }

        if (visibleCount === 0) {
            topic.value = '';
        }

        topic.required =
            visibleCount > 0;
    }

    project.addEventListener(
        'change',
        function () {
            window.setTimeout(
                syncTopics,
                0
            );
        }
    );

    service.addEventListener(
        'change',
        syncTopics
    );

    window.setTimeout(
        syncTopics,
        0
    );
})();
</script>


<script>
(function () {
    const initial =
        <?= json_encode(
            $initialTab,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?>;

    if (!initial) {
        return;
    }

    window.setTimeout(function () {
        const button =
            document.querySelector(
                '[data-admin-tab="' + initial + '"]'
            );

        if (
            typeof window.ticketingActivateTab
            === 'function'
        ) {
            window.ticketingActivateTab(
                initial
            );
        } else if (button) {
            button.click();
        }
    }, 0);


    const form =
        document.querySelector(
            '.ticketing-create-form'
        );

    if (form) {
        form.addEventListener(
            'invalid',
            function (event) {
                const field =
                    event.target;

                if (!field || !field.name) {
                    return;
                }

                const detailFields = [
                    'body',
                    'attachments[]'
                ];

                const targetTab =
                    detailFields.indexOf(
                        field.name
                    ) >= 0
                        ? 'ticket-detail'
                        : 'ticket-info';

                const button =
                    document.querySelector(
                        '[data-admin-tab="'
                        + targetTab
                        + '"]'
                    );

                if (button) {
                    button.click();
                }
            },
            true
        );
    }
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
